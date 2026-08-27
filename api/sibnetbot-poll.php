<?php
/**
 * UZDUB Sibnet video bot — getUpdates polling daemon.
 *
 * XAMPP (localhost) uchun webhook o'rniga: video yuborilganda
 * sibnetbot-handler.php orqali video.sibnet.ru'ga yuklaydi va URL qaytaradi.
 *
 * Ishga tushirish (fon daemon):
 *   php api/sibnetbot-poll.php --daemon
 *
 * Bir martalik ishlov:
 *   php api/sibnetbot-poll.php --once
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/sibnetbot-handler.php';

if (!TG_SIBNET_BOT_TOKEN || TG_CHAT_ID === 'YOUR_CHAT_ID') {
    echo "TG_SIBNET_BOT_TOKEN / TG_CHAT_ID .env'da sozlanmagan.\n";
    exit(1);
}

ini_set('default_socket_timeout', '40');
if (function_exists('set_time_limit')) @set_time_limit(0);

$offsetFile = __DIR__ . '/../cache/sibnetbot_offset.json';
if (!is_dir(dirname($offsetFile))) @mkdir(dirname($offsetFile), 0777, true);

function sibnetbot_load_offset($file): int {
    if (is_file($file)) {
        $saved = json_decode(file_get_contents($file), true);
        return (int)($saved['offset'] ?? 0);
    }
    return 0;
}

function sibnetbot_save_offset($file, $offset): void {
    @file_put_contents($file, json_encode(['offset' => $offset, 'updated_at' => date('c')]));
}

$isDaemon = in_array('--daemon', $argv ?? [], true);

if ($isDaemon) {
    $lockFile = __DIR__ . '/../cache/sibnetbot.lock';
    $lockPid = (int)@file_get_contents($lockFile);
    $alreadyRunning = false;
    if ($lockPid > 0) {
        if (stripos(PHP_OS, 'WIN') === 0) {
            $out = @shell_exec('tasklist /FI "PID eq ' . $lockPid . '" /NH 2>NUL');
            $alreadyRunning = is_string($out) && preg_match('/\b' . $lockPid . '\b/', $out);
        } else {
            $alreadyRunning = @file_exists('/proc/' . $lockPid);
        }
    }
    if ($alreadyRunning) {
        echo "Sibnet bot allaqachon ishlamoqda (PID $lockPid).\n";
        exit(0);
    }
    @file_put_contents($lockFile, getmypid());
    register_shutdown_function(function () use ($lockFile) {
        if ((string)@file_get_contents($lockFile) === (string)getmypid()) @unlink($lockFile);
    });
}

$processed = 0;

do {
    $offset = sibnetbot_load_offset($offsetFile);

    $url = 'https://api.telegram.org/bot' . TG_SIBNET_BOT_TOKEN . '/getUpdates?timeout=' . ($isDaemon ? 25 : 0) . '&offset=' . ($offset + 1);
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

    foreach (($data['result'] ?? []) as $update) {
        $id = (int)($update['update_id'] ?? 0);
        if ($id > $offset) $offset = $id;
        try {
            sb_handle_update($update);
            $processed++;
        } catch (Throwable $e) {
            echo '[' . date('H:i:s') . "] Xato (update $id): " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
        }
    }

    sibnetbot_save_offset($offsetFile, $offset);

    if (!$isDaemon) break;
} while (true);

if (!$isDaemon) {
    echo "Qayta ishlandi: $processed\n";
}
