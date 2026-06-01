# New Features (v4)

> ⚠️ **First run `database_PATCH_v4.sql`** in phpMyAdmin. Until you do, the new promo/
> notification features stay hidden and the rest of the site works as before.

## 1. Mark Paid / Not Paid (admin → Orders tab)
- **Mark Paid**: settles the order, sets its items to `reserved`, clears the buyer's cart,
  logs `PAID_MANUAL_ADMIN`. Works offline (no Xendit needed) — great for demos.
- **Mark Not Paid**: reverts the order to `pending` and returns its items to `available`.

## 2. Cancel Reservation (admin → Reserved tab)
- Each reserved card now has **Cancel Reservation**: the item goes back to `available`
  (reappears in public listings) and the related paid order is set to `cancelled`.
- (The existing **Mark as Sold** stays — that removes it permanently as sold.)

## 3. Promote button (admin → Listings tab)
- Every listing row has **☆ Promote / ★ Unpromote** so you choose exactly what's featured
  on the homepage carousel. (Sets `is_promo` + `promo_status`.)

## 4. Seller pays a promo fee → admin is notified
Flow:
1. When an inquiry is **approved**, the new listing records its **seller**.
2. The seller opens **Profile → My Approved Listings** and clicks **☆ Promote (₱500)**.
3. `promo.php` shows a **QR code + Pay button** (Xendit). They pay the promo fee.
4. On payment: the promo payment is marked `paid`, the listing's `promo_status` → `paid`,
   and an **admin notification** is created ("Seller paid the promo fee for …").
5. Admin sees the bell badge → **Notifications tab**, and on the **Listings tab** that row
   shows **✅ Activate Promo**. Clicking it features the listing (`is_promo=1`).

> Like the buyer flow, promo payments confirm by polling Xendit on the return page, so they
> work on localhost without a public webhook. The flat fee is set in
> `promoFeeFor()` in `includes/functions.php` (default ₱500).

## New / changed files
- `database_PATCH_v4.sql` — schema (seller_id, promo_status, promo_payments, admin_notifications)
- `promo.php` — seller promo payment page (QR + polling)
- `api/promo_payment.php` — creates the promo Xendit invoice
- `api/promo_status.php` — promo polling/reconcile endpoint
- `admin.php` — Mark Paid/Not Paid, Cancel Reservation, Promote/Unpromote, Activate Promo,
  Notifications tab + bell badge
- `profile.php` — "My Approved Listings" with Promote buttons
- `includes/functions.php` — notifications, promo helpers, `syncPromoWithXendit()`

## Admin notification badge
The **🔔 Notifications** tab shows a count + red dot when there are unread items. Dismiss
individually or "Mark all read".
