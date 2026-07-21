-- ============================================================
-- upgrade_payment_v2.sql
-- Premium tizimi yangilanishlari uchun ma'lumotlar bazasi o'zgarishlari
-- phpMyAdmin -> uzdub bazasini tanlang -> SQL bo'limi -> shu faylni joylashtiring -> Bajarish
-- ============================================================

USE uzdub;

-- 1) transaction_id ustuni
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'premium_payments' AND column_name = 'transaction_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE premium_payments ADD COLUMN transaction_id VARCHAR(64) DEFAULT NULL AFTER screenshot', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) payment_system ustuni
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'premium_payments' AND column_name = 'payment_system');
SET @sql = IF(@col_exists = 0, 
    "ALTER TABLE premium_payments ADD COLUMN payment_system ENUM('card','click','uzum') DEFAULT 'card' AFTER transaction_id", 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) transaction_id indeksi
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'premium_payments' AND index_name = 'idx_transaction');
SET @sql = IF(@idx_exists = 0, 
    'CREATE INDEX idx_transaction ON premium_payments (transaction_id)', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Premium tizimi muvaffaqiyatli yangilandi!' AS natija;
