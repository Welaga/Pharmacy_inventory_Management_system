-- ============================================================
-- PharmaCare IMS — Password Fix Script
-- Run this in phpMyAdmin if you already imported setup.sql
-- and the login credentials are not working.
-- ============================================================

USE pharmacy_db;

-- Update admin password to: admin123
UPDATE users
SET password = '$2y$10$WwOmVlxNEKFBolmfUmImCuCFG9vKjCpVFcafwIjq2b8/7ZKIMAtES'
WHERE email = 'admin@pharmacy.com';

-- Update pharmacist password to: pharma123
UPDATE users
SET password = '$2y$10$.z.a4AdKNGgqmJti9W7/h.2bQQAPV.z9BIGtQGMarooGAoWShe7Cm'
WHERE email = 'jane@pharmacy.com';

-- Confirm the update (should return 2 rows)
SELECT id, name, email, role, status,
       LEFT(password, 7) AS hash_prefix
FROM users;
