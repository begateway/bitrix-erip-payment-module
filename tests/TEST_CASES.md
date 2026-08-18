# BEP-29814 / BEP-30748 — test cases

Bug: after a manual change of the order items / payment sum in Bitrix admin, the
ERIP module sent the **original** amount to bePaid instead of the updated one.

Root cause (pre-fix): when `PS_INVOICE_ID` was already set on the Bitrix
payment, both `handler.php::initiatePay` and `event_handler.php::initiatePay`
short-circuited and never re-issued the bePaid bill. The customer ended up
paying the stale amount via ERIP.

Fix: `handler.php::initiatePay` now fetches the existing bill, and on
amount/currency drift (only for `pending` bills) it deletes the stale bill and
creates a new one. `event_handler.php::initiatePay` no longer skips on
existing `PS_INVOICE_ID`; it forwards to the handler and decides whether to
re-email the customer based on whether the bill UID changed.

BEP-30748 exposed a second trigger gap: 1C can save a new Payment sum while the
order remains in the ERIP-awaiting status, so `initiatePay` was never called
again. The module now records `SUM`/`CURRENCY` changes in
`OnSalePaymentEntitySaved` and reconciles the bill at `OnSaleOrderSaved`,
after Bitrix has persisted the consistent order. A recursion guard makes the
nested `PS_INVOICE_ID` save idempotent.

Bitrix also omits `ServiceResult::getData()` from
`onSalePsInitiatePaySuccess`. The request-scoped
`BeGateway\Module\Erip\PaymentData::get($paymentId)` API exposes the latest
successful ERIP template payload to listeners after that event.

---

## Automated e2e (against the bePaid sandbox)

Run: `php tests/e2e/run_erip_tests.php`

Sandbox credentials baked in (test-only):
- Shop ID: `4225`
- Secret: `3834fbef1fe6ea024ef77f5c79ec7ff1ba710ea6241c08c2f341afda8af4c1c4`
- API: `https://api.bepaid.by`

Override via env: `BEPAID_SHOP_ID`, `BEPAID_SECRET`, `BEPAID_API`.

Reference for the test triggers used by the script:
https://docs.bepaid.by/ru/payment_methods/apms/erip/testing/

| # | Scenario | What it proves |
|---|----------|----------------|
| 1 | POST `/beyag/payments` with `test:true`, BYN 63.60 → GET it back. | Round-trip works; the API echoes the amount we sent. |
| 2 | Reproduce BEP-30748 order 8122: create bill with 252.44, then DELETE it, then create a fresh bill with 483.14. GET → assert new amount. | The fix's primitive (delete + recreate) is supported by the API and produces a new UID with the new amount. There is no PATCH endpoint, so delete-and-recreate is the only correct path. |
| 3 | POST with `request.amount = 999` (the documented failure trigger) → poll bill status until `failed`. | Failure-side webhook flow is exercised end-to-end. |
| 4 | POST with `request.amount = 1000` (BYN 10.00) → poll until `successful` (auto-webhook fires after ~10 s in test mode). | Success-side webhook flow is exercised end-to-end. |

Each case prints PASS/FAIL lines and the script exits non-zero on any failure.

---

## Manual scenarios inside Bitrix (cannot be automated from outside)

These require an installed Bitrix shop with the patched module. The bePaid
sandbox shop above can be used.

### M1 — Operator changes payment sum AFTER the bill was issued (the BEP-29814 case)

1. Create a frontend order using ERIP for **63.60 BYN**. Confirm the customer
   sees the ERIP instruction page; in bePaid the bill has `amount = 6360`.
2. In Bitrix admin, edit the order: change basket items so the total drops
   to **39.44 BYN**, update the Payment sum to match, save.
3. Trigger the handler:
   - **3a (auto mode):** open the customer-facing payment page again
     (re-render). The handler runs in auto mode.
   - **3b (manual mode):** flip the order status to anything else and back
     to `EA` ("ЕРИП ожидание оплаты") in admin.
