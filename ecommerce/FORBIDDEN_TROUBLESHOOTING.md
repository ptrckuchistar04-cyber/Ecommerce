# Fixing "403 Forbidden — You don't have permission to access this resource"

That exact message is **Apache's default 403 page**. It means Apache found the request but
refused to serve it. It is *not* a PHP error. Here are the causes, in the order they're most
likely for this project, with how to confirm and fix each.

> Tip: the real reason is always written in Apache's error log.
> - XAMPP (Windows): `C:\xampp\apache\logs\error.log`
> - Linux: `/var/log/apache2/error.log`
> Open that file, reproduce the 403, and read the **last line**. It names the exact cause.

---

## 1. Directory has no index file → "Options -Indexes" 403  ★ most common
If you visit a **folder** (e.g. `http://localhost/ecommerce/` or `.../api/`) and directory
listing is off, Apache returns 403 instead of a file.

- **Confirm:** the URL that 403s ends in a `/` or a folder name with no file.
- **Fix:** make sure you open a real page, e.g. `http://localhost/ecommerce/index.php`.
  The root `.htaccess` I added sets `DirectoryIndex index.php`, so `/ecommerce/` will now
  serve `index.php` automatically. The `api/`, `includes/`, `uploads/` folders have no index
  by design — don't open them directly; the app calls `api/*.php` files individually.

## 2. The `uploads/` images 403 because of folder permissions  ★ very common
Uploaded product/inquiry images live in `uploads/products/` and `uploads/inquiries/`. If the
web server can't read them (or the folder is missing), the `<img>` requests 403/404.

- **Confirm:** broken images only; the error log says "client denied by server configuration"
  or "permission denied" for a file under `uploads/`.
- **Fix:**
  - Make sure the folders exist (they're created automatically on first upload, but create
    them manually to be safe): `uploads/products/` and `uploads/inquiries/`.
  - Permissions:
    - **Linux:** `chmod -R 755 uploads && chown -R www-data:www-data uploads`
    - **XAMPP/Windows:** usually fine; just confirm the folders exist.
  - The new `uploads/.htaccess` blocks **scripts** but allows images — that's intended.

## 3. An old/strict `.htaccess` somewhere up the tree
A leftover `.htaccess` (in the project, a parent folder, or `htdocs`) with
`Require all denied`, `Deny from all`, or a broken `RewriteRule` will 403 everything.

- **Confirm:** temporarily rename any `.htaccess` in the project and in `htdocs/`
  (e.g. to `_htaccess`) and retry.
- **Fix:** remove/repair the offending rule. The `.htaccess` files I shipped are conservative
  and won't deny normal pages.

## 4. `.htaccess` uses a directive Apache won't allow (`AllowOverride`)
If `AllowOverride None` is set for your docroot, some directives in `.htaccess` cause a
**500**, but `mod_authz`/`Options` mismatches can surface as **403**. Also, on **PHP-FPM**
hosts the `php_flag` directive errors.

- **Confirm:** error log mentions ".htaccess: <directive> not allowed here" or
  "Invalid command 'php_flag'".
- **Fix:**
  - I already guarded `php_flag` inside `<IfModule mod_php*.c>` so FPM servers ignore it.
  - In XAMPP, ensure `httpd.conf` has `AllowOverride All` for `htdocs`:
    ```apache
    <Directory "C:/xampp/htdocs">
        AllowOverride All
        Require all granted
    </Directory>
    ```
    Then restart Apache.

## 5. Wrong file/folder ownership or "execute" bit on the directory (Linux)
Apache needs **read** on files and **execute (search)** on every directory in the path.

- **Confirm:** error log says "permission denied".
- **Fix:**
  ```bash
  cd /var/www/html        # or your docroot
  sudo chown -R www-data:www-data ecommerce
  sudo find ecommerce -type d -exec chmod 755 {} \;
  sudo find ecommerce -type f -exec chmod 644 {} \;
  ```

## 6. SELinux (CentOS/RHEL/Fedora servers)
If you deployed to a RHEL-family server, SELinux can 403 the files even with correct perms.

- **Confirm:** `sudo ausearch -m avc -ts recent` shows denials for httpd.
- **Fix:** `sudo chcon -R -t httpd_sys_content_t /var/www/html/ecommerce`
  (and `httpd_sys_rw_content_t` for `uploads/`).

## 7. URL case / path mismatch
The repo folder is `ecommerce` (lowercase). On Linux, `Ecommerce` ≠ `ecommerce`.

- **Confirm:** you typed the wrong case or an extra path segment.
- **Fix:** use the exact lowercase path: `http://localhost/ecommerce/index.php`.

---

## Fast triage checklist
1. Open the Apache **error log** and read the last line after reproducing — it usually names
   the file and the exact reason. Start there.
2. Hit `http://localhost/ecommerce/index.php` directly. Works? → it was a directory-index 403
   (cause #1), now fixed by the new `.htaccess`.
3. Broken images only? → cause #2 (uploads permissions/missing folder).
4. Whole site 403s? → cause #3/#4 (a bad `.htaccess` or `AllowOverride`). Rename `.htaccess`
   to test.
5. Linux/production? → check #5 (perms) and #6 (SELinux).

If you can paste the **exact URL** that 403s and the **last line of the Apache error log**,
the cause is almost always identifiable in one step.
