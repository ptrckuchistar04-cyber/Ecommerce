<?php
/**
 * Copy this file to `config.local.php` (which is git-ignored) and put your real
 * secrets here. config.php loads it automatically if it exists.
 *
 * Alternatively, set these as real environment variables instead.
 */

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'on_the_line_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Xendit — TEST keys for this project
define('XENDIT_SECRET_KEY',     'xnd_development_xxxxxxxxxxxxxxxxxxxxxxxx');
define('XENDIT_CALLBACK_TOKEN', 'your_webhook_callback_token');

// Optional: override site URL
// define('SITE_URL', 'http://localhost/ecommerce/');
