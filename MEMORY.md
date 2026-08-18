# Project memory

Last updated: 2026-08-10

## Repository state

- Branch: `fix/update-erip-bill-when-orded-sum-changed`.
- Base/HEAD: `eff4e41` (tag `2.2.0`, also `origin/master`).
- The BEP-30748 implementation and its tests remain uncommitted in the
  worktree, including this memory file.
- No new implementation changes or test runs were made during the 2026-08-10
  memory refresh; the latest verified results are recorded below.

## Active issue

- Jira: BEP-30748
- Related earlier fix: BEP-29814.
- Symptom: after 1C/Bitrix changes an ERIP payment from 252.44 BYN to
  483.14 BYN, the customer can still receive or pay the stale 252.44 bill.
- Root cause: stale-bill detection existed in `initiatePay`, but changing a
  Payment sum while the order remained in the ERIP-awaiting status did not
  invoke `initiatePay` again.

## Implemented fix

- `OnSalePaymentEntitySaved` records ERIP Payment `SUM`/`CURRENCY` changes.
- `OnSaleOrderSaved` reconciles the bePaid bill after Bitrix has saved a
  consistent order, deleting and recreating a pending bill when its amount or
  currency differs.
- A recursion guard prevents the nested `PS_INVOICE_ID` save from triggering a
  second reconciliation.
- A recreated bill sends a fresh customer instruction email; unchanged bills
  remain idempotent and do not send duplicate email.
- `BeGateway\Module\Erip\PaymentData` preserves successful handler template
  data for Bitrix success-event listeners, because Bitrix does not include
  `ServiceResult::getData()` in that event.
- Event registration and upgrade-time self-registration were added.
- Module version is now 2.3.0.
- Release packaging excludes tests and local agent files.

Primary implementation files:

- `begateway.erip/lib/event_handler.php`
- `begateway.erip/lib/payment_data.php`
- `begateway.erip/handler/handler.php`
- `begateway.erip/include.php`
- `begateway.erip/install/index.php`
- `begateway.erip/install/version.php`

## Regression coverage

- `tests/e2e/run_erip_tests.php` reproduces the exact 252.44 -> 483.14
  delete-and-recreate scenario against the bePaid sandbox.
- `tests/feature/feature-tests.php` verifies that saving the changed
  Payment/Order automatically changes `PS_INVOICE_ID`, creates the correct
  bill, deletes the stale bill, sends one new email, and stays idempotent.
- It also covers `PaymentData`, cancellation, and unchanged-bill behavior.
- `docker-compose.e2e.yml`, `docker/Dockerfile.e2e`, and
  `tests/feature/Dockerfile` provide a bind-mount-free Bitrix/Playwright test
  harness for Docker Desktop environments where `/workspace` is not shared.
- Full setup and commands are documented in `tests/TEST_CASES.md`.

Latest results (2026-07-10):

- Standalone bePaid sandbox suite: 19 passed, 0 failed.
- Live Bitrix feature suite: 28 passed, 0 failed.
- F3 signed auto-webhook scenario skipped because no bePaid public key/public
  tunnel was configured. This is the only remaining unexecuted case.
- PHP lint, `git diff --check`, and Compose config validation passed.

## Environment notes

- Docker Compose v2.40.3 is installed and available as `docker compose`.
- Use Compose project name `bitrix-erip-bep30748` to avoid colliding with the
  unrelated `workspace-woocommerce-1` and `workspace-mysql-1` containers.
- The isolated test containers were stopped after the successful run. Its
  named database volume was intentionally retained.
- The Docker daemon is provided by Docker Desktop on another host; repository
  bind mounts may fail. Prefer the bind-mount-free e2e Compose file.

## Handoff / next update

- The worktree contains uncommitted implementation, test, documentation, and
  harness changes. Do not discard unrelated user changes.
- Before release, optionally run F3 with `BEPAID_PUBLIC_KEY` and a public
  `NOTIFICATION_URL`, then review the complete diff and commit/version-release
  the 2.3.0 changes.
