<?php
/* ============================================================
   api/click-redirect.php
   Foydalanuvchini Click to'lov sahifasiga yo'naltirish
   ============================================================ */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_user()) {
    header('Location: /uzdub/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['user_id'];
$plan_key = $_GET['plan'] ?? '';
$plans = PREMIUM_PLANS;

if (!isset($plans[$plan_key])) {
    header('Location: /uzdub/premium.php?error=invalid_plan');
    exit;
}

// Tranzaksiya ID yaratish: USER_ID_PLAN_KEY_UNIQUE
$transaction_id = $userId . '_' . $plan_key . '_' . generate_transaction_id();

// To'lov URL yaratish
$pay_url = click_generate_url($plan_key, $userId, $transaction_id);

if (!$pay_url) {
    header('Location: /uzdub/premium.php?error=click_not_configured');
    exit;
}

// Tranzaksiyani sessiyada saqlash (callback kelganda ishlatish uchun emas, faqat kuzatish uchun)
$_SESSION['click_transaction'] = $transaction_id;

// Click to'lov sahifasiga yo'naltirish
header('Location: ' . $pay_url);
exit;
