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

-- google_id ustuni (mavjud DB uchun)
SET @dbname = DATABASE();
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'google_id';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN google_id VARCHAR(100) DEFAULT NULL AFTER premium_expires_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_login_at';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN last_login_at DATETIME DEFAULT NULL AFTER google_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS user_content_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    status ENUM('watching','planned','completed','paused','dropped','favorite') NOT NULL DEFAULT 'watching',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_content_status (user_id, content_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB;

-- =====================================================
-- WATCH HISTORY
-- =====================================================
CREATE TABLE IF NOT EXISTS watch_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    episode_id INT DEFAULT NULL,
    watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress_seconds INT DEFAULT 0,
    INDEX idx_user_history (user_id, watched_at),
    INDEX idx_content (content_id),
    INDEX idx_episode (episode_id)
) ENGINE=InnoDB;

-- watch_history da UNIQUE key (user_id, content_id) вЂ” dublikatlarni oldini oladi
DELETE wh FROM watch_history wh INNER JOIN watch_history wh2 ON wh.user_id=wh2.user_id AND wh.content_id=wh2.content_id AND wh.id < wh2.id;
SELECT COUNT(*) INTO @wh_unique FROM information_schema.statistics
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'watch_history' AND INDEX_NAME = 'uniq_user_content';
SET @wh_unique_sql = IF(@wh_unique = 0, 'ALTER TABLE watch_history ADD UNIQUE KEY uniq_user_content (user_id, content_id)', 'SELECT 1');
PREPARE wh_unique_stmt FROM @wh_unique_sql; EXECUTE wh_unique_stmt; DEALLOCATE PREPARE wh_unique_stmt;

-- =====================================================
-- watch_progress: is_completed va episode_id ustunlari
-- =====================================================
SELECT COUNT(*) INTO @wp_col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'watch_progress' AND COLUMN_NAME = 'is_completed';
SET @wp_sql = IF(@wp_col = 0, 'ALTER TABLE watch_progress ADD COLUMN is_completed TINYINT(1) DEFAULT 0 AFTER duration_seconds', 'SELECT 1');
PREPARE wp_stmt FROM @wp_sql; EXECUTE wp_stmt; DEALLOCATE PREPARE wp_stmt;

SELECT COUNT(*) INTO @wp_col2 FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'watch_progress' AND COLUMN_NAME = 'episode_id';
SET @wp_sql2 = IF(@wp_col2 = 0, 'ALTER TABLE watch_progress ADD COLUMN episode_id INT DEFAULT NULL AFTER content_id, ADD INDEX idx_episode (episode_id)', 'SELECT 1');
PREPARE wp_stmt2 FROM @wp_sql2; EXECUTE wp_stmt2; DEALLOCATE PREPARE wp_stmt2;

SELECT COUNT(*) INTO @wh_col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'watch_history' AND COLUMN_NAME = 'episode_id';
SET @wh_sql = IF(@wh_col = 0, 'ALTER TABLE watch_history ADD COLUMN episode_id INT DEFAULT NULL AFTER content_id, ADD INDEX idx_episode (episode_id)', 'SELECT 1');
PREPARE wh_stmt FROM @wh_sql; EXECUTE wh_stmt; DEALLOCATE PREPARE wh_stmt;

-- =====================================================
-- Telegram 2FA: foydalanuvchining chat_id si va bog'lash kodlari
-- =====================================================
SELECT COUNT(*) INTO @tg_col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'telegram_chat_id';
SET @tg_sql = IF(@tg_col = 0, 'ALTER TABLE users ADD COLUMN telegram_chat_id VARCHAR(32) DEFAULT NULL AFTER two_factor_secret', 'SELECT 1');
PREPARE tg_stmt FROM @tg_sql; EXECUTE tg_stmt; DEALLOCATE PREPARE tg_stmt;

SELECT COUNT(*) INTO @tg_col2 FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'tg_link_code';
SET @tg_sql2 = IF(@tg_col2 = 0, 'ALTER TABLE users ADD COLUMN tg_link_code VARCHAR(8) DEFAULT NULL AFTER telegram_chat_id, ADD COLUMN tg_link_expires DATETIME DEFAULT NULL', 'SELECT 1');
PREPARE tg_stmt2 FROM @tg_sql2; EXECUTE tg_stmt2; DEALLOCATE PREPARE tg_stmt2;

SELECT COUNT(*) INTO @tg_col3 FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'tg_verify_code';
SET @tg_sql3 = IF(@tg_col3 = 0, 'ALTER TABLE users ADD COLUMN tg_verify_code VARCHAR(8) DEFAULT NULL AFTER tg_link_expires, ADD COLUMN tg_verify_expires DATETIME DEFAULT NULL', 'SELECT 1');
PREPARE tg_stmt3 FROM @tg_sql3; EXECUTE tg_stmt3; DEALLOCATE PREPARE tg_stmt3;

SELECT COUNT(*) INTO @tg_col4 FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'telegram_phone';
SET @tg_sql4 = IF(@tg_col4 = 0, 'ALTER TABLE users ADD COLUMN telegram_phone VARCHAR(32) DEFAULT NULL AFTER telegram_chat_id, ADD COLUMN telegram_user_id VARCHAR(64) DEFAULT NULL AFTER telegram_phone', 'SELECT 1');
PREPARE tg_stmt4 FROM @tg_sql4; EXECUTE tg_stmt4; DEALLOCATE PREPARE tg_stmt4;

