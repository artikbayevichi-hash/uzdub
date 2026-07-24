-- UZDUB PLATFORM — To'liq ma'lumotlar bazasi sxemasi
CREATE DATABASE IF NOT EXISTS uzdub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uzdub;

-- =====================================================
-- ADMIN
-- =====================================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO admins (username, password) VALUES
('admin', '$2y$10$wgrUJG8raPpFoYXolfyWJu/fHvJ0O2ffwAzT2hIy2Hn5XI/8WGo2O')
ON DUPLICATE KEY UPDATE username=username;

-- =====================================================
-- CATEGORIES
-- =====================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE
);
INSERT INTO categories (name, slug) VALUES
('Kino', 'kino'),('Anime', 'anime'),('Multfilm', 'multfilm')
ON DUPLICATE KEY UPDATE name=name;

-- =====================================================
-- USERS
-- =====================================================
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
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- CONTENT
-- =====================================================
CREATE TABLE IF NOT EXISTS content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_code VARCHAR(10) UNIQUE,
    title VARCHAR(255) NOT NULL,
    title_ru VARCHAR(255) DEFAULT NULL,
    title_en VARCHAR(255) DEFAULT NULL,
    description TEXT,
    description_ru TEXT DEFAULT NULL,
    description_en TEXT DEFAULT NULL,
    poster VARCHAR(255) DEFAULT NULL,
    poster_thumb VARCHAR(255) DEFAULT NULL,
    category_id INT NOT NULL,
    release_year INT DEFAULT NULL,
    rating DECIMAL(3,1) DEFAULT 0,
    is_premium TINYINT(1) DEFAULT 0,
    video_type ENUM('youtube','cloud','file') DEFAULT NULL,
    video_url VARCHAR(500) DEFAULT NULL,
    studio VARCHAR(255) DEFAULT NULL,
    director VARCHAR(255) DEFAULT NULL,
    duration VARCHAR(50) DEFAULT NULL,
    status ENUM('ongoing','completed','upcoming') DEFAULT 'completed',
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_views (views)
);

-- =====================================================
-- GENRES
-- =====================================================
CREATE TABLE IF NOT EXISTS genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#7c4dff'
);

CREATE TABLE IF NOT EXISTS content_genres (
    content_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (content_id, genre_id),
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
);

INSERT IGNORE INTO genres (name, slug, color) VALUES
('Fantastika', 'fantastika', '#7c4dff'),
('Romantika', 'romantika', '#e91e63'),
('Drama', 'drama', '#ff5722'),
('Komediya', 'komediya', '#ff9800'),
('Triller', 'triller', '#f44336'),
('Psixologik', 'psixologik', '#9c27b0'),
('Isekai', 'isekai', '#00bcd4'),
('Sehrgar', 'sehrgar', '#673ab7'),
('Sarguzasht', 'sarguzasht', '#4caf50'),
('Sinchi', 'sinchi', '#607d8b'),
('Qo''rqinchli', 'qorqinchli', '#d32f2f'),
('Harbiy', 'harbiy', '#795548'),
('Hayotiy', 'hayotiy', '#8bc34a'),
('Tarixiy', 'tarixiy', '#afb42b'),
('Sport', 'sport', '#009688');

-- =====================================================
-- EPISODES
-- =====================================================
CREATE TABLE IF NOT EXISTS episodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    season INT DEFAULT 1,
    episode_number INT NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    thumbnail VARCHAR(255) DEFAULT NULL,
    video_type ENUM('youtube','cloud','file') NOT NULL,
    video_url VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_content (content_id)
);

-- =====================================================
-- WATCHLIST & PROGRESS
-- =====================================================
CREATE TABLE IF NOT EXISTS watchlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_watch (user_id, content_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_content (content_id)
);

CREATE TABLE IF NOT EXISTS watch_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    position_seconds INT DEFAULT 0,
    duration_seconds INT DEFAULT 0,
    is_completed TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_content (user_id, content_id),
    INDEX idx_user_updated (user_id, updated_at)
);

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

CREATE TABLE IF NOT EXISTS user_content_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    status ENUM('watching','planned','completed','paused','dropped','favorite') NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_content (user_id, content_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
);

-- =====================================================
-- RATINGS & COMMENTS
-- =====================================================
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

-- =====================================================
-- PROMO CODES
-- =====================================================
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

INSERT INTO promo_codes (code, discount_percent, free_days, max_uses, expires_at)
SELECT 'UZDUBPLATFORM2026', 0, 7, 100, '2027-12-31 23:59:59'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM promo_codes WHERE code='UZDUBPLATFORM2026');

-- =====================================================
-- SUBTITLES
-- =====================================================
CREATE TABLE IF NOT EXISTS content_subtitles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    episode_id INT DEFAULT NULL,
    language VARCHAR(10) NOT NULL DEFAULT 'uz',
    label VARCHAR(50) DEFAULT 'O''zbek',
    file_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_content (content_id)
);

-- =====================================================
-- PREMIUM PAYMENTS
-- =====================================================
CREATE TABLE IF NOT EXISTS premium_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan ENUM('1month','3month','1year') NOT NULL,
    amount INT NOT NULL,
    screenshot VARCHAR(255) DEFAULT NULL,
    transaction_id VARCHAR(64) DEFAULT NULL,
    payment_system ENUM('card','click','uzum') DEFAULT 'card',
    status ENUM('pending','approved','rejected') DEFAULT 'approved',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_transaction (transaction_id)
);

-- =====================================================
-- MESSAGES
-- =====================================================
CREATE TABLE IF NOT EXISTS global_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT,
    attachment VARCHAR(255) DEFAULT NULL,
    attachment_type ENUM('image','gif') DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
);

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
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_created (created_at)
);

-- =====================================================
-- AI CHAT
-- =====================================================
CREATE TABLE IF NOT EXISTS ai_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    role ENUM('user','assistant') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id)
);

CREATE TABLE IF NOT EXISTS ai_chat_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) DEFAULT 'Yangi chat',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_updated (user_id, updated_at)
);

CREATE TABLE IF NOT EXISTS ai_knowledge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    title VARCHAR(160) DEFAULT NULL,
    content TEXT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'approved',
    use_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- SECURITY & RATE LIMITING
-- =====================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(191) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_time (identifier, attempted_at)
);

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(191) NOT NULL,
    endpoint VARCHAR(64) NOT NULL,
    hit_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate (identifier, endpoint, hit_at)
);
