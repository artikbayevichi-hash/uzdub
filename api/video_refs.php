<?php
/**
 * Bot uchun: saytdagi content/episodes jadvallarida ishlatilgan /dl/{id}/ havolalarini qaytaradi.
 * Bot eski videolarni o'chirganda saytdagi kontent buzilmasligi uchun ishlatadi.
 *
 * GET: api/video_refs.php?key=TELEGRAM_IMPORT_KEY
 * Javob: { "ok": true, "refs": [123, 456, ...] }
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$key = trim($_GET['key'] ?? '');
$expected = env('TELEGRAM_IMPORT_KEY', '');
if ($expected === '' || $key === '' || !hash_equals($expected, $key)) {
    echo json_encode(['ok' => false, 'msg' => 'Ruxsat yo\'q'], JSON_UNESCAPED_UNICODE);
    exit;
}

$refs = [];
$rows = $pdo->query(
    "SELECT video_url, NULL, NULL FROM content
     UNION ALL
     SELECT video_url, video_url_1080p, video_url_720p FROM episodes"
)->fetchAll();
foreach ($rows as $r) {
    foreach (['video_url', 'video_url_1080p', 'video_url_720p'] as $col) {
        if (preg_match('~/(dl)/(\d+)/~', (string)($r[$col] ?? ''), $m)) {
            $refs[(int)$m[2]] = (int)$m[2];
        }
    }
}

echo json_encode(['ok' => true, 'refs' => array_values($refs)], JSON_UNESCAPED_UNICODE);
