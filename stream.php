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

// Faqat ro'yxatdan o'tgan foydalanuvchilar video oqimini ko'rishi mumkin
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Lax'
    ]);
    session_start();
}
$stream_user = (int)($_SESSION['user_id'] ?? 0);
$stream_token = '';
if ($stream_user === 0) {
    // Mobil ilova (ExoPlayer/AVPlayer) cookie yubora olmaydi — video_stream API
    // orqali berilgan qisqa muddatli imzolangan token bilan ruxsat olinadi.
    $tok = trim($_GET['st'] ?? '');
    $tokParts = explode('.', $tok);
    if (count($tokParts) === 3) {
        $tokUid = (int)$tokParts[0];
        $tokExp = (int)$tokParts[1];
        $tokSig = (string)$tokParts[2];
        if ($tokUid > 0 && $tokExp > time() && $tokExp <= time() + 21600
            && hash_equals(hash_hmac('sha256', $tokUid . '|' . $tokExp, STREAM_TOKEN_SECRET), $tokSig)) {
            $stream_user = $tokUid;
            $stream_token = $tok;
        }
    }
}
if ($stream_user === 0) {
    header('Location: ' . ROOT_URL . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
// Streaming uzoq davom etishi mumkin — sessiya lock'ini bo'shatamiz, aks holda
// xuddi shu foydalanuvchining parallel (seek) so'rovlari sessiya faylini kutib
// bloklanib qoladi.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$tg = trim($_GET['tg'] ?? '');
$url = trim($_GET['url'] ?? '');
$tgCandidates = [];

if ($tg !== '') {
    $tokens = [];
    if (TG_ADMIN_BOT_TOKEN) $tokens[] = TG_ADMIN_BOT_TOKEN;
    if (TG_BOT_TOKEN && TG_BOT_TOKEN !== TG_ADMIN_BOT_TOKEN) $tokens[] = TG_BOT_TOKEN;
    if (!$tokens) {
        http_response_code(500);
        die('Telegram bot token sozlanmagan');
    }
    if (strpos($tg, '..') !== false || strpos($tg, '\\') !== false || preg_match('#^/#', $tg)) {
        http_response_code(403);
        die('Access denied');
    }
    foreach ($tokens as $tok) {
        $tgCandidates[] = 'https://api.telegram.org/file/bot' . $tok . '/' . ltrim($tg, '/');
    }
    $url = $tgCandidates[0];
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
    if ((ord($bin[0]) & 0xFE) === 0xFC) return true;                          // fc00::/7 ULA
    if ((ord($bin[0]) & 0xFF) === 0xFE && (ord($bin[1]) & 0xC0) === 0x80) return true; // fe80::/10 link-local
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
    // Protocol-relative: //host/path
    if (strpos($location, '//') === 0) return 'https:' . $location;
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
        $req_opts = $opts;
        // Odysee CDN Referer talab qiladi (aks holda 401 qaytaradi)
        if (stripos($current, 'player.odycdn.com') !== false) {
            $req_opts[CURLOPT_REFERER] = 'https://odysee.com/';
        }
        // Sibnet hotlink himoyasi — Referer kerak
        if (stripos($current, 'video.sibnet.ru') !== false) {
            $req_opts[CURLOPT_REFERER] = 'https://video.sibnet.ru/';
        }
        curl_setopt_array($ch, $req_opts + [
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
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
    ], $allow_loopback);
    $fileSize = 0;
    $acceptRanges = 'bytes';
    $errCode = '';
    $resolvedUrl = $url;

    if (isset($res['error'])) return [0, '', 0, 'none', '', $url];
    $status = $res['status'];
    $headers = $res['body'];
    if (!empty($res['url'])) $resolvedUrl = $res['url'];

    // Content-Type va Content-Length ni headerlardan o'qiymiz
    $contentType = '';
    if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $m)) $contentType = trim($m[1]);
    if (preg_match('/Content-Length:\s*(\d+)/i', $headers, $m)) $fileSize = (int)$m[1];

    // Ba'zi serverlar HEAD'ni qo'llamaydi -> Range GET bilan proba
    if ($status >= 400 || $status === 0 || !$fileSize) {
        $res2 = stream_request($url, [
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_RANGE => '0-0',
        ], $allow_loopback);
        if (isset($res2['error'])) return [0, '', 0, 'none', '', $url];
        $status = $res2['status'];
        $body = $res2['body'];
        if (!empty($res2['url'])) $resolvedUrl = $res2['url'];
        if (preg_match('/Content-Range:\s*bytes\s+0-0\/(\d+)/i', $body, $m)) $fileSize = (int)$m[1];
        if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $body, $m)) $contentType = trim($m[1]);
        if (!preg_match('/Accept-Ranges:\s*bytes/i', $body)) $acceptRanges = 'none';
        if (preg_match('/"value"\s*:\s*"([^"]+)"/i', $body, $m)) $errCode = $m[1];
    }

    return [$status, $contentType, $fileSize, $acceptRanges, $errCode, $resolvedUrl];
}

