<?php
/* ============================================================
   api/chat/create.php
   Yangi chat yaratish
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
$title = trim($_POST['title'] ?? 'Yangi chat');
$firstMessage = trim($_POST['first_message'] ?? '');

try {
    $stmt = $pdo->prepare("INSERT INTO ai_chat_sessions (user_id, title) VALUES (?, ?)");
    $stmt->execute([$userId, $title]);
    $sessionId = (int)$pdo->lastInsertId();

    echo json_encode(['success' => true, 'session_id' => $sessionId, 'title' => $title]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Xatolik: ' . $e->getMessage()]);
}
