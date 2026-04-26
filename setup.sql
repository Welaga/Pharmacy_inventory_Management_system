-- ============================================================
-- Pharmacy Inventory Management System - Database Setup
-- ============================================================

CREATE DATABASE IF NOT EXISTS pharmacy_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacy_db;

-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)                     NOT NULL,
    email       VARCHAR(100)                     NOT NULL UNIQUE,
    password    VARCHAR(255)                     NOT NULL,
    role        ENUM('admin','pharmacist')        DEFAULT 'pharmacist',
    status      ENUM('active','inactive')         DEFAULT 'active',
    created_at  TIMESTAMP                         DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP                         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role  (role)
);

-- ============================================================
-- CATEGORIES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SUPPLIERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS suppliers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)                  NOT NULL,
    contact     VARCHAR(50),
    email       VARCHAR(100),
    address     TEXT,
    status      ENUM('active','inactive')      DEFAULT 'active',
    created_at  TIMESTAMP                      DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP                      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- MEDICINES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS medicines (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150)     NOT NULL,
    category_id         INT,
    batch_no            VARCHAR(50),
    expiry_date         DATE             NOT NULL,
    purchase_price      DECIMAL(10,2)    NOT NULL,
    selling_price       DECIMAL(10,2)    NOT NULL,
    quantity            INT              NOT NULL DEFAULT 0,
    low_stock_threshold INT              DEFAULT 10,
    supplier_id         INT,
    barcode             VARCHAR(100),
    description         TEXT,
    created_at          TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_name    (name),
    INDEX idx_expiry  (expiry_date),
    INDEX idx_qty     (quantity),
    INDEX idx_barcode (barcode)
);

-- ============================================================
-- SALES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS sales (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT           NOT NULL,
    customer_name   VARCHAR(100)  DEFAULT 'Walk-in Customer',
    total_amount    DECIMAL(10,2) NOT NULL,
    discount        DECIMAL(10,2) DEFAULT 0.00,
    paid_amount     DECIMAL(10,2) NOT NULL,
    change_amount   DECIMAL(10,2) DEFAULT 0.00,
    payment_method  ENUM('cash','card','mobile') DEFAULT 'cash',
    status          ENUM('completed','cancelled','refunded') DEFAULT 'completed',
    notes           TEXT,
    sale_date       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_sale_date (sale_date),
    INDEX idx_user_id   (user_id),
    INDEX idx_status    (status)
);

-- ============================================================
-- SALE ITEMS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS sale_items (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    sale_id       INT           NOT NULL,
    medicine_id   INT           NOT NULL,
    quantity      INT           NOT NULL,
    unit_price    DECIMAL(10,2) NOT NULL,
    total_price   DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id)     REFERENCES sales(id)     ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
    INDEX idx_sale_id (sale_id),
    INDEX idx_med_id  (medicine_id)
);

-- ============================================================
-- AUDIT LOGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    action      VARCHAR(100) NOT NULL,
    table_name  VARCHAR(50),
    record_id   INT,
    old_values  TEXT,
    new_values  TEXT,
    ip_address  VARCHAR(45),
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id   (user_id),
    INDEX idx_created   (created_at),
    INDEX idx_action    (action)
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default admin password: admin123  |  pharmacist password: pharma123
-- Hashes generated and verified with PHP 8 password_hash(PASSWORD_BCRYPT)
INSERT INTO users (name, email, password, role) VALUES
('System Admin',    'admin@pharmacy.com', '$2y$10$WwOmVlxNEKFBolmfUmImCuCFG9vKjCpVFcafwIjq2b8/7ZKIMAtES', 'admin'),
('Jane Pharmacist', 'jane@pharmacy.com',  '$2y$10$.z.a4AdKNGgqmJti9W7/h.2bQQAPV.z9BIGtQGMarooGAoWShe7Cm', 'pharmacist');

