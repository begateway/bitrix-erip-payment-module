<?
$ps = CSalePaySystem::GetList(Array(), Array("ID" => $arResult["ORDER"]["PAY_SYSTEM_ID"], "ACTIVE"=>"Y"))->Fetch();
if(strlen($ps["DESCRIPTION"]) > 0)
	echo "<p>".$ps["DESCRIPTION"]."</p>";
?>