-- =====================================================
-- Login tasdiqlash (Telegram Ha/Yo'q tugmalari)
-- =====================================================
CREATE TABLE IF NOT EXISTS login_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    status ENUM('pending','approved','denied','expired') NOT NULL DEFAULT 'pending',
    ip_address VARCHAR(45) DEFAULT '',
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    decided_at DATETIME NULL,
    INDEX (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Telegram video turi (content + episodes), trailer_url, aktyorlar
-- =====================================================
SELECT COUNT(*) INTO @vt_col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'content' AND COLUMN_NAME = 'trailer_url';
SET @vt_sql = IF(@vt_col = 0, 'ALTER TABLE content ADD COLUMN trailer_url VARCHAR(500) DEFAULT NULL AFTER video_url', 'SELECT 1');
PREPARE vt_stmt FROM @vt_sql; EXECUTE vt_stmt; DEALLOCATE PREPARE vt_stmt;

SELECT COUNT(*) INTO @vt_enum FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'content' AND COLUMN_NAME = 'video_type'
  AND COLUMN_TYPE NOT LIKE '%telegram%';
SET @vt_enum_sql = IF(@vt_enum > 0, "ALTER TABLE content MODIFY COLUMN video_type ENUM('cloud','file','telegram','embed') DEFAULT 'cloud'", 'SELECT 1');
PREPARE vt_enum_stmt FROM @vt_enum_sql; EXECUTE vt_enum_stmt; DEALLOCATE PREPARE vt_enum_stmt;

SELECT COUNT(*) INTO @ep_enum FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'episodes' AND COLUMN_NAME = 'video_type'
  AND COLUMN_TYPE NOT LIKE '%telegram%';
SET @ep_enum_sql = IF(@ep_enum > 0, "ALTER TABLE episodes MODIFY COLUMN video_type ENUM('cloud','file','telegram','embed') NOT NULL DEFAULT 'cloud'", 'SELECT 1');
PREPARE ep_enum_stmt FROM @ep_enum_sql; EXECUTE ep_enum_stmt; DEALLOCATE PREPARE ep_enum_stmt;

CREATE TABLE IF NOT EXISTS content_actors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(255) DEFAULT NULL,
    image VARCHAR(500) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_content (content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Video manba URL o'zgarishlarini kuzatish
-- =====================================================
CREATE TABLE IF NOT EXISTS video_source_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NOT NULL DEFAULT 0,
    content_id INT NOT NULL DEFAULT 0,
    source_type VARCHAR(20) DEFAULT NULL,
    video_url VARCHAR(500) DEFAULT NULL,
    hls TEXT DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'unknown',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ep_content (episode_id, content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS video_source_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NOT NULL DEFAULT 0,
    content_id INT NOT NULL DEFAULT 0,
    source_type VARCHAR(20) DEFAULT NULL,
    video_url VARCHAR(500) DEFAULT NULL,
    event VARCHAR(20) NOT NULL,
    detail TEXT DEFAULT NULL,
    notified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY episode_id (episode_id),
    KEY content_id (content_id),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- related_content jadvali (watch.php uchun kerak)
-- =====================================================
CREATE TABLE IF NOT EXISTS related_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    related_id INT NOT NULL,
    type VARCHAR(50) DEFAULT 'similar',
    UNIQUE KEY uniq_rel (content_id, related_id),
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (related_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_content (content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- Profil muqova rasm (cover_photo)
-- =====================================================
SELECT COUNT(*) INTO @cp_col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'cover_photo';
SET @cp_sql = IF(@cp_col = 0, 'ALTER TABLE users ADD COLUMN cover_photo VARCHAR(255) DEFAULT NULL AFTER avatar', 'SELECT 1');
PREPARE cp_stmt FROM @cp_sql; EXECUTE cp_stmt; DEALLOCATE PREPARE cp_stmt;

-- =====================================================
-- 2026-08-13: Epizod darajasidagi premium qulf
-- =====================================================
ALTER TABLE episodes ADD COLUMN is_premium TINYINT(1) NOT NULL DEFAULT 0 AFTER intro_end;

-- =====================================================
-- "YANGI" / "YANGI QISM" belgisi — per-user holati
-- =====================================================
SET @columnname_new_since = 'new_since';
SET @preparedStatementNS = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = @columnname_new_since) > 0,
  'SELECT ''users.new_since ustuni allaqachon bor'';',
  'ALTER TABLE users ADD COLUMN new_since DATETIME DEFAULT NULL AFTER last_activity;'
));
PREPARE alterIfNotExistsNS FROM @preparedStatementNS;
EXECUTE alterIfNotExistsNS;
DEALLOCATE PREPARE alterIfNotExistsNS;

CREATE TABLE IF NOT EXISTS user_content_new_seen (
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, content_id),
    KEY content_id (content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SELECT 'Yangilash muvaffaqiyatli yakunlandi!' AS natija;

