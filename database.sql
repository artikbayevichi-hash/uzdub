-- UZDUB PLATFORM.UZ ma'lumotlar bazasi (TO'LIQ YANGILANGAN)
CREATE DATABASE IF NOT EXISTS uzdub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uzdub;

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO admins (username, password) VALUES
('doniyorbek0998', '$2y$10$wgrUJG8raPpFoYXolfyWJu/fHvJ0O2ffwAzT2hIy2Hn5XI/8WGo2O')
ON DUPLICATE KEY UPDATE username=username;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE
);
INSERT INTO categories (name, slug) VALUES
('Kino', 'kino'),('Anime', 'anime'),('Multfilm', 'multfilm')
ON DUPLICATE KEY UPDATE name=name;

CREATE TABLE IF NOT EXISTS content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_code VARCHAR(10) UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    poster VARCHAR(255) DEFAULT NULL,
    category_id INT NOT NULL,
    release_year INT DEFAULT NULL,
    rating DECIMAL(3,1) DEFAULT 0,
    is_premium TINYINT(1) DEFAULT 0,
    video_type ENUM('youtube','cloud','file') DEFAULT NULL,
    video_url VARCHAR(500) DEFAULT NULL,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_views (views)
);

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

-- AI chat: foydalanuvchilar suhbat tarixi (Premium foydalanuvchilar uchun saqlanadi)
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

-- AI chat: foydalanuvchilar "eslab qol" orqali o'rgatgan umumiy bilim (hammaga baham)
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

CREATE TABLE IF NOT EXISTS episodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    season INT DEFAULT 1,
    episode_number INT NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    video_type ENUM('youtube','cloud','file') NOT NULL,
    video_url VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_content (content_id)
);

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

CREATE TABLE IF NOT EXISTS premium_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan ENUM('1month','3month','1year') NOT NULL,
    amount INT NOT NULL,
    screenshot VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'approved',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

CREATE TABLE IF NOT EXISTS global_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT,
    attachment VARCHAR(255) DEFAULT NULL,   -- premium foydalanuvchi yuborgan rasm/gif fayli
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

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(191) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_time (identifier, attempted_at)
);

CREATE TABLE IF NOT EXISTS watch_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    position_seconds INT DEFAULT 0,
    duration_seconds INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_content (user_id, content_id),
    INDEX idx_user_updated (user_id, updated_at)
);

-- Admin login ma'lumotlari admins jadvalida yuqorida ('doniyorbek0998' / '12341234d', bcrypt bilan xeshlangan) o'rnatilgan.