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
$code = trim($input['code'] ?? '');
$csrf = $input['csrf_token'] ?? '';

if (!validate_csrf($csrf)) {
    $response['error'] = t('security_token_wrong');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['otp_code']) || empty($_SESSION['otp_pending'])) {
    $response['error'] = t('otp_expired');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if (time() > ($_SESSION['otp_expires'] ?? 0)) {
    unset($_SESSION['otp_code'], $_SESSION['otp_pending'], $_SESSION['otp_expires']);
    $response['error'] = t('otp_expired');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)$code !== (string)$_SESSION['otp_code']) {
    $response['error'] = t('otp_invalid');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$pending = $_SESSION['otp_pending'];
$uid = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, email, password FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

if (!$user) {
    $response['error'] = 'User not found';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===== Pre-verify: identity check only, set session flag ===== */
if ($pending['type'] === 'pre-verify-email') {
    unset($_SESSION['otp_code'], $_SESSION['otp_pending'], $_SESSION['otp_expires']);
    $_SESSION['settings_verified_for'] = 'email';
    $_SESSION['settings_verified_at'] = time();
    $response['ok'] = true;
    $response['message'] = t('otp_success');
    $response['success_type'] = 'pre-verify-email';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($pending['type'] === 'pre-verify-password') {
    unset($_SESSION['otp_code'], $_SESSION['otp_pending'], $_SESSION['otp_expires']);
    $_SESSION['settings_verified_for'] = 'password';
    $_SESSION['settings_verified_at'] = time();
    $response['ok'] = true;
    $response['message'] = t('otp_success');
    $response['success_type'] = 'pre-verify-password';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===== Legacy: direct email/password change (with current_password check) ===== */
if (!password_verify($pending['current_password'] ?? '', $user['password'])) {
    $response['error'] = t('otp_current_incorrect');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($pending['type'] === 'email') {
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$pending['new_email'], $uid]);
    if ($check->fetch()) {
        $response['error'] = t('otp_email_taken');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$pending['new_email'], $uid]);
    $response['ok'] = true;
    $response['message'] = t('otp_email_changed');
    $response['success_type'] = 'email';
    $response['new_value'] = $pending['new_email'];

} elseif ($pending['type'] === 'password') {
    $hashed = password_hash($pending['new_password'], PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $uid]);
    $response['ok'] = true;
    $response['message'] = t('otp_password_changed');
    $response['success_type'] = 'password';
}

refresh_user_session($pdo, $uid);

unset($_SESSION['otp_code'], $_SESSION['otp_pending'], $_SESSION['otp_expires']);

echo json_encode($response, JSON_UNESCAPED_UNICODE);
