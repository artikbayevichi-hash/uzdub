<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_user()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/imgbb.php';

$file = $_FILES['cover_photo'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Fayl yuklanmadi']);
    exit;
}

require_once __DIR__ . '/../includes/imgbb.php';
$url = imgbb_upload_cover($file);
if (!$url) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Rasm yuklashda xatolik']);
    exit;
}

$uid = $_SESSION['user_id'];
$pdo->prepare("UPDATE users SET cover_photo = ? WHERE id = ?")->execute([$url, $uid]);

echo json_encode(['ok' => true, 'url' => $url]);
