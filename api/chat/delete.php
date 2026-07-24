<?php
/* ============================================================
   api/chat/delete.php
   Chatni o'chirish
   ============================================================ */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Kirish talab qilinadi']);
    exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'Xavfsizlik tokeni noto\'g\'ri']);
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
$sessionId = (int)($_POST['session_id'] ?? 0);

if (!$sessionId) {
    echo json_encode(['error' => 'Session ID talab qilinadi']);
    exit;
}

try {
    // Avval sessiya haqiqatan shu foydalanuvchiga tegishli ekanini tekshirish
    $own = $pdo->prepare("SELECT id FROM ai_chat_sessions WHERE id = ? AND user_id = ?");
    $own->execute([$sessionId, $userId]);
    if (!$own->fetch()) {
        echo json_encode(['error' => 'Chat topilmadi']);
        exit;
    }

    $pdo->prepare("DELETE FROM ai_chat_messages WHERE session_id = ?")->execute([$sessionId]);
    $stmt = $pdo->prepare("DELETE FROM ai_chat_sessions WHERE id = ? AND user_id = ?");
    $stmt->execute([$sessionId, $userId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Xatolik yuz berdi.']);
}
