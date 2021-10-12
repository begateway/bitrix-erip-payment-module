<?php
namespace Sale\Handlers\PaySystem;

use Bitrix\Main,
  Bitrix\Main\ModuleManager,
	Bitrix\Main\Web\HttpClient,
	Bitrix\Main\Localization\Loc,
	Bitrix\Sale,
	Bitrix\Sale\PaySystem,
	Bitrix\Main\Request,
	Bitrix\Sale\Payment,
  Bitrix\Main\Diag\Debug,
	Bitrix\Sale\PaySystem\ServiceResult,
	Bitrix\Sale\PaymentCollection,
	Bitrix\Sale\PriceMaths;

Loc::loadMessages(__FILE__);

\CModule::IncludeModule('begateway.erip');

/**
 * Class BePaidHandler
 * @package Sale\Handlers\PaySystem
 */
class begateway_eripHandler
  extends PaySystem\ServiceHandler
  implements PaySystem\IHold, PaySystem\ICheckable
{
	private const API_URL                 = 'https://api.bepaid.by';

	private const TRACKING_ID_DELIMITER   = '#';

	private const STATUS_SUCCESSFUL_CODE  = 'successful';
	private const STATUS_ERROR_CODE       = 'error';

	private const SEND_METHOD_HTTP_POST   = 'POST';
	private const SEND_METHOD_HTTP_GET    = 'GET';
	private const SEND_METHOD_HTTP_DELETE = 'DELETE';

	/**
	 * @param Payment $payment
	 * @param Request|null $request
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	public function initiatePay(Payment $payment, Request $request = null): ServiceResult
	{
		$result = new ServiceResult();

    # смена статуса заказа по событию из админки
    $ajaxMode = ($request) ? $this->isAdminChangeStatus($payment) : false;

    if ($this->isAutoMode($payment) || $ajaxMode) {
      if (empty($payment->getField('PS_INVOICE_ID'))) {
    		$createEripBillResult = $this->createEripBill($payment);
      } else {
        # счет был уже создан и нужно получить данные для шаблона
    		$createEripBillResult = $this->getBeGatewayEripPayment($payment);
      }

  		if (!$createEripBillResult->isSuccess())
  		{
  			$result->addErrors($createEripBillResult->getErrors());
  			return $result;
  		}

  		$createEripBillData = $createEripBillResult->getData();
  		if (!empty($createEripBillData['transaction']['uid']))
  		{
  			$result->setPsData(['PS_INVOICE_ID' => $createEripBillData['transaction']['uid']]);
        $result->setData($this->getTemplateParams($payment, $createEripBillData));
  		}

  		$this->setExtraParams($this->getTemplateParams($payment, $createEripBillData));
    }

    $showTemplateResult = $this->showTemplate($payment, $this->getTemplateName($payment));
		if ($showTemplateResult->isSuccess())
		{
			$result->setTemplate($showTemplateResult->getTemplate());
		}
		else
		{
			$result->addErrors($showTemplateResult->getErrors());
		}

		return $result;
	}

	/**
	 * @param Payment $payment
	 * @param Request|null $request
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	public function cancel(Payment $payment): ServiceResult
	{
		$result = new ServiceResult();
		$deleteEripBillResult = $this->deleteEripBill($payment);
    if (!$deleteEripBillResult->isSuccess())
    {
      $result->addErrors($deleteEripBillResult->getErrors());
      return $result;
    }
		$deleteEripBillData = $deleteEripBillResult->getData();

    $result->setData($deleteEripBillData);

    return $result;
  }

  /**
   * @param Payment $payment
   * @return PaySystem\ServiceResult
   */
  public function confirm(Payment $payment): ServiceResult
  {
    $result = new ServiceResult();
		$result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_CONFIRM_ERROR')));
    return $result;
  }

  /**
   * @param Payment $payment
   * @return PaySystem\ServiceResult
   */
  public function check(Payment $payment): ServiceResult
  {
    $result = $this->processPayment($payment);

    return $result;
  }

	/**
	 * @param Payment $payment
	 * @return string
	 */
	private function getTemplateName(Payment $payment): string
	{
    return $this->isAutoMode($payment) ? 'auto' : 'manual';
	}

	/**
	 * @param Payment $payment
	 * @return boolean
	 */
  private function isAutoMode(Payment $payment)
  {
    $autoModeSetting = $this->getBusinessValue($payment, 'BEGATEWAY_ERIP_AUTO_BILL');
    $createdEripBill = !empty($payment->getField('PS_INVOICE_ID'));

    return $autoModeSetting == 'Y' || $createdEripBill;
  }

	/**
	 * @param Request $request
	 * @return boolean
	 */
  private function isAdminChangeStatus(Payment $payment)
  {
    return $payment->getField('PS_STATUS_MESSAGE') == 'manual';
  }

	/**
	 * @param Payment $payment
	 * @param array $paymentTokenData
	 * @return array
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function getTemplateParams(Payment $payment, array $eripBillData): array
  {
		$params = [
			'sum' => PriceMaths::roundPrecision($payment->getSum() - $payment->getSumPaid()),
			'currency' => $payment->getField('CURRENCY'),
      'instruction' => $eripBillData['transaction']['erip']['instruction'],
      'qr_code' => $eripBillData['transaction']['erip']['qr_code'],
      'account_number' =>  $eripBillData['transaction']['erip']['account_number'],
      'service_no_erip' => $eripBillData['transaction']['erip']['service_no_erip'],
      'first_name' => $this->getBusinessValue($payment, 'BUYER_PERSON_NAME_FIRST'),
      'middle_name' => $this->getBusinessValue($payment, 'BUYER_PERSON_NAME_MIDDLE')
		];

		return $params;
	}

	/**
	 * @param Payment $payment
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function createEripBill(Payment $payment): ServiceResult {
		$result = new ServiceResult();

		$url = $this->getUrl($payment, 'sendEripBill');

    $money = new \BeGateway\Module\Erip\Money;
    $money->setCurrency($payment->getField('CURRENCY'));
    $money->setAmount($payment->getSum());

		$params = [
			'request' => [
				'test' => $this->isTestMode($payment),
				'amount' => $money->getCents(),
				'currency' => $payment->getField('CURRENCY'),
				'description' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getPaymentDescription($payment), 255),
				'tracking_id' => $payment->getId().self::TRACKING_ID_DELIMITER.$this->service->getField('ID'),
				'notification_url' => $this->getBusinessValue($payment, 'BEGATEWAY_ERIP_NOTIFICATION_URL'),
				'language' => LANGUAGE_ID,
        'email' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_EMAIL')),
        'ip' => $_SERVER['HTTP_CLIENT_IP'] ? : ($_SERVER['HTTP_X_FORWARDED_FOR'] ? : $_SERVER['REMOTE_ADDR']),
        'customer' => [
          'first_name' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_NAME_FIRST')),
          'middle_name' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_NAME_MIDDLE')),
          'last_name' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_NAME_LAST')),
          'city' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_CITY')),
          'zip' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_ZIP')),
          'address' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_ADDRESS')),
          'phone' => \BeGateway\Module\Erip\Encoder::toUtf8($this->getBusinessValue($payment, 'BUYER_PERSON_PHONE')),
        ],
        'payment_method' => [
          'type' => 'erip',
          'account_number' => \BeGateway\Module\Erip\Encoder::toUtf8(
            $this->getAccountDescription($payment)
          ),
          'service_info' => \BeGateway\Module\Erip\Encoder::str_split(
            \BeGateway\Module\Erip\Encoder::toUtf8($this->getPaymentDescription($payment))
          ),
          'receipt' => \BeGateway\Module\Erip\Encoder::str_split(
            \BeGateway\Module\Erip\Encoder::toUtf8($this->getReceiptDescription($payment))
          )
        ],
        'additional_data' => [
          'platform_data' => '1C-Bitrix' . ' v' . ModuleManager::getVersion('main'),
          'integration_data' => 'BeGateway ERIP payment module ' . ' v' . ModuleManager::getVersion('begateway.erip')
        ]
			]
		];

    $service_code = trim($this->getBusinessValue($payment, 'BEGATEWAY_ERIP_SERVICE_CODE'));
    if (isset($service_code) && !empty($service_code)) {
      $params['request']['payment_method']['service_no'] = $service_code;
    }

    $timeout = intval($this->getBusinessValue($payment, 'BEGATEWAY_ERIP_EXPIRY'));

    if ($timeout > 0) {
      $params['request']['expired_at'] = date("c", $timeout*60 + time());
    }

		$headers = $this->getHeaders($payment);

		$sendResult = $this->send(self::SEND_METHOD_HTTP_POST, $url, $params, $headers);
		if ($sendResult->isSuccess())
		{
			$eripBillData = $sendResult->getData();
			$verifyResponseResult = $this->verifyResponse($eripBillData);
			if ($verifyResponseResult->isSuccess())
			{
				$result->setData($eripBillData);
			}
			else
			{
				$result->addErrors($verifyResponseResult->getErrors());
			}
		}
		else
		{
			$result->addErrors($sendResult->getErrors());
		}

		return $result;
	}

  /**
	 * @param Payment $payment
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	private function deleteEripBill(Payment $payment): ServiceResult {
		$result = new ServiceResult();

		$url = $this->getUrl($payment, 'deleteEripBill');
		$headers = $this->getHeaders($payment);

		$sendResult = $this->send(self::SEND_METHOD_HTTP_DELETE, $url, [], $headers);
		if ($sendResult->isSuccess())
		{
			$paymentData = $sendResult->getData();
			$verifyResponseResult = $this->verifyResponse($paymentData);
			if ($verifyResponseResult->isSuccess())
			{
				$result->setData($paymentData);
			}
			else
			{
				$result->addErrors($verifyResponseResult->getErrors());
			}
		}
		else
		{
			$result->addErrors($sendResult->getErrors());
		}

		return $result;
	}

	/**
	 * @param Payment $payment
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	private function getBeGatewayEripPayment(Payment $payment): ServiceResult {
		$result = new ServiceResult();

		$url = $this->getUrl($payment, 'getEripBillStatus');
		$headers = $this->getHeaders($payment);

		$sendResult = $this->send(self::SEND_METHOD_HTTP_GET, $url, [], $headers);
		if ($sendResult->isSuccess())
		{
			$paymentData = $sendResult->getData();
			$verifyResponseResult = $this->verifyResponse($paymentData);
			if ($verifyResponseResult->isSuccess())
			{
				$result->setData($paymentData);
			}
			else
			{
				$result->addErrors($verifyResponseResult->getErrors());
			}
		}
		else
		{
			$result->addErrors($sendResult->getErrors());
		}

		return $result;
	}

	/**
	 * @param string $method
	 * @param string $url
	 * @param array $params
	 * @param array $headers
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	private function send(string $method, string $url, array $params = [], array $headers = []): ServiceResult {
		$result = new ServiceResult();

		$httpClient = new HttpClient();
		foreach ($headers as $name => $value)
		{
			$httpClient->setHeader($name, $value);
		}

    PaySystem\Logger::addDebugInfo(__CLASS__.': request url: '.$url);

		if ($method === self::SEND_METHOD_HTTP_GET)
		{
			$response = $httpClient->get($url);
		} else {
			$postData = null;
			if ($params)
			{
				$postData = static::encode($params);
			}

			PaySystem\Logger::addDebugInfo(__CLASS__.': request data: '.$postData);

      $response = $httpClient->query($method, $url, $postData);

      if ($response) {
        $response = $httpClient->getResult();
      }
		}

		if ($response === false)
		{
			$errors = $httpClient->getError();
			foreach ($errors as $code => $message)
			{
				$result->addError(PaySystem\Error::create($message, $code));
			}

			return $result;
		}

		PaySystem\Logger::addDebugInfo(__CLASS__.': response data: '.$response);

		$response = static::decode($response);
		if ($response)
		{
			$result->setData($response);
		}
		else
		{
			$result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_RESPONSE_DECODE_ERROR')));
		}

		return $result;
	}

	/**
	 * @param array $response
	 * @return ServiceResult
	 */
	private function verifyResponse(array $response): ServiceResult {
		$result = new ServiceResult();

		if (!empty($response['errors']))
		{
			$result->addError(PaySystem\Error::create($response['message']));
		}

		return $result;
	}

	/**
	 * @return array|string[]
	 */
	public function getCurrencyList(): array {
		return ['BYN'];
	}

	/**
	 * @param Payment $payment
	 * @param Request $request
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	public function processRequest(Payment $payment, Request $request): ServiceResult {
		$result = new ServiceResult();

		$inputStream = static::readFromStream();
		$data = static::decode($inputStream);
		$transaction = $data['transaction'];

    if (!$this->isSignatureCorrect($payment, $inputStream)) {
			$result->addError(
				PaySystem\Error::create(
					Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_ERROR_SIGNATURE')
				)
			);
    } else {
      return $this->processPayment($payment);
    }

    return $result;
  }

   /**
	 * @param Payment $payment
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
  private function processPayment($payment) : ServiceResult {
    $result = new ServiceResult;

		$beGatewayEripPaymentResult = $this->getBeGatewayEripPayment($payment);
		if ($beGatewayEripPaymentResult->isSuccess())
		{
			$beGatewayEripPaymentData = $beGatewayEripPaymentResult->getData();
			if ($beGatewayEripPaymentData['transaction']['status'] === self::STATUS_SUCCESSFUL_CODE)
			{
        $transaction = $beGatewayEripPaymentData['transaction'];
				$description = Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_TRANSACTION', [
					'#ID#' => $transaction['uid'],
				]);

        $money = new \BeGateway\Module\Erip\Money;
        $money->setCurrency($transaction['currency']);
        $money->setCents($transaction['amount']);

				$fields = [
					'PS_STATUS_CODE' => $transaction['status'],
					'PS_STATUS_DESCRIPTION' => $description,
					'PS_SUM' => $money->getAmount(),
					'PS_STATUS' => 'N',
					'PS_CURRENCY' => $transaction['currency'],
					'PS_RESPONSE_DATE' => new Main\Type\DateTime()
				];

				if ($this->isSumCorrect($payment, $money->getAmount()))
				{
					$fields['PS_STATUS'] = 'Y';

					PaySystem\Logger::addDebugInfo(
						__CLASS__.': PS_CHANGE_STATUS_PAY='.$this->getBusinessValue($payment, 'PS_CHANGE_STATUS_PAY')
					);

					if ($this->getBusinessValue($payment, 'PS_CHANGE_STATUS_PAY') === 'Y')
					{
						$result->setOperationType(PaySystem\ServiceResult::MONEY_COMING);
					}
				}
				else
				{
					$error = Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_ERROR_SUM');
					$fields['PS_STATUS_DESCRIPTION'] .= '. '.$error;
					$result->addError(PaySystem\Error::create($error));
				}

				$result->setPsData($fields);
			}
			else
			{
				$result->addError(
					PaySystem\Error::create(
						Loc::getMessage('SALE_HPS_BEGATEWAY_ERIP_ERROR_STATUS',
							[
								'#STATUS#' => $transaction['status'],
							]
						)
					)
				);
			}
		}
		else
		{
			$result->addErrors($beGatewayEripPaymentResult->getErrors());
		}

		return $result;
	}

  /*
	 * @param Payment $payment
	 * @param string Request $inputStream
	 * @return bool
  */
  private function isSignatureCorrect(Payment $payment, $inputStream) {
    $signature = $_SERVER['HTTP_CONTENT_SIGNATURE'];

		PaySystem\Logger::addDebugInfo(
			__CLASS__.': Signature: '.$signature."; Webhook: ".$inputStream
		);

    $signature  = base64_decode($_SERVER['HTTP_CONTENT_SIGNATURE']);

    if (!$signature) {
      return false;
    }

    $public_key = $this->getBusinessValue($payment, 'BEGATEWAY_ERIP_PUBLIC_KEY');
    $public_key = str_replace(array("\r\n", "\n"), '', $public_key);
    $public_key = chunk_split($public_key, 64);
    $public_key = "-----BEGIN PUBLIC KEY-----\n" . $public_key . "-----END PUBLIC KEY-----";
    $key = openssl_pkey_get_public($public_key);

    return openssl_verify($inputStream, $signature, $key, OPENSSL_ALGO_SHA256) == 1;
  }

	/**
	 * @param Payment $payment
	 * @param $sum
	 * @return bool
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	private function isSumCorrect(Payment $payment, $sum): bool {
		PaySystem\Logger::addDebugInfo(
			__CLASS__.': bePaidSum='.PriceMaths::roundPrecision($sum)."; paymentSum=".PriceMaths::roundPrecision($payment->getSum())
		);

		return PriceMaths::roundPrecision($sum) === PriceMaths::roundPrecision($payment->getSum());
	}

	/**
	 * @param Request $request
	 * @param int $paySystemId
	 * @return bool
	 */
	public static function isMyResponse(Request $request, $paySystemId): bool {
		$inputStream = static::readFromStream();
		if ($inputStream)
		{
			$data = static::decode($inputStream);
			if ($data === false)
			{
				return false;
			}

			if (isset($data['transaction']['tracking_id']))
			{
				[, $trackingPaySystemId] = explode(self::TRACKING_ID_DELIMITER, $data['transaction']['tracking_id']);
				return (int)$trackingPaySystemId === (int)$paySystemId;
			}
		}

		return false;
	}

	/**
	 * @param Request $request
	 * @return bool|int|mixed
	 */
	public function getPaymentIdFromRequest(Request $request) {
		$inputStream = static::readFromStream();
		if ($inputStream)
		{
			$data = static::decode($inputStream);
			if (isset($data['transaction']['tracking_id']))
			{
				[$trackingPaymentId] = explode(self::TRACKING_ID_DELIMITER, $data['transaction']['tracking_id']);
				return (int)$trackingPaymentId;
			}
		}

		return false;
	}

	/**
	 * @param Payment $payment
	 * @return mixed
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function getPaymentDescription(Payment $payment) {
		return $this->setDescriptionPlaceholders('BEGATEWAY_ERIP_PAYMENT_DESCRIPTION', $payment);
	}

	/**
	 * @param Payment $payment
	 * @return mixed
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function getReceiptDescription(Payment $payment) {
		return $this->setDescriptionPlaceholders('BEGATEWAY_ERIP_RECEIPT_PAYMENT_DESCRIPTION', $payment);
	}

	/**
	 * @param Payment $payment
	 * @return mixed
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function getAccountDescription(Payment $payment) {
		return $this->setDescriptionPlaceholders('BEGATEWAY_ERIP_PAYMENT_ACCOUNT', $payment);
	}

	/**
	 * @param Payment $payment
	 * @return mixed
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function setDescriptionPlaceholders(string $description, Payment $payment) {
		/** @var PaymentCollection $collection */
		$collection = $payment->getCollection();
		$order = $collection->getOrder();
		$userEmail = $order->getPropertyCollection()->getUserEmail();

		$processed_description =  str_replace(
			[
				'#PAYMENT_NUMBER#',
				'#ORDER_NUMBER#',
				'#PAYMENT_ID#',
				'#ORDER_ID#',
				'#USER_EMAIL#'
			],
			[
				$payment->getField('ACCOUNT_NUMBER'),
				$order->getField('ACCOUNT_NUMBER'),
				$payment->getId(),
				$order->getId(),
				($userEmail) ? $userEmail->getValue() : ''
			],
			$this->getBusinessValue($payment, $description)
		);

		return $processed_description;
	}

	/**
	 * @param Payment $payment
	 * @return array
	 */
	private function getHeaders(Payment $payment): array
	{
		$headers = [
			'Content-Type' => 'application/json',
			'Accept' => 'application/json',
			'Authorization' => 'Basic '.$this->getBasicAuthString($payment),
			'RequestID' => $this->getIdempotenceKey(),
		];

		return $headers;
	}

	/**
	 * @param Payment $payment
	 * @return string
	 */
	private function getBasicAuthString(Payment $payment): string {
		return base64_encode(
			$this->getBusinessValue($payment, 'BEGATEWAY_ERIP_ID')
			. ':'
			. $this->getBusinessValue($payment, 'BEGATEWAY_ERIP_SECRET_KEY')
		);
	}

	/**
	 * @return string
	 */
	private function getIdempotenceKey(): string {
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}

	/**
	 * @param Payment $payment
	 * @param string $action
	 * @return string
	 */
	protected function getUrl(Payment $payment = null, $action): string {
		$url = parent::getUrl($payment, $action);
		if ($payment !== null &&
        in_array(
          $action, ['getEripBillStatus', 'deleteEripBill']
        ))
		{
			$url = str_replace('#uid#', $payment->getField('PS_INVOICE_ID'), $url);
		}

		return $url;
	}

	/**
	 * @return array
	 */
	protected function getUrlList(): array {
		return [
			'sendEripBill' => self::API_URL.'/beyag/payments',
      'getEripBillStatus' => self::API_URL.'/beyag/payments/#uid#',
      'deleteEripBill' => self::API_URL.'/beyag/payments/#uid#'
		];
	}

	/**
	 * @param Payment $payment
	 * @return bool
	 */
	protected function isTestMode(Payment $payment = null): bool {
		return ($this->getBusinessValue($payment, 'PS_IS_TEST') === 'Y');
	}

	/**
	 * @return bool|string
	 */
	private static function readFromStream() {
		return file_get_contents('php://input');
	}

	/**
	 * @param array $data
	 * @return mixed
	 * @throws Main\ArgumentException
	 */
	private static function encode(array $data) {
		return Main\Web\Json::encode($data, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * @param string $data
	 * @return mixed
	 */
	private static function decode($data) {
		try
		{
			return Main\Web\Json::decode($data);
		}
		catch (Main\ArgumentException $exception)
		{
			return false;
		}
	}
}
