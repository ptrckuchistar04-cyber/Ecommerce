-- ============================================================
-- ON THE LINE — PATCH v3
-- Adds: mortgage fields for properties, extra fields for vehicles,
--       full detail columns in sell_inquiries, image support.
-- Run in phpMyAdmin after importing the original database.sql.
-- ============================================================
USE on_the_line_db;

-- ====== 1. Add mortgage & extra property fields ======
ALTER TABLE property_details
  ADD COLUMN IF NOT EXISTS is_mortgaged      TINYINT(1)   DEFAULT 0 AFTER location,
  ADD COLUMN IF NOT EXISTS monthly_amortization DECIMAL(10,2) DEFAULT NULL AFTER is_mortgaged,
  ADD COLUMN IF NOT EXISTS mortgage_bank     VARCHAR(100)  DEFAULT NULL AFTER monthly_amortization,
  ADD COLUMN IF NOT EXISTS furnishing        VARCHAR(50)   DEFAULT NULL AFTER mortgage_bank,
  ADD COLUMN IF NOT EXISTS parking_slots     INT           DEFAULT 0 AFTER furnishing,
  ADD COLUMN IF NOT EXISTS floors            INT           DEFAULT 1 AFTER parking_slots,
  ADD COLUMN IF NOT EXISTS lot_area          DECIMAL(10,2) DEFAULT NULL AFTER square_meters;

-- ====== 2. Add extra vehicle fields ======
ALTER TABLE vehicle_details
  ADD COLUMN IF NOT EXISTS color             VARCHAR(30)   DEFAULT NULL AFTER vin,
  ADD COLUMN IF NOT EXISTS engine_type       VARCHAR(50)   DEFAULT NULL AFTER color,
  ADD COLUMN IF NOT EXISTS `condition`       VARCHAR(50)   DEFAULT 'used' AFTER engine_type,
  ADD COLUMN IF NOT EXISTS plate_number      VARCHAR(20)   DEFAULT NULL AFTER `condition`;

-- ====== 3. Upgrade sell_inquiries to store full listing details ======
ALTER TABLE sell_inquiries
  ADD COLUMN IF NOT EXISTS main_image        VARCHAR(255)  DEFAULT NULL AFTER contact_phone,
  -- Property fields
  ADD COLUMN IF NOT EXISTS property_type     VARCHAR(30)   DEFAULT NULL AFTER main_image,
  ADD COLUMN IF NOT EXISTS square_meters     DECIMAL(10,2) DEFAULT NULL AFTER property_type,
  ADD COLUMN IF NOT EXISTS lot_area          DECIMAL(10,2) DEFAULT NULL AFTER square_meters,
  ADD COLUMN IF NOT EXISTS bedrooms          INT           DEFAULT 0 AFTER lot_area,
  ADD COLUMN IF NOT EXISTS bathrooms         INT           DEFAULT 0 AFTER bedrooms,
  ADD COLUMN IF NOT EXISTS year_built        YEAR          DEFAULT NULL AFTER bathrooms,
  ADD COLUMN IF NOT EXISTS location          VARCHAR(255)  DEFAULT NULL AFTER year_built,
  ADD COLUMN IF NOT EXISTS is_mortgaged      TINYINT(1)    DEFAULT 0 AFTER location,
  ADD COLUMN IF NOT EXISTS monthly_amortization DECIMAL(10,2) DEFAULT NULL AFTER is_mortgaged,
  ADD COLUMN IF NOT EXISTS mortgage_bank     VARCHAR(100)  DEFAULT NULL AFTER monthly_amortization,
  ADD COLUMN IF NOT EXISTS furnishing        VARCHAR(50)   DEFAULT NULL AFTER mortgage_bank,
  ADD COLUMN IF NOT EXISTS parking_slots     INT           DEFAULT 0 AFTER furnishing,
  ADD COLUMN IF NOT EXISTS floors            INT           DEFAULT 1 AFTER parking_slots,
  -- Vehicle fields
  ADD COLUMN IF NOT EXISTS make              VARCHAR(50)   DEFAULT NULL AFTER floors,
  ADD COLUMN IF NOT EXISTS model             VARCHAR(50)   DEFAULT NULL AFTER make,
  ADD COLUMN IF NOT EXISTS vehicle_year      YEAR          DEFAULT NULL AFTER model,
  ADD COLUMN IF NOT EXISTS mileage           INT UNSIGNED  DEFAULT 0 AFTER vehicle_year,
  ADD COLUMN IF NOT EXISTS transmission      VARCHAR(30)   DEFAULT 'automatic' AFTER mileage,
  ADD COLUMN IF NOT EXISTS fuel_type         VARCHAR(30)   DEFAULT NULL AFTER transmission,
  ADD COLUMN IF NOT EXISTS modifications     TEXT          DEFAULT NULL AFTER fuel_type,
  ADD COLUMN IF NOT EXISTS vin               VARCHAR(17)   DEFAULT NULL AFTER modifications,
  ADD COLUMN IF NOT EXISTS color             VARCHAR(30)   DEFAULT NULL AFTER vin,
  ADD COLUMN IF NOT EXISTS engine_type       VARCHAR(50)   DEFAULT NULL AFTER color,
  ADD COLUMN IF NOT EXISTS vehicle_condition VARCHAR(50)   DEFAULT 'used' AFTER engine_type,
  ADD COLUMN IF NOT EXISTS plate_number      VARCHAR(20)   DEFAULT NULL AFTER vehicle_condition;

-- ====== 4. Create inquiry_images table for multiple images ======
CREATE TABLE IF NOT EXISTS inquiry_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_id INT NOT NULL,
    url VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (inquiry_id) REFERENCES sell_inquiries(id) ON DELETE CASCADE,
    INDEX idx_inquiry (inquiry_id)
) ENGINE=InnoDB;

SELECT 'Patch v3 applied successfully ✓' AS status;
