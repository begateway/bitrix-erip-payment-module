<?
$classes = array(
				'\BeGateway\Module\Erip\EventHandler' => 'lib/event_handler.php',
				'\BeGateway\Module\Erip\Encoder' => 'lib/encoder.php',
				'\BeGateway\Module\Erip\OrderStatuses' => 'lib/order_statuses.php',
				'\BeGateway\Module\Erip\Events' => 'lib/order_statuses.php'
		   );

CModule::AddAutoloadClasses('begateway.erip', $classes);