// ================= HLS (m3u8) playlist proxy =================
// RuTube kabi HLS manbalari uchun: playlist ichidagi barcha havolalar o'z serverimizga
// qayta yoziladi (segmentlar ham shu endpoint orqali oqimlanadi). Shunda brauzer CORS
// muammosisiz hls.js bilan o'ynata oladi. Playlist so'rovlarini farqlash uchun ?hls=1.
$hls = ($_GET['hls'] ?? '') === '1';
if ($hls) {
    $pl_res = stream_request($url, [
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ], $allow_loopback, 5);
    if (isset($pl_res['error']) || (int)($pl_res['status'] ?? 0) >= 400) {
        http_response_code(502);
        die('Failed to fetch HLS playlist');
    }
    $pl_body = $pl_res['body'];
    $sep = strpos($pl_body, "\r\n\r\n");
    if ($sep !== false) $pl_body = substr($pl_body, $sep + 4);
    $pl_base = $pl_res['url'];

    $rewritten = [];
    foreach (preg_split('/\r?\n/', $pl_body) as $line) {
        $t = trim($line);
        if ($t === '' || $t[0] === '#') {
            $rewritten[] = $line;
            continue;
        }
        $abs = stream_resolve_redirect($pl_base, $t);
        if ($abs === '') {
            $rewritten[] = $line;
            continue;
        }
        // Faqat m3u8 (playlist) havolalari qayta yozish orqali o'tadi; segmentlar (.ts)
        // oddiy range-proxy yo'li bilan oqimlanadi (hls=1 segmentni playlist deb adashmaslik uchun).
        // Playlist havolalari .m3u8 oxiri bilan beriladi (PATH_INFO) — mobil pleyerlar
        // (ExoPlayer/AVPlayer) HLS'ni URL kengaytmasi orqali aniqlaydi.
        $is_m3u8 = (bool)preg_match('~\.m3u8(?:[?#].*)?$~i', $abs);
        $tokSuffix = $stream_token !== '' ? '&st=' . rawurlencode($stream_token) : '';
        $rewritten[] = ROOT_URL . '/stream.php' . ($is_m3u8 ? '/playlist.m3u8' : '') . '?url=' . rawurlencode($abs) . ($is_m3u8 ? '&hls=1' : '') . $tokSuffix;
    }

    header('Content-Type: application/vnd.apple.mpegurl');
    header('Content-Disposition: inline');
    header('X-Content-Type-Options: nosniff');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: private, max-age=3600');
    echo implode("\n", $rewritten);
    exit;
}

// Get file info — birinchi ishlaydigan token (admin bot, so'ng video bot)
// DIQQAT: /dl/<msg_id> havolasi birinchi marta ochilganda video hali diskka
// yuklab olinayotgan bo'lishi mumkin (bot avval yuklab bo'lgach link beradi,
// lekin eski/avtomatik havolalarda yuklash hozir boshlanadi). Probe muvaffaq
// bo'lmaguncha bir necha marta KUTIB qayta urinamiz — aks holda "Video
// yuklanmadi" xatosi chiqib qolardi (probe 502 qaytaradi).
$candidates = $tgCandidates ?: [$url];
$probe = null;
$lastProbe = [0, '', 0, 'none', ''];
// Probe qayta urinishi: ilgari 12s*8 = ~96s osilib qolardi ("Video yuklanmoqda...").
// Endi qattiq xatolarda (500/403/404...) darhol chiqamiz; faqat vaqtinchalik
// holatlarda (502/503/ulanish xatosi) 3s tanaffus bilan qayta urinamiz (maks ~9s).
for ($attempt = 0; $attempt < 4 && !$probe; $attempt++) {
    foreach ($candidates as $cand) {
        if (!stream_validate_host($cand, $allow_loopback)) continue;
        [$h, $ct, $fs, $ar, $ec, $resolved] = http_probe($cand, $allow_loopback);
        if ($h >= 200 && $h < 400) {
            $url = $resolved;
            $probe = [$h, $ct, $fs, $ar];
            break;
        }
        $lastProbe = [$h, $ct, $fs, $ar, $ec];
        // Pixeldrain va o'xshashlar: ulanishlar band / hotlink — bu vaqtinchalik yoki
        // doimiy himoya; qayta urinish foyda bermaydi, darhol chiqamiz.
        if (in_array($ec, ['max_concurrent_downloads', 'hotlink_detected', 'rate_limited', 'insufficient_balance'], true)) {
            $attempt = 99;
            break 2;
        }
        // Qattiq xato (500/403/404/408...): pleyer darhol xato ko'rishi kerak.
        if ($h >= 400 && !in_array($h, [502, 503], true)) {
            $attempt = 99;
            break 2;
        }
    }
    if (!$probe && $attempt < 3) sleep(3);
}
if (!$probe) {
    [$httpCode, $contentType, $fileSize, $acceptRanges, $errCode] = $lastProbe;
    $friendly = [
        'max_concurrent_downloads' => 'Yuklab olishlar band — boshqa videolar yopilgach qayta urinib ko\'ring.',
        'hotlink_detected'         => 'Manba hotlink himoyasiga ega — uni o\'ynatib bo\'lmaydi.',
        'rate_limited'             => 'Manba so\'rovlar chegarasiga yetdi.',
        'insufficient_balance'     => 'Manba akkaunti balansi yetarli emas.',
    ];
    $msg = $friendly[$errCode] ?? 'Uzoq manbadan video olinmadi';
    http_response_code(503);
    die($msg);
}
[$httpCode, $contentType, $fileSize, $acceptRanges] = $probe;

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
header('Cache-Control: private, max-age=3600');
if ($acceptRanges === 'bytes') header('Accept-Ranges: bytes');

