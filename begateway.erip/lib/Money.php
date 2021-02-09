<?
namespace \BeGateway\Module\Erip;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class Money
{
	protected $amount;
	protected $currency;

	public function setAmount($amount)
	{
		$this->amount = $amount;
	}

	public function getAmount()
	{
		$amount = $this->amount * 100;
    return intval(strval($amount));
	}

	public function setCurrency($currency)
	{
		$allowed_currency = array("BYN");

		if(!in_array($currency,$allowed_currency))
			throw new \Exception(sprintf(Loc::getMessage("SALE_HPS_BEGATEWAY_ERIP_PRICE_CURRENCY_ERROR"), $currency));

		$this->currency = $currency;
	}

	public function getCurrency()
	{
		return $this->currency;
	}
}
