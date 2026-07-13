<?php
header('Content-Type: text/plain');
session_start();
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";

if (!isset($_SESSION['user_id'])) {
    echo "ERROR: Not logged in\n";
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->prepare("SELECT id, title FROM ai_chat_sessions WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Sessions found: " . count($sessions) . "\n";
    foreach ($sessions as $s) {
        echo "  - {$s['id']}: {$s['title']}\n";
    }
} catch (PDOException $e) {
    echo "DB ERROR: " . $e->getMessage() . "\n";
}
