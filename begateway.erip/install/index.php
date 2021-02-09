<?php
use Bitrix\Main\Localization\Loc;

require_once(__DIR__ . '/../include.php');
Loc::loadMessages(__FILE__);

if (!CModule::IncludeModule("sale")) return false;

class begateway_erip extends CModule
{
	public $MODULE_ID = 'begateway.erip';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;
	public $MODULE_GROUP_RIGHTS = 'N';

  const ORDER_AWAITING_STATUS = 'EA';
  const ORDER_CANCELED_STATUS = 'EC';

  function __construct()
	{
		$arModuleVersion = array();

		$path = str_replace("\\", "/", __FILE__);
		$path = substr($path, 0, strlen($path) - strlen("/index.php"));
		include($path."/version.php");

		if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion))
		{
			$this->MODULE_VERSION = $arModuleVersion["VERSION"];
			$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		}

		$this->MODULE_NAME = \BeGateway\Module\Erip\Encoder::GetEncodeMessage('SALE_HPS_BEGATEWAY_ERIP_MODULE');
		$this->MODULE_DESCRIPTION = \BeGateway\Module\Erip\Encoder::GetEncodeMessage('SALE_HPS_BEGATEWAY_ERIP_MODULE_DESC');
    $this->PARTNER_NAME = \BeGateway\Module\Erip\Encoder::GetEncodeMessage('SALE_HPS_BEGATEWAY_ERIP_PARTNER_NAME');
    $this->PARTNER_URI = \BeGateway\Module\Erip\Encoder::GetEncodeMessage('SALE_HPS_BEGATEWAY_ERIP_PARTNER_URI');
    $this->PAYMENT_HANDLER_PATH = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/php_interface/include/sale_payment/" . str_replace(".", "_", $this->MODULE_ID) . "/";
	}


	protected function copyHandlerFiles()
	{
		return CopyDirFiles(
					$_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/install/sale_payment/".$this->MODULE_ID,
          $this->PAYMENT_HANDLER_PATH,
					#$_SERVER["DOCUMENT_ROOT"]."/bitrix/php_interface/include/sale_payment",
					true, true
				);
	}

	protected function deleteHandlerFiles()
	{
		DeleteDirFilesEx("/bitrix/php_interface/include/sale_payment/" . str_replace(".", "_", $this->MODULE_ID));
		return true;
	}

	protected function addPaysysHandler( $psid )
	{
		$a_ps_act = array();
		$fields = array(
					"PAY_SYSTEM_ID" => $psid,
					"NAME" => Loc::getMessage("DEVTM_ERIP_PS_ACTION_NAME"),
          "DESCRIPTION" => Loc::getMessage("DEVTM_ERIP_PS_DESC"),
					"ACTION_FILE" => "/bitrix/php_interface/include/sale_payment/".$this->MODULE_ID,
					"NEW_WINDOW" => "N",
					"HAVE_PREPAY" => "N",
					"HAVE_RESULT" => "N",
					"HAVE_ACTION" => "N",
					"HAVE_PAYMENT" => "Y",
					"HAVE_RESULT_RECEIVE" => "Y",
					"ENCODING" => "utf-8",
				 );
		$db_pt = CSalePersonType::GetList(
							array("SORT" => "ASC", "NAME" => "ASC"),
							array()
						);
		while($pt = $db_pt->Fetch())
		{
			$fields["PERSON_TYPE_ID"] = $pt["ID"];
			$id = CSalePaySystemAction::Add($fields);
			if($id != false)
				$a_ps_act[] = $id;

		}

		return $a_ps_act;
	}

	protected function addOStatus()
	{
    $result = \Bitrix\Main\Localization\LanguageTable::getList(array(
      'select' => array('LID'),
      'filter' => array('=ACTIVE' => 'Y'),
    ));

    $statusLanguages = array();

    while ($row = $result->Fetch()) {
      $languageId = $row['LID'];
      \Bitrix\Main\Localization\Loc::loadLanguageFile($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/'.$this->MODULE_ID.'/install/install.php', $languageId);
      foreach (array(self::ORDER_AWAITING_STATUS, self::ORDER_CANCELED_STATUS) as $statusId) {
        if ($statusName = \BeGateway\Module\Erip\Encoder::GetEncodeMessage("SALE_HPS_BEGATEWAY_ERIP_{$statusId}_STATUS")) {
          $statusLanguages[$statusId] []= array(
            'LID'         => $languageId,
            'NAME'        => $statusName,
            'DESCRIPTION' => \BeGateway\Module\Erip\Encoder::GetEncodeMessage("SALE_HPS_BEGATEWAY_ERIP_{$statusId}_STATUS_DESC")
          );
        }
      }
    }

    $result = CSaleStatus::Add(array(
      'ID'     => self::ORDER_AWAITING_STATUS,
      'SORT'   => 1500,
      'NOTIFY' => 'Y',
      'LANG'   => $statusLanguages[self::ORDER_AWAITING_STATUS],
    ));

    $result = $result && CSaleStatus::Add(array(
      'ID'     => self::ORDER_CANCELED_STATUS,
      'SORT'   => 1600,
      'NOTIFY' => 'Y',
      'LANG'   => $statusLanguages[self::ORDER_CANCELED_STATUS],
    ));

    return $result;
	}

	protected function deleteOStatus()
	{
    foreach (array(self::ORDER_AWAITING_STATUS, self::ORDER_CANCELED_STATUS) as $statusId) {
      $result = \Bitrix\Sale\Order::loadByFilter(array(
        'filter' => array('=STATUS_ID' => $statusId)
      ));

      if (!empty($result))
        throw new Exception(\BeGateway\Module\Erip\Encoder::GetEncodeMessage("SALE_HPS_BEGATEWAY_ERIP_{$statusId}_STATUS_ERROR"));

  		if(!CSaleStatus::Delete($statusId))
  			throw new Exception(\BeGateway\Module\Erip\Encoder::GetEncodeMessage("SALE_HPS_BEGATEWAY_ERIP_{$statusId}_STATUS_ERROR_2"));
    }

		return true;
	}

	protected function addMailEvType()
	{
		foreach($this->lang_ids as $lang)
		{
			$f = array(
					"LID" => $lang,
					"EVENT_NAME" => $this->mail_event_name,
					"NAME" => Loc::getMessage("DEVTM_ERIP_MAIL_EVENT_NAME"),
					"DESCRIPTION" => Loc::getMessage("DEVTM_ERIP_MAIL_EVENT_DESC"),
				);

			$et = new CEventType;
			if($et->Add($f) === false)
				return false;
		}

		return true;
	}

	protected function deleteMailEvType()
	{
		$et = \Bitrix\Main\Config\Option::get( $this->MODULE_ID, "mail_event_name");
		CEventType::Delete($et);
		return true;
	}

	protected function addMailTemplate()
	{
		$ss = array();
		$db_sites = CSite::GetList($by="sort", $order="desc", array());
		while($s = $db_sites->Fetch())
			$ss[] = $s["ID"];

		$f = array(
				"ACTIVE" => "Y",
				"EVENT_NAME" => $this->mail_event_name,
				"LID" => $ss,
				"EMAIL_FROM" => "#DEFAULT_EMAIL_FROM#",
				"EMAIL_TO" => "#EMAIL_TO#",
				"SUBJECT" => Loc::getMessage("DEVTM_ERIP_MAIL_TEMPLATE_THEMA"),
				"BODY_TYPE" => "html",
				"MESSAGE" => Loc::getMessage("DEVTM_ERIP_MAIL_TEMPLATE_MESS"),
			);

		$o_mt = new CEventMessage;
		return $o_mt->Add($f);
	}

	protected function deleteMailTemplate()
	{
		$mail_template_id = (int)\Bitrix\Main\Config\Option::get( $this->MODULE_ID, "mail_template_id");
		CEventMessage::Delete($mail_template_id);
		return true;
	}

	protected function addHandlers()
	{
		RegisterModuleDependences(
			"sale",
			"OnSaleOrderBeforeSaved",
			$this->MODULE_ID,
			"Handlers",
			"chStatusNew",
			200
	   );

	   //Совместимость со старым событием OnSaleBeforeStatusOrder
	   RegisterModuleDependences(
			"sale",
			"OnSaleBeforeStatusOrder",
			$this->MODULE_ID,
			"Handlers",
			"chStatusOld",
			200
	   );

		return true;
	}

	protected function deleteHandlers()
	{
		UnRegisterModuleDependences(
			"sale",
			"OnSaleOrderBeforeSaved",
			$this->MODULE_ID,
			"Handlers",
			"chStatusNew"
		);

		//Совместимость со старым событием OnSaleBeforeStatusOrder
		UnRegisterModuleDependences(
			"sale",
			"OnSaleBeforeStatusOrder",
			$this->MODULE_ID,
			"Handlers",
			"chStatusOld"
		);

		return true;
	}

    public function DoInstall()
    {
			//Проверка зависимостей модуля
			if( ! IsModuleInstalled("sale") )
				throw new Exception(\BeGateway\Module\Erip\Encoder::GetEncodeMessage("SALE_HPS_BEGATEWAY_ERIP_SALE_MODULE_NOT_INSTALL_ERROR"));
			if( ! function_exists("curl_init") )
				throw new Exception(\BeGateway\Module\Erip\Encoder::GetEncodeMessage("SALE_HPS_BEGATEWAY_ERIP_CURL_NOT_INSTALL_ERROR"));
			if( ! function_exists("json_decode") )
				throw new Exception(\BeGateway\Module\Erip\Encoder::GetEncodeMessage("SALE_HPS_BEGATEWAY_ERIP_JSON_NOT_INSTALL_ERROR"));

			//копируем файлы обработчика платежной системы
			if(!$this->copyHandlerFiles())
				throw new Exception(\BeGateway\Module\Erip\Encoder::GetEncodeMessage("SALE_HPS_BEGATEWAY_ERIP_COPY_ERROR_MESS"));

			//создание статуса заказа [ЕРИП]Ожидание оплаты
			if(!$this->addOStatus())
				throw new Exception(\BeGateway\Module\Erip\Encoder::GetEncodeMessage("SALE_HPS_BEGATEWAY_ERIP_ADD_ORDER_STATUS_ERROR"));

			//регистраниция модуля
      RegisterModule($this->MODULE_ID);
			// //Создание типа почтового события
			// if($this->addMailEvType() === false)
			// 	throw new Exception(Loc::getMessage("DEVTM_ERIP_MAIL_EVENT_ADD_ERROR"));
      //
			// //сохранение названия типа почтового события в настройках модуля
			// \Bitrix\Main\Config\Option::set( $this->MODULE_ID, "mail_event_name",  $this->mail_event_name);
      //
			// //создание почтового шаблона
			// $mail_temp_id = $this->addMailTemplate();
			// if($mail_temp_id === false)
			// 	throw new Exception(Loc::getMessage("DEVTM_ERIP_MAIL_TEMPLATE_ADD_ERROR"));
      //
			// //сохранение ID почтового шаблона в настройках модуля
			// \Bitrix\Main\Config\Option::set( $this->MODULE_ID, "mail_template_id",  $mail_temp_id);
      //
			// //регистрация обработчика обновления заказа
			// if($this->addHandlers() === false)
			// 	throw new Exception(Loc::getMessage("DEVTM_ERIP_HANDLERS_ADD_ERROR"));
      return true;
    }

    public function DoUninstall()
    {
			//удаление статуса заказа [ЕРИП]Ожидание оплаты
			$this->deleteOStatus();

			// //удаление почтового шаблона
			// $this->deleteMailTemplate();
      //
			// //удаление почтового события
			// $this->deleteMailEvType();
      //
			// //удаляем обработчики пл. системы
			// $this->deletePaysysHandler();
      //
			//удаления файлов обработчика пл. системы
			$this->deleteHandlerFiles();

			//удаление модуля из системы
			//Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);
      UnRegisterModule($this->MODULE_ID);
			return true;
    }
}
