<?php
session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Strict']);
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

// Video tugashiga 10 daqiqadan kam qolganda qatordan chiqadi va "Ko'rilgan" bo'limiga o'tadi.
// 10 daqiqadan qisqa videolar uchun bu qoida ishlamaydi (position>=manfiy har doim true bo'lardi).
$is_completed = $completed || ($duration > 600 && $position >= $duration - 600);

try {
    $pdo->prepare("INSERT INTO watch_progress (user_id, content_id, position_seconds, duration_seconds, is_completed)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE position_seconds=VALUES(position_seconds), duration_seconds=VALUES(duration_seconds), is_completed=GREATEST(is_completed, VALUES(is_completed))")
        ->execute([$_SESSION['user_id'], $content_id, $position, $duration, $is_completed ? 1 : 0]);

    // Tarixda barcha kirilgan kontentlar ko'rinishi uchun har safar yoziladi
    $pdo->prepare("INSERT INTO watch_history (user_id, content_id, progress_seconds) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE watched_at = CURRENT_TIMESTAMP, progress_seconds = VALUES(progress_seconds)")
        ->execute([$_SESSION['user_id'], $content_id, $position]);

    if ($is_completed) {
        mark_content_watched($pdo, $_SESSION['user_id'], $content_id);
    }
    echo json_encode(['ok'=>true]);
} catch (PDOException $e) {
    echo json_encode(['ok'=>false]);
}
