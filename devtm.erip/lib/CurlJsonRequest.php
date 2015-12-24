<?
namespace Dm;

class CurlJsonRequest
{	
	protected $login = 363;
	protected $password = "4f585d2709776e53d080f36872fd1b63b700733e7624dfcadd057296daa37df6";
	public $address_for_send = "https://api.bepaid.by/beyag/payments";
	public $t_req;

	public function setLogin($login)
	{
		$this->login = $login;
	}
	
	public function setPassword($password)
	{
		$this->password = $password;
	}
	
	public function submit()
	{
        $process = curl_init($this->address_for_send);
        $json = json_encode($this->t_req);

        if (!empty($this->t_req))
		{
          curl_setopt($process, CURLOPT_HTTPHEADER, array("Accept: application/json", "Content-type: application/json"));
          curl_setopt($process, CURLOPT_POST, 1);
          curl_setopt($process, CURLOPT_POSTFIELDS, $json);
        }
        curl_setopt($process, CURLOPT_URL, $this->address_for_send);
        curl_setopt($process, CURLOPT_USERPWD, $this->login . ":" . $this->password);
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($process);
        $error = curl_error($process);
        curl_close($process);

        if ($response === false)
		{
          throw new \Exception("cURL error " . $error);
        }
        return $response;
    }
}