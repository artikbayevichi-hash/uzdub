<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$csrf = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
$content_id = (int)($input['content_id'] ?? $_POST['content_id'] ?? 0);

if (!is_user()) { echo json_encode(['ok' => false, 'msg' => 'Login required']); exit; }
if (!validate_csrf($csrf)) { echo json_encode(['ok' => false, 'msg' => 'Invalid token']); exit; }
if ($content_id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Invalid content']); exit; }

$uid = $_SESSION['user_id'];

$chk = $pdo->prepare("SELECT id FROM user_content_status WHERE user_id=? AND content_id=? AND status='favorite'");
$chk->execute([$uid, $content_id]);
$row = $chk->fetch();

if ($row) {
    $pdo->prepare("DELETE FROM user_content_status WHERE id=?")->execute([$row['id']]);
    $pdo->prepare("DELETE FROM watchlist WHERE user_id=? AND content_id=?")->execute([$uid, $content_id]);
    log_user_activity($pdo, $uid, 'watchlist_remove', 'content', $content_id);
    echo json_encode(['ok' => true, 'added' => false]);
} else {
    $pdo->prepare("INSERT INTO user_content_status (user_id, content_id, status) VALUES (?,?,'favorite')")->execute([$uid, $content_id]);
    $pdo->prepare("INSERT IGNORE INTO watchlist (user_id, content_id) VALUES (?,?)")->execute([$uid, $content_id]);
    log_user_activity($pdo, $uid, 'watchlist_add', 'content', $content_id);
    echo json_encode(['ok' => true, 'added' => true]);
}
