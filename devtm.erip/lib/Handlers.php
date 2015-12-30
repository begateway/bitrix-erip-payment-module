<?
\Bitrix\Main\Loader::includeModule("devtm.erip");
\Bitrix\Main\Loader::includeModule("sale");

class Handlers
{
	static public $module_id = "devtm.erip";
	static public $o_erip;
	static public $values;
	static public $opt_status;
	static public $opt_payment;
	static public $o_response;

	static public function chStatusNew($entity)
	{
		try
		{
			$fields = $entity->getFields();
			self::$values = $fields->getValues();
			$old_values = $fields->getOriginalValues();
			self::$o_erip = new \Dm\Erip();
			self::$opt_status = \Bitrix\Main\Config\Option::get( self::$module_id, "order_status_code_erip");
			self::$opt_payment = \Bitrix\Main\Config\Option::get( self::$module_id, "payment_system_id");
			//var_dump(self::$opt_status == self::$values["STATUS_ID"]);
			//var_dump($old_values["STATUS_ID"] != self::$values["STATUS_ID"]);
			//var_dump(self::$values["PAY_SYSTEM_ID"] == self::$opt_payment);
			//die;
			if(self::$opt_status == self::$values["STATUS_ID"] &&
				$old_values["STATUS_ID"] != self::$values["STATUS_ID"] &&
				self::$values["PAY_SYSTEM_ID"] == self::$opt_payment)
			{
				static::setTehnicalInfo();
		
				static::setUserInfo();
				static::setMoneyInfo();
				
				$r = self::$o_erip->submit();
			
				self::$o_response = json_decode($r);
				
				if(isset(self::$o_response->errors))
					throw new \Exception(self::$o_response->message);
				
				
				if(\Bitrix\Sale\Internals\OrderTable::update(self::$values["ID"], array("COMMENTS" => "status: ". self::$o_response->transaction->status ."\n".
																"transaction_id: ". self::$o_response->transaction->transaction_id ."\n".
																"order_id: ". self::$o_response->transaction->order_id ."\n".
																"account_number: ". self::$o_response->transaction->erip->account_number ."\n")))
				{
					static::sendMail();
				}
				return true;
			}
		
		}catch(Exception $e){
			$GLOBALS["APPLICATION"]->ThrowException($e->getMessage());
			return false;
		}
	}
	
	static public function chStatusOld($id, $status)
	{
		try
		{
			self::$o_erip = new \Dm\Erip();
			self::$opt_status = \Bitrix\Main\Config\Option::get( self::$module_id, "order_status_code_erip");
			self::$opt_payment = \Bitrix\Main\Config\Option::get( self::$module_id, "payment_system_id");
			self::$values = CSaleOrder::GetList(array(), array("ID" => $id), false, false, array("ID", "PAY_SYSTEM_ID", "PRICE", "CURRENCY", "STATUS_ID"))->Fetch();
			
			
			if(self::$values["PAY_SYSTEM_ID"] == self::$opt_payment &&
				$status != self::$values["STATUS_ID"] &&
				$status == self::$opt_status)
			{
				static::setTehnicalInfo();
				
				static::setUserInfo();
				static::setMoneyInfo();
				
				$r = self::$o_erip->submit();
				self::$o_response = json_decode($r);
				
				if(isset(self::$o_response->errors))
					throw new \Exception(self::$o_response->message);
				
				if(CSaleOrder::Update($id, array("COMMENTS" => "status: ". self::$o_response->transaction->status ."\n".
																"transaction_id: ". self::$o_response->transaction->transaction_id ."\n".
																"order_id: ". self::$o_response->transaction->order_id ."\n".
																"account_number: ". self::$o_response->transaction->erip->account_number ."\n")))
				{
					static::sendMail();
				}
				return true;
			}
			
		}catch(Exception $e){
			$GLOBALS["APPLICATION"]->ThrowException($e->getMessage());
			return false;
		}
	}
	
	static public function sendMail()
	{
		$emt = \Bitrix\Main\Config\Option::get( self::$module_id, "mail_event_name");
 
		$mf = array(
				"EMAIL_TO" => self::$o_response->transaction->customer->email,
				"NAME" => self::$o_response->transaction->billing_address->first_name,
				"ORDER_ID" => self::$values["ID"],
				"SALE_NAME" => \Bitrix\Main\Config\Option::get( self::$module_id, "sale_name"),
				"SATE_NAME" => \Bitrix\Main\Config\Option::get( "main", "site_name"),
				"SALE_EMAIL" => \Bitrix\Main\Config\Option::get( "sale", "order_email"),
				"COMPANY_NAME" => \Bitrix\Main\Config\Option::get( self::$module_id, "company_name"),
				"PATH_TO_SERVICE" => \Bitrix\Main\Config\Option::get( self::$module_id, "path_to_service"),
				"SERVER_NAME" => $_SERVER["SERVER_NAME"],
			  );
		
		CEvent::Send($emt, static::getSites(), $mf, "N", \Bitrix\Main\Config\Option::get( self::$module_id, "mail_template_id"));
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
		self::$o_erip->money->setCurrency(self::$values["CURRENCY"]);
		self::$o_erip->money->setAmount(self::$values["PRICE"]);
	}
	
	static public function setTehnicalInfo()
	{
		self::$o_erip->setLogin(\Bitrix\Main\Config\Option::get( self::$module_id, "shop_id"));
		self::$o_erip->setPassword(\Bitrix\Main\Config\Option::get( self::$module_id, "shop_key"));
		self::$o_erip->setAddress4Send(\Bitrix\Main\Config\Option::get( self::$module_id, "address_for_send"));
		self::$o_erip->description = "order: ".self::$values["ID"];
		
		$notification_url = \Bitrix\Main\Config\Option::get( self::$module_id, "notification_url");
		$notification_url = str_replace('bitrix.local', 'bitrix.webhook.begateway.com:8443', $notification_url);
		
		self::$o_erip->notification_url = $notification_url;
		self::$o_erip->account_number = self::$values["ID"];
		self::$o_erip->service_number = \Bitrix\Main\Config\Option::get( self::$module_id, "service_number");
		self::$o_erip->service_info = \Bitrix\Main\Config\Option::get( self::$module_id, "service_info");
		self::$o_erip->receipt = \Bitrix\Main\Config\Option::get( self::$module_id, "receipt");;
		self::$o_erip->orderGenerate(self::$values["ID"]);
	}
	
	static public function setUserInfo()
	{
		$db_prop_order_vals = CSaleOrderPropsValue::GetList(
									array("SORT" => "ASC"),
									array(
										"ORDER_ID" => self::$values["ID"], 
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
		while( $value = $db_prop_order_vals->Fetch() )
		{
			if( !empty( $value["VALUE"]  ) )
			{
				if($value["CODE"] == "FIO")
					self::$o_erip->costumer->first_name = $value["VALUE"];
				else
				{
					$value["CODE"] = strtolower($value["CODE"]);
					self::$o_erip->costumer->$value["CODE"] = $value["VALUE"];
				}
			}
		}
		self::$o_erip->costumer->setCountry("BY");
		self::$o_erip->costumer->ip = $_SERVER["REMOTE_ADDR"];
	}
}
