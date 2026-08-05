<?php
/**
 * Telegram 2FA bot — getUpdates polling daemon.
 *
 * Localhost (XAMPP) uchun webhook o'rniga ishlatiladi: Telegram API'dan
 * yangi xabarlarni olib, telegram-handler.php orqali ishlaydi.
 *
 * Ishga tushirish (fon daemon):
 *   php api/telegram-poll.php --daemon
 *
 * Bir martalik ishlov:
 *   php api/telegram-poll.php --once
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/telegram-handler.php';

if (!TG_2FA_BOT_TOKEN) {
    echo "TG_2FA_BOT_TOKEN .env'da sozlanmagan.\n";
    exit(1);
}

$offsetFile = __DIR__ . '/../cache/telegram_offset.json';
if (!is_dir(dirname($offsetFile))) {
    @mkdir(dirname($offsetFile), 0777, true);
}

function telegram_load_offset($file): int {
    if (is_file($file)) {
        $saved = json_decode(file_get_contents($file), true);
        return (int)($saved['offset'] ?? 0);
    }
    return 0;
}

function telegram_save_offset($file, $offset): void {
    @file_put_contents($file, json_encode(['offset' => $offset, 'updated_at' => date('c')]));
}

$isDaemon = in_array('--daemon', $argv ?? [], true);
$processed = 0;

do {
    $offset = telegram_load_offset($offsetFile);

    $url = 'https://api.telegram.org/bot' . TG_2FA_BOT_TOKEN . '/getUpdates?timeout=' . ($isDaemon ? 25 : 0) . '&offset=' . ($offset + 1);
    $res = @file_get_contents($url);
    $data = json_decode($res, true);

    if (!$data || !$data['ok']) {
        if (isset($data['description'])) {
            echo '[' . date('H:i:s') . "] API xato: {$data['description']}\n";
        }
        if ($isDaemon) { sleep(3); continue; }
        break;
    }

    $updates = $data['result'] ?? [];
    foreach ($updates as $update) {
        $id = (int)($update['update_id'] ?? 0);
        if ($id > $offset) $offset = $id;
        tg_2fa_handle_update($pdo, $update);
        $processed++;
    }

    if ($offset > telegram_load_offset($offsetFile)) {
        telegram_save_offset($offsetFile, $offset);
    }

    if (!$isDaemon) break;
} while (true);

if (!$isDaemon) {
    echo "Qayta ishlandi: $processed\n";
}
