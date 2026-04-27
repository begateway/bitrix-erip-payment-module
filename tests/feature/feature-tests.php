<?php
/**
 * BEP-29814 feature tests against the live Bitrix instance.
 *
 * Drives the patched begateway.erip handler exactly as the customer-facing
 * checkout and the admin-side status-change flow would, but via the Bitrix
 * PHP API for stability. Validates outcomes both in Bitrix DB and via the
 * bePaid sandbox REST API.
 *
 * Run inside the bitrix container:
 *   docker compose exec -T bitrix php /tmp/feature-tests.php
 */

declare(strict_types=1);

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('STOP_STATISTICS', true);
define('BX_NO_ACCELERATOR_RESET', true);
define('BX_BUFFER_USED', true);

$_SERVER['DOCUMENT_ROOT'] = '/var/www/html';
$_SERVER['HTTP_HOST']     = 'localhost';
$_SERVER['REQUEST_URI']   = '/';
// Handler builds request.ip from $_SERVER; without these bePaid returns 422 "Ip can't be blank".
$_SERVER['REMOTE_ADDR']      = '127.0.0.1';
$_SERVER['HTTP_CLIENT_IP']   = '127.0.0.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1';

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Sale;
use Bitrix\Sale\Internals\PaySystemActionTable;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\PaymentCollection;

if (!Loader::includeModule('sale')) { fwrite(STDERR, "cannot load sale\n"); exit(2); }
if (!Loader::includeModule('catalog')) { fwrite(STDERR, "cannot load catalog\n"); exit(2); }
if (!Loader::includeModule('iblock')) { fwrite(STDERR, "cannot load iblock\n"); exit(2); }
if (!Loader::includeModule('begateway.erip')) { fwrite(STDERR, "cannot load begateway.erip\n"); exit(2); }

$tests = 0; $failures = 0;
function out(string $s): void { echo $s . "\n"; }
function pass(string $m): void { global $tests; $tests++; out("  PASS  $m"); }
function fail(string $m, $exp = null, $act = null): void {
    global $tests, $failures; $tests++; $failures++;
    out("  FAIL  $m");
    if (func_num_args() >= 3) {
        out("        expected: " . var_export($exp, true));
        out("        actual:   " . var_export($act, true));
    }
}
function assertEq($e, $a, string $m): void { $e === $a ? pass($m) : fail($m, $e, $a); }
function assertTrue($c, string $m): void { $c ? pass($m) : fail($m); }
function section(string $t): void { out("\n== $t =="); }

// -----------------------------------------------------------------
// helpers
// -----------------------------------------------------------------

function bepaidRequest(string $method, string $path, ?array $body = null): array {
    $shopId = '4225';
    $secret = '3834fbef1fe6ea024ef77f5c79ec7ff1ba710ea6241c08c2f341afda8af4c1c4';
    $ch = curl_init('https://api.bepaid.by' . $path);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode("$shopId:$secret"),
            'RequestID: ' . bin2hex(random_bytes(16)),
        ],
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $code, 'body' => json_decode((string)$raw, true), 'raw' => (string)$raw];
}

function getPaySystemId(): int {
    $row = PaySystemActionTable::getList([
        'filter' => [
            ['LOGIC' => 'OR',
                ['=ACTION_FILE' => 'begateway_erip'],
                ['=ACTION_FILE' => 'begateway.erip'], // back-compat
            ],
            '=ACTIVE' => 'Y',
        ],
        'select' => ['ID'],
        'limit' => 1,
    ])->fetch();
    if (!$row) { fwrite(STDERR, "no active begateway.erip pay system\n"); exit(2); }
    return (int)$row['ID'];
}

