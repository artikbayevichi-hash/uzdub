<?php
/**
 * Stream proxy — videolarni brauzerda to'g'ridan-to'g'ri pleyerda ko'rsatadi
 * Yuklab olishni oldini oladi (Content-Disposition: inline)
 * HTTP Range (seeking) ni qo'llab-quvvatlaydi
 *
 * Parametrlar:
 *   ?url=<https://...>  — istalgan http/https video URL
 *   ?tg=<file_path>     — Telegram Bot API file_path (token server tomonda, URL da yo'q)
 *
 * Xavfsizlik: SSRF himoyasi — barcha DNS yozuvlari (A/AAAA) tekshiriladi,
 * shaxsiy/reserved IP'lar bloklanadi, redirect'lar har qadamda qayta tekshiriladi.
 */

require_once __DIR__ . '/config/payment.php';

$tg = trim($_GET['tg'] ?? '');
$url = trim($_GET['url'] ?? '');

if ($tg !== '') {
    if (!TG_BOT_TOKEN) {
        http_response_code(500);
        die('Telegram bot token sozlanmagan');
    }
    if (strpos($tg, '..') !== false || strpos($tg, '\\') !== false || preg_match('#^/#', $tg)) {
        http_response_code(403);
        die('Access denied');
    }
    $url = 'https://api.telegram.org/file/bot' . TG_BOT_TOKEN . '/' . ltrim($tg, '/');
} elseif ($url === '') {
    http_response_code(400);
    die('Missing url parameter');
}

// Allow only http/https URLs (no filesystem paths like ../)
if (strpos($url, '..') !== false || strpos($url, '\\') !== false) {
    http_response_code(403);
    die('Access denied');
}

// If relative path, prepend site base URL
if (!preg_match('#^https?://#i', $url)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $url = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/' . ltrim($url, '/');
}

// ================= SSRF himoyasi =================
// Loopback (127.0.0.1) faqat .env da ALLOW_LOOPBACK_STREAM=true bo'lsa ruxsat (Telegram yuklab
// oluvchi server shu kompyuterda ishlaganda). Sukut bo'yicha YOPIQ.
$allow_loopback = env('ALLOW_LOOPBACK_STREAM', 'false') !== 'false';

function stream_is_private_ip(string $ip, bool $allow_loopback): bool {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $l = ip2long($ip);
        if ($l === false) return true;
        if (($l & 0xFF000000) === 0x7F000000) return !$allow_loopback;   // 127/8 loopback
        if (($l & 0xFF000000) === 0x0A000000) return true;               // 10/8
        if (($l & 0xFFF00000) === 0xAC100000) return true;               // 172.16/12
        if (($l & 0xFFFF0000) === 0xC0A80000) return true;               // 192.168/16
        if (($l & 0xFFFF0000) === 0xA9FE0000) return true;               // 169.254/16
        if (($l & 0xC0000000) === 0x64400000) return true;               // 100.64/10 CGNAT
        if ($l === 0) return true;                                       // 0/8
        if (($l & 0xE0000000) === 0xE0000000) return true;               // 224/4 multicast
        if (($l & 0xF0000000) === 0xF0000000) return true;               // 240/4 reserved
        return false;
    }
    $ip = strtolower($ip);
    if ($ip === '::1') return !$allow_loopback;
    if (strpos($ip, '::ffff:') === 0) {
        return stream_is_private_ip(substr($ip, 7), $allow_loopback);    // v4-mapped
    }
    if ($ip === '::') return true;
    $bin = @inet_pton($ip);
    if ($bin === false) return true;
    if (($bin[0] & 0xFE) === 0xFC) return true;                          // fc00::/7 ULA
    if (($bin[0] & 0xFF) === 0xFE && ($bin[1] & 0xC0) === 0x80) return true; // fe80::/10 link-local
    return false;
}

function stream_validate_host(string $url, bool $allow_loopback): bool {
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return false;
    if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) return false;
    if (isset($parts['user']) || isset($parts['pass'])) return false;
    $host = strtolower($parts['host']);
    if ($host === '' || $host[0] === '[') return false; // bare-IP IPv6 not accepted

    // Agar host to'g'ridan-to'g'ri IP bo'lsa
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return !stream_is_private_ip($host, $allow_loopback);
    }

    // BARCHA DNS yozuvlarini tekshiramiz (DNS rebinding uchun)
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    if ($records) {
        $checked = 0;
        foreach ($records as $r) {
            $ip = $r['type'] === 'AAAA' ? ($r['ipv6'] ?? '') : ($r['ip'] ?? '');
            if ($ip === '') continue;
            $checked++;
            if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
            if (stream_is_private_ip($ip, $allow_loopback)) return false;
        }
        if ($checked > 0) return true;
    }

    // DNS yozuv topilmasa — yakuniy tekshiruv (sokin hostlar uchun)
    $ip = @gethostbyname($host);
    if ($ip === false || $ip === $host) return false;
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    return !stream_is_private_ip($ip, $allow_loopback);
}

// Redirect URL'ni (nisbiy bo'lishi mumkin) mutlaq qilish
function stream_resolve_redirect(string $current, string $location): string {
    if (preg_match('#^https?://#i', $location)) return $location;
    $parts = parse_url($current);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return '';
    $base = $parts['scheme'] . '://' . $parts['host'];
    if (isset($parts['port'])) $base .= ':' . $parts['port'];
    if ($location === '') return $base . '/';
    if ($location[0] === '/') return $base . $location;
    $path = $parts['path'] ?? '/';
    $dir = substr($path, 0, strrpos($path, '/') + 1);
    return $base . $dir . $location;
}

