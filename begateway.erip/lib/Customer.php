<?
namespace BeGateway\Module\Erip;

class Customer
{
	public $first_name;
	public $last_name;
	public $middle_name;
	public $city;
	public $zip;
	public $address;
	public $phone;
	public $ip;
	public $email;
	protected $country;

	public function setCountry($country)
	{
		$this->country = $country;
	}

	public function getCountry()
	{
		return $this->country;
	}
}
