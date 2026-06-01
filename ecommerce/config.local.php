<?php
/**
 * Local secrets — this file is git-ignored (see .gitignore), so it is NOT committed.
 * config.php loads it automatically if it exists.
 *
 * NOTE: rotate this key in the Xendit dashboard since it was exposed publicly,
 * then replace the value below with the new one.
 */

// ===== Database (stock XAMPP defaults) =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'on_the_line_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// ===== Xendit =====
define('XENDIT_SECRET_KEY', 'xnd_development_l4Na8u8AACRCVqlzyVJj8pVuvzx2bjMF8Cy7VCAtrlVjMWxVwKXybXvfpQ2yJnwd');

// Set this after you create the webhook in Xendit (Settings → Callbacks).
// Leave as-is for now; invoices work without it (only auto-PAID status needs it).
define('XENDIT_CALLBACK_TOKEN', 'REPLACE_WITH_YOUR_WEBHOOK_TOKEN');
