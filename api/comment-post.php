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
$parent_id = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;

if (!$content_id || $comment === '') {
    echo json_encode(['ok'=>false,'msg'=>'Izoh bo\'sh']); exit;
}
if (mb_strlen($comment) > 1000) {
    echo json_encode(['ok'=>false,'msg'=>'Izoh juda uzun']); exit;
}

try {
    if ($parent_id) {
        $chk = $pdo->prepare("SELECT id FROM comments WHERE id = ? AND content_id = ?");
        $chk->execute([$parent_id, $content_id]);
        if (!$chk->fetch()) $parent_id = null;
    }

    $pdo->prepare("INSERT INTO comments (user_id, content_id, parent_id, comment) VALUES (?,?,?,?)")
        ->execute([$_SESSION['user_id'], $content_id, $parent_id, $comment]);

    $new_id = $pdo->lastInsertId();

    if ($parent_id) {
        $parentStmt = $pdo->prepare("SELECT cc.user_id FROM comments cc WHERE cc.id = ?");
        $parentStmt->execute([$parent_id]);
        $parentComment = $parentStmt->fetch();
        if ($parentComment && $parentComment['user_id'] != $_SESSION['user_id']) {
            $senderStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $senderStmt->execute([$_SESSION['user_id']]);
            $senderName = $senderStmt->fetchColumn();
            $contentStmt = $pdo->prepare("SELECT title FROM content WHERE id = ?");
            $contentStmt->execute([$content_id]);
            $contentTitle = $contentStmt->fetchColumn();
            notify_send($pdo, $parentComment['user_id'], 'comment_reply',
                "$senderName sizning izohingizga javob yozdi",
                mb_substr($comment, 0, 100),
                ROOT_URL . "/watch.php?id=$content_id#comment-$parent_id",
                $_SESSION['user_id']
            );
        }
    }

    $stmt = $pdo->prepare("
        SELECT cc.*, u.username, u.avatar, u.user_id AS uid, u.is_premium
        FROM comments cc JOIN users u ON cc.user_id = u.id WHERE cc.id = ?
    ");
    $stmt->execute([$new_id]);
    $row = $stmt->fetch();
    $row['avatar_url'] = avatar_url($row['avatar']);
    $row['time_ago'] = time_ago($row['created_at']);
    $row['like_count'] = 0;
    $row['dislike_count'] = 0;
    $row['user_like'] = null;
    $row['replies'] = [];

    echo json_encode(['ok'=>true, 'comment'=>$row], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['ok'=>false,'msg'=>'Saqlanmadi']);
}
