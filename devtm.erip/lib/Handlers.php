<?
class Handlers
{
	static public $module_id = "devtm.erip";
	static public $o_erip;
	static public $v;
	
	public function __construct()
	{
		\Bitrix\Main\Loader::includeModule("sale");
	}
	
	static public function onSaleOrderBeforeSaved($event)
	{
		
		self::$v = $event->getFields()->getValues();
		$old_v = $event->getOriginalValues;

		
		$opt_st = \Bitrix\Main\Config\Option::get( self::$module_id, "order_status_code_erip");
			
		if($opt_st == self::$v["STATUS_ID"] && $old_v["STATUS_ID"] != self::$v["STATUS_ID"])
		{
			
			\Bitrix\Main\Loader::includeModule(self::$module_id);
			
			self::$o_erip = new \Dm\Erip();

			static::setTehnicalInfo();
			
			static::setUserInfo();
			static::setMoneyInfo();
			
			$r = self::$o_erip->submit();
			$m = json_decode($r);
			
			if(isset($m->errors))
				throw new \Exception($m->message);
			
			
			if(\Bitrix\Sale\Internals\OrderTable::update(self::$v["ID"], array("COMMENTS" => "status: ". $m->transaction->status ."\n".
															"transaction_id: ". $m->transaction->transaction_id ."\n".
															"order_id: ". $m->transaction->order_id ."\n".
															"account_number: ". $m->transaction->erip->account_number ."\n")))
			{
				$emt = \Bitrix\Main\Config\Option::get( self::$module_id, "mail_event_name");
 
				$mf = array(
						"EMAIL_TO" => $m->costumer->email,
						"NAME" => $m->costumer->first_name,
						"ORDER_ID" => self::$v["ID"],
						"SALE_NAME" => \Bitrix\Main\Config\Option::get( self::$module_id, "sale_name"),
						"COMPANY_NAME" => \Bitrix\Main\Config\Option::get( self::$module_id, "company_name"),
						"PATH_TO_SERVICE" => \Bitrix\Main\Config\Option::get( self::$module_id, "path_to_service"),
						"SERVER_NAME" => $_SERVER["SERVER_NAME"],
					  );
				CEvent::Send($emt, static::getSites(), $mf, "N", \Bitrix\Main\Config\Option::get( self::$module_id, "mail_template_id"));
			}
			
		}
		return true;
	}
	
	static public function getSites()
	{
		$ss = array();
		$dbs = CSite::GetList($b="sort", $o="desc");
		while ($s = $dbs->Fetch())
		{
		  $ss[] = $s["LID"];
		}
		return $ss;
	}
	
	static public function setMoneyInfo()
	{
		self::$o_erip->money->setCurrency(self::$v["CURRENCY"]);
		self::$o_erip->money->setAmount(self::$v["PRICE"]);
	}
	
	static public function setTehnicalInfo()
	{
		self::$o_erip->setLogin(\Bitrix\Main\Config\Option::get( self::$module_id, "shop_id"));
		self::$o_erip->setPassword(\Bitrix\Main\Config\Option::get( self::$module_id, "shop_key"));
		self::$o_erip->setAddress4Send(\Bitrix\Main\Config\Option::get( self::$module_id, "address_for_send"));
		self::$o_erip->description = "order: ".self::$v["ID"];
		
		$notification_url = \Bitrix\Main\Config\Option::get( self::$module_id, "notification_url");
		$notification_url = str_replace('bitrix.local', 'bitrix.webhook.begateway.com:8443', $notification_url);
		
		self::$o_erip->notification_url = $notification_url;
		self::$o_erip->account_number = self::$v["ID"];
		self::$o_erip->service_number = \Bitrix\Main\Config\Option::get( self::$module_id, "service_number");
		self::$o_erip->service_info = \Bitrix\Main\Config\Option::get( self::$module_id, "service_info");
		self::$o_erip->receipt = \Bitrix\Main\Config\Option::get( self::$module_id, "receipt");;
		self::$o_erip->orderGenerate(self::$v["ID"]);
	}
	
	static public function setUserInfo()
	{
		$db_prop_order_vals = CSaleOrderPropsValue::GetList(
									array("SORT" => "ASC"),
									array(
										"ORDER_ID" => self::$v["ID"], 
										"CODE" => array(
													"FIO",
													"EMAIL",
													"CITY",
													"ZIP",
													"PHONE",
													"ADDRESS"
												  )
										),
									false,
									false,
									array("CODE", "ID", "VALUE")
							  );
		while( $val = $db_prop_order_vals->Fetch() )
		{
			if( !empty( $val["VALUE"]  ) )
			{
				if($val["CODE"] == "FIO")
					self::$o_erip->costumer->first_name = $val["VALUE"];
				else
				{
					$val["CODE"] = strtolower($val["CODE"]);
					self::$o_erip->costumer->$val["CODE"] = $val["VALUE"];
				}
			}
			self::$o_erip->costumer->setCountry("BY");
			self::$o_erip->costumer->ip = $_SERVER["REMOTE_ADDR"];
		}
	}
}