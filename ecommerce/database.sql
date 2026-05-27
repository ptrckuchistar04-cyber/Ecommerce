-- ============================================================
-- On The Line — v2 Database Schema
-- Import in phpMyAdmin: select DB (or create on_the_line_db) and run.
-- Safe to re-run: drops then recreates everything.
-- ============================================================

CREATE DATABASE IF NOT EXISTS on_the_line_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE on_the_line_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS payment_logs;
DROP TABLE IF EXISTS transaction_items;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS reservation_carts;
DROP TABLE IF EXISTS images;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS amenities;
DROP TABLE IF EXISTS vehicle_details;
DROP TABLE IF EXISTS real_estate_details;
DROP TABLE IF EXISTS property_details;
DROP TABLE IF EXISTS compare_sessions;
DROP TABLE IF EXISTS sell_inquiries;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS listings;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- USERS  (with birthday + structured address + auto-age)
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(11) DEFAULT NULL,
    -- Structured address (Philippines PSGC standard)
    address_barangay VARCHAR(150) DEFAULT NULL,
    address_city     VARCHAR(150) DEFAULT NULL,
    address_province VARCHAR(150) DEFAULT NULL,
    address_region   VARCHAR(150) DEFAULT NULL,
    address_country  VARCHAR(50)  DEFAULT 'Philippines',
    -- Birthday + auto-computed age (always current)
    birthday DATE DEFAULT NULL,
    age INT GENERATED ALWAYS AS (
        CASE WHEN birthday IS NULL THEN NULL
        ELSE TIMESTAMPDIFF(YEAR, birthday, CURDATE())
             - IF(DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(birthday, '%m%d'), 1, 0)
        END
    ) VIRTUAL,
    gender ENUM('male','female','other','prefer_not_to_say') DEFAULT 'prefer_not_to_say',
    role ENUM('customer','administrator') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- ============================================================
-- LISTINGS
-- ============================================================
CREATE TABLE listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    type ENUM('property','vehicle') NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(12,2) NOT NULL,
    reservation_fee DECIMAL(10,2) NOT NULL,
    main_image VARCHAR(255) DEFAULT NULL,
    status ENUM('available','reserved','sold','hidden') DEFAULT 'available',
    is_promo TINYINT(1) DEFAULT 0,
    is_bundle TINYINT(1) DEFAULT 0,
    bundle_pair_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_promo (is_promo)
) ENGINE=InnoDB;

CREATE TABLE property_details (
    listing_id INT PRIMARY KEY,
    property_type ENUM('house','condo','townhouse','land') NOT NULL,
    square_meters DECIMAL(8,2) NOT NULL,
    bedrooms INT DEFAULT 0,
    bathrooms INT DEFAULT 0,
    year_built YEAR DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vehicle_details (
    listing_id INT PRIMARY KEY,
    make VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year YEAR NOT NULL,
    mileage INT UNSIGNED NOT NULL,
    transmission ENUM('automatic','manual','cvt') DEFAULT 'automatic',
    fuel_type VARCHAR(30) DEFAULT NULL,
    modifications TEXT DEFAULT NULL,
    vin VARCHAR(17) UNIQUE DEFAULT NULL,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE amenities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    INDEX idx_listing (listing_id)
) ENGINE=InnoDB;

CREATE TABLE images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    url VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE compare_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_key VARCHAR(64) NOT NULL,
    listing_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_session_listing (session_key, listing_id),
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    INDEX idx_session (session_key)
) ENGINE=InnoDB;

CREATE TABLE reservation_carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    listing_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_listing (user_id, listing_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_number VARCHAR(40) UNIQUE NOT NULL,
    total_reservation_fee DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid','processing','completed','cancelled','expired') DEFAULT 'pending',
    xendit_invoice_id VARCHAR(100) DEFAULT NULL,
    xendit_invoice_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_invoice (xendit_invoice_id)
) ENGINE=InnoDB;

CREATE TABLE transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    listing_id INT NOT NULL,
    reservation_fee DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT DEFAULT NULL,
    invoice_id VARCHAR(100),
    status VARCHAR(50),
    payload TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE sell_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_type ENUM('property','vehicle') NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    asking_price DECIMAL(12,2) NOT NULL,
    contact_phone VARCHAR(20),
    status ENUM('new','reviewing','approved','rejected') DEFAULT 'new',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA  (fresh, correct password hashes)