// Handle Range requests (for seeking support)
$range = $_SERVER['HTTP_RANGE'] ?? '';
$start = 0;
$end = ($fileSize > 0) ? ($fileSize - 1) : null;
$isRange = false;
if ($range !== '' && preg_match('/bytes\s*=\s*([^,\s]+)/i', $range, $rm)) {
    // Ko'p qismli Range (a-b,c-d) bo'lsa birinchi qismini ishlatamiz.
    // Suffix range (bytes=-N) ham qo'llab-quvvatlanadi.
    if (preg_match('/^(\d*)-(\d*)$/', $rm[1], $m)) {
        $a = ($m[1] !== '') ? (int)$m[1] : null;
        $b = ($m[2] !== '') ? (int)$m[2] : null;
        if ($a === null && $b !== null) {
            // bytes=-N: oxirgi N bayt (Safari moov atomini shu bilan so'raydi)
            $start = ($fileSize > 0) ? max(0, $fileSize - $b) : 0;
            $end = ($fileSize > 0) ? ($fileSize - 1) : null;
            $isRange = true;
        } elseif ($a !== null) {
            $start = $a;
            if ($b === null) {
                $end = ($fileSize > 0) ? ($fileSize - 1) : null;
            } else {
                $end = ($fileSize > 0 && $b >= $fileSize) ? ($fileSize - 1) : $b;
            }
            $isRange = true;
        }
    }
    if ($isRange && $fileSize > 0 && $start >= $fileSize) {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }
    if ($isRange && $end !== null && $end < $start) {
        http_response_code(416);
        header('Content-Range: bytes */' . ($fileSize > 0 ? $fileSize : '*'));
        exit;
    }
    if ($isRange) {
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . ($fileSize > 0 ? $fileSize : '*'));
        header('Content-Length: ' . ($end - $start + 1));
    } elseif ($fileSize > 0) {
        header('Content-Length: ' . $fileSize);
    }
} elseif ($fileSize > 0) {
    header('Content-Length: ' . $fileSize);
}

// Stream the file — cURL output directly to the browser (redirect-safe)
$current = $url;
$rangeSpec = null;
if ($isRange) {
    // DIQQAT: CURLOPT_RANGE "bytes=" PREFIKSISIZ bo'lishi kerak.
    $rangeSpec = $start . '-' . $end;
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
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_BUFFERSIZE => 65536,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_CONNECTTIMEOUT => 15,
    ];
    if ($rangeSpec !== null) $opts[CURLOPT_RANGE] = $rangeSpec;
    if (stripos($current, 'player.odycdn.com') !== false) $opts[CURLOPT_REFERER] = 'https://odysee.com/';
    if (stripos($current, 'video.sibnet.ru') !== false) $opts[CURLOPT_REFERER] = 'https://video.sibnet.ru/';
    curl_setopt_array($ch, $opts);
    curl_exec($ch);
    $finalStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalErr = curl_error($ch);
    curl_close($ch);

    if ($finalStatus >= 400 || ($finalStatus === 0 && $finalErr)) {
        http_response_code(502);
        die('Upstream error');
    }
    break;
}