4. **Expected with the fix:**
   - Pay system error log (`b_sale_pay_system_err_log`) shows
     `bill staleness check: stale … reason: amount drift bill=6360 expected=3944`
     followed by `createEripBill (after stale-bill delete)`.
   - In bePaid the old bill is deleted, a new bill exists with `amount = 3944`.
   - `PS_INVOICE_ID` on the payment is updated to the new UID.
   - In manual mode (3b) the customer receives a new instruction email; in
     auto mode the rendered template shows the new sum and QR.
5. **Expected without the fix (regression check):** old bill kept; bePaid
   record still shows 6360.

### M2 — Idempotency: status re-toggled with amount unchanged

1. Order with ERIP bill for 50.00 BYN, status `EA`.
2. Flip status away from `EA` and back to `EA` without editing items/sum.
3. **Expected:** no DELETE/POST to bePaid (only a GET). `PS_INVOICE_ID`
   unchanged. **No** new instruction email is sent. Log shows
   `bill staleness check: … reason: matches`.

### M3 — Currency change after bill issuance

1. Order with bill for 100.00 BYN, then somehow set Payment CURRENCY to a
   different value (edge case: BYN remains the only supported currency in
   `getCurrencyList`, but the check is defensive).
2. **Expected:** stale → delete + recreate (or, if the new currency is not
   supported, the create call fails and the operator sees the error).

### M4 — Already-paid bill must not be touched

1. Pay an ERIP bill via the bePaid sandbox so its `transaction.status` is
   `successful`.
2. In Bitrix admin, edit the order amount and re-trigger the handler.
3. **Expected:** the handler does **not** delete the paid bill. Log shows
   `bill staleness check: stale=false … non-pending bill status 'successful'`.
   The amount mismatch surfaces later through the existing
   `processPayment::isSumCorrect` guard.

### M5 — Webhook arrives for a recreated bill

1. After M1's recreate, simulate the customer paying the new bill in the
   sandbox (or wait for the auto-success webhook in test mode).
2. **Expected:** webhook reaches `processRequest`, signature check passes,
   `processPayment` matches `transaction.amount` against the **new**
   Payment sum (39.44), `PS_STATUS = Y`, order moves to paid via
   `MONEY_COMING` (assuming `PS_CHANGE_STATUS_PAY = Y`).

### M6 — Cancel still works

1. Order with ERIP bill, status `EA`. Move status to `EC` ("ЕРИП отменён").
2. **Expected:** `EventHandler::cancelPay` calls `deleteEripBill`, clears
   `PS_INVOICE_ID`. Unchanged from pre-fix behaviour.

---

## Automated feature tests (live Bitrix + live bePaid sandbox)

These exercise the patched handler end-to-end inside a real Bitrix install
talking to the bePaid sandbox over the internet (incl. signed webhooks).
They replace the manual M-series with a runnable suite for CI / pre-release
regression checks.

The tests live in `tests/feature/`:

```
bitrix-wizard.mjs   Playwright script that walks the Bitrix install wizard
install-erip.php    installs the begateway.erip module + creates a configured
                    pay system inside the running Bitrix container
feature-tests.php   F1-F4 suite, runs inside the container, talks to bePaid
package.json        bare npm manifest (only @playwright/test, for the wizard)
```

### Prereqs (one-time, on the dev machine)

* Docker + Docker Compose
* Node 18+ (for `npx`)
* A public HTTP tunnel that points at the Bitrix container (ngrok or
  localtunnel — both work; the bePaid webhook for F3 needs a public URL).
* The bePaid sandbox **public key** for the configured shop, otherwise F3
  is auto-skipped (`isSignatureCorrect` would always fail).

### Bring-up (bind-mount-free Docker Compose)

