<?php
/**
 * serve.php — Minimal authenticated cached video file server
 *
 * Hozirgi stream.php har bir so'rovda session_start + auth check qiladi.
 * Bu og'irlik keltiradi (10-50ms PHP overhead har bir so'rovda).
 *
 * serve.php faqat HMAC token tekshiradi va faylni beradi:
 *   - session_start YO'Q (10-30ms tejamkorlik)
 *   - database YO'Q
 *   - curl YO'Q
 *   - faqat: HMAC verify → readfile()
 *
 * 1,000,000 tomoshabin = 1M Apache socket (fayl o'qish) = ozgina RAM
 * 1,000,000 tomoshabin = 1M PHP process = 3GB+ RAM, crash
 */

require_once __DIR__ . '/config/payment.php';

$key = STREAM_TOKEN_SECRET;

// URL parametrlar
$fileHash = trim($_GET['h'] ?? '');
$expiry   = (int)($_GET['e'] ?? 0);
$sig      = trim($_GET['s'] ?? '');
$ref      = trim($_GET['ref'] ?? '');

// Validatsiya
if ($fileHash === '' || $expiry === 0 || $sig === '') {
    http_response_code(400);
    exit('Missing parameters');
}

// Token muddati tugagan → stream.php ga redirect (yangi token olish uchun)
if (time() > $expiry) {
    $streamUrl = ROOT_URL . '/stream.php?url=' . rawurlencode($ref) . '&ref=' . rawurlencode($ref);
    http_response_code(302);
    header('Location: ' . $streamUrl);
    header('Cache-Control: no-store');
    exit;
}

// HMAC tekshirish
$expected = hash_hmac('sha256', $fileHash . '|' . $expiry, $key);
if (!hash_equals($expected, $sig)) {
    http_response_code(403);
    exit('Invalid signature');
}

// Cache fayl yo'lini topish
$cacheDir = __DIR__ . '/cache/video/' . substr($fileHash, 0, 2);
$dataFile = $cacheDir . '/' . $fileHash . '.bin';
$metaFile = $cacheDir . '/' . $fileHash . '.json';

if (!is_file($dataFile) || filesize($dataFile) === 0) {
    // Cache topilmadi → stream.php ga qaytaramiz
    http_response_code(302);
    header('Location: ' . ROOT_URL . '/stream.php?url=' . urlencode($ref) . '&ref=' . urlencode($ref));
    exit;
}

// Meta ma'lumotlarni o'qish
$meta = @json_decode(@file_get_contents($metaFile), true);
$ct = $meta['ct'] ?? 'video/mp4';
$fSize = filesize($dataFile);

// Referer check — faqat o'z saytimiz
$_referer = $_SERVER['HTTP_REFERER'] ?? '';
$_origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$_host    = $_SERVER['HTTP_HOST'] ?? '';
if ($_host !== '') {
    $ok = false;
    if ($_referer !== '' && strpos($_referer, '://' . $_host) !== false) $ok = true;
    if ($_origin !== '' && strpos($_origin, '://' . $_host) !== false) $ok = true;
    if ($_referer === '' && $_origin === '') $ok = true;
    if (!$ok) {
        http_response_code(403);
        exit('Hotlink denied');
    }
}

// ===== Response headers =====
header('Content-Type: ' . $ct);
header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: public, max-age=604800');
header('Accept-Ranges: bytes');
$origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_host;
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Expose-Headers: Content-Range, Accept-Ranges, Content-Length, Content-Disposition');

// ===== Range handling (seeking) =====
$range = $_SERVER['HTTP_RANGE'] ?? '';
if ($range !== '' && preg_match('/bytes\s*=\s*([^,\s]+)/i', $range, $rm)) {
    if (preg_match('/^(\d*)-(\d*)$/', $rm[1], $m)) {
        $a = ($m[1] !== '') ? (int)$m[1] : null;
        $b = ($m[2] !== '') ? (int)$m[2] : null;
        if ($a === null && $b !== null) {
            $start = max(0, $fSize - $b);
            $end = $fSize - 1;
        } elseif ($a !== null) {
            $start = $a;
            $end = ($b === null || $b >= $fSize) ? $fSize - 1 : $b;
        } else {
            $start = 0;
            $end = $fSize - 1;
        }
        if ($start >= $fSize) {
            http_response_code(416);
            header('Content-Range: bytes */' . $fSize);
            exit;
        }
        http_response_code(206);
        $len = $end - $start + 1;
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fSize);
        header('Content-Length: ' . $len);
        $fp = fopen($dataFile, 'rb');
        if ($fp) {
            fseek($fp, $start);
            $remaining = $len;
            while ($remaining > 0 && !feof($fp)) {
                $chunk = min(65536, $remaining);
                echo fread($fp, $chunk);
                $remaining -= $chunk;
                if (ob_get_level()) ob_flush();
                flush();
            }
            fclose($fp);
        }
        exit;
    }
}

// Full file
header('Content-Length: ' . $fSize);
readfile($dataFile);
if (ob_get_level()) ob_flush();
flush();
