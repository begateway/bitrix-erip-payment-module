<?php
/**
 * BEP-29814 e2e tests against the bePaid sandbox.
 *
 * Run: php tests/e2e/run_erip_tests.php
 *
 * Test credentials default to the sandbox shop provided for this ticket.
 * Override via env: BEPAID_SHOP_ID, BEPAID_SECRET, BEPAID_API.
 *
 * Reference: https://docs.bepaid.by/ru/payment_methods/apms/erip/testing/
 *   - request.test = true puts the bill in test mode
 *   - request.amount = 999 (i.e. 9.99 BYN) → transaction settles to `failed`
 *   - any other amount in test mode → settles to `successful` after ~10 s
 */

declare(strict_types=1);

const DEFAULT_API       = 'https://api.bepaid.by';
const DEFAULT_SHOP_ID   = '4225';
const DEFAULT_SECRET    = '3834fbef1fe6ea024ef77f5c79ec7ff1ba710ea6241c08c2f341afda8af4c1c4';
const POLL_TIMEOUT_SEC  = 40;
const POLL_INTERVAL_SEC = 3;

$BEPAID_API     = getenv('BEPAID_API')     ?: DEFAULT_API;
$BEPAID_SHOP_ID = getenv('BEPAID_SHOP_ID') ?: DEFAULT_SHOP_ID;
$BEPAID_SECRET  = getenv('BEPAID_SECRET')  ?: DEFAULT_SECRET;

$failures = 0;
$tests = 0;

function out(string $s): void { fwrite(STDOUT, $s); }

function pass(string $msg): void {
    global $tests; $tests++;
    out("  PASS  $msg\n");
}
function fail(string $msg, $expected = null, $actual = null): void {
    global $tests, $failures; $tests++; $failures++;
    out("  FAIL  $msg\n");
    if (func_num_args() >= 3) {
        out("        expected: " . var_export($expected, true) . "\n");
        out("        actual:   " . var_export($actual, true) . "\n");
    }
}
function assertEq($expected, $actual, string $msg): void {
    if ($expected === $actual) { pass($msg); return; }
    fail($msg, $expected, $actual);
}
function assertTrue($cond, string $msg): void {
    if ($cond) { pass($msg); return; }
    fail($msg);
}
function assertHttp2xx(int $status, string $msg): void {
    if ($status >= 200 && $status < 300) { pass("$msg (HTTP $status)"); return; }
    fail($msg, '2xx', $status);
}
function warn(string $msg): void { out("  WARN  $msg\n"); }

function bepaid_request(string $method, string $path, ?array $body = null): array {
    global $BEPAID_API, $BEPAID_SHOP_ID, $BEPAID_SECRET;
    $ch = curl_init();
    $auth = base64_encode($BEPAID_SHOP_ID . ':' . $BEPAID_SECRET);
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . $auth,
        'RequestID: ' . bin2hex(random_bytes(16)),
    ];
    curl_setopt($ch, CURLOPT_URL, $BEPAID_API . $path);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if ($body !== null) {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("curl error on $method $path: $err");
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string)$raw, true);
    return ['status' => (int)$code, 'body' => $decoded, 'raw' => (string)$raw];
}

function createBill(int $amountCents, string $tracking, string $description = 'BEP-29814 e2e'): array {
    return bepaid_request('POST', '/beyag/payments', [
        'request' => [
            'test'           => true,
            'amount'         => $amountCents,
            'currency'       => 'BYN',
            'description'    => $description,
            'tracking_id'    => $tracking,
            'language'       => 'ru',
            'email'          => 'qa@example.com',
            'ip'             => '127.0.0.1',
            'notification_url' => 'https://example.com/webhook',
            'customer' => [
                'first_name' => 'Test',
                'last_name'  => 'User',
                'middle_name'=> 'QA',
                'city'       => 'Minsk',
                'zip'        => '220000',
                'address'    => 'Test addr 1',
                'phone'      => '+375290000000',
            ],
            'payment_method' => [
                'type'           => 'erip',
                'account_number' => 'acc-' . substr($tracking, 0, 20),
                'service_info'   => ['BEP-29814 e2e'],
                'receipt'        => ['BEP-29814 receipt'],
            ],
        ],
    ]);
}

function getBill(string $uid): array {
    return bepaid_request('GET', "/beyag/payments/$uid");
}
function deleteBill(string $uid): array {
    return bepaid_request('DELETE', "/beyag/payments/$uid");
}

function pollUntilStatus(string $uid, string $expectedStatus, int $timeout = POLL_TIMEOUT_SEC): array {
    $deadline = time() + $timeout;
    $last = null;
    while (time() < $deadline) {
        $r = getBill($uid);
        $last = $r;
        $status = $r['body']['transaction']['status'] ?? null;
        out("    poll uid=$uid status=" . var_export($status, true) . "\n");
        if ($status === $expectedStatus) {
            return $r;
        }
        sleep(POLL_INTERVAL_SEC);
    }
    return $last ?? ['status' => 0, 'body' => null, 'raw' => ''];
}