function pickProduct(): array {
    // Find any active simple product with a price.
    $res = \CCatalogProduct::GetList(
        [],
        ['QUANTITY_TRACE' => 'N'], // any
        false, ['nTopCount' => 50], ['ID']
    );
    while ($p = $res->Fetch()) {
        $price = \CPrice::GetList([], ['PRODUCT_ID' => $p['ID']])->Fetch();
        if (!$price) continue;
        $info = \CIBlockElement::GetByID($p['ID'])->Fetch();
        if (!$info || $info['ACTIVE'] !== 'Y') continue;
        return [
            'id'    => (int)$p['ID'],
            'name'  => $info['NAME'],
            'price' => (float)$price['PRICE'],
            'currency' => $price['CURRENCY'],
        ];
    }
    fwrite(STDERR, "no usable product found in catalog\n"); exit(2);
}

function makeOrder(int $paySystemId, float $sum, string $currency = 'BYN'): Sale\Order {
    $product = pickProduct();
    $siteId = 's1';
    $userId = 1; // admin
    $order = Sale\Order::create($siteId, $userId, $currency);
    $order->setPersonTypeId(1);
    // basket
    $basket = Sale\Basket::create($siteId);
    $item = $basket->createItem('catalog', $product['id']);
    $item->setFields([
        'NAME'      => $product['name'] . " (e2e $sum)",
        'QUANTITY'  => 1,
        'CURRENCY'  => $currency,
        'PRICE'     => $sum,
        'BASE_PRICE'=> $sum,
        'CUSTOM_PRICE' => 'Y',
        'PRODUCT_ID' => $product['id'],
        'PRODUCT_PROVIDER_CLASS' => '',
        'LID'       => $siteId,
    ]);
    $order->setBasket($basket);
    // payment
    $pc = $order->getPaymentCollection();
    $payment = $pc->createItem(Sale\PaySystem\Manager::getObjectById($paySystemId));
    $payment->setField('SUM', $sum);
    $payment->setField('CURRENCY', $currency);
    $r = $order->save();
    if (!$r->isSuccess()) {
        fwrite(STDERR, "order save FAILED: " . implode('; ', $r->getErrorMessages()) . "\n");
        exit(2);
    }
    return $order;
}

function reloadOrder(int $orderId): Sale\Order {
    return Sale\Order::load($orderId);
}

const EA_MAIL_EVENT = 'BEGATEWAY_ERIP_SALE_ORDER_STATUS_CHANGED_EA';

function countEripMails(): int {
    $conn = \Bitrix\Main\Application::getConnection();
    $row = $conn->query(
        "SELECT COUNT(*) AS c FROM b_event WHERE EVENT_NAME = '" . EA_MAIL_EVENT . "'"
    )->fetch();
    return (int)($row['c'] ?? 0);
}

function setOrderStatus(int $orderId, string $statusId): array {
    $order = reloadOrder($orderId);
    // Order::setField('STATUS_ID', ...) fires OnBeforeSaleOrderSetField, which our
    // EventHandler uses to call Service::initiatePay → handler::showTemplate (HTML
    // gets echoed to stdout). Capture both setField and save in one buffer.
    ob_start();
    try {
        $r = $order->setField('STATUS_ID', $statusId);
        if (!$r->isSuccess()) {
            return ['success' => false, 'errors' => $r->getErrorMessages()];
        }
        $sr = $order->save();
    } finally {
        ob_end_clean();
    }
    return ['success' => $sr->isSuccess(), 'errors' => $sr->getErrorMessages()];
}

function changePaymentSum(int $orderId, float $newSum): void {
    $order = reloadOrder($orderId);
    /** @var Sale\Payment $payment */
    foreach ($order->getPaymentCollection() as $payment) {
        $payment->setField('SUM', $newSum);
    }
    foreach ($order->getBasket() as $item) {
        $item->setFields(['PRICE' => $newSum, 'BASE_PRICE' => $newSum, 'CUSTOM_PRICE' => 'Y']);
    }
    $order->setField('PRICE', $newSum);
    $r = $order->save();
    if (!$r->isSuccess()) {
        fwrite(STDERR, "change sum FAILED: " . implode('; ', $r->getErrorMessages()) . "\n");
        exit(2);
    }
}