if (!stream_validate_host($url, $allow_loopback)) {
    http_response_code(403);
    die('Access denied');
}

// HTTP so'rovni redirect-safe bajarish (har bir hop tekshiriladi)
function stream_request(string $url, array $opts, bool $allow_loopback, int $max_redirects = 5): array {
    $current = $url;
    for ($i = 0; $i <= $max_redirects; $i++) {
        if (!stream_validate_host($current, $allow_loopback)) return ['error' => 'blocked'];
        $ch = curl_init($current);
        curl_setopt_array($ch, $opts + [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
        ]);
        $resp = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) return ['error' => $err];
        if ($status >= 300 && $status < 400 && $redirect) {
            $next = stream_resolve_redirect($current, $redirect);
            if ($next === '') return ['error' => 'invalid redirect'];
            $current = $next;
            continue;
        }
        return ['status' => $status, 'body' => $resp, 'url' => $current];
    }
    return ['error' => 'too many redirects'];
}

function http_probe($url, $allow_loopback) {
    // Avval HEAD bilan urinamiz
    $res = stream_request($url, [
        CURLOPT_NOBODY => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
    ], $allow_loopback);
    $fileSize = 0;
    $acceptRanges = 'bytes';

    if (isset($res['error'])) return [0, '', 0, 'none'];
    $status = $res['status'];
    $headers = $res['body'];

    // Content-Type va Content-Length ni headerlardan o'qiymiz
    $contentType = '';
    if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $m)) $contentType = trim($m[1]);
    if (preg_match('/Content-Length:\s*(\d+)/i', $headers, $m)) $fileSize = (int)$m[1];

    // Ba'zi serverlar HEAD'ni qo'llamaydi -> Range GET bilan proba
    if ($status >= 400 || $status === 0 || !$fileSize) {
        $res2 = stream_request($url, [
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_RANGE => '0-0',
        ], $allow_loopback);
        if (isset($res2['error'])) return [0, '', 0, 'none'];
        $status = $res2['status'];
        $body = $res2['body'];
        if (preg_match('/Content-Range:\s*bytes\s+0-0\/(\d+)/i', $body, $m)) $fileSize = (int)$m[1];
        if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $body, $m)) $contentType = trim($m[1]);
        if (!preg_match('/Accept-Ranges:\s*bytes/i', $body)) $acceptRanges = 'none';
    }

    return [$status, $contentType, $fileSize, $acceptRanges];
}

// Get file info
[$httpCode, $contentType, $fileSize, $acceptRanges] = http_probe($url, $allow_loopback);

if ($httpCode >= 400 || $httpCode === 0) {
    http_response_code(502);
    die('Failed to fetch remote resource');
}

// Faqat video-ga o'xshash kontent oqimlanadi (ochiq proksi sifatida ishlatilishining oldini oladi)
$ctype = strtolower(trim(explode(';', $contentType)[0]));
$is_video_like = (
    strpos($ctype, 'video/') === 0 ||
    in_array($ctype, ['application/octet-stream', 'application/x-mpegurl', 'application/vnd.apple.mpegurl', 'application/dash+xml', 'audio/mp4', 'audio/mpeg'], true)
);
if (!$contentType || !$is_video_like) {
    http_response_code(415);
    die('Unsupported content type');
}

// Set response headers
header('Content-Type: ' . $contentType);
header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Expose-Headers: Content-Range, Accept-Ranges, Content-Length, Content-Disposition');
header('Cache-Control: no-store');
if ($acceptRanges === 'bytes') header('Accept-Ranges: bytes');

// Handle Range requests (for seeking support)
$range = $_SERVER['HTTP_RANGE'] ?? '';
$start = 0;
$end = ($fileSize > 0) ? ($fileSize - 1) : null;
if ($range) {
    if (preg_match('/bytes=(\d+)-(\d*)/', $range, $m)) {
        $start = (int)$m[1];
        if ($m[2] !== '') $end = (int)$m[2];
        if ($fileSize > 0 && $start >= $fileSize) {
            http_response_code(416);
            header('Content-Range: bytes */' . $fileSize);
            exit;
        }
        if ($fileSize > 0 && $end >= $fileSize) $end = $fileSize - 1;
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . ($fileSize > 0 ? $fileSize : '*'));
        header('Content-Length: ' . ($end - $start + 1));
    } else {
        http_response_code(416);
        header('Content-Range: bytes */' . ($fileSize > 0 ? $fileSize : '*'));
        exit;
    }
} elseif ($fileSize > 0) {
    header('Content-Length: ' . $fileSize);
}

// Stream the file — cURL output directly to the browser (redirect-safe)
$current = $url;
$rangeSpec = null;
if ($range || $start > 0) {
    // DIQQAT: CURLOPT_RANGE "bytes=" PREFIKSISIZ bo'lishi kerak.
    $rangeSpec = $start . '-' . ($end !== null ? $end : '');
} elseif ($acceptRanges === 'none') {
    $rangeSpec = '0-';
}

for ($i = 0; $i <= 5; $i++) {
    if (!stream_validate_host($current, $allow_loopback)) {
        http_response_code(403);
        die('Access denied');
    }
    $ch = curl_init($current);
    $opts = [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_BUFFERSIZE => 65536,
    ];
    if ($rangeSpec !== null) $opts[CURLOPT_RANGE] = $rangeSpec;
    curl_setopt_array($ch, $opts);
    curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    if ($status >= 300 && $status < 400 && $redirect) {
        $next = stream_resolve_redirect($current, $redirect);
        if ($next === '') break;
        $current = $next;
        continue;
    }
    break;
}
