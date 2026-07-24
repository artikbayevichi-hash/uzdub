<?php
/* ============================================================
   api/chat/update_title.php
   Chat sarlavhasini yangilash
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
$title = trim($_POST['title'] ?? '');

if (!$sessionId || !$title) {
    echo json_encode(['error' => 'Ma\'lumotlar to\'liq emas']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE ai_chat_sessions SET title = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$title, $sessionId, $userId]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Xatolik yuz berdi. Qaytadan urinib ko\'ring.']);
}
