<?
$classes = array(
				'\BeGateway\Module\Erip\EventHandler' => 'lib/event_handler.php',
				'\BeGateway\Module\Erip\Encoder' => 'lib/encoder.php',
				'\BeGateway\Module\Erip\OrderStatuses' => 'lib/order_statuses.php',
				'\BeGateway\Module\Erip\Events' => 'lib/order_statuses.php',
				'\BeGateway\Module\Erip\Money' => 'lib/money.php',
				'\BeGateway\Module\Erip\PaymentData' => 'lib/payment_data.php'
		   );

CModule::AddAutoloadClasses('begateway.erip', $classes);

// Module updates copy files without re-running DoInstall(). Register the
// BEP-30748 post-save hooks once for existing installations.
if (\Bitrix\Main\ModuleManager::isModuleInstalled('begateway.erip')
    && \Bitrix\Main\Config\Option::get(
      'begateway.erip',
      'bep_30748_handlers_registered',
      'N'
    ) !== 'Y') {
  $eventManager = \Bitrix\Main\EventManager::getInstance();
  $eventManager->registerEventHandler(
    'sale',
    'OnSalePaymentEntitySaved',
    'begateway.erip',
    '\\BeGateway\\Module\\Erip\\EventHandler',
    'OnSalePaymentEntitySaved'
  );
  $eventManager->registerEventHandler(
    'sale',
    'OnSaleOrderSaved',
    'begateway.erip',
    '\\BeGateway\\Module\\Erip\\EventHandler',
    'OnSaleOrderSaved'
  );
  \Bitrix\Main\Config\Option::set(
    'begateway.erip',
    'bep_30748_handlers_registered',
    'Y'
  );
}
