<?php
/* ============================================================
   api/chat/list.php
   Foydalanuvchining chatlar ro'yxati
   ============================================================ */

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

try {
    $stmt = $pdo->prepare("
        SELECT cs.id, cs.title, cs.created_at, cs.updated_at,
               COUNT(cm.id) as message_count,
               MAX(cm.created_at) as last_message_at
        FROM ai_chat_sessions cs
        LEFT JOIN ai_chat_messages cm ON cm.session_id = cs.id
        WHERE cs.user_id = ?
        GROUP BY cs.id
        ORDER BY cs.updated_at DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'sessions' => $sessions]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Xatolik yuz berdi. Qaytadan urinib ko\'ring.']);
}
