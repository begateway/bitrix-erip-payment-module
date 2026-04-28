# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

1C-Bitrix payment module that adds **ERIP** (Belarusian unified payment system) support via the **bePaid / BeGateway** gateway. Distributed as a Bitrix module under the ID `begateway.erip` and installed into `bitrix/modules/`.

## Build & Release

- `make` — produces `bitrix-begateway-erip.zip` (zips the `begateway.erip/` directory). This zip is what gets installed into a Bitrix site.
- Releases are published automatically by `.github/workflows/release.yml` when a git tag is pushed: it builds the zip (excluding `docker/`, docs, screenshots) and attaches it to the GitHub release. Tagging is the release mechanism — do not edit `bitrix-begateway-erip.zip` by hand for releases.
- Bump `begateway.erip/install/version.php` (`VERSION` and `VERSION_DATE`) before tagging.

## Local Development Environment

- `docker compose up` brings up Apache+PHP 8.2 (`docker/Dockerfile`) on host port **8088** plus MySQL 8. The module source is bind-mounted into `/var/www/html/bitrix/modules/begateway.erip` so edits in `begateway.erip/` are live.
- The Dockerfile downloads the Bitrix `business_encode` distribution at build time. Older PHP 7 base is preserved in `docker/Dockerfile_php7`; the large `docker/business_encode_*.tar.gz` archives in the tree are gitignored cached distributions.
- After install in the running Bitrix site, the module is registered via the standard installer (`begateway.erip/install/index.php` `DoInstall`/`DoUninstall`). For a fully scripted setup, see the test workspace described below — `tests/feature/bitrix-wizard.mjs` walks the install wizard and `tests/feature/install-erip.php` registers the pay system inside the running container.

## Tests

Three test layers live under `tests/`. Detailed bring-up, env vars, and per-case assertions are in `tests/TEST_CASES.md` — read that before running anything.

1. **`tests/e2e/run_erip_tests.php`** — standalone PHP CLI that hits the bePaid sandbox REST API directly (no Bitrix needed). Run via `php tests/e2e/run_erip_tests.php`. Covers: round-trip create+GET, the BEP-29814 delete-and-recreate primitive, the `amount=999` failure trigger, and the success-trigger auto-webhook timing. Sandbox shop `4225` and key are baked in as defaults; override via `BEPAID_SHOP_ID` / `BEPAID_SECRET` / `BEPAID_API`.

2. **`tests/feature/feature-tests.php`** — runs **inside the Bitrix container** (`docker compose exec bitrix php /tmp/feature-tests.php` after `docker cp`). Drives the patched handler exactly as a customer-facing render or admin status-change would, and verifies outcomes both in the Bitrix DB and via the bePaid API. Cases F1–F4 map to the manual scenarios M1, M2, M5, M6 in `TEST_CASES.md`. F3 (signed-webhook round-trip) needs a public tunnel (ngrok or `npx localtunnel --port 8088`) and the bePaid public key for the configured shop — without the key F3 self-skips.

3. **`tests/TEST_CASES.md`** — narrative test plan. Documents the bug, the fix, automated cases, and manual M1–M6 scenarios for cases that aren't worth automating (e.g., currency change, paid-bill-must-not-be-touched).

Supporting scripts: `tests/feature/bitrix-wizard.mjs` (Playwright; one-shot Bitrix install) and `tests/feature/install-erip.php` (idempotent module install + pay-system config inside the container; reads `BEPAID_SHOP_ID`, `BEPAID_SECRET`, `BEPAID_PUBLIC_KEY`, `NOTIFICATION_URL` from env).

There is no `phpunit` / `npm test` runner — each script exits non-zero on failure and is meant to be invoked directly (or wired into CI as separate steps).

## Architecture

This is a Bitrix payment-system handler, not a standalone app. It plugs into Bitrix's `Bitrix\Sale\PaySystem` framework. Three integration surfaces:

1. **Payment handler** — `begateway.erip/handler/handler.php` defines `Sale\Handlers\PaySystem\begateway_eripHandler` (extends `PaySystem\ServiceHandler`, implements `IHold`, `ICheckable`). All bePaid REST calls (`https://api.bepaid.by/beyag/payments`) live here: `createEripBill`, `getBeGatewayEripPayment`, `deleteEripBill`. `processRequest` handles webhooks and verifies them via `openssl_verify` against the merchant public key (`HTTP_CONTENT_SIGNATURE` header, SHA256). The handler is mirrored into `install/sale_payment/begateway.erip/` — `DoInstall` copies that directory into `bitrix/php_interface/include/sale_payment/` so Bitrix's PaySystem registry can discover it.

