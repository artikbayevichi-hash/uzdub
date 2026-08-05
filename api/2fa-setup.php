<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../config/payment.php';

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
    $stmt = $pdo->prepare("SELECT email, two_factor_enabled, telegram_chat_id, telegram_phone FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();

    echo json_encode([
        'ok' => true,
        'enabled' => (bool)$user['two_factor_enabled'],
        'email' => $user['email'],
        'telegram_linked' => !empty($user['telegram_chat_id']),
        'telegram_phone' => $user['telegram_phone'],
        'masked_phone' => mask_phone($user['telegram_phone']),
    ], JSON_UNESCAPED_UNICODE);
    exit;

} elseif ($method === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'error' => t('security_token_wrong')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $stmt = $pdo->prepare("SELECT two_factor_enabled, telegram_chat_id FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if ($user['two_factor_enabled']) {
            echo json_encode(['ok' => false, 'error' => '2FA allaqachon yoqilgan'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!TG_2FA_BOT_TOKEN) {
            echo json_encode(['ok' => false, 'error' => 'Telegram 2FA bot sozlanmagan. Admin bilan bog\'laning.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $link_code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $pdo->prepare("UPDATE users SET tg_link_code = ?, tg_link_expires = DATE_ADD(NOW(), INTERVAL 5 MINUTE), tg_verify_code = NULL, tg_verify_expires = NULL WHERE id = ?")
            ->execute([$link_code, $uid]);

        $linked = !empty($user['telegram_chat_id']);
        $verify_pending = false;
        if ($linked) {
            // Telegram allaqachon bog'langan — darhol yangi tasdiqlash kodini yuboramiz
            $new_code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE users SET tg_verify_code = ?, tg_verify_expires = DATE_ADD(NOW(), INTERVAL 3 MINUTE) WHERE id = ?")
                ->execute([$new_code, $uid]);
            tg_2fa_send_code($user['telegram_chat_id'], $new_code);
            $verify_pending = true;
        }

        echo json_encode([
            'ok' => true,
            'link_code' => $link_code,
            'bot_username' => TG_2FA_BOT_USERNAME,
            'telegram_linked' => $linked,
            'verify_pending' => $verify_pending,
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } elseif ($action === 'check_link') {
        $stmt = $pdo->prepare("SELECT telegram_chat_id, telegram_phone, tg_verify_code, tg_verify_expires FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        $linked = !empty($user['telegram_chat_id']);
        $verify_pending = !empty($user['tg_verify_code']) && (!empty($user['tg_verify_expires']) && strtotime($user['tg_verify_expires']) > time());

        // Bog'langan + telefon ulangan, lekin kod yo'q yoki eskirgan bo'lsa — yangi kod yuboramiz
        if ($linked && !$verify_pending && !empty($user['telegram_phone'])) {
            $new_code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE users SET tg_verify_code = ?, tg_verify_expires = DATE_ADD(NOW(), INTERVAL 3 MINUTE) WHERE id = ?")
                ->execute([$new_code, $uid]);
            tg_2fa_send_code($user['telegram_chat_id'], $new_code);
            $verify_pending = true;
        }

        echo json_encode([
            'ok' => true,
            'telegram_linked' => $linked,
            'telegram_phone' => $user['telegram_phone'],
            'masked_phone' => mask_phone($user['telegram_phone']),
            'verify_pending' => $verify_pending,
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } elseif ($action === 'enable') {
        $code = trim($_POST['code'] ?? '');
        $current_password = $_POST['current_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password, telegram_chat_id, tg_verify_code, tg_verify_expires FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (empty($user['telegram_chat_id'])) {
            echo json_encode(['ok' => false, 'error' => 'Avval Telegram akkaunt bog\'lanmagan.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!password_verify($current_password, $user['password'])) {
            echo json_encode(['ok' => false, 'error' => t('otp_current_incorrect')], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (strlen($code) !== 6 || !ctype_digit($code)) {
            echo json_encode(['ok' => false, 'error' => '6 xonali kod kiriting'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!$user['tg_verify_code'] || !$user['tg_verify_expires'] || strtotime($user['tg_verify_expires']) < time()) {
            echo json_encode(['ok' => false, 'error' => 'Kod muddati tugagan. Telegram botdan yangi kod oling.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!hash_equals($user['tg_verify_code'], $code)) {
            echo json_encode(['ok' => false, 'error' => 'Noto\'g\'ri kod. Qayta urinib ko\'ring.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $pdo->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_secret = NULL, tg_verify_code = NULL, tg_verify_expires = NULL WHERE id = ?")
            ->execute([$uid]);
        unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_time']);

        refresh_user_session($pdo, $uid);
        echo json_encode(['ok' => true, 'message' => 'Telegram 2FA muvaffaqiyatli yoqildi!'], JSON_UNESCAPED_UNICODE);
        exit;

    } elseif ($action === 'disable') {
        $current_password = $_POST['current_password'] ?? '';
        $code = trim($_POST['code'] ?? '');

        $stmt = $pdo->prepare("SELECT password, telegram_chat_id, tg_verify_code, tg_verify_expires, two_factor_enabled FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!password_verify($current_password, $user['password'])) {
            echo json_encode(['ok' => false, 'error' => t('otp_current_incorrect')], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!empty($user['two_factor_enabled'])) {
            if (strlen($code) !== 6 || !ctype_digit($code)) {
                echo json_encode(['ok' => false, 'error' => '6 xonali kod kiriting'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!$user['tg_verify_code'] || !$user['tg_verify_expires'] || strtotime($user['tg_verify_expires']) < time()) {
                echo json_encode(['ok' => false, 'error' => 'Kod muddati tugagan. Telegram botdan yangi kod oling.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!hash_equals($user['tg_verify_code'], $code)) {
                echo json_encode(['ok' => false, 'error' => 'Noto\'g\'ri kod. Qayta urinib ko\'ring.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $pdo->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL, tg_verify_code = NULL, tg_verify_expires = NULL WHERE id = ?")
            ->execute([$uid]);
        refresh_user_session($pdo, $uid);

        echo json_encode(['ok' => true, 'message' => '2FA o\'chirildi'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
