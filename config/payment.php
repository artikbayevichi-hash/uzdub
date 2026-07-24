<?php
require_once __DIR__ . '/env.php';

define('CARD_NUMBER', env('CARD_NUMBER', '8600XXXX0000XXXX'));
define('CARD_OWNER', env('CARD_OWNER', 'UZDUB PLATFORM'));
define('TG_BOT_TOKEN', env('TG_BOT_TOKEN', ''));
define('TG_CHAT_ID', env('TG_CHAT_ID', 'YOUR_CHAT_ID'));
define('CLICK_MERCHANT_ID', env('CLICK_MERCHANT_ID', ''));
define('CLICK_SERVICE_ID', env('CLICK_SERVICE_ID', ''));
define('CLICK_USER_ID', env('CLICK_USER_ID', ''));
define('CLICK_SECRET_KEY', env('CLICK_SECRET_KEY', ''));
define('CLICK_RETURN_URL', '/uzdub/premium.php?status=success');
define('CLICK_CALLBACK_URL', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/uzdub/api/click-callback.php');
define('UZUM_MERCHANT_ID', env('UZUM_MERCHANT_ID', ''));
define('UZUM_SECRET_KEY', env('UZUM_SECRET_KEY', ''));
define('UZUM_RETURN_URL', '/uzdub/premium.php?status=success');
define('UZUM_CALLBACK_URL', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/uzdub/api/uzum-callback.php');

define('PREMIUM_PLANS', [
    '1month' => ['label' => '1 Oy',  'price' => 10000,  'days' => 30],
    '3month' => ['label' => '3 Oy',  'price' => 25000,  'days' => 90],
    '1year'  => ['label' => '1 Yil', 'price' => 80000,  'days' => 365],
]);

function apply_promo_discount(int $price, int $discount_percent): int {
    if ($discount_percent <= 0) return $price;
    return max(0, (int)round($price * (100 - $discount_percent) / 100));
}

function redeem_promo_code(PDO $pdo, string $code, int $user_id): array {
    $code = strtoupper(trim($code));
    if ($code === '') return ['ok' => false, 'msg' => 'Promo kod kiriting.'];

    $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $promo = $stmt->fetch();
    if (!$promo) return ['ok' => false, 'msg' => 'Promo kod topilmadi.'];
    if ($promo['expires_at'] && strtotime($promo['expires_at']) < time()) {
        return ['ok' => false, 'msg' => 'Promo kod muddati tugagan.'];
    }
    if ((int)$promo['used_count'] >= (int)$promo['max_uses']) {
        return ['ok' => false, 'msg' => 'Promo kod limiti tugagan.'];
    }

    $chk = $pdo->prepare("SELECT id FROM promo_redemptions WHERE promo_id = ? AND user_id = ?");
    $chk->execute([$promo['id'], $user_id]);
    if ($chk->fetch()) return ['ok' => false, 'msg' => 'Siz bu promo kodni allaqachon ishlatgansiz.'];

    $days = (int)$promo['free_days'];
    if ($days > 0) {
        $stmt = $pdo->prepare("SELECT is_premium, premium_expires_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $u = $stmt->fetch();
        if ($u && $u['is_premium'] && $u['premium_expires_at'] && strtotime($u['premium_expires_at']) > time()) {
            $expires = date('Y-m-d H:i:s', strtotime($u['premium_expires_at']) + $days * 86400);
        } else {
            $expires = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
        }
        $pdo->prepare("UPDATE users SET is_premium=1, premium_expires_at=? WHERE id=?")->execute([$expires, $user_id]);
    }

    $pdo->prepare("INSERT INTO promo_redemptions (promo_id, user_id) VALUES (?,?)")->execute([$promo['id'], $user_id]);
    $pdo->prepare("UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ?")->execute([$promo['id']]);

    $msg = $days > 0 ? "$days kun Premium faollashtirildi!" : 'Promo kod qabul qilindi.';
    return ['ok' => true, 'msg' => $msg, 'discount_percent' => (int)$promo['discount_percent'], 'free_days' => $days];
}

function extend_premium($pdo, $user_db_id, $plan_key) {
    $plans = PREMIUM_PLANS;
    if (!isset($plans[$plan_key])) return false;

    $days = $plans[$plan_key]['days'];
    $stmt = $pdo->prepare("SELECT is_premium, premium_expires_at FROM users WHERE id = ?");
    $stmt->execute([$user_db_id]);
    $u = $stmt->fetch();

    if ($u && $u['is_premium'] && $u['premium_expires_at']) {
        $current_expires = strtotime($u['premium_expires_at']);
        if ($current_expires < time()) {
            $expires = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
        } else {
            $expires = date('Y-m-d H:i:s', $current_expires + ($days * 86400));
        }
    } else {
        $expires = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
    }

    $pdo->prepare("UPDATE users SET is_premium=1, premium_expires_at=? WHERE id=?")
        ->execute([$expires, $user_db_id]);
    return $expires;
}

function activate_premium($pdo, $user_db_id, $plan_key, $payment_id = null, $amount = 0) {
    $expires = extend_premium($pdo, $user_db_id, $plan_key);
    if (!$expires) return false;

    if ($payment_id) {
        $pdo->prepare("UPDATE premium_payments SET status='approved', expires_at=? WHERE id=?")
            ->execute([$expires, $payment_id]);
    }

    $stmt = $pdo->prepare("SELECT username, user_id FROM users WHERE id = ?");
    $stmt->execute([$user_db_id]);
    $user = $stmt->fetch();

    tg_send_message("✅ <b>Premium tasdiqlandi!</b>\n"
        . "👤 Foydalanuvchi: <b>" . e($user['username']) . "</b> (ID: " . $user['user_id'] . ")\n"
        . "📅 Tugash sanasi: <b>" . date('d.m.Y H:i', strtotime($expires)) . "</b>");

    return $expires;
}

function tg_send_message($text) {
    if (TG_CHAT_ID === 'YOUR_CHAT_ID' || !TG_BOT_TOKEN) return false;
    $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/sendMessage';
    $data = [
        'chat_id' => TG_CHAT_ID,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    return tg_api_call($url, $data);
}

function tg_send_photo($photo_path, $caption = '') {
    if (TG_CHAT_ID === 'YOUR_CHAT_ID' || !TG_BOT_TOKEN) return false;
    $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/sendPhoto';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => [
            'chat_id'    => TG_CHAT_ID,
            'photo'      => new CURLFile($photo_path),
            'caption'    => $caption,
            'parse_mode' => 'HTML',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function tg_send_inline_keyboard($text, $buttons) {
    if (TG_CHAT_ID === 'YOUR_CHAT_ID' || !TG_BOT_TOKEN) return false;
    $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/sendMessage';
    $keyboard = ['inline_keyboard' => $buttons];
    $data = [
        'chat_id' => TG_CHAT_ID,
        'text' => $text,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode($keyboard),
        'disable_web_page_preview' => true,
    ];
    return tg_api_call($url, $data);
}

function tg_api_call($url, $data) {
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data),
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ];
    $context = stream_context_create($opts);
    return @file_get_contents($url, false, $context);
}

function click_generate_url($plan_key, $user_id, $transaction_id) {
    if (!CLICK_MERCHANT_ID) return null;
    $plans = PREMIUM_PLANS;
    if (!isset($plans[$plan_key])) return null;
    $amount = $plans[$plan_key]['price'];
    $params = http_build_query([
        'merchant_id' => CLICK_MERCHANT_ID,
        'service_id'  => CLICK_SERVICE_ID,
        'user_id'     => $user_id,
        'amount'      => $amount,
        'transaction' => $transaction_id,
        'return_url'  => CLICK_RETURN_URL,
    ]);
    return 'https://my.click.uz/services/pay?' . $params;
}

function click_verify_callback($data) {
    if (!CLICK_SECRET_KEY) return false;
    $sign_string = $data['click_trans_id'] . $data['merchant_trans_id']
        . $data['service_id'] . CLICK_SECRET_KEY
        . $data['amount'] . $data['action'] . $data['sign_time'];
    return hash_equals(md5($sign_string), $data['sign_string'] ?? '');
}

function uzum_generate_url($plan_key, $transaction_id) {
    if (!UZUM_MERCHANT_ID) return null;
    $plans = PREMIUM_PLANS;
    if (!isset($plans[$plan_key])) return null;
    $amount = $plans[$plan_key]['price'] * 100;
    $params = http_build_query([
        'm' => UZUM_MERCHANT_ID,
        'ac.transaction_id' => $transaction_id,
        'a' => $amount,
    ]);
    return 'https://checkout.uzum.uz/pay?' . $params;
}

function uzum_verify_callback($data) {
    if (!UZUM_SECRET_KEY) return false;
    $sign_string = $data['transaction_id'] . $data['status']
        . $data['amount'] . UZUM_SECRET_KEY;
    return hash_equals(md5($sign_string), $data['sign_string'] ?? '');
}

function generate_transaction_id() {
    return 'UZ' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
}

function tg_send_photo_old($photo_path, $caption = '') {
    return tg_send_photo($photo_path, $caption);
}
