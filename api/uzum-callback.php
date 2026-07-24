<?php
/* ============================================================
   api/uzum-callback.php
   Uzum (Payme) to'lov tizimidan kelgan callback-ni qabul qiladi
   va to'lov muvaffaqiyatli bo'lsa Premiumni avtomatik yoqadi.
   ============================================================ */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payment.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

// Method va transaction_id ni erta olish
$method = $data['method'] ?? '';
$params = $data['params'] ?? [];

if ($method !== '' && in_array($method, ['Payme','CheckPerformTransaction','CreateTransaction','PerformTransaction','CancelTransaction'])) {
    $account = $params['account'] ?? [];
    $transaction_id = $account['transaction_id'] ?? ($params['transaction_id'] ?? '');
    $amount = $params['amount'] ?? 0;
    $status = $method;
} else {
    $transaction_id = $data['transaction_id'] ?? ($params['transaction_id'] ?? '');
    $status = $data['status'] ?? ($params['status'] ?? '');
    $amount = $data['amount'] ?? 0;
}

// Log (sezgirsiz)
$log_file = __DIR__ . '/../logs/uzum_payments.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
$safe_log = json_encode([
    'time' => date('Y-m-d H:i:s'),
    'method' => $method ?: ($data['method'] ?? 'unknown'),
    'transaction_id' => $transaction_id,
    'status' => $status,
], JSON_UNESCAPED_UNICODE);
file_put_contents($log_file, $safe_log . "\n", FILE_APPEND);

// Oddiy format uchun — signature tekshirish
$has_payme_method = $method !== '' && in_array($method, ['Payme','CheckPerformTransaction','CreateTransaction','PerformTransaction','CancelTransaction']);
if (!$has_payme_method && UZUM_SECRET_KEY) {
    if (!uzum_verify_callback($data)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
}

// Transaction ID dan ma'lumotlarni olish
$parts = explode('_', $transaction_id);
if (count($parts) < 3) {
    http_response_code(200);
    echo json_encode(['error' => ['code' => -50, 'message' => 'Noto\'g\'ri transaction_id']]);
    exit;
}

$user_db_id = (int)$parts[0];
$plan_key = $parts[1] ?? '';
$custom_trans_id = $transaction_id;

$plans = PREMIUM_PLANS;
if (!isset($plans[$plan_key])) {
    http_response_code(200);
    echo json_encode(['error' => ['code' => -50, 'message' => 'Noto\'g\'ri tarif']]);
    exit;
}

$expected_amount = $plans[$plan_key]['price'];

// === Payme formatidagi so'rovlarni qayta ishlash ===
if ($method === 'CheckPerformTransaction') {
    echo json_encode(['result' => ['allowed' => true]]);
    exit;
}

if ($method === 'CreateTransaction') {
    $stmt = $pdo->prepare("SELECT id FROM premium_payments WHERE transaction_id = ?");
    $stmt->execute([$custom_trans_id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . $plans[$plan_key]['days'] . ' days'));
        $stmt = $pdo->prepare("INSERT INTO premium_payments (user_id, plan, amount, transaction_id, status, payment_system, expires_at) VALUES (?, ?, ?, ?, 'pending', 'uzum', ?)");
        $stmt->execute([$user_db_id, $plan_key, (int)$expected_amount, $custom_trans_id, $expires_at]);
    }

    echo json_encode([
        'result' => [
            'create_time' => time() * 1000,
            'transaction' => $custom_trans_id,
            'state' => 1,
        ]
    ]);
    exit;
}

if ($method === 'PerformTransaction') {
    $stmt = $pdo->prepare("SELECT * FROM premium_payments WHERE transaction_id = ? AND status='pending' AND payment_system='uzum'");
    $stmt->execute([$custom_trans_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        echo json_encode([
            'result' => [
                'transaction' => $custom_trans_id,
                'perform_time' => time() * 1000,
                'state' => 2,
            ]
        ]);
        exit;
    }

    $expires = activate_premium($pdo, (int)$payment['user_id'], $payment['plan'], (int)$payment['id']);

    if ($expires) {
        echo json_encode([
            'result' => [
                'transaction' => $custom_trans_id,
                'perform_time' => time() * 1000,
                'state' => 2,
            ]
        ]);
    } else {
        echo json_encode(['error' => ['code' => -50, 'message' => 'Activation failed']]);
    }
    exit;
}

if ($method === 'CancelTransaction') {
    $stmt = $pdo->prepare("UPDATE premium_payments SET status='rejected' WHERE transaction_id = ?");
    $stmt->execute([$custom_trans_id]);

    echo json_encode([
        'result' => [
            'transaction' => $custom_trans_id,
            'cancel_time' => time() * 1000,
            'state' => -1,
        ]
    ]);
    exit;
}

// Oddiy format
if ($status === 'completed' || $status === 'success') {
    $stmt = $pdo->prepare("SELECT * FROM premium_payments WHERE transaction_id = ? AND status='pending' AND payment_system='uzum'");
    $stmt->execute([$custom_trans_id]);
    $payment = $stmt->fetch();

    if ($payment) {
        $expires = activate_premium($pdo, (int)$payment['user_id'], $payment['plan'], (int)$payment['id']);
        if ($expires) {
            echo json_encode(['success' => true, 'expires' => $expires]);
        } else {
            echo json_encode(['error' => 'Activation failed']);
        }
    } else {
        echo json_encode(['error' => 'Transaction not found']);
    }
    exit;
}

echo json_encode(['error' => ['code' => -1, 'message' => 'Unknown method']]);
