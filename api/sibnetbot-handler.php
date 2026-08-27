<?php
/**
 * UZDUB Sibnet video bot — handler.
 *
 * Video (yoki video dokument) yuborilsa faylni Telegram'dan yuklab olib,
 * video.sibnet.ru'ga yuklaydi va sahifa + embed URL'larni qaytaradi.
 *
 * Buyruqlar:
 *   /start  /help  — yordam
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../includes/sibnet_upload.php';
require_once __DIR__ . '/../includes/vk_upload.php';

function sb_tg(string $method, array $data) {
    return tg_api_call('https://api.telegram.org/bot' . TG_SIBNET_BOT_TOKEN . '/' . $method, $data);
}

function sb_send(int $chat_id, string $text) {
    return sb_tg('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ]);
}

function sb_can_use(int $chat_id): bool {
    if (!TG_SIBNET_BOT_TOKEN) return false;
    if (TG_CHAT_ID === 'YOUR_CHAT_ID') return true; // sozlash bosqichi — hammaga ochiq
    return (int)TG_CHAT_ID === $chat_id;
}

function sb_help(int $chat_id): void {
    sb_send($chat_id, "🎬 <b>VK / Sibnet video yuklovchi bot</b>\n\n"
        . "Videoni shu yerga yuboring (fayl yoki forward) — bot uni <b>VK Video</b> (agar VK_ACCESS_TOKEN sozlangan bo'lsa) "
        . "yoki <b>video.sibnet.ru</b>'ga yuklab, sahifa va embed URL'larini qaytaradi.\n\n"
        . "Qo'llab-quvvatlanadi:\n"
        . "• video fayl (mp4, webm, mkv...)\n"
        . "• dokument ko'rinishidagi video\n\n"
        . "Katta fayllar uchun biroz kutish kerak.");
}

function sb_get_file_path(string $file_id): ?string {
    $res = sb_tg('getFile', ['file_id' => $file_id]);
    $d = json_decode((string)$res, true);
    return ($d && $d['ok'] && !empty($d['result']['file_path'])) ? $d['result']['file_path'] : null;
}

function sb_download(string $file_id): ?string {
    $path = sb_get_file_path($file_id);
    if (!$path) return null;
    $url = 'https://api.telegram.org/file/bot' . TG_SIBNET_BOT_TOKEN . '/' . $path;
    $dir = __DIR__ . '/../cache/sibnet_dl';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $tmp = $dir . '/dl_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . basename($path);
    $ctx = stream_context_create(['http' => ['timeout' => 120, 'ignore_errors' => true]]);
    $bin = @file_get_contents($url, false, $ctx);
    if ($bin === false) return null;
    @file_put_contents($tmp, $bin);
    return is_file($tmp) ? $tmp : null;
}

function sb_handle_update(array $update): void {
    $msg = $update['message'] ?? [];
    $chat_id = (int)($msg['chat']['id'] ?? 0);
    $text = trim((string)($msg['text'] ?? ''));

    if (!$chat_id || !sb_can_use($chat_id)) {
        if ($chat_id) sb_send($chat_id, "🚫 Ruxsat yo'q. Bot egasiga murojaat qiling.");
        return;
    }

    if ($text === '/start' || $text === '/help') {
        sb_help($chat_id);
        return;
    }

    // Video faylni topish: video, video_note, yoki video dokument
    $file_id = '';
    $caption = '';
    if (!empty($msg['video'])) {
        $file_id = $msg['video']['file_id'] ?? '';
        $caption = $msg['video']['file_name'] ?? '';
    } elseif (!empty($msg['document']) && strpos($msg['document']['mime_type'] ?? '', 'video') !== false) {
        $file_id = $msg['document']['file_id'] ?? '';
        $caption = $msg['document']['file_name'] ?? '';
    } elseif (!empty($msg['video_note'])) {
        $file_id = $msg['video_note']['file_id'] ?? '';
        $caption = 'video_note';
    }

    if ($file_id === '') {
        if ($text !== '') {
            sb_send($chat_id, "❌ Video topilmadi. Video fayl yuboring yoki <b>/help</b> ni bosing.");
            return;
        }
        return; // rasm, stiker va h.k. — e'tiborsiz
    }

    if (!VK_ACCESS_TOKEN && (!SIBNET_LOGIN || !SIBNET_PASSWORD)) {
        sb_send($chat_id, "⚠️ Bot hali sozlanmagan (VK_ACCESS_TOKEN yoki SIBNET_LOGIN/SIBNET_PASSWORD .env da yo'q).");
        return;
    }

    sb_send($chat_id, "⏳ <b>Yuklanmoqda...</b>\nVideo Telegram'dan yuklab olinmoqda.");

    $file = sb_download($file_id);
    if (!$file) {
        sb_send($chat_id, "❌ Videoni Telegram'dan yuklab bo'lmadi. Qayta urinib ko'ring.");
        return;
    }

    // Kapşion yoki fayl nomidan sarlavha
    $title = trim($caption !== '' ? pathinfo($caption, PATHINFO_FILENAME) : '');
    if ($title === '') $title = 'Telegram video';

    // VK sozlangan bo'lsa — avval VK'ga yuklaymiz, xato bo'lsa Sibnet'ga tushamiz.
    if (VK_ACCESS_TOKEN) {
        try {
            $res = vk_upload_video($file, $title);
        } catch (Throwable $e) {
            $res = ['ok' => false, 'error' => $e->getMessage()];
        }
        if ($res['ok']) {
            @unlink($file);
            sb_send($chat_id, "✅ <b>Video VK'ga yuklandi!</b>\n\n"
                . "📄 Sahifa: {$res['url']}\n"
                . "🔗 Embed: {$res['embed']}");
            return;
        }
        if (!SIBNET_LOGIN || !SIBNET_PASSWORD) {
            @unlink($file);
            sb_send($chat_id, "❌ VK'ga yuklab bo'lmadi: " . ($res['error'] ?? 'noma\'lum'));
            return;
        }
        sb_send($chat_id, "⚠️ VK'ga yuklab bo'lmadi: " . ($res['error'] ?? 'noma\'lum') . "\nSibnet'ga urinmoqda...");
    }

    try {
        $res = sibnet_upload_video($file, $title);
    } catch (Throwable $e) {
        $res = ['ok' => false, 'error' => $e->getMessage()];
    }
    @unlink($file);

    if (!$res['ok']) {
        sb_send($chat_id, "❌ Sibnet'ga yuklashda xato: " . ($res['error'] ?? 'noma\'lum'));
        return;
    }

    sb_send($chat_id, "✅ <b>Video yuklandi!</b>\n\n"
        . "📄 Sahifa: {$res['url']}\n"
        . "🔗 Embed: {$res['embed']}");
}
