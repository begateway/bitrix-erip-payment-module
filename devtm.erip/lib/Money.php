<?
namespace Dm;

class Money
{
	protected $amount;
	protected $currency;
	
	public function setAmount($amount)
	{
		if(!is_numeric($amount))
			throw new Exception("Price must be a number");
		
		$this->amount = $amount;
	}
	
	public function getAmount()
	{
		return $this->amount;
	}
	
	public function setCurrency($currency)
	{
		$allowed_currency = array("RUB", "BYR",	"USD", "EUR");
		
		if(!in_array($currency,$allowed_currency))
			throw new Exception("An invalid currency symbol");
		
		$this->currency = $currency;
	}
	
	public function getCurrency()
	{
		return $this->currency;
	}
}
