# 🔧 What changed in this update

## ✅ Issues fixed

| Issue you reported | Fix |
|--------------------|-----|
| ❌ Internal Server Error on first load | Removed invalid `<DirectoryMatch>` from root `.htaccess` (only valid in `httpd.conf`). Per-directory `.htaccess` files now block `includes/` and `config/`. |
| ❌ Admin can't log in | **Original SQL had broken password hashes** (the hash from the original repo actually verified the word "password", not "admin123"). Regenerated and put real `password_hash()` values in `database.sql`. Run `database_PATCH.sql` if you don't want to re-import. |
| ❌ Adding to cart doesn't work | Reordered `api/cart.php` checks (auth before CSRF), better error messages, friendly redirects instead of `die()`. Also added Reserve buttons to listing **cards** (not only detail page). |
| ❌ Profile dropdown closed too fast on hover | Converted to **click-to-toggle**. Click outside the dropdown to close. Works on touch devices too. |
| ❌ Age in registration was wrong | Fixed off-by-one (was sometimes adding 1 year). Uses correct year-month-day comparison + JS preview matches PHP. Blocks future dates. |
| ❌ Phone needs limit to 11 digits | Hard `maxlength=11`, `pattern="09\d{9}"`, JS strips non-digits live, PHP re-validates server-side. |
| ❌ Address should be cascading dropdown | **Region → Province → City/Municipality → Barangay** using the public PSGC API (psgc.gitlab.io — no key needed). Falls back to free-text if you select "Other" as country. |
| ❌ Lazy Susan positioning broken | Rebuilt with absolute positioning + `marginLeft/Top = -w/2` so cards stay centered. Card size + radius scale together. Front-card highlight, hidden cards become non-clickable. |
| ❌ Wants logo BIG on homepage, small on scroll | New hero shows logo at 340px with float animation. As you scroll, logo scales to 0.35 and fades. Lazy Susan appears in a second section below. |
| ❌ Logo needs transparent background + white border | CSS treatment: white 3px border + orange glow + screen blend mode. Put `logo.png` (transparent) in `assets/images/`. |

## 📥 To apply

**If you already have v2 running with data you want to keep:**
1. Replace all files (or unzip the new `ecommerce.zip` over your existing folder)
2. In phpMyAdmin → run **`database_PATCH.sql`** (fixes password hashes + adds address columns)
3. Save your logo as `assets/images/logo.png` (transparent PNG from remove.bg)

**If you don't care about existing data:**
1. Replace all files
2. In phpMyAdmin → run **`database.sql`** (drops everything, rebuilds with correct seed)
3. Save your logo as `assets/images/logo.png`

## 🔑 Login credentials (after running the SQL)

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@ontheline.com | admin123 |
| Customer | customer@example.com | customer123 |

## 🖼 Logo prep (one-time, ~30 seconds)

1. Open https://www.remove.bg/upload
2. Upload your "On The Line" logo JPG (the one you sent)
3. Click "Download" → save the transparent PNG
4. Rename to **logo.png**
5. Drop it into `ecommerce/assets/images/logo.png`
6. Refresh — done!

The CSS gives it:
- White 3px border + 2px orange ring + soft navy shadow
- Big 340px size in hero
- Smooth shrink to 42px in nav as you scroll
- Floating animation

## ⚠️ If login STILL fails after the patch

The most common cause is that the new password hash row didn't update. Verify in phpMyAdmin:

```sql
SELECT email, LEFT(password_hash, 7) AS hash_prefix FROM users;
```

You should see `$2y$12$` (not `$2y$10$`). If it's still `$2y$10$`, run the patch again — the `UPDATE` statement maybe didn't match.

## 🐛 If add-to-cart STILL fails

Open Browser DevTools (F12) → Network tab → click Reserve → look at the request to `api/cart.php?action=add`:
- **Response 200 + Location header to cart.php** = working, check `cart.php` instead
- **Response 200 + redirect to listing.php** = check error in flash message (it now shows the exact error)
- **Response 500** = enable `display_errors` in `config.php` and screenshot the error
