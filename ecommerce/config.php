<?php
/**
 * On The Line — Master Config
 *
 * Secrets (DB password, Xendit keys) are read from the environment first, then
 * fall back to an OPTIONAL, git-ignored `config.local.php` that defines them, and
 * finally to the safe defaults below. This keeps real keys OUT of version control.
 *
 *   1. Set env vars (best for real hosting), OR
 *   2. Copy config.local.example.php → config.local.php and put your keys there.
 */

// Load local overrides if present (this file is git-ignored — see .gitignore).
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

/**
 * Define a constant only if it isn't already set, pulling from (in order):
 *   1. an env var of the same name,
 *   2. a constant already defined in config.local.php,
 *   3. the supplied default.
 */
function def(string $key, string $default = ''): void {
    if (defined($key)) return;                 // already set by config.local.php
    $env = getenv($key);
    define($key, ($env !== false && $env !== '') ? $env : $default);
}

// ====== APP ======
define('SITE_NAME', 'On The Line');
def('SITE_URL', 'http://localhost/ecommerce/');
define('UPLOAD_DIR', __DIR__ . '/uploads/products/');
define('MAX_COMPARE_ITEMS', 4);

// ====== DATABASE ======
def('DB_HOST', 'localhost');
def('DB_NAME', 'on_the_line_db');
def('DB_USER', 'root');
def('DB_PASS', '');                 // stock XAMPP root has no password
define('DB_CHARSET', 'utf8mb4');

// ====== XENDIT ======
// IMPORTANT: never commit a real key. Provide it via env var XENDIT_SECRET_KEY
// or config.local.php. The placeholder below lets the UI load without a key.
def('XENDIT_SECRET_KEY', 'REPLACE_WITH_YOUR_XENDIT_SECRET_KEY');
// Token Xendit sends in X-CALLBACK-TOKEN header for webhooks.
// Get this from Xendit dashboard → Settings → Callbacks.
def('XENDIT_CALLBACK_TOKEN', 'REPLACE_WITH_YOUR_WEBHOOK_TOKEN');
define('XENDIT_API_BASE',      'https://api.xendit.co');
define('XENDIT_SUCCESS_URL',   SITE_URL . 'order-success.php');
define('XENDIT_FAILURE_URL',   SITE_URL . 'checkout.php?status=failed');

// ====== SESSION ======
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    // Send cookies over HTTPS only when the request is actually HTTPS (safe on localhost).
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.cookie_secure', $https ? 1 : 0);
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
