<?php
// ===== TO'LOV SOZLAMALARI =====
// Karta raqamingiz (foydalanuvchilarga ko'rsatiladi)
define('CARD_NUMBER', 'YOUR_CARD_NUMBER'); // <-- O'zingizning karta raqamingizni kiriting
define('CARD_OWNER',  'YOUR_NAME');          // <-- Karta egasining ismi

// Telegram bot sozlamalari
define('TG_BOT_TOKEN', '');
define('TG_CHAT_ID',   'YOUR_CHAT_ID'); // <-- Botga /start yuborgach olingan Chat ID

// Premium narxlar (so'mda)
define('PREMIUM_PLANS', [
    '1month' => ['label' => '1 Oy',  'price' => 10000,  'days' => 30],
    '3month' => ['label' => '3 Oy',  'price' => 25000,  'days' => 90],
    '1year'  => ['label' => '1 Yil', 'price' => 80000,  'days' => 365],
]);

// Telegram orqali xabar yuborish funksiyasi
function tg_send_message($text) {
    if (TG_CHAT_ID === 'YOUR_CHAT_ID') return;
    $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/sendMessage';
    $data = ['chat_id' => TG_CHAT_ID, 'text' => $text, 'parse_mode' => 'HTML'];
    $opts = ['http' => ['method' => 'POST', 'header' => 'Content-Type: application/json',
        'content' => json_encode($data), 'timeout' => 5]];
    @file_get_contents($url, false, stream_context_create($opts));
}

// Telegram orqali rasm (screenshot) yuborish
function tg_send_photo($photo_path, $caption = '') {
    if (TG_CHAT_ID === 'YOUR_CHAT_ID') return;
    $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/sendPhoto';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => [
            'chat_id'  => TG_CHAT_ID,
            'photo'    => new CURLFile($photo_path),
            'caption'  => $caption,
            'parse_mode' => 'HTML',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
