<?php
/* ============================================================
   api/click-callback.php
   Click to'lov tizimidan kelgan callback-ni qabul qiladi
   va to'lov muvaffaqiyatli bo'lsa Premiumni avtomatik yoqadi.
   
   Click ushbu URLga POST so'rov yuboradi:
   https://sizning.saytingiz/uzdub/api/click-callback.php
   
   Click sozlamalarida (https://my.click.uz/services)-> URL ni kiriting:
   https://sizning.saytingiz/uzdub/api/click-callback.php
   ============================================================ */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payment.php';

header('Content-Type: application/json; charset=utf-8');

// Click yuborgan ma'lumotlarni olish
$data = $_POST;

// So'rovni logga yozish (debug uchun)
$log_file = __DIR__ . '/../logs/click_payments.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
file_put_contents($log_file, date('[Y-m-d H:i:s] ') . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

// Click har doim quyidagi maydonlarni yuboradi:
// click_trans_id, merchant_trans_id, service_id, amount, action, sign_time, sign_string, merchant_prepare_id
$click_trans_id   = (int)($data['click_trans_id'] ?? 0);
$merchant_trans_id = $data['merchant_trans_id'] ?? '';  // Bizning transaction_id
$service_id       = (int)($data['service_id'] ?? 0);
$amount           = (float)($data['amount'] ?? 0);
$action           = (int)($data['action'] ?? 0);
$sign_time        = $data['sign_time'] ?? '';
$sign_string      = $data['sign_string'] ?? '';

// 1) Action tekshirish
// Click: action=0 — prepare (buyurtma yaratish), action=1 — complete (to'lov tasdiqlash)
if ($action !== 0 && $action !== 1) {
    http_response_code(400);
    echo json_encode(['error' => -1, 'error_note' => 'Noto\'g\'ri action']);
    exit;
}

// 2) Imzoni tekshirish
$expected_sign = md5($click_trans_id . $merchant_trans_id . $service_id . CLICK_SECRET_KEY . $amount . $action . $sign_time);
if (!hash_equals($expected_sign, $sign_string)) {
    http_response_code(400);
    echo json_encode(['error' => -1, 'error_note' => 'Sign noto\'g\'ri']);
    exit;
}

// 3) merchant_trans_id dan ma'lumotlarni olish
// Format: USER_ID_PLAN_KEY_TRANSACTION_ID
$parts = explode('_', $merchant_trans_id);
if (count($parts) < 3) {
    echo json_encode(['error' => -5, 'error_note' => 'Noto\'g\'ri merchant_trans_id']);
    exit;
}

$user_db_id = (int)$parts[0];
$plan_key = $parts[1] ?? '';
$custom_trans_id = $parts[2] ?? '';

$plans = PREMIUM_PLANS;
if (!isset($plans[$plan_key])) {
    echo json_encode(['error' => -5, 'error_note' => 'Noto\'g\'ri tarif']);
    exit;
}

$expected_amount = $plans[$plan_key]['price'];

// 4) Summani tekshirish (Click tiyinga aylantirib yuboradi, masalan 10000 so'm = 1000000 tiyin)
$actual_amount = $amount;
$expected_amount_tiyin = $expected_amount; // Click so'mda ham ishlaydi

if ($action === 0) {
    // PREPARE — buyurtma yaratish, faqat to'lov mavjudligini tekshiramiz
    // Oldin to'lanmaganligini tekshirish
    $stmt = $pdo->prepare("SELECT id FROM premium_payments WHERE transaction_id = ? AND status='approved'");
    $stmt->execute([$custom_trans_id]);
    if ($stmt->fetch()) {
        // Bu tranzaksiya allaqachon tasdiqlangan
        echo json_encode([
            'error' => 0,
            'error_note' => 'Success',
            'click_trans_id' => $click_trans_id,
            'merchant_trans_id' => $merchant_trans_id,
            'merchant_prepare_id' => $custom_trans_id,
        ]);
        exit;
    }

    // Yangi to'lov yozuvini yaratish
    $stmt = $pdo->prepare("SELECT id FROM premium_payments WHERE transaction_id = ?");
    $stmt->execute([$custom_trans_id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . $plans[$plan_key]['days'] . ' days'));
        $stmt = $pdo->prepare("INSERT INTO premium_payments (user_id, plan, amount, transaction_id, status, payment_system, expires_at) VALUES (?, ?, ?, ?, 'pending', 'click', ?)");
        $stmt->execute([$user_db_id, $plan_key, (int)$expected_amount, $custom_trans_id, $expires_at]);
    }

    // Click dan kelgan javob
    echo json_encode([
        'error' => 0,
        'error_note' => 'Success',
        'click_trans_id' => $click_trans_id,
        'merchant_trans_id' => $merchant_trans_id,
        'merchant_prepare_id' => $custom_trans_id,
    ]);

} elseif ($action === 1) {
    // COMPLETE — to'lov tasdiqlangan
    // Click faqat prepare muvaffaqiyatli bo'lgandan keyin complete yuboradi

    // To'lovni topish
    $stmt = $pdo->prepare("SELECT * FROM premium_payments WHERE transaction_id = ? AND status='pending' AND payment_system='click'");
    $stmt->execute([$custom_trans_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        // Allaqachon tasdiqlangan bo'lishi mumkin
        $stmt = $pdo->prepare("SELECT * FROM premium_payments WHERE transaction_id = ? AND status='approved'");
        $stmt->execute([$custom_trans_id]);
        $payment = $stmt->fetch();

        if ($payment) {
            echo json_encode([
                'error' => 0,
                'error_note' => 'Success',
                'click_trans_id' => $click_trans_id,
                'merchant_trans_id' => $merchant_trans_id,
                'merchant_prepare_id' => $custom_trans_id,
            ]);
            exit;
        }

        echo json_encode(['error' => -5, 'error_note' => 'Transaction not found']);
        exit;
    }

    // Premiumni faollashtirish
    $expires = activate_premium($pdo, (int)$payment['user_id'], $payment['plan'], (int)$payment['id']);

    if ($expires) {
        echo json_encode([
            'error' => 0,
            'error_note' => 'Success',
            'click_trans_id' => $click_trans_id,
            'merchant_trans_id' => $merchant_trans_id,
            'merchant_prepare_id' => (int)$payment['id'],
        ]);
    } else {
        echo json_encode(['error' => -5, 'error_note' => 'Activation failed']);
    }
}
