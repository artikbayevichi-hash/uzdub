-- ============================================================
-- UZDUB PLATFORM v3 — Yangi funksiyalar (reyting, izoh, promo, subtitr, ko'rildi)
-- phpMyAdmin -> UZDUB -> SQL -> import
-- ============================================================

USE uzdub;

-- Admin parolini yangilash uchun: brauzerda install_v3.php ni bir marta oching

-- Foydalanuvchi reytinglari (1-10)
CREATE TABLE IF NOT EXISTS content_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_content (user_id, content_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_content (content_id)
);

-- Izohlar
CREATE TABLE IF NOT EXISTS content_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_content (content_id),
    INDEX idx_created (created_at)
);

-- Ko'rildi belgisi
CREATE TABLE IF NOT EXISTS watched_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    episode_id INT DEFAULT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_content (user_id, content_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
);

-- Promo kodlar
CREATE TABLE IF NOT EXISTS promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    discount_percent INT DEFAULT 0,
    free_days INT DEFAULT 0,
    max_uses INT DEFAULT 1,
    used_count INT DEFAULT 0,
    expires_at DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS promo_redemptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promo_id INT NOT NULL,
    user_id INT NOT NULL,
    redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_promo_user (promo_id, user_id),
    FOREIGN KEY (promo_id) REFERENCES promo_codes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Subtitrlar (.vtt)
CREATE TABLE IF NOT EXISTS content_subtitles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    episode_id INT DEFAULT NULL,
    language VARCHAR(10) NOT NULL DEFAULT 'uz',
    label VARCHAR(50) DEFAULT 'O\'zbek',
    file_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_content (content_id)
);

-- API rate limiting
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(191) NOT NULL,
    endpoint VARCHAR(64) NOT NULL,
    hit_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate (identifier, endpoint, hit_at)
);

-- watch_progress ga completed ustuni
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'watch_progress' AND COLUMN_NAME = 'is_completed');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE watch_progress ADD COLUMN is_completed TINYINT(1) DEFAULT 0 AFTER duration_seconds',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Demo promo kod
INSERT INTO promo_codes (code, discount_percent, free_days, max_uses, expires_at)
SELECT 'UZDUBPLATFORM2026', 0, 7, 100, '2027-12-31 23:59:59'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM promo_codes WHERE code='UZDUBPLATFORM2026');
