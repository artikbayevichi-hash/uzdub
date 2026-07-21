-- ============================================================
-- UZDUB — Xavfsizlik va yangi funksiyalar uchun upgrade skripti
-- Agar sayt allaqachon o'rnatilgan bo'lsa (database.sql avval import
-- qilingan bo'lsa), ushbu faylni phpMyAdmin orqali ishga tushiring.
-- Yangi o'rnatishda bunga hojat yo'q — database.sql allaqachon yangilangan.
-- ============================================================

USE uzdub;

-- 1) Admin login/parolni yangilash: doniyorbek0998 / 12341234d
--    (parol bcrypt bilan xeshlangan, oddiy matn emas)
UPDATE admins
SET username = 'doniyorbek0998',
    password = '$2y$10$wgrUJG8raPpFoYXolfyWJu/fHvJ0O2ffwAzT2hIy2Hn5XI/8WGo2O'
WHERE id = 1;

-- Agar yuqoridagi UPDATE hech narsani o'zgartirmasa (masalan boshqa id bo'lsa),
-- avval joriy admin qatorlarini ko'ring: SELECT * FROM admins;
-- va WHERE shartini to'g'ri id ga moslashtiring.

-- 2) Brute-force himoyasi uchun jadval
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(191) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_time (identifier, attempted_at)
);

-- 3) "Davom eting" (continue watching) funksiyasi uchun jadval
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

-- 4) "Serial" bo'limidan qolgan o'lik ustunni tozalash (agar mavjud bo'lsa)
--    Eski MySQL versiyalarida IF EXISTS qo'llab-quvvatlanmasa, xato chiqsa
--    shunchaki bu qatorni o'chirib tashlang — ustun bo'lmasa muammo emas.
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'content' AND COLUMN_NAME = 'is_series'
);
SET @sql := IF(@col_exists > 0, 'ALTER TABLE content DROP COLUMN is_series', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
