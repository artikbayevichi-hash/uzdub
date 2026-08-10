<?php
/**
 * UZDUB admin bоt — getUpdates polling daemon (asosiy bot TG_ADMIN_BOT_TOKEN).
 *
 * Localhost (XAMPP) uchun webhook o'rniga ishlatiladi:
 * adminbot-handler.php orqali kontent/qismlar boshqaruvi amallarini bajaradi.
 *
 * Ishga tushirish (fon daemon):
 *   php api/adminbot-poll.php --daemon
 *
 * Bir martalik ishlov:
 *   php api/adminbot-poll.php --once
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/adminbot-handler.php';

if (!TG_ADMIN_BOT_TOKEN || TG_CHAT_ID === 'YOUR_CHAT_ID') {
    echo "TG_ADMIN_BOT_TOKEN / TG_CHAT_ID .env'da sozlanmagan.\n";
    exit(1);
}

ab_ensure_tables($pdo);

// Long-polling da osilib qolmaslik uchun socket timeout
ini_set('default_socket_timeout', '40');
if (function_exists('set_time_limit')) @set_time_limit(0);

$offsetFile = __DIR__ . '/../cache/adminbot_offset.json';
if (!is_dir(dirname($offsetFile))) {
    @mkdir(dirname($offsetFile), 0777, true);
}

function adminbot_load_offset($file): int {
    if (is_file($file)) {
        $saved = json_decode(file_get_contents($file), true);
        return (int)($saved['offset'] ?? 0);
    }
    return 0;
}

function adminbot_save_offset($file, $offset): void {
    @file_put_contents($file, json_encode(['offset' => $offset, 'updated_at' => date('c')]));
}

$isDaemon = in_array('--daemon', $argv ?? [], true);
$processed = 0;

do {
    $offset = adminbot_load_offset($offsetFile);

    // Long-poll (timeout=25) uchun alohida stream context — socket'da ham aniq timeout
    $url = 'https://api.telegram.org/bot' . TG_ADMIN_BOT_TOKEN . '/getUpdates?timeout=' . ($isDaemon ? 25 : 0) . '&offset=' . ($offset + 1);
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => ($isDaemon ? 40 : 10),
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    $data = json_decode((string)$res, true);

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
        try {
            ab_handle_update($pdo, $update);
            $processed++;
        } catch (Throwable $e) {
            echo '[' . date('H:i:s') . "] Xato (update $id): " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
        }
    }

    // DOIMO saqlanadi (yangi update bo'lmasa ham) — watchdorga daemon
    // "yashayotgani" haqida heartbeat signali bo'ladi.
    adminbot_save_offset($offsetFile, $offset);

    if (!$isDaemon) break;
} while (true);

if (!$isDaemon) {
    echo "Qayta ishlandi: $processed\n";
}