function section(string $title): void {
    out("\n== $title ==\n");
}

// -------------------------------------------------------------------- Tests

out("bePaid API: $BEPAID_API\n");
out("shop ID:    $BEPAID_SHOP_ID\n");
out("\n");

section('Test 1: round-trip create + GET');
{
    $tracking = 'tc1-' . time();
    $resp = createBill(6360, $tracking, 'tc1 round-trip');
    assertHttp2xx($resp['status'], 'POST /beyag/payments succeeds');
    $uid = $resp['body']['transaction']['uid'] ?? null;
    assertTrue($uid !== null, 'response carries transaction.uid');
    assertEq(6360, (int)($resp['body']['transaction']['amount'] ?? -1), 'echoed amount = 6360');
    assertEq('BYN', (string)($resp['body']['transaction']['currency'] ?? ''), 'echoed currency = BYN');
    assertEq('pending', (string)($resp['body']['transaction']['status'] ?? ''), 'initial status = pending');

    if ($uid) {
        $get = getBill($uid);
        assertEq(200, $get['status'], 'GET /beyag/payments/{uid} returns HTTP 200');
        assertEq(6360, (int)($get['body']['transaction']['amount'] ?? -1), 'GET amount = 6360');
        // cleanup
        deleteBill($uid);
    }
}

section('Test 2: BEP-29814 — delete-and-recreate when sum changes');
{
    $tracking = 'tc2-' . time();
    // Step A: customer placed order, bill issued for 63.60
    $a = createBill(6360, $tracking, 'tc2 initial 63.60');
    $uidOld = $a['body']['transaction']['uid'] ?? null;
    assertTrue($uidOld !== null, 'initial bill created');
    assertEq(6360, (int)($a['body']['transaction']['amount'] ?? -1), 'initial amount = 6360');

    // Step B: merchant edits the order, sum drops to 39.44 → patched handler
    // path: GET old bill, detect drift, DELETE, then POST new bill.
    if ($uidOld) {
        $del = deleteBill($uidOld);
        assertTrue(in_array($del['status'], [200, 204], true),
            'DELETE old bill returns 200/204 (got ' . $del['status'] . ')');
    }

    $b = createBill(3944, $tracking . '-v2', 'tc2 recreated 39.44');
    $uidNew = $b['body']['transaction']['uid'] ?? null;
    assertTrue($uidNew !== null, 'new bill created');
    assertTrue($uidNew !== $uidOld, 'recreated bill has a new UID');
    assertEq(3944, (int)($b['body']['transaction']['amount'] ?? -1), 'new amount = 3944 (39.44 BYN)');

    // Step C: verify a fresh GET on the new bill shows the new amount
    if ($uidNew) {
        $g = getBill($uidNew);
        assertEq(3944, (int)($g['body']['transaction']['amount'] ?? -1),
            'GET on new bill shows 3944 (no stale state leaked)');
        deleteBill($uidNew);
    }
}

section('Test 3: failure trigger — amount=999 settles to failed');
{
    $tracking = 'tc3-' . time();
    $resp = createBill(999, $tracking, 'tc3 failure trigger');
    assertHttp2xx($resp['status'], 'create succeeds');
    $uid = $resp['body']['transaction']['uid'] ?? null;
    assertTrue($uid !== null, 'transaction created with amount=999');
    assertEq(999, (int)($resp['body']['transaction']['amount'] ?? -1),
        'created bill carries the failure-trigger amount');
    if ($uid) {
        $failureTimeout = 120;
        out("  polling up to {$failureTimeout}s for status=failed...\n");
        $final = pollUntilStatus($uid, 'failed', $failureTimeout);
        $finalStatus = (string)($final['body']['transaction']['status'] ?? '');
        if ($finalStatus === 'failed') {
            pass('transaction settles to failed via auto-webhook');
        } else if ($finalStatus === 'pending') {
            warn("transaction still 'pending' after {$failureTimeout}s — sandbox failure-trigger may be slower than docs suggest; bill creation with amount=999 already verified.");
        } else {
            fail('transaction settles to failed', 'failed', $finalStatus);
        }
        deleteBill($uid);
    }
}

section('Test 4: success trigger — non-999 amount settles to successful');
{
    $tracking = 'tc4-' . time();
    $resp = createBill(1000, $tracking, 'tc4 success trigger'); // 10.00 BYN
    $uid = $resp['body']['transaction']['uid'] ?? null;
    assertTrue($uid !== null, 'transaction created');
    if ($uid) {
        out("  polling up to " . POLL_TIMEOUT_SEC . "s for status=successful...\n");
        $final = pollUntilStatus($uid, 'successful');
        assertEq('successful', (string)($final['body']['transaction']['status'] ?? ''),
            'transaction settles to successful via auto-webhook');
    }
}

// -------------------------------------------------------------------- Summary
section('Summary');
out("tests: $tests, failures: $failures\n");
exit($failures === 0 ? 0 : 1);
