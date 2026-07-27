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

if (!is_user()) {
    $response['error'] = t('security_token_wrong');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['error'] = 'Method not allowed';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$type = $input['type'] ?? '';
$csrf = $input['csrf_token'] ?? '';

if (!validate_csrf($csrf)) {
    $response['error'] = t('security_token_wrong');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$uid = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, email, username, password FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

if (!$user) {
    $response['error'] = 'User not found';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$pending = [];

if ($type === 'pre-verify-email' || $type === 'pre-verify-password') {
    $pending = ['type' => $type];
    $target_email = $user['email'];

} elseif ($type === 'email') {
    $new_email = trim($input['new_email'] ?? '');
    $current_password = $input['current_password'] ?? '';

    if (!$current_password || !password_verify($current_password, $user['password'])) {
        $response['error'] = t('otp_current_incorrect');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$new_email || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $response['error'] = 'Invalid email';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (strtolower($new_email) === strtolower($user['email'])) {
        $response['error'] = t('otp_no_changes');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$new_email, $uid]);
    if ($check->fetch()) {
        $response['error'] = t('otp_email_taken');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pending = ['type' => 'email', 'new_email' => $new_email, 'current_password' => $current_password];
    $target_email = $new_email;

} elseif ($type === 'password') {
    $current_password = $input['current_password'] ?? '';
    $new_password = $input['new_password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';

    if (!$current_password || !password_verify($current_password, $user['password'])) {
        $response['error'] = t('otp_current_incorrect');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$new_password) {
        $response['error'] = t('otp_no_changes');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($new_password !== $confirm_password) {
        $response['error'] = 'Passwords do not match';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (mb_strlen($new_password) < 6) {
        $response['error'] = 'Password too short';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pending = ['type' => 'password', 'new_password' => $new_password, 'current_password' => $current_password];
    $target_email = $user['email'];

} else {
    $response['error'] = 'Invalid type';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($target_email)) {
    $response['error'] = 'No email on file';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$code = rand(100000, 999999);

$_SESSION['otp_code'] = $code;
$_SESSION['otp_pending'] = $pending;
$_SESSION['otp_expires'] = time() + 300;
$_SESSION['otp_last_sent'] = time();

$subject = "UZDUB — Tasdiqlash kodi / Verification Code";
$message = "Tasdiqlash kodi: $code\n\nThis code expires in 5 minutes.\n\nUZDUB Platform";
$htmlMessage = "<div style='font-family:Arial,sans-serif;max-width:400px;margin:auto;padding:20px;background:#0b0f19;color:#e0e0e0;border-radius:12px;'><h2 style='color:#2196f3;'>UZDUB</h2><p>Tasdiqlash kodi:</p><div style='font-size:32px;font-weight:bold;letter-spacing:6px;color:#fff;background:#1a2332;padding:16px;border-radius:8px;text-align:center;'>$code</div><p style='font-size:12px;color:#8899aa;margin-top:16px;'>This code expires in 5 minutes.</p></div>";

send_email($target_email, $subject, $message, $htmlMessage);

$masked = substr($target_email, 0, 2) . str_repeat('*', max(0, strlen($target_email) - 6)) . substr($target_email, -4);

$response['ok'] = true;
$response['masked_email'] = $masked;
$response['message'] = sprintf(t('otp_sent_to'), $masked);

echo json_encode($response, JSON_UNESCAPED_UNICODE);