function invokeInitiatePay(int $orderId, bool $manual = false): array {
    $order = reloadOrder($orderId);
    /** @var Sale\Payment $payment */
    $payment = $order->getPaymentCollection()->current();
    $service = $payment->getPaySystem();
    $autoBill = \Bitrix\Sale\BusinessValue::get('BEGATEWAY_ERIP_AUTO_BILL', 'PAYSYSTEM_' . $service->getField('ID'), $order->getPersonTypeId());
    out("    [diag] payment id=" . $payment->getId()
        . " sum=" . $payment->getSum()
        . " currency=" . $payment->getField('CURRENCY')
        . " ps_invoice=" . var_export($payment->getField('PS_INVOICE_ID'), true)
        . " person_type_id=" . $order->getPersonTypeId()
        . " ps_id=" . $service->getField('ID')
        . " AUTO_BILL=" . var_export($autoBill, true)
    );
    if ($manual) {
        // Mimic admin status-change flow (event_handler.php sets PS_STATUS_MESSAGE='manual')
        $payment->setField('PS_STATUS_MESSAGE', 'manual');
    }
    $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
    // Service::initiatePay -> handler -> showTemplate emits HTML to stdout; capture it.
    ob_start();
    try {
        $result = $service->initiatePay($payment, $request);
    } finally {
        ob_end_clean();
    }
    $psData = $result->getPsData();
    $data = $result->getData();
    if (!empty($psData['PS_INVOICE_ID'])) {
        $payment->setField('PS_INVOICE_ID', $psData['PS_INVOICE_ID']);
        $order->save();
    } else {
        // Diagnostic: nothing came back. Print the data the handler got from bePaid.
        out("    [diag] no PS_INVOICE_ID in psData. result->isSuccess=" . var_export($result->isSuccess(), true)
            . " errors=" . json_encode($result->getErrorMessages())
            . " data-keys=" . json_encode(array_keys((array)$data)));
        if (!empty($data) && is_array($data)) {
            out("    [diag] data='" . substr(json_encode($data, JSON_UNESCAPED_UNICODE), 0, 400) . "'");
        }
    }
    return [
        'success'        => $result->isSuccess(),
        'errors'         => $result->getErrorMessages(),
        'ps_invoice_id'  => $payment->getField('PS_INVOICE_ID'),
        'data'           => $data,
    ];
}

// -----------------------------------------------------------------
// tests
// -----------------------------------------------------------------

$paySystemId = getPaySystemId();
out(">> using begateway.erip pay system id=$paySystemId");

section('F1: BEP-29814 — basket sum lowered after bill issuance must recreate the bePaid bill');
{
    $A = 63.60; // initial
    $B = 39.44; // operator-edited
    $order = makeOrder($paySystemId, $A);
    $orderId = $order->getId();
    out("created order id=$orderId at sum=$A");

    // Step 1 — initial bill creation (auto mode, like a customer hitting the payment page)
    $r1 = invokeInitiatePay($orderId);
    assertTrue($r1['success'], 'initial initiatePay succeeds');
    $uidA = $r1['ps_invoice_id'];
    assertTrue(!empty($uidA), 'initial bill UID stored on Payment');
    if ($uidA) {
        $bill = bepaidRequest('GET', "/beyag/payments/$uidA");
        assertEq(6360, (int)($bill['body']['transaction']['amount'] ?? -1), 'bePaid bill A has amount=6360');
    }

    // Step 2 — operator lowers the basket / payment sum to 39.44
    changePaymentSum($orderId, $B);
    out("operator changed sum: $A -> $B");

    // Step 3 — handler runs again (manual mode emulates admin EA status change)
    $r2 = invokeInitiatePay($orderId, true);
    assertTrue($r2['success'], 'second initiatePay succeeds');
    $uidB = $r2['ps_invoice_id'];
    assertTrue(!empty($uidB), 'new bill UID stored on Payment');
    assertTrue($uidB !== $uidA, 'PS_INVOICE_ID changed (recreate happened)');

    // Step 4 — verify bePaid: old bill gone (or non-pending), new bill has 3944
    if ($uidB) {
        $billNew = bepaidRequest('GET', "/beyag/payments/$uidB");
        assertEq(3944, (int)($billNew['body']['transaction']['amount'] ?? -1), 'new bePaid bill has amount=3944');
    }
    if ($uidA) {
        $billOld = bepaidRequest('GET', "/beyag/payments/$uidA");
        $oldStatus = (string)($billOld['body']['transaction']['status'] ?? '');
        assertTrue(in_array($billOld['status'], [200, 404], true) && $oldStatus !== 'pending',
            "old bePaid bill is no longer pending (HTTP {$billOld['status']}, status='$oldStatus')");
    }

    // cleanup
    if ($uidB) bepaidRequest('DELETE', "/beyag/payments/$uidB");
}

