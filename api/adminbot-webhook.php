<?php
/**
 * UZDUB admin bоt — webhook (HTTP) varianti.
 *
 * Public URL bo'lsa ishlatiladi:
 *   https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://SITE/api/adminbot-webhook.php
 *
 * Localhost (XAMPP) uchun api/adminbot-poll.php dan foydalaning.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/adminbot-handler.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

ab_ensure_tables($pdo);

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);
if (!$update) {
    echo json_encode(['ok' => false]);
    exit;
}

ab_handle_update($pdo, $update);

echo json_encode(['ok' => true]);
