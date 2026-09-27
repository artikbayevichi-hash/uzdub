<?php
/**
 * Reel'ga layk qo'yish / olib tashlash.
 * POST {reel_id, csrf_token}
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!is_user()) { echo json_encode(['ok' => false, 'msg' => t('login_required')], JSON_UNESCAPED_UNICODE); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!validate_csrf($input['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'msg' => t('security_token_wrong')], JSON_UNESCAPED_UNICODE);
    exit;
}

$reel_id = (int)($input['reel_id'] ?? 0);
if ($reel_id <= 0) { echo json_encode(['ok' => false, 'msg' => 'invalid_id']); exit; }

$uid = (int)$_SESSION['user_id'];

try {
    $chk = $pdo->prepare("SELECT 1 FROM reel_likes WHERE reel_id = ? AND user_id = ?");
    $chk->execute([$reel_id, $uid]);
    if ($chk->fetch()) {
        $pdo->prepare("DELETE FROM reel_likes WHERE reel_id = ? AND user_id = ?")->execute([$reel_id, $uid]);
        $liked = false;
    } else {
        $pdo->prepare("INSERT IGNORE INTO reel_likes (reel_id, user_id) VALUES (?,?)")->execute([$reel_id, $uid]);
        $liked = true;
    }
    $cnt = $pdo->prepare("SELECT COUNT(*) c FROM reel_likes WHERE reel_id = ?");
    $cnt->execute([$reel_id]);
    echo json_encode(['ok' => true, 'liked' => $liked, 'likes' => (int)$cnt->fetch()['c']]);
} catch (PDOException $e) {
    error_log('reel-like error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
