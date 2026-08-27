<?php
/**
 * Sibnet (video.sibnet.ru) video upload client.
 *
 * Sibnet'da ommaviy upload API yo'q — bot brauzer sessiyasini takrorlaydi:
 *   1. www.sibnet.ru/profile/login'ga login qilish (CSRF token + cookie)
 *   2. video.sibnet.ru/add/ sahifasini ochib, upload formani topish
 *   3. Videoni multipart POST bilan yuborib, videoid olish
 *
 * CLI tekshiruv:
 *   php includes/sibnet_upload.php login          — login'ni tekshirish
 *   php includes/sibnet_upload.php add-info       — /add/ formasini faylga saqlash
 *   php includes/sibnet_upload.php upload file title [description]
 *
 * Session cookie'lari cache/sibnet_cookies.txt da saqlanadi.
 */

if (PHP_SAPI === 'cli') {
    require_once __DIR__ . '/../config/payment.php';
}

define('SIBNET_CACHE_DIR', __DIR__ . '/../cache');

function sibnet_cookie_jar(): string {
    if (!is_dir(SIBNET_CACHE_DIR)) @mkdir(SIBNET_CACHE_DIR, 0777, true);
    return SIBNET_CACHE_DIR . '/sibnet_cookies.txt';
}

function sibnet_ua(): string {
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
}

/**
 * cURL HTTP yordamchisi. Cookie jar'ni avtomatik saqlaydi.
 * @return array ['status' => int, 'body' => string, 'headers' => array, 'url' => string]
 */
function sibnet_http(string $method, string $url, array $opts = []): array {
    $jar = $opts['jar'] ?? sibnet_cookie_jar();
    $ch = curl_init($url);
    $headers = [];
    $default = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => (int)($opts['timeout'] ?? 90),
        CURLOPT_USERAGENT => sibnet_ua(),
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_ENCODING => '',
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
            $len = strlen($line);
            $trim = trim($line);
            if ($trim !== '' && stripos($trim, 'HTTP/') === 0) {
                $headers = [];
            } elseif ($trim !== '' && strpos($trim, ':') !== false) {
                [$k, $v] = explode(':', $trim, 2);
                $headers[strtolower($k)] = trim($v);
            }
            return $len;
        },
    ];
    if (strtoupper($method) === 'POST') {
        $default[CURLOPT_POST] = true;
        if (isset($opts['fields'])) {
            $default[CURLOPT_POSTFIELDS] = $opts['fields'];
        }
    }
    if (!empty($opts['headers'])) {
        $default[CURLOPT_HTTPHEADER] = $opts['headers'];
    }
    if (!empty($opts['referer'])) {
        $default[CURLOPT_REFERER] = $opts['referer'];
    }
    if (isset($opts['nofollow'])) {
        $default[CURLOPT_FOLLOWLOCATION] = false;
    }
    curl_setopt_array($ch, $default);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $eff = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'body' => (string)$body, 'headers' => $headers, 'url' => $eff, 'err' => $err];
}

/** HTML ichidan input qiymatlarini olish */
function sibnet_parse_inputs(string $html): array {
    $fields = [];
    if (preg_match_all('/<input[^>]+>/i', $html, $mm)) {
        foreach ($mm[0] as $tag) {
            $name = '';
            $value = '';
            if (preg_match('/\bname\s*=\s*["\']?([^"\'\s>]+)/i', $tag, $m)) $name = $m[1];
            if (preg_match('/\bvalue\s*=\s*["\']([^"\']*)["\']/i', $tag, $m)) $value = $m[1];
            elseif (preg_match('/\bvalue\s*=\s*([^"\'\s>]+)/i', $tag, $m)) $value = $m[1];
            if ($name !== '') $fields[$name] = $value;
        }
    }
    return $fields;
}

/** Video bo'limida login borligini tekshiradi */
function sibnet_is_logged_in(): bool {
    $r = sibnet_http('GET', 'https://video.sibnet.ru/');
    $body = $r['body'];
    if (preg_match('/sibnet-head__user-name[^>]*>([^<]+)</', $body, $m)) {
        return trim($m[1]) !== '';
    }
    return stripos($body, 'Login.showForm') === false;
}

