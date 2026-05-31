# 🔍 Code Review — `on-the-line` (Original Project)

## Summary
The original project is **functional but has several bugs, broken links, missing files, and security/UX issues**. Below is the full review by category, followed by the list of fixes applied in **v2**.

---

## 🐛 Critical Bugs (Pages That Won't Work)

| # | Issue | File | Effect |
|---|-------|------|--------|
| 1 | File named `customer/add-to-cart` is **missing the `.php` extension** | `customer/add-to-cart` | Form posts to `customer/add-to-cart.php` which doesn't exist → 404. Reserve button is broken. |
| 2 | File named `compare..php` (double dot) | `compare..php` | Header links to `compare.php` → 404. Compare page is unreachable. |
| 3 | `compare..php` has handler for `?clear=1` link, but no code to clear it | `compare..php` | "Clear Comparison" button just reloads the page. |
| 4 | `admin/products.php`, `admin/orders.php`, `admin/edit-product.php` referenced but **don't exist** | header.php, index.php | Admin menu links 404. "Edit" button on product card 404s. |
| 5 | `profile.php` referenced in header but **doesn't exist** | header.php | "My Profile" link 404s. |
| 6 | `assets/images/no-image.jpg` referenced but **doesn't exist** | multiple | Broken image icons everywhere. |
| 7 | `check_files.php` has stray `s` at the end of the file after the closing `?>` | check_files.php | Outputs literal "s" to the page. Also: dev-only file should never be in production. |
| 8 | Xendit callback uses placeholder token `'your_callback_verification_token_here'` | xendit_callback.php | Real Xendit webhooks will be rejected (403). Real payments will never confirm. |
| 9 | `customer/checkout.php` **fakes the payment** — sets `status='paid'` instantly with `TEST-uniqid()` invoice id | checkout.php | Customers never actually pay. Inventory marked as reserved without money. |
| 10 | No `xendit/Xendit.php` SDK or HTTP call anywhere — Xendit is **not actually integrated** | (missing) | The whole payment pipeline is decorative. |

---

## ⚠️ Security Issues

| # | Issue | Severity |
|---|-------|----------|
| 1 | `sanitize()` runs `htmlspecialchars` on the **email** before DB lookup. If an email contained `&` etc. it would fail comparison. (PDO already protects against SQLi — html-escaping inputs going into DB is the wrong layer.) | Medium |
| 2 | No CSRF protection on **any** form (login, register, add-to-cart, checkout) | High |
| 3 | No rate-limiting on login → brute-force possible | Medium |
| 4 | `config/database.php` has hard-coded credentials and **echoes connection errors** to the page (leaks server info) | Medium |
| 5 | `display_errors=0` is set, but DB exception in `checkout.php` does `die('Error: ' . $e->getMessage())` — leaks SQL info | Medium |
| 6 | Session fixation: no `session_regenerate_id()` after login | Medium |
| 7 | Xendit secret key would be hard-coded in the file if present — should go in `config.php` outside webroot or `.env` | High (when keys added) |
| 8 | `password_hash` column is `VARCHAR(255)` ✅ good. Cost = 12 ✅ good. | OK |

---

## 🎨 UI / UX Issues

- Mix of CSS framework styles (`assets/css/style.css`) and **inline styles all over** (`cart.php`, `checkout.php`, `orders.php`). Inconsistent look.
- Compare button only shows for logged-in customers — guests can't even *try* the compare feature to see if they like the site.
- "Reserve Now" requires login but doesn't preserve the intended product after login (no `?redirect=` param).
- No success/error toast system — uses native `alert()` in `main.js`.
- Mobile nav not implemented (no hamburger).
- Homepage shows **all** listings at once — no hero CTA, no filtering UX, just a flat grid.

---

## 🗂️ Architecture Issues

- No `api/` folder — every endpoint is a top-level PHP file (hard to scale).
- Functions file mixes DB helpers + view helpers + utilities.
- Header/footer hard-code asset paths with a brittle `$basePath` calc based on `PHP_SELF`.
- No autoloader, no namespacing.
- SQL file lacks `DROP TABLE IF EXISTS` and uses `LAST_INSERT_ID()` with `SET @pid` which is fine but fragile if rerun.
- No `.htaccess` to deny direct access to `config/` or `includes/`.

---

## ✅ What's Already Good

- PDO with prepared statements — well done.
- `password_hash` / `password_verify` used correctly.
- Foreign keys with `ON DELETE CASCADE` set up properly.
- Bcrypt cost of 12 is reasonable.
- Color palette (navy / orange / silver) is consistent.

---

# 🛠️ Fixes Applied in v2

| Original Problem | Fix |
|------------------|-----|
| `customer/add-to-cart` missing `.php` | Replaced by `api/cart.php` (action-based) |
| `compare..php` double dot | New `compare.php` (single dot) |
| Missing admin pages | Built `admin.php` with full CRUD |
| Missing `profile.php` | Built it with Sell-Inquiry feature |
| Missing `no-image.jpg` | Inline SVG placeholder, no external asset needed |
| Fake checkout | Real Xendit Invoice API call in `api/payment.php` |
| Placeholder callback token | Loaded from `config.php` constant, documented |
| No CSRF | CSRF token generated per session, validated on all POSTs |
| Inline styles everywhere | Tailwind CSS (CDN) + a few custom utility classes |
| Compare blocked for guests | Guests can browse + compare; only reserve/checkout requires login |
| Flat homepage | **3D Lazy Susan** rotating promo carousel + All/Vehicles/Properties filter |
| Minimal registration | Adds birthday, address, phone, gender; **age auto-calculated** on save |
| No "Sell" option | Profile page has "I want to sell" form → admin inquiries table |
| Bug: session regen | `session_regenerate_id(true)` after login |
| Bug: error leakage | `die('Error: ')` replaced with generic message + `error_log()` |
| Brittle `$basePath` | All v2 pages live in root; clean relative paths |

See `README.md` for installation and `MIGRATION.md` for what to drop into XAMPP.
