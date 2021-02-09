<?
$classes = array(
				#'\BeGateway\Module\Erip\CurlJsonRequest' => 'lib/CurlJsonRequest.php',
				#'\BeGateway\Module\Erip\Customer' => 'lib/Customer.php',
				#'\BeGateway\Module\Erip\Money' => 'lib/Money.php',
				#'\BeGateway\Module\Erip\Erip' => 'lib/Erip.php',
				#'\BeGateway\Module\Erip\Webhook' => 'lib/Webhook.php',
				#'\BeGateway\Module\Erip\Handlers' => 'lib/Handlers.php',
				'\BeGateway\Module\Erip\Encoder' => 'lib/encoder.php',
				'\BeGateway\Module\Erip\OrderStatuses' => 'lib/order_statuses.php'
		   );

CModule::AddAutoloadClasses('begateway.erip', $classes);
