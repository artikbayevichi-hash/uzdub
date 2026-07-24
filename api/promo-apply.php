<?php
session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_user();
if (!rate_limit_check($pdo, 'promo', 10, 60)) rate_limit_deny_json();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!validate_csrf($input['csrf_token'] ?? '')) {
    echo json_encode(['ok'=>false,'msg'=>'CSRF xato']); exit;
}

$result = redeem_promo_code($pdo, $input['code'] ?? '', $_SESSION['user_id']);
if ($result['ok']) {
    refresh_user_session($pdo, $_SESSION['user_id']);
}
echo json_encode($result, JSON_UNESCAPED_UNICODE);
