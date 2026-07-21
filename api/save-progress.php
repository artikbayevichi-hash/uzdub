<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_user()) { echo json_encode(['ok'=>false,'msg'=>'Kirish kerak']); exit; }
if (!rate_limit_check($pdo, 'save-progress', 120, 60)) rate_limit_deny_json();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!validate_csrf($input['csrf_token'] ?? '')) {
    echo json_encode(['ok'=>false,'msg'=>'CSRF xato']); exit;
}

$content_id = (int)($input['content_id'] ?? 0);
$position = max(0, (int)($input['position'] ?? 0));
$duration = max(0, (int)($input['duration'] ?? 0));
$completed = !empty($input['completed']);

if (!$content_id) { echo json_encode(['ok'=>false]); exit; }

$is_completed = $completed || ($duration > 0 && $position >= $duration - 10);

try {
    $pdo->prepare("INSERT INTO watch_progress (user_id, content_id, position_seconds, duration_seconds, is_completed)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE position_seconds=VALUES(position_seconds), duration_seconds=VALUES(duration_seconds), is_completed=GREATEST(is_completed, VALUES(is_completed))")
        ->execute([$_SESSION['user_id'], $content_id, $position, $duration, $is_completed ? 1 : 0]);

    if ($is_completed) {
        mark_content_watched($pdo, $_SESSION['user_id'], $content_id, isset($input['episode_id']) ? (int)$input['episode_id'] : null);
    }
    echo json_encode(['ok'=>true]);
} catch (PDOException $e) {
    echo json_encode(['ok'=>false]);
}
