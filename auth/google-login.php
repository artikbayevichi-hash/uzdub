<?php
require_once __DIR__ . '/../config/env.php';

$client_id     = env('GOOGLE_CLIENT_ID', '');
$client_secret = env('GOOGLE_CLIENT_SECRET', '');
$redirect_uri  = env('SITE_URL', 'http://localhost/uzdub') . '/auth/google-callback.php';

if (!$client_id || !$client_secret) {
    header('Location: /uzdub/auth/login.php');
    exit;
}

session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();
$_SESSION['google_state'] = bin2hex(random_bytes(16));

$params = http_build_query([
    'client_id'     => $client_id,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $_SESSION['google_state'],
    'prompt'        => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
