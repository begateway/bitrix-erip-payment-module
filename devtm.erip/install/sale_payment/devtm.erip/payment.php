<?

if($arResult["ORDER"]["PAY_SYSTEM_ID"] > 0)
{
	$ps = CSalePaySystem::GetByID($arResult["ORDER"]["PAY_SYSTEM_ID"]);
	if(strlen($ps["DESCRIPTION"]) > 0)
	{
?>
		<p><?= $ps["DESCRIPTION"]?></p>
<?
	}
}
