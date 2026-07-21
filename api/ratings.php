<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_user()) { echo json_encode(['ok'=>false,'msg'=>'Kirish kerak']); exit; }
if (!rate_limit_check($pdo, 'ratings', 20, 60)) rate_limit_deny_json();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!validate_csrf($input['csrf_token'] ?? '')) {
    echo json_encode(['ok'=>false,'msg'=>'CSRF xato']); exit;
}

$content_id = (int)($input['content_id'] ?? 0);
$rating = (int)($input['rating'] ?? 0);
if (!$content_id || $rating < 1 || $rating > 10) {
    echo json_encode(['ok'=>false,'msg'=>'Noto\'g\'ri ma\'lumot']); exit;
}

try {
    $pdo->prepare("INSERT INTO content_ratings (user_id, content_id, rating) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE rating=VALUES(rating), updated_at=CURRENT_TIMESTAMP")
        ->execute([$_SESSION['user_id'], $content_id, $rating]);
    $avg = get_avg_user_rating($pdo, $content_id);
    echo json_encode(['ok'=>true,'avg'=>$avg,'your'=>$rating]);
} catch (PDOException $e) {
    echo json_encode(['ok'=>false,'msg'=>'Reyting saqlanmadi']);
}
