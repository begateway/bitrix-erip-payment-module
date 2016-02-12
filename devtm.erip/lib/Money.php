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
		return ($this->currency == "BYR") ? $this->amount : $this->amount * 100;
	}

	public function setCurrency($currency)
	{
		$allowed_currency = array("BYR", "BYN");

		if(!in_array($currency,$allowed_currency))
			throw new \Exception("An invalid currency symbol");

		$this->currency = $currency;
	}

	public function getCurrency()
	{
		return $this->currency;
	}
}
