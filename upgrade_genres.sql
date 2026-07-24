-- ANIHUB O'XSHASH FUNKSIYALAR UCHUN YANGILASH
-- phpMyAdmin -> UZDUB bazasini tanlang -> SQL bo'limi -> shu faylni joylashtiring -> Bajarish

USE uzdub;

-- Janrlar jadvali
CREATE TABLE IF NOT EXISTS genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#7c4dff'
);

-- Kontent va janrlar o'rtasidagi bog'lanish (ko'pdan-ko'p)
CREATE TABLE IF NOT EXISTS content_genres (
    content_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (content_id, genre_id),
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
);

-- Kontent jadvaliga qo'shimcha maydonlar
SET @dbname = DATABASE();
SET @tablename = 'content';

SET @col = 'studio';
SET @stmt = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @dbname AND table_name = @tablename AND column_name = @col) > 0,
  'SELECT ''studio ustuni allaqachon bor'';',
  'ALTER TABLE content ADD COLUMN studio VARCHAR(255) DEFAULT NULL AFTER rating;'
));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = 'director';
SET @stmt = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @dbname AND table_name = @tablename AND column_name = @col) > 0,
  'SELECT ''director ustuni allaqachon bor'';',
  'ALTER TABLE content ADD COLUMN director VARCHAR(255) DEFAULT NULL AFTER studio;'
));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = 'duration';
SET @stmt = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @dbname AND table_name = @tablename AND column_name = @col) > 0,
  'SELECT ''duration ustuni allaqachon bor'';',
  'ALTER TABLE content ADD COLUMN duration VARCHAR(50) DEFAULT NULL AFTER director;'
));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = 'status';
SET @stmt = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @dbname AND table_name = @tablename AND column_name = @col) > 0,
  'SELECT ''status ustuni allaqachon bor'';',
  "ALTER TABLE content ADD COLUMN status ENUM('ongoing','completed','upcoming') DEFAULT 'completed' AFTER duration;"
));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = 'poster_thumb';
SET @stmt = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @dbname AND table_name = @tablename AND column_name = @col) > 0,
  'SELECT ''poster_thumb ustuni allaqachon bor'';',
  'ALTER TABLE content ADD COLUMN poster_thumb VARCHAR(255) DEFAULT NULL AFTER poster;'
));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- Epizodlar jadvaliga thumbnail qo'shish
SET @tablename = 'episodes';
SET @col = 'thumbnail';
SET @stmt = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @dbname AND table_name = @tablename AND column_name = @col) > 0,
  'SELECT ''thumbnail ustuni allaqachon bor'';',
  'ALTER TABLE episodes ADD COLUMN thumbnail VARCHAR(255) DEFAULT NULL AFTER title;'
));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- Standart janrlarni qo'shish
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
('Qo\'rqinchli', 'qorqinchli', '#d32f2f'),
('Harbiy', 'harbiy', '#795548'),
('Hayotiy', 'hayotiy', '#8bc34a'),
('Tarixiy', 'tarixiy', '#afb42b'),
('Sport', 'sport', '#009688');

SELECT 'Janrlar va yangi maydonlar muvaffaqiyatli qo''shildi!' AS natija;