-- admin password: admin123      customer password: customer123
-- ============================================================
INSERT INTO users (full_name, email, password_hash, phone, address_barangay, address_city, address_province, address_region, address_country, birthday, gender, role) VALUES
('Admin User', 'admin@ontheline.com', '$2y$12$vgZ7zzWWdCZwQ9kFJZrURO3PIBZlizRDbRdnVPYBOd.Y78auqR57e', '09171234567', 'Poblacion', 'Makati', 'Metro Manila', 'NCR', 'Philippines', '1990-05-15', 'male', 'administrator'),
('John Doe',   'customer@example.com', '$2y$12$6LTvGlwjS2BymcrlewZrr.Hdyh9na6PiJ6g8zBQBtBecwxiXcQRg2', '09181234567', 'Brgy. Commonwealth', 'Quezon City', 'Metro Manila', 'NCR', 'Philippines', '1995-08-20', 'male', 'customer');

INSERT INTO listings (admin_id, type, title, description, price, reservation_fee, status, is_promo) VALUES
(1, 'property', 'Modern Family Home in Makati',  'Beautiful 3-bedroom family home in the heart of Makati. Recently renovated with modern finishes.', 8500000.00, 50000.00, 'available', 1),
(1, 'property', 'Luxury Condo in BGC',           'Premium 2-bedroom condo unit with stunning city views.', 12000000.00, 75000.00, 'available', 1),
(1, 'property', 'Townhouse in Quezon City',      'Spacious 4-bedroom townhouse perfect for growing families.', 6500000.00, 35000.00, 'available', 0),
(1, 'property', 'Vacant Lot in Tagaytay',        'Prime vacant lot with panoramic views of Taal Volcano.', 3500000.00, 25000.00, 'available', 0),
(1, 'property', 'Beach House in Batangas',       'Stunning beachfront property with private access to white sand beach.', 15000000.00, 100000.00, 'available', 1),
(1, 'vehicle',  '2023 Toyota Fortuner LTD',      'Like-new Toyota Fortuner with low mileage. Full service records available.', 1850000.00, 15000.00, 'available', 1),
(1, 'vehicle',  '2022 Honda Civic RS Turbo',     'Sporty Civic RS in excellent condition. Complete with Honda sensing.', 1350000.00, 12000.00, 'available', 0),
(1, 'vehicle',  '2020 Mitsubishi Montero Sport', 'Well-maintained Montero Sport with comprehensive insurance.', 1250000.00, 10000.00, 'available', 0),
(1, 'vehicle',  '2023 Ford Ranger Raptor',       'Powerful Ranger Raptor with off-road package.', 2150000.00, 20000.00, 'available', 1),
(1, 'vehicle',  '2021 Suzuki Jimny GLX',         'Fun and capable mini SUV. Perfect for city driving.', 950000.00, 8000.00, 'available', 0);

INSERT INTO property_details (listing_id, property_type, square_meters, bedrooms, bathrooms, year_built, location) VALUES
(1, 'house',     180.50, 3, 2, 2020, 'Makati City'),
(2, 'condo',      95.00, 2, 2, 2022, 'Taguig (BGC)'),
(3, 'townhouse', 150.00, 4, 3, 2019, 'Quezon City'),
(4, 'land',      300.00, 0, 0, NULL, 'Tagaytay'),
(5, 'house',     200.00, 4, 3, 2021, 'Batangas');

INSERT INTO amenities (listing_id, name) VALUES
(1,'Swimming Pool'),(1,'Garden'),(1,'2-Car Garage'),
(2,'Gym'),(2,'Infinity Pool'),(2,'24/7 Security'),
(3,'Gated Community'),(3,'Playground'),(3,'Parking'),
(4,'Mountain View'),(4,'Near Highway'),
(5,'Beach Access'),(5,'Veranda'),(5,'Outdoor Kitchen');

INSERT INTO vehicle_details (listing_id, make, model, year, mileage, transmission, fuel_type, modifications, vin) VALUES
(6,'Toyota','Fortuner',2023,15000,'automatic','Diesel','All stock, premium tint','MHFZX80G000123456'),
(7,'Honda','Civic',2022,25000,'automatic','Gasoline','Ceramic coating, modulo kit','FD23456789012'),
(8,'Mitsubishi','Montero Sport',2020,45000,'automatic','Diesel','Upgraded sound system','MMBJRKH100345678'),
(9,'Ford','Ranger',2023,8000,'automatic','Diesel','Roll bar, bed liner','RAP1234567890'),
(10,'Suzuki','Jimny',2021,30000,'automatic','Gasoline','Lifted suspension, roof rack','JIMNY2021000987');
