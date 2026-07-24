<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Referrer-Policy: no-referrer');

$uid  = $_GET['uid']  ?? '';
$token = $_GET['token'] ?? '';

if (!$uid || !$token) {
    header('Location: /uzdub/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND switch_token = ?");
$stmt->execute([$uid, $token]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: /uzdub/index.php');
    exit;
}

$last_login = $user['last_login_at'];
$needs_verify = false;
if (!$last_login) {
    $needs_verify = true;
} else {
    $diff = time() - strtotime($last_login);
    if ($diff > 7 * 24 * 60 * 60) {
        $needs_verify = true;
    }
}

if ($needs_verify) {
    $_SESSION['verify_switch_uid'] = $uid;
    $_SESSION['verify_switch_token'] = $token;
    $_SESSION['verify_switch_username'] = $user['username'];
    header('Location: /uzdub/auth/verify-switch.php');
    exit;
}

check_premium_expiry($pdo, $user['id']);
$pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
refresh_user_session($pdo, $user['id']);
session_regenerate_id(true);

header('Location: /uzdub/index.php');
exit;