INSERT INTO categories (name, description) VALUES
('Antibiotics',         'Medicines that inhibit or kill bacteria'),
('Analgesics',          'Pain-relief medicines'),
('Antifungals',         'Medicines used to treat fungal infections'),
('Vitamins & Supplements', 'Nutritional supplements and vitamins'),
('Antihypertensives',   'Medicines to control high blood pressure'),
('Antidiabetics',       'Medicines for diabetes management'),
('Antihistamines',      'Allergy and cold relief medicines'),
('Antacids & GI',       'Gastrointestinal and heartburn medicines'),
('Antiparasitics',      'Medicines for parasitic infections'),
('Dermatologicals',     'Skin care and treatment medicines');

INSERT INTO suppliers (name, contact, email, address) VALUES
('MedSupply Co.',          '+1-555-0101', 'info@medsupply.com',          '123 Supply Street, Medical District, NY 10001'),
('PharmaDistrib Inc.',     '+1-555-0202', 'orders@pharmadistrib.com',    '456 Pharma Avenue, Business Park, CA 90001'),
('HealthCare Wholesale',   '+1-555-0303', 'wholesale@healthcare.com',    '789 Health Blvd, Commerce Zone, TX 75001'),
('Global Meds Ltd.',       '+1-555-0404', 'global@globalmeds.com',       '321 Global Way, International Trade Center, FL 33101');

INSERT INTO medicines (name, category_id, batch_no, expiry_date, purchase_price, selling_price, quantity, low_stock_threshold, supplier_id, description) VALUES
('Amoxicillin 500mg',        1, 'BAT2024001', '2026-06-30', 5.00,  8.50,  150,  20, 1, 'Broad-spectrum penicillin antibiotic'),
('Paracetamol 500mg',        2, 'BAT2024002', '2027-01-15', 1.50,  3.00,  500,  50, 2, 'Common pain and fever relief'),
('Ibuprofen 400mg',          2, 'BAT2024003', '2026-12-31', 2.00,  4.50,  200,  30, 1, 'NSAID for pain, fever, and inflammation'),
('Fluconazole 150mg',        3, 'BAT2024004', '2025-11-30', 8.00,  15.00,  5,   10, 3, 'Antifungal for yeast infections'),
('Vitamin C 1000mg',         4, 'BAT2024005', '2027-03-20', 3.00,  6.00,  300,  40, 2, 'Immune system booster'),
('Amlodipine 5mg',           5, 'BAT2024006', '2026-08-15', 4.00,  7.50,   8,   15, 1, 'Calcium channel blocker for hypertension'),
('Metformin 500mg',          6, 'BAT2024007', '2024-12-31', 2.50,  5.00,  100,  25, 3, 'First-line drug for type 2 diabetes'),
('Cetirizine 10mg',          7, 'BAT2024008', '2026-10-20', 1.00,  2.50,   80,  20, 2, 'Second-generation antihistamine'),
('Omeprazole 20mg',          8, 'BAT2024009', '2026-05-10', 3.50,  7.00,   12,  15, 1, 'Proton pump inhibitor for GERD'),
('Aspirin 81mg',             2, 'BAT2024010', '2027-06-30', 1.00,  2.00,  400,  50, 2, 'Low-dose aspirin for cardiovascular protection'),
('Azithromycin 250mg',       1, 'BAT2024011', '2026-09-15', 6.00,  12.00,  60,  15, 1, 'Macrolide antibiotic for bacterial infections'),
('Lisinopril 10mg',          5, 'BAT2024012', '2026-11-20', 3.00,  6.50,  120,  20, 3, 'ACE inhibitor for blood pressure'),
('Zinc 50mg',                4, 'BAT2024013', '2027-05-10', 2.00,  4.00,  250,  30, 2, 'Zinc supplement for immune function'),
('Loratadine 10mg',          7, 'BAT2024014', '2027-02-28', 1.50,  3.50,  180,  25, 4, 'Non-drowsy antihistamine'),
('Ranitidine 150mg',         8, 'BAT2024015', '2025-08-31', 2.50,  5.00,    3,  10, 1, 'H2 blocker for stomach acid');
