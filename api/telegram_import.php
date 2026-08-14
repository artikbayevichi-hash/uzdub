<?php
/**
 * Telegram bot orqali video yuborilganda content jadvaliga avtomatik qo'shish.
 * Faqat maxfiy kalit (TELEGRAM_IMPORT_KEY) bilan chaqiriladi.
 *
 * POST:
 *   key        - TELEGRAM_IMPORT_KEY (bot .env'dagi bilan bir xil)
 *   title      - kontent nomi (majburiy)
 *   category   - kino | anime | multfilm (default: kino)
 *   video_url  - bot qaytargan dl havolasi (majburiy)
 *   status     - completed | ongoing | upcoming (default: completed)
 *
 * Javob: { "ok": true, "content_id": 12, "content_code": "KN0007" }
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function import_error($msg) {
    echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    import_error('POST talab qilinadi');
}

$key = trim($_POST['key'] ?? '');
$expected = env('TELEGRAM_IMPORT_KEY', '');
if ($expected === '' || $key === '' || !hash_equals($expected, $key)) {
    import_error('Ruxsat yo\'q');
}

$title = trim($_POST['title'] ?? '');
$category = strtolower(trim($_POST['category'] ?? 'kino'));
$video_url = trim($_POST['video_url'] ?? '');
$status = strtolower(trim($_POST['status'] ?? 'completed'));

if ($title === '') import_error('Kontent nomi kiritilmagan');
if ($video_url === '') import_error('Video havolasi kiritilmagan');
if (!in_array($category, ['kino', 'anime', 'multfilm'], true)) $category = 'kino';
$allowed_statuses = ['completed', 'ongoing', 'upcoming'];
if (!in_array($status, $allowed_statuses, true)) $status = 'completed';

$cat_stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
$cat_stmt->execute([$category]);
$category_id = (int)$cat_stmt->fetchColumn();
if (!$category_id) import_error('Kategoriya topilmadi');

$content_code = generate_content_code($pdo, $category);
$video_type = video_type_for_url($video_url);

$stmt = $pdo->prepare(
    "INSERT INTO content (content_code, title, category_id, video_type, video_url, status, views, is_premium)
     VALUES (?, ?, ?, ?, ?, ?, 0, 0)"
);
$stmt->execute([$content_code, $title, $category_id, $video_type, $video_url, $status]);

echo json_encode([
    'ok' => true,
    'content_id' => (int)$pdo->lastInsertId(),
    'content_code' => $content_code,
    'category' => $category,
    'video_type' => $video_type,
    'video_url' => $video_url,
], JSON_UNESCAPED_UNICODE);
