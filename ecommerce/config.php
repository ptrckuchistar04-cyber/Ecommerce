<?php
/**
 * On The Line — Master Config
 * Edit DB credentials and Xendit keys here.
 */

// ====== APP ======
define('SITE_NAME', 'On The Line');
define('SITE_URL',  'http://localhost/ecommerce/');
define('UPLOAD_DIR', __DIR__ . '/uploads/products/');
define('MAX_COMPARE_ITEMS', 4);

// ====== DATABASE ======
define('DB_HOST', 'localhost');
define('DB_NAME', 'on_the_line_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ====== XENDIT ======
// Your DEV secret key (replace with live key in production):
define('XENDIT_SECRET_KEY', 'xnd_development_l4Na8u8AACRCVqlzyVJj8pVuvzx2bjMF8Cy7VCAtrlVjMWxVwKXybXvfpQ2yJnwd');
// Token Xendit sends in X-CALLBACK-TOKEN header for webhooks.
// Get this from Xendit dashboard → Settings → Callbacks.
define('XENDIT_CALLBACK_TOKEN', 'REPLACE_WITH_YOUR_WEBHOOK_TOKEN');
define('XENDIT_API_BASE',      'https://api.xendit.co');
define('XENDIT_SUCCESS_URL',   SITE_URL . 'order-success.php');
define('XENDIT_FAILURE_URL',   SITE_URL . 'checkout.php?status=failed');

// ====== SESSION ======
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// CSRF token bootstrap
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ====== ERRORS ======
error_reporting(E_ALL);
ini_set('display_errors', 0);    // never show to users
ini_set('log_errors', 1);

date_default_timezone_set('Asia/Manila');

// ====== BRAND COLORS ======
define('COLOR_NAVY',   '#191970');
define('COLOR_ORANGE', '#FF8C00');
define('COLOR_SILVER', '#C0C0C0');
