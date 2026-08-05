<?php
/* ============================================================
   api/uzum-callback.php
   Uzum (Payme) to'lov tizimidan kelgan callback-ni qabul qiladi
   va to'lov muvaffaqiyatli bo'lsa Premiumni avtomatik yoqadi.

   XAVFSIZLIK:
   - Payme formatidagi barcha so'rovlar imzo bilan tekshiriladi:
     base64(sha1(method . json_encode(params, JSON_UNESCAPED_UNICODE) . UZUM_SECRET_KEY))
   - Oddiy Uzum formatidagi so'rovlar ham imzo bilan tekshiriladi.
   - UZUM_SECRET_KEY sozlanmagan bo'lsa, barcha so'rovlar rad etiladi.
   ============================================================ */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payment.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$payme_methods = ['CheckPerformTransaction', 'CreateTransaction', 'PerformTransaction', 'CancelTransaction'];
$has_payme_method = is_array($data) && isset($data['method']) && in_array($data['method'], $payme_methods, true);

if (!$has_payme_method) {
    $data = is_array($data) ? $data : ($_POST ?: []);
}

$method = $data['method'] ?? '';
$params = is_array($data['params'] ?? null) ? $data['params'] : [];
$sign = $data['sign'] ?? '';
$account = is_array($params['account'] ?? null) ? $params['account'] : [];

$transaction_id = $account['transaction_id'] ?? ($params['transaction_id'] ?? ($data['transaction_id'] ?? ''));
$amount = $params['amount'] ?? ($data['amount'] ?? 0);
$status = $method;

// --- Log (sezgirsiz) ---
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

// --- Imzo tekshirish (majburiy) ---
if (!UZUM_SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Payment not configured']);
    exit;
}

if ($has_payme_method) {
    // Payme: base64(sha1(method . json_encode(params) . key))
    $expected = base64_encode(sha1($method . json_encode($params, JSON_UNESCAPED_UNICODE) . UZUM_SECRET_KEY, true));
    if (!is_string($sign) || !hash_equals($expected, $sign)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
} else {
    // Oddiy Uzum formati
    if (!uzum_verify_callback($data)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
}

// --- Transaction ID parse: USER_ID_PLAN_KEY_TRANSACTION_ID ---
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

$expected_amount = (int)$plans[$plan_key]['price'];

// Payme formatida summa tiyinda keladi (so'm * 100)
if ($has_payme_method && $amount !== '' && $amount !== null && (int)$amount !== $expected_amount * 100) {
    http_response_code(200);
    echo json_encode(['error' => ['code' => -50, 'message' => 'Noto\'g\'ri summa']]);
    exit;
}

// === Payme format ===
if ($method === 'CheckPerformTransaction') {
    echo json_encode(['result' => ['allowed' => true]]);
    exit;
}

if ($method === 'CreateTransaction') {
    $stmt = $pdo->prepare("SELECT id, status FROM premium_payments WHERE transaction_id = ? AND user_id = ?");
    $stmt->execute([$custom_trans_id, $user_db_id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . $plans[$plan_key]['days'] . ' days'));
        $stmt = $pdo->prepare("INSERT INTO premium_payments (user_id, plan, amount, transaction_id, status, payment_system, expires_at) VALUES (?, ?, ?, ?, 'pending', 'uzum', ?)");
        $stmt->execute([$user_db_id, $plan_key, $expected_amount, $custom_trans_id, $expires_at]);
    } elseif ($existing['status'] === 'approved') {
        // Allaqachon tasdiqlangan — yangi transaksiya ochmaslik
        echo json_encode(['error' => ['code' => -50, 'message' => 'Allaqachon to\'langan']]);
        exit;
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
    $stmt = $pdo->prepare("SELECT * FROM premium_payments WHERE transaction_id = ? AND user_id = ? AND status='pending' AND payment_system='uzum'");
    $stmt->execute([$custom_trans_id, $user_db_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        $stmt = $pdo->prepare("SELECT * FROM premium_payments WHERE transaction_id = ? AND user_id = ? AND status='approved'");
        $stmt->execute([$custom_trans_id, $user_db_id]);
        $approved = $stmt->fetch();
        if ($approved) {
            echo json_encode([
                'result' => [
                    'transaction' => $custom_trans_id,
                    'perform_time' => time() * 1000,
                    'state' => 2,
                ]
            ]);
            exit;
        }
        echo json_encode(['error' => ['code' => -50, 'message' => 'Transaksiya topilmadi']]);
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
    $stmt = $pdo->prepare("UPDATE premium_payments SET status='rejected' WHERE transaction_id = ? AND user_id = ?");
    $stmt->execute([$custom_trans_id, $user_db_id]);

    echo json_encode([
        'result' => [
            'transaction' => $custom_trans_id,
            'cancel_time' => time() * 1000,
            'state' => -1,
        ]
    ]);
    exit;
}

// === Oddiy Uzum format ===
if ($status === 'completed' || $status === 'success') {
    $stmt = $pdo->prepare("SELECT * FROM premium_payments WHERE transaction_id = ? AND user_id = ? AND status='pending' AND payment_system='uzum'");
    $stmt->execute([$custom_trans_id, $user_db_id]);
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
