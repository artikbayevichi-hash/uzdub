<?php
/**
 * Login tasdiqlash (Telegram Ha/Yo'q tugmalari) uchun API.
 *
 * action=status    — so'rov holatini qaytaradi (pending/approved/denied/expired)
 * action=finalize  — tasdiqlangan bo'lsa, kirishni yakunlab qayta yo'naltiradi
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/payment.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Lax'
    ]);
    session_start();
}

$action = $_GET['action'] ?? '';
$token = $_SESSION['login_approval_token'] ?? '';

if ($token === '' || !isset($_SESSION['2fa_pending_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'So\'rov mavjud emas. Qayta kirishga urinib ko\'ring.']);
    exit;
}

$stmt = $pdo->prepare("SELECT status, expires_at, user_id FROM login_approvals WHERE token = ? LIMIT 1");
$stmt->execute([$token]);
$appr = $stmt->fetch();

if (!$appr || (int)$appr['user_id'] !== (int)$_SESSION['2fa_pending_id']) {
    unset($_SESSION['login_approval_token'], $_SESSION['2fa_pending_id'], $_SESSION['2fa_pending_email'], $_SESSION['2fa_redirect'], $_SESSION['2fa_show_totp']);
    echo json_encode(['ok' => false, 'status' => 'expired', 'error' => 'So\'rov topilmadi. Qayta kirishga urinib ko\'ring.']);
    exit;
}

if ($appr['status'] === 'pending' && strtotime($appr['expires_at']) < time()) {
    $pdo->prepare("UPDATE login_approvals SET status = 'expired' WHERE token = ?")->execute([$token]);
    $appr['status'] = 'expired';
}

if ($action === 'status') {
    echo json_encode(['ok' => true, 'status' => $appr['status']]);
    exit;
}

if ($action === 'finalize') {
    if ($appr['status'] !== 'approved') {
        header('Location: ' . ROOT_URL . '/auth/login.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['2fa_pending_id']]);
    $user = $stmt->fetch();

    if (!$user || !$user['two_factor_enabled']) {
        header('Location: ' . ROOT_URL . '/auth/login.php');
        exit;
    }

    $redirect = $_SESSION['2fa_redirect'] ?? ROOT_URL . '/index.php';
    unset($_SESSION['2fa_pending_id'], $_SESSION['login_approval_token'], $_SESSION['2fa_pending_email'], $_SESSION['2fa_redirect'], $_SESSION['2fa_show_totp']);

    login_clear_attempts($pdo, 'user:' . client_ip() . ':' . mb_strtolower($user['username']));
    $pdo->prepare("UPDATE users SET new_since = last_login_at, last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
    check_premium_expiry($pdo, $user['id']);
    refresh_user_session($pdo, $user['id']);
    session_regenerate_id(true);
    record_user_session($pdo, $user['id']);
    $_SESSION['login_redirect'] = $redirect;

    header('Location: ' . ROOT_URL . '/auth/save-account.php');
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Noto\'g\'ri so\'rov']);
