-- ============================================================
-- ON THE LINE — PATCH for existing v2 install
-- Run this in phpMyAdmin if you already imported database.sql
-- and don't want to wipe your data.
-- ============================================================
USE on_the_line_db;

-- 1. Fix the broken password hashes
UPDATE users SET password_hash = '$2y$12$vgZ7zzWWdCZwQ9kFJZrURO3PIBZlizRDbRdnVPYBOd.Y78auqR57e'
  WHERE email = 'admin@ontheline.com';
UPDATE users SET password_hash = '$2y$12$6LTvGlwjS2BymcrlewZrr.Hdyh9na6PiJ6g8zBQBtBecwxiXcQRg2'
  WHERE email = 'customer@example.com';

-- 2. Add structured address columns (if not present)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS address_barangay VARCHAR(150) DEFAULT NULL AFTER phone,
  ADD COLUMN IF NOT EXISTS address_city     VARCHAR(150) DEFAULT NULL AFTER address_barangay,
  ADD COLUMN IF NOT EXISTS address_province VARCHAR(150) DEFAULT NULL AFTER address_city,
  ADD COLUMN IF NOT EXISTS address_region   VARCHAR(150) DEFAULT NULL AFTER address_province,
  ADD COLUMN IF NOT EXISTS address_country  VARCHAR(50)  DEFAULT 'Philippines' AFTER address_region;

-- 3. Migrate legacy `address` column into city if it exists, then drop it
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'address'
);
SET @sql := IF(@col_exists > 0,
  'UPDATE users SET address_city = address WHERE address_city IS NULL AND address IS NOT NULL',
  'SELECT "address column already removed"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@col_exists > 0, 'ALTER TABLE users DROP COLUMN address', 'SELECT "skip"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Limit phone column to 11 chars
ALTER TABLE users MODIFY phone VARCHAR(11) DEFAULT NULL;

SELECT 'Patch applied successfully ✓' AS status;
