-- ============================================================
-- ON THE LINE — PATCH v4
-- Adds: seller link on listings, promo-fee payment tracking,
--       and an admin notifications table.
-- Run in phpMyAdmin after the earlier patches.
-- ============================================================
USE on_the_line_db;

-- 1. Link a listing back to the seller (set when an inquiry is approved),
--    and track the seller's promo-fee payment state.
ALTER TABLE listings
  ADD COLUMN IF NOT EXISTS seller_id    INT DEFAULT NULL AFTER admin_id,
  ADD COLUMN IF NOT EXISTS promo_status ENUM('none','pending_payment','paid') DEFAULT 'none' AFTER is_promo,
  ADD COLUMN IF NOT EXISTS promo_fee    DECIMAL(10,2) DEFAULT NULL AFTER promo_status;

-- 2. Promo-fee payments (separate from reservation transactions).
CREATE TABLE IF NOT EXISTS promo_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    seller_id  INT NOT NULL,
    order_number VARCHAR(40) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid','expired','cancelled') DEFAULT 'pending',
    xendit_invoice_id  VARCHAR(100) DEFAULT NULL,
    xendit_invoice_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id)  REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- 3. Admin notifications (e.g. "seller paid a promo fee").
CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    message VARCHAR(500) NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_read (is_read)
) ENGINE=InnoDB;

SELECT 'Patch v4 applied successfully ✓' AS status;
