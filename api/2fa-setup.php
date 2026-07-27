<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/totp.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!is_user()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uid = $_SESSION['user_id'];

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT email, two_factor_enabled FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();

    echo json_encode([
        'ok' => true,
        'enabled' => (bool)$user['two_factor_enabled'],
        'email' => $user['email']
    ], JSON_UNESCAPED_UNICODE);
    exit;

} elseif ($method === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'error' => t('security_token_wrong')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $stmt = $pdo->prepare("SELECT email, two_factor_enabled, password FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if ($user['two_factor_enabled']) {
            echo json_encode(['ok' => false, 'error' => '2FA allaqachon yoqilgan'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $secret = TOTP::generateSecret();
        $uri = TOTP::getProvisioningUri($secret, $user['email']);
        $qr_url = TOTP::getQRCodeUrl($uri);

        $_SESSION['pending_2fa_secret'] = $secret;
        $_SESSION['pending_2fa_time'] = time();

        echo json_encode([
            'ok' => true,
            'secret' => $secret,
            'qr_url' => $qr_url,
            'uri' => $uri,
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } elseif ($action === 'enable') {
        $code = trim($_POST['code'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $secret = $_SESSION['pending_2fa_secret'] ?? '';

        if (!$secret || (time() - ($_SESSION['pending_2fa_time'] ?? 0)) > 600) {
            echo json_encode(['ok' => false, 'error' => 'Sessiya muddati tugadi. Qayta urinib ko\'ring.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!password_verify($current_password, $user['password'])) {
            echo json_encode(['ok' => false, 'error' => t('otp_current_incorrect')], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (strlen($code) !== 6 || !ctype_digit($code)) {
            echo json_encode(['ok' => false, 'error' => '6 xonali kod kiriting'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!TOTP::verifyCode($secret, $code)) {
            echo json_encode(['ok' => false, 'error' => 'Noto\'g\'ri kod. Qayta urinib ko\'ring.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $pdo->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?")->execute([$secret, $uid]);
        unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_time']);

        refresh_user_session($pdo, $uid);
        echo json_encode(['ok' => true, 'message' => '2FA muvaffaqiyatli yoqildi!'], JSON_UNESCAPED_UNICODE);
        exit;

    } elseif ($action === 'disable') {
        $current_password = $_POST['current_password'] ?? '';
        $code = trim($_POST['code'] ?? '');

        $stmt = $pdo->prepare("SELECT password, two_factor_secret FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!password_verify($current_password, $user['password'])) {
            echo json_encode(['ok' => false, 'error' => t('otp_current_incorrect')], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (strlen($code) !== 6 || !ctype_digit($code)) {
            echo json_encode(['ok' => false, 'error' => '6 xonali kod kiriting'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!TOTP::verifyCode($user['two_factor_secret'], $code)) {
            echo json_encode(['ok' => false, 'error' => 'Noto\'g\'ri kod. Qayta urinib ko\'ring.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $pdo->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?")->execute([$uid]);
        refresh_user_session($pdo, $uid);

        echo json_encode(['ok' => true, 'message' => '2FA o\'chirildi'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
