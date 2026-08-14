<?php
session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$content_id = (int)($_GET['content_id'] ?? 0);
if (!$content_id) { echo json_encode(['comments' => [], 'total' => 0]); exit; }

$uid = is_user() ? $_SESSION['user_id'] : 0;

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE content_id = ?");
    $countStmt->execute([$content_id]);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT cc.*, u.username, u.avatar, u.user_id AS uid, u.is_premium,
            (SELECT COUNT(*) FROM likes WHERE comment_id = cc.id AND type = 'like') AS like_count,
            (SELECT COUNT(*) FROM likes WHERE comment_id = cc.id AND type = 'dislike') AS dislike_count
        FROM comments cc
        JOIN users u ON cc.user_id = u.id
        WHERE cc.content_id = ?
        ORDER BY cc.id ASC
    ");
    $stmt->execute([$content_id]);
    $rows = $stmt->fetchAll();

    $userLikes = [];
    if ($uid && $rows) {
        $ids = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $likeStmt = $pdo->prepare("SELECT comment_id, type FROM likes WHERE user_id = ? AND comment_id IN ($placeholders)");
        $likeParams = array_merge([$uid], $ids);
        $likeStmt->execute($likeParams);
        foreach ($likeStmt->fetchAll() as $l) {
            $userLikes[$l['comment_id']] = $l['type'];
        }
    }

    $byId = [];
    $topLevel = [];
    foreach ($rows as $row) {
        $row['avatar_url'] = avatar_url($row['avatar']);
        $row['time_ago'] = time_ago($row['created_at']);
        $row['user_like'] = $userLikes[$row['id']] ?? null;
        $row['replies'] = [];
        $byId[$row['id']] = $row;
        $pid = (int)$row['parent_id'];
        if ($pid > 0 && isset($byId[$pid])) {
            $byId[$pid]['replies'][] = &$byId[$row['id']];
        } else {
            $topLevel[] = &$byId[$row['id']];
        }
    }
    unset($row);

    usort($topLevel, function ($a, $b) { return strcmp($b['created_at'], $a['created_at']); });
    foreach ($byId as &$n) { usort($n['replies'], function ($a, $b) { return strcmp($a['created_at'], $b['created_at']); }); }
    unset($n);

    echo json_encode(['comments' => $topLevel, 'total' => $total], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['comments' => [], 'total' => 0]);
}
