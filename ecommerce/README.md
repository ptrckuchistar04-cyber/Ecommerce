# On The Line — v2

Modern restructure of the marketplace: **PHP + MySQL + Tailwind (CDN) + 3D Lazy Susan + Xendit Invoice integration**.

## ⚡ Quick install (XAMPP)

1. **Copy** the entire `ecommerce/` folder into `C:\xampp\htdocs\` (or wherever your DocumentRoot is).
2. Start **Apache** and **MySQL** in XAMPP.
3. Open **phpMyAdmin** → click "SQL" → paste the contents of `database.sql` → click "Go".
4. Open `config.php` and adjust:
   - `SITE_URL` (default: `http://localhost/ecommerce/`)
   - DB credentials (defaults work with stock XAMPP: `root` / no password)
   - `XENDIT_CALLBACK_TOKEN` — set this AFTER you create the webhook in Xendit dashboard
5. Visit **http://localhost/ecommerce/** 🎉

## 👤 Demo accounts
| Role | Email | Password |
|------|-------|----------|
| Admin | admin@ontheline.com | admin123 |
| Customer | customer@example.com | customer123 |

## 🗂️ File map

```
ecommerce/
├── index.php           ← Homepage + Lazy Susan promo carousel
├── listing.php         ← Listings grid + single-listing detail (handles ?id=)
├── compare.php         ← Side-by-side compare (up to 4 items, guest-friendly)
├── cart.php            ← Reservation cart
├── checkout.php        ← Checkout screen → POSTs to api/payment.php
├── order-success.php   ← Confirmation after Xendit redirect
├── orders.php          ← Customer's order history
├── login.php / register.php / logout.php
├── profile.php         ← Edit info + "Sell" inquiry form (admin-curated)
├── admin.php           ← Single-page admin: overview / listings / orders / inquiries
├── xendit_webhook.php  ← Receives PAID / EXPIRED / FAILED callbacks
│
├── config.php          ← App config + DB creds + Xendit keys
├── db.php              ← PDO connection singleton
├── database.sql        ← Schema + seed data (drop-into phpMyAdmin)
│
├── api/
│   ├── cart.php        ← add / remove cart endpoints
│   ├── compare.php     ← add / remove / count / clear endpoints (JSON)
│   └── payment.php     ← Creates Xendit invoice, redirects to Xendit
│
├── includes/
│   ├── header.php      ← Nav, Tailwind setup, brand theme
│   ├── footer.php      ← Footer + global JS
│   └── functions.php   ← Auth, CSRF, queries, helpers
│
├── assets/
│   ├── js/app.js       ← Lazy Susan, tilt, toasts, compare API client
│   └── images/         ← (drop your listing photos here)
│
├── uploads/products/   ← Future: admin-uploaded listing photos
└── .htaccess           ← Blocks direct access to config/, includes/
```

## ✨ Key features added in v2

- 🎠 **3D Lazy Susan** rotating promo carousel on the homepage (drag to spin, hover to pause)
- 🏷️ **All / Properties / Vehicles** filter chips
- 📝 Registration with **birthday, address, phone, gender** + age **auto-calculated** (DB-generated column)
- 💼 **Sell** form in profile (creates `sell_inquiries` → admin reviews in dashboard)
- 🔒 Guest can browse + compare. **Login required only for Reserve/Checkout**.
- 💳 **Real Xendit Invoice integration** via cURL (replaces the fake checkout)
- 🛡️ CSRF tokens on every POST, `session_regenerate_id()` after login, generic errors only
- 🎨 Tailwind CDN with custom navy/orange/silver theme, glass-morphism, 3D tilt, fade-up reveals
- 📊 **Admin dashboard** with overview cards, listings CRUD, orders viewer, inquiries manager

## 🔌 Xendit setup

Your dev secret key is already in `config.php`:
```
xnd_development_l4Na8u8AACRCVqlzyVJj8pVuvzx2bjMF8Cy7VCAtrlVjMWxVwKXybXvfpQ2yJnwd
```

To receive payment confirmations (PAID, EXPIRED):

1. Make your local site reachable from the internet via **ngrok**:
   ```
   ngrok http 80
   ```
2. In **Xendit Dashboard → Settings → Callbacks → Invoices**, set:
   - URL: `https://<your-ngrok-id>.ngrok.io/ecommerce/xendit_webhook.php`
   - Copy the **Callback Verification Token** it gives you
3. Paste that token into `config.php` as `XENDIT_CALLBACK_TOKEN`.
4. Send a test invoice — payment status will flip from `pending` → `paid` automatically and the listing will be marked `reserved`.

## 🐛 Issues from v1 that are fixed
See `CODE_REVIEW.md` for the full list. Highlights:

- `customer/add-to-cart` (missing .php) → replaced by `api/cart.php`
- `compare..php` (double dot) → `compare.php`
- Missing `profile.php`, `admin/products.php`, `admin/orders.php`, `admin/edit-product.php` → unified in `admin.php` + `profile.php`
- Fake checkout that auto-marked orders paid → real Xendit invoice + webhook
- No CSRF anywhere → CSRF token on every POST
- Hard-coded callback token placeholder → loaded from config
- DB error leaks in `die('Error: '.$e->getMessage())` → generic message + `error_log`

## 🚧 Future improvements (not in scope but easy to add)
- Image upload UI for admin (`api/upload.php` skeleton can be wired to a form)
- Pagination on listings (>60 items)
- Saved/favorited listings
- Email notifications on order paid
- Refund flow via Xendit refund API
- "Forgot password" via email reset token
