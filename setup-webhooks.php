<?php
/**
 * setup-webhooks.php — Telegram bot webhook'larini boshqarish (CLI).
 *
 * Ishlatish (terminaldan):
 *   php setup-webhooks.php status     — har bir botning joriy holati
 *   php setup-webhooks.php webhook    — 2FA bot uchun webhook yoqish
 *                                      (public hosting URL kerak, .env dagi SITE_URL dan olinadi)
 *   php setup-webhooks.php poll       — barcha webhook'larni o'chirish (localhost/XAMPP, poll rejimi)
 *
 * Tokenlar .env faylidan olinadi: TG_2FA_BOT_TOKEN
 */

require_once __DIR__ . '/config/env.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Faqat terminaldan ishlatiladi: php setup-webhooks.php <status|webhook|poll>\n");
}

$site_url = rtrim((string)env('SITE_URL', 'http://localhost/uzdub'), '/');

// bot => [token-key, webhook url (yoki null = send/poll rejimi)]
$bots = [
    '2FA bot'  => ['TG_2FA_BOT_TOKEN', $site_url . '/api/telegram-webhook.php'],
    'Asosiy bot' => ['TG_BOT_TOKEN', null],   // sendMessage uchun, webhook kerak emas
    'VK bot'   => ['TG_VK_BOT_TOKEN', null],  // hozircha ishlatilmayapti
];

function tg_call(string $token, string $method, array $fields = []): array
{
    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $fields,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['ok' => false, 'description' => 'cURL: ' . $err];
    }
    $r = json_decode($res, true);
    return is_array($r) ? $r : ['ok' => false, 'description' => 'Javob o\'qib bo\'lmadi'];
}

$mode = $argv[1] ?? 'status';

$fail = 0;
foreach ($bots as $name => [$key, $webhook]) {
    $token = (string)env($key, '');
    if ($token === '' || strpos($token, 'YOUR_') === 0) {
        echo "[SKIP] $name — $key sozlanmagan (.env).\n";
        continue;
    }

    if ($mode === 'status') {
        $r = tg_call($token, 'getWebhookInfo');
        $url  = $r['result']['url'] ?? '';
        $pend = $r['result']['pending_update_count'] ?? 0;
        $status = $r['ok'] ? ($url === '' ? 'webhook yo\'q (poll rejimi)' : 'webhook o\'rnatilgan') : 'so\'rov xato: ' . ($r['description'] ?? 'noma\'lum');
        echo "[INFO] $name — $status" . ($url !== '' ? " ($url)" : '') . ", kutilayotgan: $pend\n";
        continue;
    }

    if ($mode === 'poll') {
        $r = tg_call($token, 'deleteWebhook', ['drop_pending_updates' => 'true']);
        echo $r['ok'] ? "[OK] $name — webhook o'chirildi (poll rejimi).\n"
                      : "[XATO] $name — " . ($r['description'] ?? 'noma\'lum') . "\n";
        if (!$r['ok']) $fail = 1;
        continue;
    }

    // webhook rejimi
    if ($webhook === null) {
        echo "[SKIP] $name — webhook rejimi kerak emas (send/poll ishlatadi).\n";
        continue;
    }
    $r = tg_call($token, 'setWebhook', [
        'url'             => $webhook,
        'allowed_updates' => json_encode(['message', 'callback_query']),
    ]);
    if ($r['ok']) {
        echo "[OK] $name — webhook sozlandi: $webhook\n";
    } else {
        echo "[XATO] $name — " . ($r['description'] ?? 'noma\'lum') . "\n";
        $fail = 1;
    }
}

if ($mode === 'webhook' && !$fail) {
    echo "\nEslatma: webhook ishlashi uchun SITE_URL public (https) URL bo'lishi kerak.\n";
    echo "localhost uchun poll rejimidan foydalaning: php setup-webhooks.php poll\n";
}

exit($fail ? 1 : 0);
