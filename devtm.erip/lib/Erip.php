<?
namespace Dm;

class Erip extends CurlJsonRequest
{
	public $costumer;
	public $money;
	public $description;
	public $notification_url;
	public $account_number;
	public $service_number;
	public $service_info;
	public $receipt;
	public $order_id;
	
	public function __construct()
	{
		$this->costumer = new \Dm\Costumer;
		$this->money = new \Dm\Money;
	}
	
	public function orderGenerate( $oid )
	{
		$oid = (int)$oid;
		$n = 100000000000;
		$this->order_id = $oid + $n;
	}
	
	protected function buildRequest()
	{
		return array(
					"request" => array(
									"amount" => $this->money->getAmount(),
									"currency" => $this->money->getCurrency(),
									"description" => $this->description,
									"email" => $this->costumer->email,
									"ip" => $this->costumer->ip,
									"order_id" => $this->order_id,
									"notification_url" => $this->notification_url,
									"customer" => array(
													"first_name" => $this->costumer->first_name,
													"last_name" => $this->costumer->last_name,
													"country" => $this->costumer->getCountry(),
													"city" => $this->costumer->city,
													"zip" => $this->costumer->zip,
													"address" => $this->costumer->address,
													"phone" => $this->costumer->phone,
												  ),
									"payment_method" => array(
															"type" => "erip",
															"account_number" => $this->account_number,
															"service_no" => $this->service_number,
															"service_info" => array('"'. $this->service_info .'"'),
															"receipt" => array('"'.$this->receipt.'"'),
														)
								)
				);
	}
	
	public function submit()
	{
		$this->t_req = $this->buildRequest();
		$p = parent::submit();
		return $p; 
	}
}