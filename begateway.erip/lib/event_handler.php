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
        for ($i = 0;$i < $data['counter']; $i++) {
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

  public static function initiatePay(Order $order) {
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

      // пропускаем счета уже выставленные в ЕРИП
      if (!empty($payment->getField('PS_INVOICE_ID'))) {
       continue;
      }

      // вызываем обработчик платежной системы, чтобы создать счет
      $result = $ps->initiatePay($payment, null, true);

      if ($result->isSuccess()) {

        // сохраняем номер операции ЕРИП в данных способа оплаты
        $psData = $result->getPsData();
        $payment->setField('PS_INVOICE_ID', $psData['PS_INVOICE_ID']);
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
      $result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_EA_STATUS_CHANGE_ERROR')));
    }
    return $result;
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

      // Debug::payment/roboxchange/result_rec.php($order->getField('STATUS_ID'));
      // Debug::dumpToFile($event->getParameter("VALUE"));

    // return new \Bitrix\Main\EventResult(
    //   \Bitrix\Main\EventResult::SUCCESS
    //   // new \Bitrix\Sale\ResultNotice(Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_EA_STATUS_CHANGE_SUCCESS'), 'BEGATEWAY_ERIP_CREATE_SUCCESS'),
    //   // 'sale'
    // );
    //
    // return new \Bitrix\Main\EventResult(
    //   \Bitrix\Main\EventResult::ERROR,
    //   new \Bitrix\Sale\ResultError(Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_EA_STATUS_CHANGE_ERROR'), 'BEGATEWAY_ERIP_CREATE_ERROR'),
    //   'sale'
    // );
}
