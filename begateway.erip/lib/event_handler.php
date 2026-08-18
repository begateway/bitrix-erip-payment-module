<?
namespace BeGateway\Module\Erip;

use Bitrix\Main,
  Bitrix\Main\ModuleManager,
	Bitrix\Main\Web\HttpClient,
	Bitrix\Main\Localization\Loc,
	Bitrix\Sale,
	Bitrix\Sale\Order,
	Bitrix\Sale\PaySystem,
	Bitrix\Main\Request,
	Bitrix\Sale\Payment,
	Bitrix\Sale\PaySystem\ServiceResult,
	Bitrix\Sale\PaymentCollection,
  Bitrix\Main\Diag\Debug,
	Bitrix\Sale\PriceMaths;

Loc::loadMessages(__FILE__);

\CModule::IncludeModule('begateway.erip');

class EventHandler {
  private static $paymentsToReconcile = [];
  private static $reconciling = false;

  public static function OnBeforeSaleOrderSetField(\Bitrix\Main\Event $event)
  {

    if ($event->getParameter("NAME") != 'STATUS_ID')
      return;

    $order = $event->getParameter("ENTITY");
    $value = $event->getParameter("VALUE");

    # проверяем не находился ли заказ уже в статусе ORDER_AWAITING_STATUS
    # и не был ли создан заказ хэндлеров в автоматическом режиме
    if ($value == \BeGateway\Module\Erip\OrderStatuses::ORDER_AWAITING_STATUS &&
        $order->getField('STATUS_ID') != \BeGateway\Module\Erip\OrderStatuses::ORDER_AWAITING_STATUS) {

      $result = self::initiatePay($order);

      if ($result->isSuccess()) {
        // отсылаем письмо с инструкцией
        $data = $result->getData();
        for ($i = 0; $i < count($data['ids']); $i++) {
          // BEP-29814: params[$i] is null when the existing pending bill was
          // reused unchanged — no need to re-email the customer.
          if (empty($data['params'][$i])) {
            continue;
          }
          $collection = $order->getPaymentCollection();
          $payment = $collection->getItemById($data['ids'][$i]);

          self::sendMail($order, $payment, $data['params'][$i]);
        }
      } else {
        return new \Bitrix\Main\EventResult(
          \Bitrix\Main\EventResult::ERROR,
          new \Bitrix\Sale\ResultError(Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_EA_STATUS_CHANGE_ERROR'), 'BEGATEWAY_ERIP_CREATE_ERROR'),
          'sale'
        );
      }
    }

    # проверяем не находился ли заказ уже в статусе ORDER_CANCELED_STATUS
    # и был ли создан счет в ЕРИП для заказа ранее
    if ($value == \BeGateway\Module\Erip\OrderStatuses::ORDER_CANCELED_STATUS &&
        $order->getField('STATUS_ID') != \BeGateway\Module\Erip\OrderStatuses::ORDER_CANCELED_STATUS) {

      $result = self::cancelPay($order);

      if (!$result->isSuccess()) {
        return new \Bitrix\Main\EventResult(
          \Bitrix\Main\EventResult::ERROR,
          new \Bitrix\Sale\ResultError(Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_EC_STATUS_CHANGE_ERROR'), 'BEGATEWAY_ERIP_CANCEL_ERROR'),
          'sale'
        );
      }
    }

    return new \Bitrix\Main\EventResult(
      \Bitrix\Main\EventResult::SUCCESS
    );
  }

  /**
   * Remember existing ERIP bills whose payment amount/currency changed.
   * Reconciliation is deferred until OnSaleOrderSaved so the complete order is
   * already persisted and consistent (BEP-30748).
   */
  public static function OnSalePaymentEntitySaved(\Bitrix\Main\Event $event)
  {
    if (self::$reconciling) {
      return;
    }

    /** @var Payment $payment */
    $payment = $event->getParameter('ENTITY');
    $oldValues = (array)$event->getParameter('VALUES');

    $sumChanged = array_key_exists('SUM', $oldValues)
      && PriceMaths::roundPrecision($oldValues['SUM']) !== PriceMaths::roundPrecision($payment->getSum());
    $currencyChanged = array_key_exists('CURRENCY', $oldValues)
      && $oldValues['CURRENCY'] !== $payment->getField('CURRENCY');

    if ((!$sumChanged && !$currencyChanged)
        || $payment->isPaid()
        || empty($payment->getField('PS_INVOICE_ID'))
        || !self::isEripPayment($payment)) {
      return;
    }

    $order = $payment->getCollection()->getOrder();
    if (!$order || !$order->getId()) {
      return;
    }

    self::$paymentsToReconcile[$order->getId()][$payment->getId()] = true;
  }

  /**
   * Reconcile changed payments after Bitrix has saved the order and all child
   * entities. Service::initiatePay persists a replacement PS_INVOICE_ID; the
   * guard prevents that nested save from starting another reconciliation.
   */
  public static function OnSaleOrderSaved(\Bitrix\Main\Event $event)
  {
    if (self::$reconciling || $event->getParameter('IS_NEW')) {
      return;
    }

    /** @var Order $order */
    $order = $event->getParameter('ENTITY');
    $orderId = $order->getId();
    if (empty(self::$paymentsToReconcile[$orderId])) {
      return;
    }

    $paymentIds = array_keys(self::$paymentsToReconcile[$orderId]);
    unset(self::$paymentsToReconcile[$orderId]);

    self::$reconciling = true;
    try {
      $result = self::initiatePay($order, $paymentIds);
      if ($result->isSuccess()) {
        self::sendInstructionMails($order, $result);
      } else {
        PaySystem\Logger::addError(
          __CLASS__ . ': failed to reconcile ERIP bill after payment change: '
          . implode('; ', $result->getErrorMessages())
        );
      }
    } catch (\Throwable $e) {
      PaySystem\Logger::addError(
        __CLASS__ . ': exception while reconciling ERIP bill after payment change: '
        . $e->getMessage()
      );
    } finally {
      self::$reconciling = false;
    }
  }

  /**
	 * @param Order $payment
	 * @param Request|null $request
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */

  public static function initiatePay(Order $order, array $paymentIds = null) {
    $result = new ServiceResult();

    $resultStorage = [
      'counter' => 0,
      'ids' => [],
      'params' => []
    ];

    $result->setData($resultStorage);

    $paymentCollection = $order->getPaymentCollection();

    foreach ($paymentCollection as $payment) {

      if ($paymentIds !== null && !in_array($payment->getId(), $paymentIds, true)) {
        continue;
      }

      if (!self::isEripPayment($payment)) { // не обработчик ЕРИП
        continue;
      }

      $ps = $payment->getPaySystem();

      if ($payment->isPaid()) {// пропускаем уже оплаченные ЕРИП платежи
        continue;
      }

      // BEP-29814: do NOT skip when PS_INVOICE_ID is set — let the handler
      // decide whether the existing bill is still valid for the current sum.
      // The handler will recreate the bill when amount/currency drift is
      // detected, and return a new PS_INVOICE_ID via setPsData.
      $oldInvoiceId = $payment->getField('PS_INVOICE_ID');

      $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
      // вызываем обработчик платежной системы, чтобы создать счет
      $ps_status_message = $payment->getField('PS_STATUS_MESSAGE');
      $payment->setField('PS_STATUS_MESSAGE', 'manual');

      $result = $ps->initiatePay($payment, $request);

      if ($payment->getField('PS_STATUS_MESSAGE') == 'manual') {
        $payment->setField('PS_STATUS_MESSAGE', $ps_status_message);
      }

      if ($result->isSuccess()) {

        // сохраняем номер операции ЕРИП в данных способа оплаты
        $psData = $result->getPsData();
        $newInvoiceId = isset($psData['PS_INVOICE_ID']) ? $psData['PS_INVOICE_ID'] : null;

        if ($newInvoiceId) {
          $payment->setField('PS_INVOICE_ID', $newInvoiceId);

          // Always count success — the payment ends with a valid bill.
          $resultStorage['ids'] []= $payment->getId();

          // Re-send instructions to the customer only when a brand-new bill
          // was actually issued (initial create OR amount-drift recreate).
          // Reuse of an unchanged pending bill must NOT re-trigger the email.
          if ($newInvoiceId !== $oldInvoiceId) {
            $order->save();
            $resultStorage['params'] []= $result->getData();
          } else {
            $resultStorage['params'] []= null;
          }
        }
      }

      $resultStorage['counter'] += 1;
    }

    # проверяем, что все обработчики завершились успешно
    if ($resultStorage['counter'] == count($resultStorage['ids'])) {
      $result->setData($resultStorage);
    } else {
      $result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_EA_STATUS_CHANGE_ERROR')));
    }

    return $result;
  }

  private static function isEripPayment(Payment $payment): bool
  {
    $ps = $payment->getPaySystem();
    if (!$ps) {
      return false;
    }

    $description = $ps->getHandlerDescription();
    return isset($description['CODES']['BEGATEWAY_ERIP_ID']);
  }

  private static function sendInstructionMails(Order $order, ServiceResult $result): void
  {
    $data = $result->getData();
    if (empty($data['ids'])) {
      return;
    }

    for ($i = 0; $i < count($data['ids']); $i++) {
      if (empty($data['params'][$i])) {
        continue;
      }

      $payment = $order->getPaymentCollection()->getItemById($data['ids'][$i]);
      if ($payment) {
        self::sendMail($order, $payment, $data['params'][$i]);
      }
    }
  }

  public static function cancelPay(Order $order) {
    $result = new ServiceResult();

    $resultStorage = [
      'counter' => 0,
      'ids' => [],
      'params' => []
    ];

    $result->setData($resultStorage);

    $paymentCollection = $order->getPaymentCollection();

    foreach ($paymentCollection as $payment) {

      $ps = $payment->getPaySystem();
      $description = $ps->getHandlerDescription();

      if (!isset($description['CODES']['BEGATEWAY_ERIP_ID'])) { // не обработчик ЕРИП
        continue;
      }

      if ($payment->isPaid()) {// пропускаем уже оплаченные ЕРИП платежи
        continue;
      }

      // пропускаем счета не выставленные в ЕРИП
      if (empty($payment->getField('PS_INVOICE_ID'))) {
       continue;
      }

      // вызываем обработчик платежной системы, чтобы отменить счет
      $result = $ps->cancel($payment);

      if ($result->isSuccess()) {

        // удаляем номер операции ЕРИП в данном способе оплаты
        $payment->setField('PS_INVOICE_ID', null);
        $order->save();
        $resultStorage['ids'] []= $payment->getId();
        // сохраняем данные ЕРИП счета для шаблона письма
        $resultStorage['params'] []= $result->getData();
      }

      $resultStorage['counter'] += 1;
    }

    # проверяем, что все обработчики завершились успешно
    if ($resultStorage['counter'] == count($resultStorage['ids'])) {
      $result->setData($resultStorage);
    } else {
      $result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_EC_STATUS_CHANGE_ERROR')));
    }

    return $result;
  }

  static public function sendMail(Order $order, Payment $payment, $params)
	{
    $info = self::getSiteInfo($order);
    $userEmail = $order->getPropertyCollection()->getUserEmail();
    $userName = $order->getPropertyCollection()->getPayerName();

		$fields = array(
				'EMAIL' => ($userEmail) ? $userEmail->getValue() : '',
				'NAME' => ($userName) ? $userName->getValue() : '',
				"ORDER_ID" => $order->getId(),
        'ORDER_NUMBER' => $order->getField('ACCOUNT_NUMBER'),
        'ORDER_DATE' => $order->getDateInsert()->toString(),
        'PAYMENT_NUMBER' => $payment->getField('ACCOUNT_NUMBER'),
        'PAYMENT_ID' => $payment->getId(),
				'SALE_EMAIL' => Main\Config\Option::get("sale", "order_email", "order@".$_SERVER["SERVER_NAME"]),
        'BCC' => Main\Config\Option::get("sale", "order_email", "order@".$_SERVER['SERVER_NAME']),
        'ORDER_PUBLIC_URL' => '',
        'INSTRUCTION' => $params['instruction'],
        'ACCOUNT_NUMBER' => $params['account_number'],
        'ERIP_SERVICE_CODE' => $params['service_no_erip'],
				'QR_CODE' => $params['qr_code'],
	  );

    if (!empty($info)) {
      $fields["SITE_NAME"] = $info['SITE_NAME'];
      $fields["SERVER_NAME"] = $info['SERVER_NAME'];
      $fields["ORDER_PUBLIC_URL"] = 'http://' . $info['SERVER_NAME'];
    }

    $public_link = self::getPublicLink($order);

    if (!empty($public_link)) {
      $fields["ORDER_PUBLIC_URL"] = $public_link;
    }

    \Bitrix\Main\Mail\Event::send(array(
      "EVENT_NAME" => \BeGateway\Module\Erip\Events::ORDER_STATUS_CHANGED_TO_EA,
      "LID" => $order->getField('LID'),
      "LANGUAGE_ID" => $info["LANGUAGE_ID"],
      "C_FIELDS" => $fields
    ));
	}

  static protected function getPublicLink(Order $order) {
    $link = '';
    if (method_exists('Bitrix\Sale\Helpers\Order', 'isAllowGuestView')) {
      $link = Sale\Helpers\Order::isAllowGuestView($order) ? Sale\Helpers\Order::getPublicLink($order) : "";
    }

    return $link;
  }

  static protected function getSiteInfo(Order $order) {
    $dbSite = \CSite::GetByID($order->getSiteId());
    $arFields =  $dbSite->Fetch();

    return ($arFields) ?: [];
  }
}
