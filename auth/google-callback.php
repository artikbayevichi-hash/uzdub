<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/functions.php';

$client_id     = env('GOOGLE_CLIENT_ID', '');
$client_secret = env('GOOGLE_CLIENT_SECRET', '');
$redirect_uri  = env('SITE_URL', 'http://localhost/uzdub') . '/auth/google-callback.php';

if (!$client_id || !$client_secret) {
    header('Location: ' . ROOT_URL . '/auth/login.php');
    exit;
}

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

if (!$code || !$state || !isset($_SESSION['google_state']) || $state !== $_SESSION['google_state']) {
    header('Location: ' . ROOT_URL . '/auth/login.php');
    exit;
}
unset($_SESSION['google_state']);

// Google API so'rovlari uchun timeout'li curl yordamchisi.
// (file_get_contents timeout'siz ishlaydi — tarmoq osilib qolsa sahifa
// abadiy yuklanadi, qora ekran + spinner ko'rinadi. Shuning uchun curl.)
function google_http($url, $post = null, $timeout = 15) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return null;
    if ($status < 200 || $status >= 300) return null;
    return $body;
}

$token_response = json_decode((string)(google_http(
    'https://oauth2.googleapis.com/token',
    http_build_query([
        'code'          => $code,
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri'  => $redirect_uri,
        'grant_type'    => 'authorization_code',
    ])
) ?? ''));

if (empty($token_response->id_token)) {
    header('Location: ' . ROOT_URL . '/auth/login.php?google=invalid');
    exit;
}

// id_token ni Google serverida tekshiramiz (imzo + muddat + email_verified)
$tokeninfo = google_http('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token_response->id_token));
$payload = $tokeninfo ? json_decode($tokeninfo) : null;

if (!$payload || empty($payload->sub) || empty($payload->email)) {
    header('Location: ' . ROOT_URL . '/auth/login.php?google=invalid');
    exit;
}

// Faqat Google tomonidan tasdiqlangan email akkauntlariga kirishga ruxsat beriladi
if (!isset($payload->email_verified) || $payload->email_verified !== 'true') {
    header('Location: ' . ROOT_URL . '/auth/login.php?google=unverified');
    exit;
}

// Token bizning OAuth appimizga berilgan bo'lishi kerak
if (!empty($payload->aud) && $payload->aud !== $client_id) {
    header('Location: ' . ROOT_URL . '/auth/login.php?google=invalid');
    exit;
}

$name = $payload->name ?? $payload->email;
$email = $payload->email;
$google_id = $payload->sub;

$user_db_id = find_or_create_google_user($pdo, $google_id, $email, $name);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_db_id]);
$user = $stmt->fetch();

// ===== 2FA himoyasi (normal parol kirishi bilan bir xil) =====
if ($user && !empty($user['two_factor_enabled'])) {
    require_once __DIR__ . '/../config/payment.php';

    $_SESSION['2fa_pending_id'] = $user['id'];
    $masked = substr($email, 0, 2) . str_repeat('*', max(0, strlen($email) - 6)) . substr($email, -4);
    $_SESSION['2fa_pending_email'] = $masked;

    $tg_chat = $user['telegram_chat_id'] ?? '';
    if ($tg_chat !== '') {
        // Telegram orqali kirishni tasdiqlash ("Ha, bu menman" tugmasi)
        $token = bin2hex(random_bytes(16));
        $ip = client_ip();
        $expires = date('Y-m-d H:i:s', time() + 180);
        $pdo->prepare("UPDATE login_approvals SET status = 'expired' WHERE user_id = ? AND status = 'pending'")->execute([$user['id']]);
        $ins = $pdo->prepare("INSERT INTO login_approvals (user_id, token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$user['id'], $token, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', $expires]);
        $_SESSION['login_approval_token'] = $token;
        $_SESSION['2fa_redirect'] = ROOT_URL . '/index.php';
        tg_2fa_send_approval($tg_chat, $token, $ip, date('H:i d.m.Y'));

        header('Location: ' . ROOT_URL . '/auth/login.php');
        exit;
    }

    // Telegram ulanmagan — TOTP kod fallback
    require_once __DIR__ . '/../includes/totp.php';
    if (!empty($user['two_factor_secret'])) {
        $_SESSION['2fa_totp_secret'] = $user['two_factor_secret'];
        $_SESSION['2fa_redirect'] = ROOT_URL . '/index.php';
        $_SESSION['2fa_show_totp'] = true;
        header('Location: ' . ROOT_URL . '/auth/login.php');
        exit;
    }

    // 2FA yoqilgan, lekin na Telegram, na TOTP sekreti bor — bloklash
    header('Location: ' . ROOT_URL . '/auth/login.php?google=invalid');
    exit;
}

$pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user_db_id]);
check_premium_expiry($pdo, $user_db_id);
refresh_user_session($pdo, $user_db_id);
session_regenerate_id(true);

header('Location: ' . ROOT_URL . '/auth/save-account.php');
exit;
