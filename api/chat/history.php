<?php
/* ============================================================
   api/chat/history.php
   Chat tarixini olish
   ============================================================ */

session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Kirish talab qilinadi']);
    exit;
}

$userId = $_SESSION['user_id'];

// Premium tekshirish
$chk = $pdo->prepare("SELECT is_premium FROM users WHERE id = ?");
$chk->execute([$userId]);
if (!$chk->fetchColumn()) {
    echo json_encode(['error' => 'AI chat faqat Premium foydalanuvchilar uchun.']);
    exit;
}
$sessionId = (int)($_GET['session_id'] ?? 0);

if (!$sessionId) {
    echo json_encode(['error' => 'Session ID talab qilinadi']);
    exit;
}

try {
    // Session tegishli ekanligini tekshirish
    $stmt = $pdo->prepare("SELECT id FROM ai_chat_sessions WHERE id = ? AND user_id = ?");
    $stmt->execute([$sessionId, $userId]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Chat topilmadi']);
        exit;
    }

    // Xabarlarni olish
    $stmt = $pdo->prepare("
        SELECT id, role, message, created_at
        FROM ai_chat_messages
        WHERE session_id = ?
        ORDER BY created_at ASC
        LIMIT 100
    ");
    $stmt->execute([$sessionId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Xatolik yuz berdi. Qaytadan urinib ko\'ring.']);
}
