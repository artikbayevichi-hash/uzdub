<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/functions.php';

$client_id     = env('GOOGLE_CLIENT_ID', '');
$client_secret = env('GOOGLE_CLIENT_SECRET', '');
$redirect_uri  = env('SITE_URL', 'http://localhost/uzdub') . '/auth/google-callback.php';

if (!$client_id || !$client_secret) {
    header('Location: /uzdub/auth/login.php');
    exit;
}

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

if (!$code || !$state || !isset($_SESSION['google_state']) || $state !== $_SESSION['google_state']) {
    header('Location: /uzdub/auth/login.php');
    exit;
}
unset($_SESSION['google_state']);

$token_response = json_decode(file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query([
            'code'          => $code,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ]),
    ],
])));

if (empty($token_response->id_token)) {
    header('Location: /uzdub/auth/login.php');
    exit;
}

$payload = json_decode(base64_decode(explode('.', $token_response->id_token)[1]));
if (empty($payload->sub) || empty($payload->email)) {
    header('Location: /uzdub/auth/login.php');
    exit;
}

$name = $payload->name ?? $payload->email;
$email = $payload->email;
$google_id = $payload->sub;

$user_db_id = find_or_create_google_user($pdo, $google_id, $email, $name);
$pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user_db_id]);
check_premium_expiry($pdo, $user_db_id);
refresh_user_session($pdo, $user_db_id);
session_regenerate_id(true);

$token = generate_switch_token($pdo, $user_db_id);
$_SESSION['switch_token'] = $token;
$_SESSION['switch_user_id'] = current_user()['user_id'];

header('Location: /uzdub/auth/save-account.php');
exit;
