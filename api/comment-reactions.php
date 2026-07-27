<?php
session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$ids = $_GET['ids'] ?? '';
$idArr = array_filter(array_map('intval', explode(',', $ids)), function($v) { return $v > 0; });
if (empty($idArr)) { echo json_encode(['updates' => []]); exit; }

$uid = is_user() ? $_SESSION['user_id'] : 0;

$placeholders = implode(',', array_fill(0, count($idArr), '?'));
$stmt = $pdo->prepare("SELECT id FROM comments WHERE id IN ($placeholders)");
$stmt->execute($idArr);
$validIds = array_column($stmt->fetchAll(), 'id');

if (empty($validIds)) { echo json_encode(['updates' => []]); exit; }

$validPlaceholders = implode(',', array_fill(0, count($validIds), '?'));

$counts = $pdo->prepare("
    SELECT comment_id,
        SUM(type = 'like') AS likes,
        SUM(type = 'dislike') AS dislikes
    FROM likes WHERE comment_id IN ($validPlaceholders) GROUP BY comment_id
");
$counts->execute($validIds);
$countsMap = [];
foreach ($counts->fetchAll() as $row) {
    $countsMap[$row['comment_id']] = ['likes' => (int)$row['likes'], 'dislikes' => (int)$row['dislikes']];
}

$userLikes = [];
if ($uid) {
    $ulStmt = $pdo->prepare("SELECT comment_id, type FROM likes WHERE user_id = ? AND comment_id IN ($validPlaceholders)");
    $ulParams = array_merge([$uid], $validIds);
    $ulStmt->execute($ulParams);
    foreach ($ulStmt->fetchAll() as $ul) {
        $userLikes[$ul['comment_id']] = $ul['type'];
    }
}

$updates = [];
foreach ($validIds as $cid) {
    $updates[] = [
        'comment_id' => $cid,
        'likes' => $countsMap[$cid]['likes'] ?? 0,
        'dislikes' => $countsMap[$cid]['dislikes'] ?? 0,
        'user_like' => $userLikes[$cid] ?? null,
    ];
}

echo json_encode(['updates' => $updates], JSON_UNESCAPED_UNICODE);
