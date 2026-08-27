<?php
session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) { echo json_encode(['ok' => false, 'msg' => 'Admin kirishi kerak']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!validate_csrf($input['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'msg' => 'CSRF xato']); exit;
}

$user_db_id = (int)($input['user_id'] ?? 0);
if (!$user_db_id) { echo json_encode(['ok' => false, 'msg' => 'Foydalanuvchi topilmadi']); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_db_id]);
$user = $stmt->fetch();
if (!$user) { echo json_encode(['ok' => false, 'msg' => 'Foydalanuvchi topilmadi']); exit; }

$ban = is_user_banned($pdo, $user_db_id);
if ($ban) { echo json_encode(['ok' => false, 'msg' => 'Foydalanuvchi bloklangan']); exit; }

$saved_admin_id = $_SESSION['admin_id'];
$saved_admin_username = $_SESSION['admin_username'] ?? 'admin';

$session_token = bin2hex(random_bytes(32));
$pdo->prepare("UPDATE users SET active_session_token = ?, last_login_at = NOW() WHERE id = ?")
    ->execute([$session_token, $user_db_id]);

unset($user['password']);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_data'] = $user;
$_SESSION['session_token'] = $session_token;
$_SESSION['impersonating'] = true;
$_SESSION['admin_id'] = $saved_admin_id;
$_SESSION['admin_username'] = $saved_admin_username;

$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$ip = client_ip();
$pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$user_db_id]);
$stmt_ins = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, user_agent, ip_address) VALUES (?, ?, ?, ?)");
$stmt_ins->execute([$user_db_id, session_id(), $ua, $ip]);

log_user_activity($pdo, $user_db_id, 'login', null, null, 'Admin tomonidan kirish');

echo json_encode([
    'ok' => true,
    'msg' => $user['username'] . ' hisobiga kirildi',
    'redirect' => ROOT_URL . '/index.php'
], JSON_UNESCAPED_UNICODE);
