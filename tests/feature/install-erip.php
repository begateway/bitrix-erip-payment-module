<?php
/**
 * Install + configure the begateway.erip module inside the running Bitrix container.
 *
 * Run inside the bitrix container (CWD is the project root mounted at /var/www/html):
 *   docker compose exec -T bitrix php /var/www/html/bitrix/modules/begateway.erip-tests/install-erip.php
 *
 * Configuration via env (see docker compose exec -e ...):
 *   BEPAID_SHOP_ID  default 4225
 *   BEPAID_SECRET   default sandbox key
 *   BEPAID_PUBLIC_KEY  PEM-formatted public key (optional but webhook verification will fail without it)
 *   NOTIFICATION_URL   the publicly-reachable URL the bePaid webhook should hit
 */

declare(strict_types=1);

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('STOP_STATISTICS', true);
define('BX_NO_ACCELERATOR_RESET', true);
define('BX_BUFFER_USED', true);

$_SERVER['DOCUMENT_ROOT'] = '/var/www/html';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Loader;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\Internals\PaySystemActionTable;
use Bitrix\Sale\Internals\BusinessValueTable;
use Bitrix\Sale\Internals\PersonTypeTable;

function out(string $s): void { echo $s . "\n"; }

$shopId = getenv('BEPAID_SHOP_ID') ?: '4225';
$secret = getenv('BEPAID_SECRET') ?: '3834fbef1fe6ea024ef77f5c79ec7ff1ba710ea6241c08c2f341afda8af4c1c4';
$publicKey = getenv('BEPAID_PUBLIC_KEY') ?: '';
$notificationUrl = getenv('NOTIFICATION_URL') ?: '';
$paySystemName = 'BeGateway ERIP (sandbox)';
$paySystemCode = 'begateway_erip';
// install/index.php::copyHandlerFiles writes to .../sale_payment/begateway_erip/
// (str_replace('.', '_', $this->MODULE_ID)), so ACTION_FILE must use the underscore form.
$paySystemActionFile = 'begateway_erip';

out("== Step 1: install begateway.erip module ==");
if (ModuleManager::isModuleInstalled('begateway.erip')) {
    out("module already installed — skipping");
} else {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/begateway.erip/install/index.php';
    $installer = new begateway_erip();
    try {
        $installer->DoInstall();
        out("install OK");
    } catch (Throwable $e) {
        out("INSTALL FAILED: " . $e->getMessage());
        exit(1);
    }
}

out("\n== Step 2: ensure sale module loaded ==");
if (!Loader::includeModule('sale')) {
    out("ERROR: cannot load sale module");
    exit(1);
}
out("sale loaded");

out("\n== Step 3: pick a person type ==");
$pt = PersonTypeTable::getList(['select' => ['ID', 'NAME'], 'order' => ['ID' => 'ASC']])->fetch();
if (!$pt) { out("no person types defined; create one first via the admin"); exit(1); }
$personTypeId = (int)$pt['ID'];
out("using person type id={$personTypeId} name='{$pt['NAME']}'");

out("\n== Step 4: create or update the pay system ==");
$existing = PaySystemActionTable::getList([
    'filter' => ['=NAME' => $paySystemName],
    'select' => ['ID'],
])->fetch();

$siteId = '';
$res = \CSite::GetList('sort', 'asc', ['ACTIVE' => 'Y']);
if ($s = $res->Fetch()) { $siteId = $s['LID']; }
out("using site id='{$siteId}'");

