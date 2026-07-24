<?php
session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$content_id = (int)($_GET['content_id'] ?? 0);
if (!$content_id) { echo json_encode([]); exit; }

try {
    $stmt = $pdo->prepare("SELECT cc.*, u.username, u.avatar, u.user_id AS uid, u.is_premium
        FROM content_comments cc JOIN users u ON cc.user_id = u.id
        WHERE cc.content_id = ? ORDER BY cc.created_at DESC LIMIT 50");
    $stmt->execute([$content_id]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['avatar_url'] = avatar_url($r['avatar']);
        $r['time_ago'] = time_ago($r['created_at']);
    }
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode([]);
}
