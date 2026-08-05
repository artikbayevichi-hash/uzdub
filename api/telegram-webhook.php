<?php
/**
 * Telegram 2FA bot webhook (HTTP).
 *
 * Telegram'da sozlash (public URL kerak, localhost uchun emas):
 *   https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://SITE/uzdub/api/telegram-webhook.php
 *
 * Localhost (XAMPP) uchun api/telegram-poll.php dan foydalaning.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/telegram-handler.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);
if (!$update || empty($update['message'])) {
    echo json_encode(['ok' => true]);
    exit;
}

tg_2fa_handle_update($pdo, $update);

echo json_encode(['ok' => true]);