section('F2: idempotent re-render — sum unchanged, no recreate, no new email');
{
    $A = 25.00;
    $order = makeOrder($paySystemId, $A);
    $orderId = $order->getId();

    $r1 = invokeInitiatePay($orderId);
    $uid1 = $r1['ps_invoice_id'];
    assertTrue(!empty($uid1), 'first render creates bill');

    // second render with no edits — should reuse the bill
    $r2 = invokeInitiatePay($orderId);
    $uid2 = $r2['ps_invoice_id'];
    assertEq($uid1, $uid2, 'PS_INVOICE_ID unchanged on idempotent re-render');

    // bePaid amount still A
    $bill = bepaidRequest('GET', "/beyag/payments/$uid2");
    assertEq(2500, (int)($bill['body']['transaction']['amount'] ?? -1), 'bill amount unchanged at 2500');

    if ($uid2) bepaidRequest('DELETE', "/beyag/payments/$uid2");
}

section('F4: cancel via PaySystem::cancel deletes the bill in bePaid');
{
    $A = 18.00;
    $order = makeOrder($paySystemId, $A);
    $orderId = $order->getId();
    $r1 = invokeInitiatePay($orderId);
    $uid = $r1['ps_invoice_id'];
    assertTrue(!empty($uid), 'bill created');

    // call the handler's cancel directly (this is what EventHandler::cancelPay does on EC status)
    $orderReloaded = reloadOrder($orderId);
    /** @var Sale\Payment $payment */
    $payment = $orderReloaded->getPaymentCollection()->current();
    $cancelResult = $payment->getPaySystem()->cancel($payment);
    assertTrue($cancelResult->isSuccess(), 'cancel call succeeds');

    // bePaid: bill should not be in pending state any more
    if ($uid) {
        $bill = bepaidRequest('GET', "/beyag/payments/$uid");
        $st = (string)($bill['body']['transaction']['status'] ?? '');
        assertTrue($st !== 'pending', "bill no longer pending after cancel (status='$st')");
    }
}

