<?php
/**
 * VK Video upload client (api.vk.com).
 *
 * VK video upload oqimi:
 *   1. video.save — upload_url olish (user access token, video scope)
 *   2. Faylni upload_url ga multipart POST qilish (video_file)
 *   3. Natijadan owner_id/vid olib, sahifa + embed URL qaytarish
 *
 * Talab: .env da VK_ACCESS_TOKEN=... (video scope bilan user token).
 *
 * CLI tekshiruv:
 *   php includes/vk_upload.php login             — token va video scope ni tekshirish
 *   php includes/vk_upload.php upload <fayl> [title] [description]
 */

if (PHP_SAPI === 'cli') {
    require_once __DIR__ . '/../config/payment.php';
}

/** VK API chaqiruvi. @return array ['ok'=>bool, 'data'=>array|string, 'error'=>string] */
function vk_api(string $method, array $params = []): array {
    if (!VK_ACCESS_TOKEN) {
        return ['ok' => false, 'data' => [], 'error' => 'VK_ACCESS_TOKEN .env da sozlanmagan'];
    }
    $params['access_token'] = VK_ACCESS_TOKEN;
    $params['v'] = defined('VK_API_VERSION') ? VK_API_VERSION : '5.131';

    $ch = curl_init('https://api.vk.com/method/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        return ['ok' => false, 'data' => [], 'error' => 'VK javobini o\'qib bo\'lmadi' . ($err !== '' ? " (cURL: $err)" : '')];
    }
    if (!empty($data['error'])) {
        $e = $data['error'];
        $msg = (string)($e['error_msg'] ?? 'noma\'lum xato');
        return ['ok' => false, 'data' => [], 'error' => "VK xato [{$e['error_code']}]: $msg"];
    }
    return ['ok' => true, 'data' => $data['response'] ?? [], 'error' => ''];
}

/** UTF-8 xavfsiz substr */
function vk_truncate(string $s, int $len): string {
    if (function_exists('mb_substr')) return mb_substr($s, 0, $len, 'UTF-8');
    if (function_exists('iconv_substr')) return iconv_substr($s, 0, $len, 'UTF-8');
    return substr($s, 0, $len);
}

/**
 * video.save — upload_url va dastlabki vid/owner_id olish.
 * @return array ['ok'=>bool, 'upload_url'=>string, 'owner_id'=>string, 'vid'=>string, 'error'=>string]
 */
function vk_video_save(string $title, string $description = ''): array {
    $empty = ['ok' => false, 'upload_url' => '', 'owner_id' => '', 'vid' => '', 'error' => ''];
    $params = [
        'name'            => vk_truncate($title !== '' ? $title : 'Telegram video', 256),
        'is_private'      => 0,
        'privacy_view'    => 'all',
        'privacy_comment' => 'all',
        'wallpage'        => 1,
        'no_comments'     => 0,
        'repeat'          => 0,
    ];
    if ($description !== '') $params['description'] = vk_truncate($description, 1024);

    $r = vk_api('video.save', $params);
    if (!$r['ok']) {
        return ['ok' => false, 'upload_url' => '', 'owner_id' => '', 'vid' => '', 'error' => $r['error']];
    }
    $d = is_array($r['data']) ? $r['data'] : [];
    return [
        'ok'         => true,
        'upload_url' => (string)($d['upload_url'] ?? ''),
        'owner_id'   => (string)($d['owner_id'] ?? ''),
        'vid'        => (string)($d['vid'] ?? ''),
        'error'      => '',
    ];
}

/**
 * Videoni VK'ga yuklash.
 * @return array ['ok'=>bool, 'videoid'=>string, 'url'=>string, 'embed'=>string, 'error'=>string]
 */
function vk_upload_video(string $filePath, string $title = '', string $description = ''): array {
    $err = ['ok' => false, 'videoid' => '', 'url' => '', 'embed' => '', 'error' => ''];
    if (!is_file($filePath)) {
        $err['error'] = 'Fayl topilmadi: ' . $filePath;
        return $err;
    }

    $save = vk_video_save($title, $description);
    if (!$save['ok']) {
        $err['error'] = $save['error'];
        return $err;
    }
    if ($save['upload_url'] === '') {
        $err['error'] = 'video.save upload_url qaytarmadi.';
        return $err;
    }

    $ch = curl_init($save['upload_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'video_file' => new CURLFile(realpath($filePath), mime_content_type($filePath), basename($filePath)),
        ],
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT        => 900,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
    ]);
    $body = curl_exec($ch);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($body === false || $body === '') {
        $err['error'] = 'Yuklashda xato: ' . ($cerr !== '' ? $cerr : 'bo\'sh javob');
        return $err;
    }

    $res = json_decode($body, true);
    if (!is_array($res)) {
        $err['error'] = 'VK upload javobini o\'qib bo\'lmadi: ' . vk_truncate($body, 300);
        return $err;
    }
    if (!empty($res['error'])) {
        $err['error'] = 'VK upload xato: ' . vk_truncate((string)$res['error'], 200);
        return $err;
    }

    $owner_id = (string)($res['owner_id'] ?? $save['owner_id']);
    $vid = (string)($res['video_id'] ?? $res['vid'] ?? $save['vid']);
    if ($vid === '') {
        $err['error'] = 'Upload javobidan videoid topilmadi: ' . vk_truncate($body, 300);
        return $err;
    }

    return [
        'ok'       => true,
        'videoid'  => $owner_id . '_' . $vid,
        'url'      => 'https://vk.com/video' . $owner_id . '_' . $vid,
        'embed'    => 'https://vkvideo.ru/video_ext.php?oid=' . $owner_id . '&id=' . $vid . '&hd=2',
        'error'    => '',
    ];
}

// ===== CLI rejimi =====
// Faqat ushbu fayl to'g'ridan-to'g'ri ishga tushirilganda ishlaydi
// (boshqa fayl ichidan require qilinganda argv ga aralashmaydi).
$__vk_cli_entry = PHP_SAPI === 'cli'
    && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__);
if ($__vk_cli_entry) {
    $cmd = $argv[1] ?? 'login';
    switch ($cmd) {
        case 'login':
            $r = vk_api('video.get', ['count' => 1]);
            echo $r['ok'] ? "[OK] Token va video scope ishlaydi\n"
                          : "[XATO] " . ($r['error'] ?? 'noma\'lum') . "\n";
            break;

        case 'upload':
            $file = $argv[2] ?? '';
            $title = $argv[3] ?? '';
            $desc = $argv[4] ?? '';
            if ($file === '') { echo "Ishlatish: php includes/vk_upload.php upload <fayl> [title] [description]\n"; exit(1); }
            echo "Yuklanmoqda: $file\n";
            $r = vk_upload_video($file, $title, $desc);
            if ($r['ok']) {
                echo "[OK] videoid: {$r['videoid']}\nURL: {$r['url']}\nEmbed: {$r['embed']}\n";
            } else {
                echo "[XATO] " . $r['error'] . "\n";
                exit(1);
            }
            break;

        default:
            echo "Noma'lum buyruq: $cmd\n";
            exit(1);
    }
}
