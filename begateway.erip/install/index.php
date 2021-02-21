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

  function __construct()
	{
		$arModuleVersion = array();

		$this->MODULE_NAME = \BeGateway\Module\Erip\Encoder::GetEncodeMessage('SALE_HPS_BEGATEWAY_ERIP_MODULE');
		$this->MODULE_DESCRIPTION = \BeGateway\Module\Erip\Encoder::GetEncodeMessage('SALE_HPS_BEGATEWAY_ERIP_MODULE_DESC');
    $this->PARTNER_NAME = \BeGateway\Module\Erip\Encoder::GetEncodeMessage('SALE_HPS_BEGATEWAY_ERIP_PARTNER_NAME');
    $this->PARTNER_URI = \BeGateway\Module\Erip\Encoder::GetEncodeMessage('SALE_HPS_BEGATEWAY_ERIP_PARTNER_URI');
    $this->PAYMENT_HANDLER_PATH = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/php_interface/include/sale_payment/" . str_replace(".", "_", $this->MODULE_ID) . "/";
    $this->MODULE_PATH = $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID;

		include($this->MODULE_PATH . '/install/version.php');

		if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion))
		{
			$this->MODULE_VERSION = $arModuleVersion["VERSION"];
			$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		}
	}


	protected function copyHandlerFiles()
	{
    \BeGateway\Module\Erip\Encoder::reEncode($this->MODULE_PATH . "/lang/", SITE_CHARSET);
		return CopyDirFiles(
					$this->MODULE_PATH ."/install/sale_payment/".$this->MODULE_ID,
          $this->PAYMENT_HANDLER_PATH,
					true, true
				);
	}

	protected function deleteHandlerFiles()
	{
		DeleteDirFilesEx("/bitrix/php_interface/include/sale_payment/" . str_replace(".", "_", $this->MODULE_ID));
		return true;
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
      \Bitrix\Main\Localization\Loc::loadLanguageFile($this->MODULE_PATH.'/install/install.php', $languageId);
      foreach (array(\BeGateway\Module\Erip\OrderStatuses::ORDER_AWAITING_STATUS, \BeGateway\Module\Erip\OrderStatuses::ORDER_CANCELED_STATUS) as $statusId) {
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
      'ID'     => \BeGateway\Module\Erip\OrderStatuses::ORDER_AWAITING_STATUS,
      'SORT'   => 1500,
      'NOTIFY' => 'Y',
      'LANG'   => $statusLanguages[\BeGateway\Module\Erip\OrderStatuses::ORDER_AWAITING_STATUS],
    ));

    $result = $result && CSaleStatus::Add(array(
      'ID'     => \BeGateway\Module\Erip\OrderStatuses::ORDER_CANCELED_STATUS,
      'SORT'   => 1600,
      'NOTIFY' => 'Y',
      'LANG'   => $statusLanguages[\BeGateway\Module\Erip\OrderStatuses::ORDER_CANCELED_STATUS],
    ));

    return $result;
	}

	protected function deleteOStatus()
	{
    foreach (array(\BeGateway\Module\Erip\OrderStatuses::ORDER_AWAITING_STATUS, \BeGateway\Module\Erip\OrderStatuses::ORDER_CANCELED_STATUS) as $statusId) {
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

	protected function addHandlers()
	{
    $eventManager = \Bitrix\Main\EventManager::getInstance();
    $eventManager->registerEventHandler('sale', 'OnBeforeSaleOrderSetField', $this->MODULE_ID, '\\BeGateway\\Module\\Erip\\EventHandler', 'OnBeforeSaleOrderSetField');

		return true;
	}

	protected function deleteHandlers()
	{
    $eventManager = \Bitrix\Main\EventManager::getInstance();
    $eventManager->unRegisterEventHandler('sale', 'OnBeforeSaleOrderSetField', $this->MODULE_ID, '\\BeGateway\\Module\\Erip\\EventHandler', 'OnBeforeSaleOrderSetField');

		return true;
	}

  protected function addMailEvent()
	{
    $dbEvent = CEventMessage::GetList($b="ID", $order="ASC", Array("EVENT_NAME" => \BeGateway\Module\Erip\Events::ORDER_STATUS_CHANGED_TO_EA));
    $id = false;

    if(!($dbEvent->Fetch())) {
      $langs = CLanguage::GetList(($b=""), ($o=""));
      while($lang = $langs->Fetch()) {
        $lid = $lang["LID"];
        IncludeModuleLangFile(__FILE__, $lid);

        $et = new CEventType;
        $et->Add(array(
          "LID" => $lid,
          "EVENT_NAME" => \BeGateway\Module\Erip\Events::ORDER_STATUS_CHANGED_TO_EA,
          "NAME" => GetMessage("SALE_HPS_BEGATEWAY_ERIP_MAIL_EVENT_NAME"),
          "DESCRIPTION" => GetMessage("SALE_HPS_BEGATEWAY_ERIP_MAIL_EVENT_DESC"),
        ));

        $arSites = array();
        $sites = CSite::GetList(($b=""), ($o=""), Array("LANGUAGE_ID"=>$lid));

        while ($site = $sites->Fetch())
          $arSites[] = $site["LID"];

        if(count($arSites) > 0) {
          $template = str_replace([
            "#SITE_CHARSET#",
            '#ABOUT_SERVICE#'
          ],
          [
            $lang["CHARSET"],
            GetMessage("SALE_HPS_BEGATEWAY_ERIP_MAIL_TEMPLATE_CHECKOUT_DESCRIPTION")
          ], GetMessage("SALE_HPS_BEGATEWAY_ERIP_MAIL_TEMPLATE_HTML"));

          $emess = new CEventMessage;
          $id = $emess->Add(array(
            "ACTIVE" => "Y",
            "EVENT_NAME" => \BeGateway\Module\Erip\Events::ORDER_STATUS_CHANGED_TO_EA,
            "LID" => $arSites,
            "EMAIL_FROM" => "#SALE_EMAIL#",
            "EMAIL_TO" => "#EMAIL#",
            "BCC" => "#BCC#",
            "SUBJECT" => GetMessage("SALE_HPS_BEGATEWAY_ERIP_MAIL_TEMPLATE_SUBJECT"),
            "MESSAGE" => $template,
            "BODY_TYPE" => "html",
          ));
        }
      }
    }

    Loc::loadMessages(__FILE__);
    return $id;
	}

  protected function deleteMailEvent()
	{
		CEventType::Delete(\BeGateway\Module\Erip\Events::ORDER_STATUS_CHANGED_TO_EA);

		$mail_template_id = (int)\Bitrix\Main\Config\Option::get($this->MODULE_ID, "mail_template_id");
		CEventMessage::Delete($mail_template_id);
		return true;
	}

  public function DoInstall() {

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

		// Создание типа почтового события
    $id = $this->addMailEvent();
		if($id === false)
			throw new Exception(Loc::getMessage("SALE_HPS_BEGATEWAY_ERIP_MAIL_EVENT_ADD_ERROR"));

		//сохранение ID почтового шаблона в настройках модуля
		\Bitrix\Main\Config\Option::set($this->MODULE_ID, "mail_template_id",  $id);

		//регистрация обработчика обновления заказа
		if($this->addHandlers() === false)
			throw new Exception(Loc::getMessage("SALE_HPS_BEGATEWAY_ERIP_HANDLERS_ADD_ERROR"));

		//регистраниция модуля
    RegisterModule($this->MODULE_ID);
    return true;
  }

  public function DoUninstall()
  {
		//удаление статуса заказа [ЕРИП]Ожидание оплаты
		$this->deleteOStatus();

		//удаление почтового события
		$this->deleteMailEvent();

		// удаление обработчика обновления заказа
		if($this->deleteHandlers() === false)
			throw new Exception(Loc::getMessage("SALE_HPS_BEGATEWAY_ERIP_HANDLERS_DELETE_ERROR"));

		//удаления файлов обработчика пл. системы
		$this->deleteHandlerFiles();

		//удаление модуля из системы
    UnRegisterModule($this->MODULE_ID);
		return true;
  }
}
