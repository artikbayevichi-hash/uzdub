<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Session cookie qabul qilish (heartbeat uchun)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Lax'
    ]);
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];
$response = ['ok' => false];

// POST — heartbeat (last_activity yangilash + online_time qo'shish)
if ($method === 'POST' && is_user()) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $elapsed = max(0, min(120, (int)($input['elapsed'] ?? 0))); // sekundlarda, max 120

    $uid = $_SESSION['user_id'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW(), online_time = online_time + ? WHERE id = ?");
        $stmt->execute([$elapsed, $uid]);
        touch_user_session($pdo);
        $response['ok'] = true;
    } catch (PDOException $e) {
        // Agar online_time ustuni yo'q bo'lsa — xatosiz davom et
        try {
            $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
            $stmt->execute([$uid]);
            $response['ok'] = true;
        } catch (PDOException $e2) {}
    }
}

// Online foydalanuvchilar sonini olish (har qanday method)
try {
    $onl = $pdo->query("SELECT COUNT(*) FROM users WHERE last_activity >= NOW() - INTERVAL 5 MINUTE")->fetchColumn();
    $response['online_count'] = (int)$onl;
} catch (PDOException $e) {
    $response['online_count'] = 0;
}

// Joriy foydalanuvchining umumiy online vaqti (soat:dqiqa:soniya formatida)
$response['online_time'] = 0;
if (is_user()) {
    try {
        $stmt = $pdo->prepare("SELECT online_time FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $response['online_time'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {}
}

$response['ok'] = true;
echo json_encode($response, JSON_UNESCAPED_UNICODE);