/**
 * www.sibnet.ru'ga login qilish.
 * @return array ['ok' => bool, 'error' => string]
 */
function sibnet_login_www(): array {
    if (!SIBNET_LOGIN || !SIBNET_PASSWORD) {
        return ['ok' => false, 'error' => 'SIBNET_LOGIN / SIBNET_PASSWORD .env da sozlanmagan'];
    }
    $jar = sibnet_cookie_jar();
    @unlink($jar);

    $r = sibnet_http('GET', 'https://www.sibnet.ru/profile/login');
    $token = '';
    if (preg_match('/name="csrf-token"\s+content="([^"]+)"/', $r['body'], $m)) $token = $m[1];
    if ($token === '' && preg_match('/content="([^"]+)"\s+name="csrf-token"/', $r['body'], $m)) $token = $m[1];

    $fields = [
        'login' => SIBNET_LOGIN,
        'password' => SIBNET_PASSWORD,
        'iehack' => '&#9760;',
    ];
    if ($token !== '') $fields['authenticity_token'] = $token;

    $r2 = sibnet_http('POST', 'https://www.sibnet.ru/profile/login?next=http%3A%2F%2Fwww.sibnet.ru%2F', [
        'fields' => http_build_query($fields),
        'referer' => 'https://www.sibnet.ru/profile/login',
    ]);

    $err = '';
    if (preg_match('/b-form-message-error[^>]*>\s*([^<]+)</', $r2['body'], $m)) {
        $err = trim($m[1]);
    }
    if ($err !== '') {
        return ['ok' => false, 'error' => 'Sibnet login xatosi: ' . $err];
    }
    if (stripos($r2['body'], 'Логин') !== false && stripos($r2['body'], 'Пароль') !== false
        && stripos($r2['body'], 'Неправильный') === false) {
        // forma qaytgan bo'lsa — muvaffaqiyatli emas
        return ['ok' => false, 'error' => 'Sibnet login javobini tahlil qilib bo\'lmadi'];
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * Login'ni kafolatlash — cookie bor bo'lsa qayta ishlatadi.
 */
function sibnet_ensure_login(): array {
    if (is_file(sibnet_cookie_jar()) && sibnet_is_logged_in()) {
        return ['ok' => true, 'reused' => true, 'error' => ''];
    }
    $r = sibnet_login_www();
    if (!$r['ok']) return $r;
    // video bo'limi sessiyasi SSO orqali o'tishi kerak
    if (!sibnet_is_logged_in()) {
        return ['ok' => false, 'error' => 'www.sibnet.ru\'ga kirdi, lekin video.sibnet.ru sessiyasi o\'tmadi (SSO)'];
    }
    return ['ok' => true, 'reused' => false, 'error' => ''];
}

/**
 * video.sibnet.ru/add/ sahifasini o'qib, forma tuzilishini qaytaradi.
 * @return array ['ok' => bool, 'html' => string, 'fields' => array, 'action' => string, 'error' => string]
 */
function sibnet_add_page(): array {
    $r = sibnet_http('GET', 'https://video.sibnet.ru/add/');
    if (stripos($r['body'], 'Login.showForm') !== false) {
        return ['ok' => false, 'html' => $r['body'], 'fields' => [], 'action' => '', 'error' => 'video.sibnet.ru\'ga kirilmagan'];
    }
    $action = '';
    if (preg_match('/<form[^>]+action\s*=\s*["\']([^"\']+)["\']/i', $r['body'], $m)) {
        $action = $m[1];
    }
    return ['ok' => true, 'html' => $r['body'], 'fields' => sibnet_parse_inputs($r['body']), 'action' => $action, 'error' => ''];
}

/**
 * Videoni Sibnet'ga yuklash.
 * @return array ['ok' => bool, 'videoid' => string, 'url' => string, 'embed' => string, 'error' => string]
 */
function sibnet_upload_video(string $filePath, string $title = '', string $description = ''): array {
    if (!is_file($filePath)) return ['ok' => false, 'videoid' => '', 'url' => '', 'embed' => '', 'error' => 'Fayl topilmadi: ' . $filePath];

    $login = sibnet_ensure_login();
    if (!$login['ok']) return ['ok' => false, 'videoid' => '', 'url' => '', 'embed' => '', 'error' => $login['error']];

    $page = sibnet_add_page();
    if (!$page['ok']) return ['ok' => false, 'videoid' => '', 'url' => '', 'embed' => '', 'error' => $page['error']];

    $fields = $page['fields'];
    $action = $page['action'] !== '' ? $page['action'] : 'https://video.sibnet.ru/add/';
    if (strpos($action, 'http') !== 0) {
        $action = 'https://video.sibnet.ru' . ($action[0] === '/' ? '' : '/') . $action;
    }

    $fields['title'] = $title !== '' ? $title : pathinfo($filePath, PATHINFO_FILENAME);
    if ($description !== '') $fields['description'] = $description;

    // multipart yuborish — file nomi 'file' deb qabul qilinadi, kerak bo'lsa
    // real forma tekshiruvidan keyin tuzatiladi.
    $fields['file'] = new CURLFile(realpath($filePath), mime_content_type($filePath), basename($filePath));

    $r = sibnet_http('POST', $action, [
        'fields' => $fields,
        'referer' => 'https://video.sibnet.ru/add/',
        'timeout' => 600,
    ]);

    $body = $r['body'];
    $videoid = '';
    if (preg_match('/(?:videoid|id)\s*[:=]\s*["\']?(\d+)["\']?/i', $body, $m)) $videoid = $m[1];
    if ($videoid === '' && preg_match('#/video(\d+)/#', $body, $m)) $videoid = $m[1];
    if ($videoid === '' && preg_match('#/shell\.php\?videoid=(\d+)#', $body, $m)) $videoid = $m[1];

    if ($videoid === '') {
        return ['ok' => false, 'videoid' => '', 'url' => '', 'embed' => '', 'error' => 'Upload javobidan videoid topilmadi. (status ' . $r['status'] . ')'];
    }
    return [
        'ok' => true,
        'videoid' => $videoid,
        'url' => 'https://video.sibnet.ru/video' . $videoid . '/',
        'embed' => 'https://video.sibnet.ru/shell.php?videoid=' . $videoid,
        'error' => '',
    ];
}

// ===== CLI rejimi =====
// Faqat ushbu fayl to'g'ridan-to'g'ri ishga tushirilganda ishlaydi
// (boshqa fayl ichidan require qilinganda argv ga aralashmaydi).
$__sibnet_cli_entry = PHP_SAPI === 'cli'
    && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__);
if ($__sibnet_cli_entry) {
    $cmd = $argv[1] ?? 'login';
    switch ($cmd) {
        case 'login':
            $r = sibnet_ensure_login();
            echo $r['ok'] ? "[OK] Login muvaffaqiyatli" . (!empty($r['reused']) ? ' (cookie qayta ishlatildi)' : '') . "\n"
                          : "[XATO] " . ($r['error'] ?? 'noma\'lum') . "\n";
            break;

        case 'add-info':
            $r = sibnet_ensure_login();
            if (!$r['ok']) { echo "[XATO] " . $r['error'] . "\n"; exit(1); }
            $p = sibnet_add_page();
            if (!$p['ok']) { echo "[XATO] " . $p['error'] . "\n"; exit(1); }
            $file = SIBNET_CACHE_DIR . '/sibnet_add_info.html';
            @file_put_contents($file, $p['html']);
            echo "[OK] /add/ sahifasi saqlandi: $file\n";
            echo "Form action: " . ($p['action'] !== '' ? $p['action'] : '(form topilmadi)') . "\n";
            echo "Inputlar (" . count($p['fields']) . "):\n";
            foreach ($p['fields'] as $k => $v) echo "  $k = " . substr($v, 0, 80) . "\n";
            break;

        case 'upload':
            $file = $argv[2] ?? '';
            $title = $argv[3] ?? '';
            $desc = $argv[4] ?? '';
            if ($file === '') { echo "Ishlatish: php includes/sibnet_upload.php upload <fayl> [title] [description]\n"; exit(1); }
            echo "Yuklanmoqda: $file\n";
            $r = sibnet_upload_video($file, $title, $desc);
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
