<?
use \Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

//получение номера заказа
$order_id = $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["ID"];

if($order_id <= 0) return;

$module_id = "devtm.erip";

//получение статуса [ЕРИП Ожидание оплаты]
$status = \Bitrix\Main\Config\Option::get($module_id, "order_status_code_erip");

CModule::IncludeModule($module_id);

//Вызов Handlers::chStatusOld
$result = Handlers::chStatusOld($order_id, $status);
if($result === true)
{
	//Сохранение статуса заказа
	CModule::IncludeModule("sale");
	CSaleOrder::Update($order_id, array("STATUS_ID" => $status));
	echo "<p>".Loc::getMessage("DEVTM_ERIP_PAYMENT_OK_TEXT", array("#ORDER_ID#" => $order_id, "#PATH_TO_SERVICE#" => \Bitrix\Main\Config\Option::get($module_id, "path_to_service")))."</p>";
}


