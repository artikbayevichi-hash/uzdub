<?php
session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!is_user()) { echo json_encode(['ok'=>false,'msg'=>'Kirish kerak']); exit; }
if (!rate_limit_check($pdo, 'comments', 15, 60)) rate_limit_deny_json();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!validate_csrf($input['csrf_token'] ?? '')) {
    echo json_encode(['ok'=>false,'msg'=>'CSRF xato']); exit;
}

$content_id = (int)($input['content_id'] ?? 0);
$comment = trim($input['comment'] ?? '');
if (!$content_id || $comment === '') {
    echo json_encode(['ok'=>false,'msg'=>'Izoh bo\'sh']); exit;
}
if (mb_strlen($comment) > 1000) {
    echo json_encode(['ok'=>false,'msg'=>'Izoh juda uzun']); exit;
}

try {
    $pdo->prepare("INSERT INTO content_comments (user_id, content_id, comment) VALUES (?,?,?)")
        ->execute([$_SESSION['user_id'], $content_id, $comment]);
    echo json_encode(['ok'=>true]);
} catch (PDOException $e) {
    echo json_encode(['ok'=>false,'msg'=>'Saqlanmadi']);
}