```bash
# 1) Build and start an isolated stack. The e2e image bakes in the module,
#    so this also works when Docker Desktop cannot bind-mount /workspace.
docker compose -f docker-compose.e2e.yml -p bitrix-erip-bep30748 up -d --build

# 2) Walk the install wizard headlessly (~2 min) over the Compose network.
docker compose -f docker-compose.e2e.yml -p bitrix-erip-bep30748 \
  --profile tools run --rm --build wizard

# 3) Optional public tunnel (required only for F3).
ngrok http 8088 --host-header=localhost:8088 &      # or
npx localtunnel --port 8088 &                       # if ngrok is rate-limited
TUNNEL_URL=...                                      # whatever the tunnel prints

# 4) Copy the setup/tests into the container and configure the module.
CID=$(docker compose -f docker-compose.e2e.yml -p bitrix-erip-bep30748 ps -q bitrix)
docker cp tests/feature/install-erip.php  "$CID":/tmp/
docker cp tests/feature/feature-tests.php "$CID":/tmp/
docker compose -f docker-compose.e2e.yml -p bitrix-erip-bep30748 exec -T \
  -e NOTIFICATION_URL="$TUNNEL_URL/bitrix/tools/sale_ps_result.php" \
  -e BEPAID_PUBLIC_KEY="$(cat path/to/shop-4225-public-key.pem)" \
  bitrix php /tmp/install-erip.php
```

`install-erip.php` env (defaults are sandbox):

| var                | default                                                            |
|--------------------|--------------------------------------------------------------------|
| `BEPAID_SHOP_ID`   | `4225`                                                             |
| `BEPAID_SECRET`    | sandbox key                                                         |
| `BEPAID_PUBLIC_KEY`| empty (F3 will skip)                                                |
| `NOTIFICATION_URL` | empty (set to your tunnel URL + `/bitrix/tools/sale_ps_result.php`)|

### Run

```bash
docker compose -f docker-compose.e2e.yml -p bitrix-erip-bep30748 \
  exec -T bitrix php /tmp/feature-tests.php
```

Exit code is non-zero on any FAIL. F3 is the only test that needs the
tunnel to be reachable from the public internet.

### Cases

| #  | Name                                                  | Patch coverage                                                                                 | Maps to manual case |
|----|-------------------------------------------------------|------------------------------------------------------------------------------------------------|---------------------|
| F1 | Saved payment/order sum change automatically recreates bill | `OnSalePaymentEntitySaved` → `OnSaleOrderSaved` → stale bill delete/recreate              | M1                  |
| F2 | Idempotent re-render — sum unchanged                  | Stale-check returns `matches`; same `PS_INVOICE_ID`; no email; no DELETE                       | M2                  |
| F2b| Successful initiate payload remains available        | `PaymentData::get($paymentId)` exposes the handler result after Bitrix's success event          | —                   |
| F3 | Success-path bePaid auto-webhook lands as paid        | Whole webhook chain: signed POST → `processRequest` → `processPayment`                         | M5                  |
| F4 | Cancel deletes the bill in bePaid                     | `Service::cancel` → handler `cancel` → `deleteEripBill`                                        | M6                  |
| F5 | Instruction email fires once per real bill issuance   | EA-status-change → `EventHandler` → `b_event` row created on initial+recreate, NOT on re-toggle | M1+M2 (email side)  |

### How the live tests drive the code

* They call the real `Bitrix\Sale\PaySystem\Service::initiatePay`, which
  loads our patched `begateway_eripHandler` and goes through the actual
  `isAutoMode`/`isAdminChangeStatus` branching.
* HTTP traffic to bePaid uses the same `Bitrix\Main\Web\HttpClient`/`curl`
  path the production module uses — just driven from a CLI bootstrap of
  the Bitrix kernel rather than from an HTTP request.
* F3 leaves the orchestration to bePaid: after creating a bill in `test`
  mode, the sandbox auto-fires the success webhook ~10 s later. The
  webhook hits the public tunnel → Apache → the same
  `/bitrix/tools/sale_ps_result.php` endpoint a real customer payment
  would. The test polls Bitrix DB for `PS_STATUS = Y`.
* F1/F2/F4 manipulate the order via the public Sale\\Order API
  (basket items, payment SUM) exactly the way Bitrix admin does on an
  edit-and-save.

### Known limitations

* The eshop demo currency is RUB; all tests force orders to BYN because
  `getCurrencyList()` only returns `['BYN']` for ERIP. Real shops should
  configure the catalogue currency to BYN.
* The bePaid sandbox failure trigger (`amount = 999`) sometimes does not
  auto-settle inside the polling window. The standalone API suite
  (`tests/e2e/run_erip_tests.php`) treats that as a WARN, not a FAIL.