$fields = [
    'NAME' => $paySystemName,
    'PSA_NAME' => $paySystemName,
    'ACTION_FILE' => $paySystemActionFile,
    'NEW_WINDOW' => 'N',
    'ACTIVE' => 'Y',
    'PERSON_TYPE_ID' => $personTypeId,
    'ENTITY_REGISTRY_TYPE' => 'ORDER',
    'PS_MODE' => '',
    'HAVE_PAYMENT' => 'Y',
    'HAVE_RESULT' => 'Y',
    'HAVE_RESULT_RECEIVE' => 'Y',
    'HAVE_PRICE' => 'N',
    'HAVE_PREPAY' => 'N',
    'HAVE_ACTION' => 'N',
    'CODE' => $paySystemCode,
    'TARIF' => null,
    'XML_ID' => 'begateway_erip_sandbox',
];
if ($existing) {
    $paySystemId = (int)$existing['ID'];
    PaySystemActionTable::update($paySystemId, $fields);
    out("updated existing pay system id={$paySystemId}");
} else {
    $r = PaySystemActionTable::add($fields);
    if (!$r->isSuccess()) { out("ADD FAILED: " . implode('; ', $r->getErrorMessages())); exit(1); }
    $paySystemId = $r->getId();
    out("created pay system id={$paySystemId}");
}

out("\n== Step 5: configure handler business values ==");
$bv = [
    'BEGATEWAY_ERIP_ID'                 => $shopId,
    'BEGATEWAY_ERIP_SECRET_KEY'         => $secret,
    'BEGATEWAY_ERIP_PUBLIC_KEY'         => $publicKey,
    'BEGATEWAY_ERIP_SERVICE_CODE'       => '',
    'BEGATEWAY_ERIP_PAYMENT_DESCRIPTION'=> 'Заказ #ORDER_ID#',
    'BEGATEWAY_ERIP_PAYMENT_ACCOUNT'    => 'order#ORDER_ID#',
    'BEGATEWAY_ERIP_RECEIPT_PAYMENT_DESCRIPTION' => 'Заказ #ORDER_ID#',
    'BEGATEWAY_ERIP_NOTIFICATION_URL'   => $notificationUrl,
    'BEGATEWAY_ERIP_SMS_NOTIFICATION'   => 'N',
    'BEGATEWAY_ERIP_AUTO_BILL'          => 'Y',
    'BEGATEWAY_ERIP_EXPIRY'             => '60',
    'BUYER_PERSON_NAME_FIRST'           => '',
    'BUYER_PERSON_NAME_MIDDLE'          => '',
    'BUYER_PERSON_NAME_LAST'            => '',
    'BUYER_PERSON_EMAIL'                => '',
    'BUYER_PERSON_ADDRESS'              => '',
    'BUYER_PERSON_CITY'                 => '',
    'BUYER_PERSON_ZIP'                  => '',
    'BUYER_PERSON_PHONE'                => '',
    'PS_IS_TEST'                        => 'Y',
    'PS_CHANGE_STATUS_PAY'              => 'Y',
];

$consumer = 'PAYSYSTEM_' . $paySystemId;
foreach ($bv as $code => $value) {
    // wipe any prior value for this consumer/code/personType
    $rows = BusinessValueTable::getList([
        'filter' => [
            '=CODE_KEY'      => $code,
            '=CONSUMER_KEY'  => $consumer,
            '=PERSON_TYPE_ID'=> $personTypeId,
        ],
        'select' => ['CODE_KEY', 'CONSUMER_KEY', 'PERSON_TYPE_ID'],
    ])->fetchAll();
    foreach ($rows as $row) {
        BusinessValueTable::delete([
            'CODE_KEY'      => $row['CODE_KEY'],
            'CONSUMER_KEY'  => $row['CONSUMER_KEY'],
            'PERSON_TYPE_ID'=> $row['PERSON_TYPE_ID'],
        ]);
    }
    $r = BusinessValueTable::add([
        'CODE_KEY'      => $code,
        'CONSUMER_KEY'  => $consumer,
        'PERSON_TYPE_ID'=> $personTypeId,
        'PROVIDER_KEY'  => 'VALUE',
        'PROVIDER_VALUE'=> (string)$value,
    ]);
    if (!$r->isSuccess()) {
        out("  - $code: ADD FAILED: " . implode('; ', $r->getErrorMessages()));
    } else {
        $shown = $code === 'BEGATEWAY_ERIP_SECRET_KEY' ? '<redacted>' : (strlen((string)$value) > 60 ? '<' . strlen((string)$value) . ' chars>' : (string)$value);
        out("  - $code = $shown");
    }
}

out("\nDONE. Pay system id={$paySystemId} configured for sandbox shop {$shopId}.");
