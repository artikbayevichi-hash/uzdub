<?php
session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!is_user()) { echo json_encode(['ok' => false, 'msg' => 'Kirish kerak']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!validate_csrf($input['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'msg' => 'CSRF xato']); exit;
}

$comment_id = (int)($input['comment_id'] ?? 0);
$type = $input['type'] ?? 'like';

if (!$comment_id || !in_array($type, ['like', 'dislike'])) {
    echo json_encode(['ok' => false, 'msg' => 'Noto\'g\'ri ma\'lumot']); exit;
}

$uid = $_SESSION['user_id'];

try {
    $check = $pdo->prepare("SELECT id, type FROM likes WHERE user_id = ? AND comment_id = ?");
    $check->execute([$uid, $comment_id]);
    $existing = $check->fetch();

    if ($existing) {
        if ($existing['type'] === $type) {
            $pdo->prepare("DELETE FROM likes WHERE id = ?")->execute([$existing['id']]);
            $action = 'removed';
        } else {
            $pdo->prepare("UPDATE likes SET type = ? WHERE id = ?")->execute([$type, $existing['id']]);
            $action = 'updated';
        }
    } else {
        $pdo->prepare("INSERT INTO likes (user_id, comment_id, type) VALUES (?, ?, ?)")
            ->execute([$uid, $comment_id, $type]);
        $action = 'added';
    }

    $likeCount = (int)$pdo->query("SELECT COUNT(*) FROM likes WHERE comment_id = $comment_id AND type = 'like'")->fetchColumn();
    $dislikeCount = (int)$pdo->query("SELECT COUNT(*) FROM likes WHERE comment_id = $comment_id AND type = 'dislike'")->fetchColumn();

    $userLikeStmt = $pdo->prepare("SELECT type FROM likes WHERE user_id = ? AND comment_id = ?");
    $userLikeStmt->execute([$uid, $comment_id]);
    $userLikeRow = $userLikeStmt->fetch();
    $userLike = $userLikeRow ? $userLikeRow['type'] : null;

    if ($action === 'added' && $uid) {
        $cmtStmt = $pdo->prepare("SELECT cc.user_id, cc.content_id FROM comments cc WHERE cc.id = ?");
        $cmtStmt->execute([$comment_id]);
        $cmtOwner = $cmtStmt->fetch();
        if ($cmtOwner && $cmtOwner['user_id'] != $uid) {
            $senderStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $senderStmt->execute([$uid]);
            $senderName = $senderStmt->fetchColumn();
            $label = $type === 'like' ? 'yoqdi' : 'yoqmadi';
            notify_send($pdo, $cmtOwner['user_id'], 'reaction',
                "$senderName sizning izohingizga $label",
                '',
                ROOT_URL . "/watch.php?id=" . $cmtOwner['content_id'] . "#comment-$comment_id",
                $uid
            );
        }
    }

    echo json_encode([
        'ok' => true,
        'action' => $action,
        'likes' => $likeCount,
        'dislikes' => $dislikeCount,
        'user_like' => $userLike,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => 'Xatolik']);
}
