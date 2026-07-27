<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/lang.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Lax'
    ]);
    session_start();
}

$response = ['ok' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';
$csrf = $input['csrf_token'] ?? '';

if (!validate_csrf($csrf)) {
    $response['error'] = t('security_token_wrong');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// === STEP 1: Send OTP to email ===
if ($action === 'send_code') {
    $email = trim($input['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['error'] = 'Email noto\'g\'ri';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $response['ok'] = true;
        $response['message'] = 'Agar bu email ro\'yxatdan o\'tgan bo\'lsa, tasdiqlash kodi yuborildi.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $code = rand(100000, 999999);
    $_SESSION['fp_code'] = $code;
    $_SESSION['fp_user_id'] = $user['id'];
    $_SESSION['fp_expires'] = time() + 300;
    $_SESSION['fp_last_sent'] = time();

    $subject = "UZDUB — Parolni tiklash kodi / Password Reset Code";
    $message = "Tasdiqlash kodi: $code\n\nBu kod 5 daqiqa amal qiladi.\n\nUZDUB Platform";
    $htmlMessage = "<div style='font-family:Arial,sans-serif;max-width:400px;margin:auto;padding:20px;background:#0b0f19;color:#e0e0e0;border-radius:12px;'><h2 style='color:#2196f3;'>UZDUB</h2><p>Parolni tiklash kodi:</p><div style='font-size:32px;font-weight:bold;letter-spacing:6px;color:#fff;background:#1a2332;padding:16px;border-radius:8px;text-align:center;'>$code</div><p style='font-size:12px;color:#8899aa;margin-top:16px;'>Bu kod 5 daqiqa amal qiladi.</p></div>";
    send_email($email, $subject, $message, $htmlMessage);

    $masked = substr($email, 0, 2) . str_repeat('*', max(0, strlen($email) - 6)) . substr($email, -4);

    $response['ok'] = true;
    $response['masked_email'] = $masked;
    $response['message'] = sprintf(t('otp_sent_to'), $masked);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// === STEP 2: Verify OTP ===
if ($action === 'verify_code') {
    $code = trim($input['code'] ?? '');

    if (empty($_SESSION['fp_code']) || empty($_SESSION['fp_user_id'])) {
        $response['error'] = t('otp_expired');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (time() > ($_SESSION['fp_expires'] ?? 0)) {
        unset($_SESSION['fp_code'], $_SESSION['fp_user_id'], $_SESSION['fp_expires']);
        $response['error'] = t('otp_expired');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((string)$code !== (string)$_SESSION['fp_code']) {
        $response['error'] = t('otp_invalid');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['fp_verified'] = true;
    unset($_SESSION['fp_code']);

    $response['ok'] = true;
    $response['message'] = 'Kod tasdiqlandi. Yangi parol kiriting.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// === STEP 3: Set new password ===
if ($action === 'reset_password') {
    $new_pass = $input['new_password'] ?? '';
    $conf_pass = $input['confirm_password'] ?? '';

    if (empty($_SESSION['fp_verified']) || empty($_SESSION['fp_user_id'])) {
        $response['error'] = 'Avval tasdiqlash kodini kiriting.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$new_pass) {
        $response['error'] = 'Yangi parol kiriting';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($new_pass !== $conf_pass) {
        $response['error'] = 'Parollar mos kelmaydi';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (mb_strlen($new_pass) < 6) {
        $response['error'] = 'Parol kamida 6 ta belgi bo\'lishi kerak';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $uid = $_SESSION['fp_user_id'];
    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $uid]);

    unset($_SESSION['fp_code'], $_SESSION['fp_user_id'], $_SESSION['fp_expires'], $_SESSION['fp_verified']);

    $response['ok'] = true;
    $response['message'] = 'Parol muvaffaqiyatli yangilandi! Yangi parol bilan kiring.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
