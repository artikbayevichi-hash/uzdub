<?php
/* ============================================================
   api/premium-expiry-check.php
   Premium muddati tugashini tekshirish va eslatma yuborish.
   
   CRON sozlamasi (cPanel yoki server crontab):
   Har kuni soat 9:00 da ishlash uchun:
   0 9 * * * php /path/to/uzdub/api/premium-expiry-check.php
   
   Yoki web cron xizmati orqali:
   wget -q -O /dev/null https://sizning.saytingiz/uzdub/api/premium-expiry-check.php?key=YOUR_SECRET_KEY
   ============================================================ */

// Xavfsizlik: faqat to'g'ri kalit bilan yoki CLI orqali ishlaydi
$secret_key = env('CRON_SECRET_KEY', '');
if ($secret_key === '') {
    die('Xatolik: CRON_SECRET_KEY .env faylida o\'rnatilmagan.');
}
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    $request_key = $_GET['key'] ?? '';
    if ($request_key !== $secret_key) {
        http_response_code(403);
        die('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payment.php';

echo "=== Premium muddat tekshiruvi ===\n";
echo "Vaqt: " . date('Y-m-d H:i:s') . "\n\n";

$notified_count = 0;
$expired_count = 0;

// 1) Muddati tugaganlarni bekor qilish
$stmt = $pdo->query("SELECT id, username, user_id, premium_expires_at FROM users WHERE is_premium=1 AND premium_expires_at IS NOT NULL AND premium_expires_at < NOW()");
$expired = $stmt->fetchAll();

foreach ($expired as $user) {
    $pdo->prepare("UPDATE users SET is_premium=0, premium_expires_at=NULL WHERE id=?")
        ->execute([$user['id']]);

    tg_send_message("⏰ <b>Premium tugadi!</b>\n"
        . "👤 Foydalanuvchi: <b>" . e($user['username']) . "</b> (ID: " . $user['user_id'] . ")\n"
        . "📅 Tugagan vaqt: " . date('d.m.Y H:i', strtotime($user['premium_expires_at'])) . "\n"
        . "Premium muddati tugadi va bekor qilindi.");

    $expired_count++;
    echo "  [BEKOR] {$user['username']} (ID: {$user['user_id']}) — muddati tugagan\n";
}

// 2) 3 kun qolganlarga eslatma
$stmt = $pdo->query("SELECT id, username, user_id, premium_expires_at FROM users WHERE is_premium=1 AND premium_expires_at IS NOT NULL AND premium_expires_at > NOW() AND premium_expires_at < (NOW() + INTERVAL 3 DAY)");
$expiring_soon = $stmt->fetchAll();

foreach ($expiring_soon as $user) {
    $days_left = ceil((strtotime($user['premium_expires_at']) - time()) / 86400);
    $expire_date = date('d.m.Y', strtotime($user['premium_expires_at']));

    tg_send_message("⚠️ <b>Premium tugashiga {$days_left} kun qoldi!</b>\n"
        . "👤 Foydalanuvchi: <b>" . e($user['username']) . "</b> (ID: " . $user['user_id'] . ")\n"
        . "📅 Tugash sanasi: <b>{$expire_date}</b>\n\n"
        . "Uzatish uchun: /uzdub/premium.php\n"
        . "Admin panel: /uzdub/admin/users.php");

    $notified_count++;
    echo "  [ESLATMA] {$user['username']} (ID: {$user['user_id']}) — {$days_left} kun qoldi\n";
}

echo "\n=== Yakun ===\n";
echo "Tugaganlar: {$expired_count}\n";
echo "Eslatma yuborilganlar: {$notified_count}\n";
