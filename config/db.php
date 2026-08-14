<?php
require_once __DIR__ . '/env.php';

date_default_timezone_set('Asia/Tashkent');

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'uzdub'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// Sayt ildiz yo'li (root-relative). Localhost: /uzdub, hosting ildizida: '' (bo'sh)
// Masalan ROOT_URL.'/watch.php' -> /uzdub/watch.php yoki /watch.php
$root_url = rtrim((string)env('ROOT_URL', '/uzdub'), '/');
define('ROOT_URL', $root_url === '' ? '' : $root_url);

// To'liq sayt URL (Open Graph, PWA, webhook va h.k. uchun). Oxirgi '/'siz.
if (!defined('SITE_URL')) {
    define('SITE_URL', rtrim((string)env('SITE_URL', 'http://localhost/uzdub'), '/'));
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB ulanish xatosi: ' . $e->getMessage());
    die('Ma\'lumotlar bazasiga ulanishda xatolik yuz berdi. Iltimos, keyinroq qayta urinib ko\'ring.');
}
