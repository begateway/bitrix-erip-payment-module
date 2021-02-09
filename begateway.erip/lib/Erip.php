<?
namespace \BeGateway\Module\Erip;

class Erip extends CurlJsonRequest
{
  public $customer;
  public $money;
  public $description;
  public $notification_url;
  public $account_number;
  public $service_number;
  public $service_info;
  public $receipt;
  public $order_id;
  public $expired_at;

  public function __construct()
  {
    $this->customer = new Customer;
    $this->money = new Money;
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
        "description" => Erip::to_utf8($this->description),
        "email" => $this->customer->email,
        "ip" => $this->customer->ip,
        "order_id" => $this->order_id,
        "notification_url" => $this->notification_url,
        "expired_at" => Erip::to_utf8($this->expired_at),
        "customer" => array(
          "first_name" => Erip::to_utf8($this->customer->first_name, 30),
          "last_name" => Erip::to_utf8($this->customer->last_name, 30),
          "country" => Erip::to_utf8($this->customer->getCountry()),
          "city" => Erip::to_utf8($this->customer->city),
          "zip" => Erip::to_utf8($this->customer->zip),
          "address" => Erip::to_utf8($this->customer->address, 255),
          "phone" => Erip::to_utf8($this->customer->phone),
        ),
        "payment_method" => array(
          "type" => "erip",
          "account_number" => $this->account_number,
          "service_no" => $this->service_number,
          "service_info" => array(Erip::to_utf8(str_replace('#INVOICE#', $this->account_number, $this->service_info))),
          "receipt" => array(Erip::to_utf8(str_replace('#INVOICE#', $this->account_number, $this->receipt))),
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

  public static function to_utf8($param, $size = 0)
  {
    $in = $param;
    if ($size > 0) {
      $in = substr($in, 0, $size);
    }
    if (strtolower(LANG_CHARSET) == 'windows-1251') {
      $in = mb_convert_encoding($in, 'UTF-8', 'WINDOWS-1251');
    }
    return $in;
  }
}
