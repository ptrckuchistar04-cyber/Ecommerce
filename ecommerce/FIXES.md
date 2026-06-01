# Fixes Applied

Date: 2026-06-01. Xendit kept as the test payment gateway (per project requirement).

## Security / secrets
- **Secrets removed from code.** `config.php` now reads DB + Xendit keys from environment
  variables, then from a **git-ignored** `config.local.php`, then safe defaults.
  - Added `config.local.example.php` (template) and `.gitignore`.
  - ⚠️ **You must now put your Xendit test key back** in `config.local.php`
    (or as an env var). See "How to set your keys" below.
- **Rotate the old key.** The previous `xnd_development_...` key was public in git history —
  revoke/rotate it in the Xendit dashboard.
- `session.cookie_secure` is now enabled automatically when the request is HTTPS.
- `uploads/.htaccess` blocks execution of any uploaded script (defense-in-depth).
- `includes/.htaccess` + root `.htaccess` deny direct access to templates and
  `.sql`/`.md`/`config.local.php`.

## Payments (Xendit)
- **Webhook now verifies the paid amount** before marking an order `paid`
  (underpayment → `processing` for manual review instead of auto-confirm).
- **Webhook is idempotent** — a duplicate `PAID`/`EXPIRED` callback is ignored once the
  order is already settled.
- **Cart is cleared on confirmed payment** (in the webhook), not at invoice creation,
  so an abandoned payment keeps the cart intact.
- **No more duplicate orders** — `api/payment.php` reuses a recent matching `pending`
  invoice instead of creating a new one on refresh/double-click.
- **Reservation race fixed** — checkout locks the listing rows (`FOR UPDATE`) and aborts
  if any item is no longer `available`.

## Bugs / quality
- `createListingFromInquiry()` no longer hard-codes `admin_id = 1`; it uses the approving
  admin's id (falls back to the first administrator).
- Stopped leaking raw exception text to users in `api/cart.php` and `admin.php`.
- `order-success.php` status chip now shows the correct color per status (was always green).
- `api/compare.php` add/remove/clear now require **POST + CSRF**; `app.js` sends the token
  (exposed via a `<meta name="csrf-token">` tag).
- Schema-detection helpers (`hasPropertyExtraFields`, etc.) are memoized — they ran a
  `SHOW COLUMNS` query on every page load before.

---

## How to set your keys (do this after pulling these changes)

**Option A — local file (easiest for XAMPP):**
1. Copy `config.local.example.php` → `config.local.php`
2. Put your real values in it:
   ```php
   define('XENDIT_SECRET_KEY',     'xnd_development_your_test_key');
   define('XENDIT_CALLBACK_TOKEN', 'your_webhook_token');
   define('DB_PASS', '');
   ```
3. `config.local.php` is git-ignored, so it never gets committed.

**Option B — environment variables** (set `XENDIT_SECRET_KEY`, `DB_PASS`, etc. in your host).

---

## See FORBIDDEN_TROUBLESHOOTING.md for the "403 Forbidden" investigation.
