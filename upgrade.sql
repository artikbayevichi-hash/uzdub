-- YANGILASH SKRIPTI: eski bazaga yangi jadvallarni qo'shadi
-- phpMyAdmin -> UZDUB bazasini tanlang -> SQL bo'limi -> shu faylni joylashtiring -> Bajarish

USE uzdub;

-- content jadvaliga yangi ustunlar (agar mavjud bo'lmasa)
SET @dbname = DATABASE();
SET @tablename = 'content';
SET @columnname = 'content_code';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @dbname AND table_name = @tablename AND column_name = @columnname) > 0,
  'SELECT ''content_code ustuni allaqachon bor'';',
  'ALTER TABLE content ADD COLUMN content_code VARCHAR(10) UNIQUE AFTER id;'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname2 = 'is_premium';
SET @preparedStatement2 = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @dbname AND table_name = @tablename AND column_name = @columnname2) > 0,
  'SELECT ''is_premium ustuni allaqachon bor'';',
  'ALTER TABLE content ADD COLUMN is_premium TINYINT(1) DEFAULT 0 AFTER is_series;'
));
PREPARE alterIfNotExists2 FROM @preparedStatement2;
EXECUTE alterIfNotExists2;
DEALLOCATE PREPARE alterIfNotExists2;

-- Foydalanuvchilar jadvali
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(8) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    is_premium TINYINT(1) DEFAULT 0,
    premium_expires_at DATETIME DEFAULT NULL,
    switch_token VARCHAR(64) DEFAULT NULL,
    google_id VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Premium to'lovlar
CREATE TABLE IF NOT EXISTS premium_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan ENUM('1month','3month','1year') NOT NULL,
    amount INT NOT NULL,
    screenshot VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'approved',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Global chat
CREATE TABLE IF NOT EXISTS global_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT,
    attachment VARCHAR(255) DEFAULT NULL,
    attachment_type ENUM('image','gif') DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Shaxsiy chat
CREATE TABLE IF NOT EXISTS private_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT,
    attachment VARCHAR(255) DEFAULT NULL,
    attachment_type ENUM('image','gif') DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Keyinroq ko'rish ro'yxati
CREATE TABLE IF NOT EXISTS watchlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_watch (user_id, content_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE
);

-- switch_token va google_id ustunlari (mavjud DB uchun)
SET @dbname = DATABASE();
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'switch_token';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN switch_token VARCHAR(64) DEFAULT NULL AFTER premium_expires_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'google_id';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN google_id VARCHAR(100) DEFAULT NULL AFTER switch_token', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_login_at';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN last_login_at DATETIME DEFAULT NULL AFTER google_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Yangilash muvaffaqiyatli yakunlandi!' AS natija;
