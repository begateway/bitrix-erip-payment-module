<?php
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
\CModule::IncludeModule('begateway.erip');

for($i = 0; $i < count($params['instruction']); $i++) {
  $params['instruction'][$i] = \BeGateway\Module\Erip\Encoder::GetEncodeText($params['instruction'][$i]);
}

$sum = round($params['sum'], 2);
?>

<div class="mb-4" id="begateway-erip">
	<p><?= Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_TEMPLATE_BEGATEWAY_ERIP_CHECKOUT_DESCRIPTION') ?></p>
	<p><?= Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_TEMPLATE_BEGATEWAY_ERIP_CHECKOUT_SUM',
		[
			'#SUM#' => SaleFormatCurrency($sum, $params['currency']),
		]
	) ?></p>
	<p><?= Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_TEMPLATE_BEGATEWAY_ERIP_INSTRUCTION',
		[
			'#INSTRUCTION#' => implode('<br>', $params['instruction']),
			'#ACCOUNT_NUMBER#' => $params['account_number'],
      '#ERIP_SERVICE_CODE#' => $params['service_no_erip']
		]
	) ?></p>
  <?
    if (isset($params['qr_code']) && !is_null($params['qr_code'])) { ?>
    	<p><?= Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_TEMPLATE_BEGATEWAY_ERIP_QR_INSTRUCTION') ?></p>
      <p><img src="<?= $params['qr_code'] ?>"</p>
  <?}?>

	<p><?= Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_TEMPLATE_BEGATEWAY_ERIP_CHECKOUT_WARNING_RETURN') ?></p>
</div>
