<?
namespace Dm;

class Costumer
{
	public $first_name;
	public $last_name;
	public $city;
	public $zip;
	public $address;
	public $phone;
	public $ip;
	public $email;
	protected $country;
	
	public function setCountry($country)
	{
		$allowed_countries = array(
								"BY", // Беларусь
								"RU", // Россия
								"UA", // Украина
							);
							
		if(!in_array($country, $allowed_countries))
			throw new \Exception("An invalid country symbol");
		
		$this->country = $country;
	}
	
	public function getCountry()
	{
		return $this->country;
	}
}