2. **Order-status event handler** — `begateway.erip/lib/event_handler.php` (`\BeGateway\Module\Erip\EventHandler`) is registered on `sale / OnBeforeSaleOrderSetField`. It implements the "manual" flow: when an order transitions **into** status `EA` (`ORDER_AWAITING_STATUS`) the handler programmatically calls the payment handler's `initiatePay` to create an ERIP bill and emails the customer instructions; when transitioning into `EC` (`ORDER_CANCELED_STATUS`) it cancels the bill via `deleteEripBill`. To distinguish admin-triggered initiation from a normal frontend payment, the event handler temporarily sets `PS_STATUS_MESSAGE = 'manual'` on the payment, which `handler.php::isAdminChangeStatus` reads.

3. **Templates** — `begateway.erip/template/auto.php` (renders ERIP instructions, account number, QR code after a bill is created) and `manual.php` (placeholder shown when the customer must request bill creation manually). The handler picks one via `BEGATEWAY_ERIP_AUTO_BILL` setting; once `PS_INVOICE_ID` exists the auto template is always used.

### Module wiring

- `begateway.erip/include.php` registers PSR-style autoload for `\BeGateway\Module\Erip\*` classes via `CModule::AddAutoloadClasses`.
- `begateway.erip/install/index.php` (`DoInstall`/`DoUninstall`):
  - copies the handler bridge into `php_interface/include/sale_payment/`,
  - registers two custom Bitrix order statuses `EA` and `EC` (codes in `lib/order_statuses.php`),
  - creates an `CEventMessage` mail template (`BEGATEWAY_ERIP_SALE_ORDER_STATUS_CHANGED_EA`) used to email ERIP payment instructions,
  - registers the `OnBeforeSaleOrderSetField` event handler.
- Settings (shop ID, secret key, public key, ERIP service code, descriptions, notification URL, expiry, auto-bill mode, test mode, `PS_CHANGE_STATUS_PAY`) are declared in `begateway.erip/handler/.description.php` under the `CODES` array — this is what Bitrix's pay-system admin form renders.

### Cross-cutting helpers in `lib/`

- `Money` — converts between major-units (`amount`) and minor-units (`cents`); knows currency-specific decimal exponents (used because bePaid wants amounts in cents).
- `Encoder` — Bitrix sites can run in CP1251 *or* UTF-8. `toUtf8` ensures outbound API payloads are UTF-8; `GetEncodeText` / `GetEncodeMessage` convert localized messages back to `SITE_CHARSET` for display; `reEncode` rewrites the `lang/` tree to the site charset on install. `str_split` is a multibyte-safe splitter used to chunk ERIP `service_info` / `receipt` arrays.
- `OrderStatuses` / `Events` — string constants (`EA`, `EC`, mail-event name) shared between installer, event handler, and handler.

### Webhook correlation

The handler uses `tracking_id = "<paymentId>#<paySystemId>"` (delimiter `#`) when creating a bill. `isMyResponse` and `getPaymentIdFromRequest` parse it back from incoming webhook JSON to route the callback to the right Bitrix payment record. Only `BYN` is in `getCurrencyList`.

## Conventions specific to this codebase

- The module supports **PHP 8** (current Dockerfile) but must continue to work under older Bitrix sites — code uses short open tags `<?` in many files; preserve that style when editing existing files. Indentation is mixed (tabs in `handler.php`, two-space in `install/index.php` and `lib/`); follow whatever the file already uses.
- Anything sent to bePaid must go through `Encoder::toUtf8` — the site may be CP1251.
- The handler is available only when the Bitrix site's locale/zone is `ru` (`install/index.php` checks `bitrix24` / `intranet` modules and sets `IS_AVAILABLE` accordingly). Don't remove that gate.
- Debug logs go to Bitrix's pay-system error log (`b_sale_pay_system_err_log`). To enable verbose logging on a running site:
  `\Bitrix\Main\Config\Option::set('sale', 'pay_system_log_level', 0);`
  View at `/bitrix/admin/perfmon_table.php?table_name=b_sale_pay_system_err_log`.
- End-user setup instructions live in `manual.pdf` (linked from `README.md`); do not duplicate that into code comments.