section('F5: instruction email fires on bill creation but not on idempotent re-toggle');
{
    // 5a — initial creation via EA status change must enqueue exactly one email.
    $order = makeOrder($paySystemId, 12.34);
    $orderId = $order->getId();
    $before = countEripMails();
    $r = setOrderStatus($orderId, 'EA');
    assertTrue($r['success'], 'setStatus EA succeeds (errors=' . json_encode($r['errors']) . ')');
    $afterCreate = countEripMails();
    assertEq($before + 1, $afterCreate, 'one mail enqueued after initial bill creation');

    // capture UID for later cleanup + drift assertion
    $uidInitial = reloadOrder($orderId)->getPaymentCollection()->current()->getField('PS_INVOICE_ID');
    assertTrue(!empty($uidInitial), 'PS_INVOICE_ID set after EA transition');

    // 5b — idempotent re-toggle (away then back, sum unchanged) must NOT enqueue another email.
    $r = setOrderStatus($orderId, 'N');
    assertTrue($r['success'], 'setStatus back to N succeeds');
    $r = setOrderStatus($orderId, 'EA');
    assertTrue($r['success'], 'setStatus EA again succeeds');
    $afterRetoggle = countEripMails();
    assertEq($afterCreate, $afterRetoggle, 'no extra mail on idempotent EA re-toggle');

    $uidAfterRetoggle = reloadOrder($orderId)->getPaymentCollection()->current()->getField('PS_INVOICE_ID');
    assertEq($uidInitial, $uidAfterRetoggle, 'bill UID unchanged on idempotent re-toggle');

    // 5c — change basket sum, then re-toggle to EA → patched handler recreates the bill,
    //       and event_handler must enqueue a fresh instruction email for the new sum.
    changePaymentSum($orderId, 5.67);
    $r = setOrderStatus($orderId, 'N');
    assertTrue($r['success'], 'setStatus N before recreate succeeds');
    $r = setOrderStatus($orderId, 'EA');
    assertTrue($r['success'], 'setStatus EA after sum change succeeds');
    $afterRecreate = countEripMails();
    assertEq($afterRetoggle + 1, $afterRecreate, 'one extra mail enqueued after amount-drift recreate');

    $uidAfterRecreate = reloadOrder($orderId)->getPaymentCollection()->current()->getField('PS_INVOICE_ID');
    assertTrue($uidAfterRecreate !== $uidInitial && !empty($uidAfterRecreate),
        'bill UID changed after sum-drift recreate (was=' . substr((string)$uidInitial, 0, 8)
        . '... now=' . substr((string)$uidAfterRecreate, 0, 8) . '...)');

    // cleanup bePaid bill
    if ($uidAfterRecreate) bepaidRequest('DELETE', "/beyag/payments/$uidAfterRecreate");
}

section('F3: success-path bePaid auto-webhook lands as paid');
{
    $publicKey = '';
    // Read configured public key for the pay system
    $paySystemId = getPaySystemId();
    $bv = \Bitrix\Sale\Internals\BusinessValueTable::getList([
        'filter' => ['=CODE_KEY' => 'BEGATEWAY_ERIP_PUBLIC_KEY', '=CONSUMER_KEY' => 'PAYSYSTEM_' . $paySystemId],
        'select' => ['PROVIDER_VALUE'],
    ])->fetch();
    if ($bv) $publicKey = trim((string)$bv['PROVIDER_VALUE']);

    if ($publicKey === '') {
        out("  SKIP  no BEGATEWAY_ERIP_PUBLIC_KEY configured — webhook signature would always fail. Set it via install-erip.php (env BEPAID_PUBLIC_KEY) and rerun.");
    } else {
        // Even with key, this test depends on bePaid firing the auto-webhook within ~30s.
        $A = 10.00;
        $order = makeOrder($paySystemId, $A);
        $orderId = $order->getId();
        $r = invokeInitiatePay($orderId);
        $uid = $r['ps_invoice_id'];
        assertTrue(!empty($uid), 'bill created for success-path test');

        out("  waiting up to 60s for bePaid sandbox to settle the bill to 'successful'...");
        $deadline = time() + 60;
        $reached = false;
        while (time() < $deadline) {
            $bill = bepaidRequest('GET', "/beyag/payments/$uid");
            $st = $bill['body']['transaction']['status'] ?? null;
            if ($st === 'successful') { $reached = true; break; }
            sleep(3);
        }
        assertTrue($reached, "bePaid bill reached 'successful' status");

        // Now poll Bitrix DB until the webhook has been processed (PS_STATUS = Y).
        $deadline = time() + 45;
        $psStatus = '';
        while (time() < $deadline) {
            $psStatus = (string)reloadOrder($orderId)->getPaymentCollection()->current()->getField('PS_STATUS');
            if ($psStatus === 'Y') break;
            sleep(3);
        }
        assertEq('Y', $psStatus, "Bitrix Payment.PS_STATUS = Y after webhook (within 45s)");
    }
}

out("\n== Summary ==");
out("tests: $tests, failures: $failures");
exit($failures === 0 ? 0 : 1);
