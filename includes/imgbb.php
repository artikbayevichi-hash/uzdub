<?php
/**
 * freeimage.host cloud'ga rasm yuklash (profil rasmlari / chat fayllar uchun).
 *
 * API: https://freeimage.host/api/1/upload
 * Bepul, doimiy saqlash, 64MB chegarasi.
 *
 * Foydalanish:
 *   $url = freeimage_upload('/path/to/file.jpg');
 *   // $url => 'https://iili.io/...' yoki null xatoda
 */

function freeimage_api_key(): string {
    return '6d207e02198a847aa98d0a2a901485a5';
}

/**
 * Faylni freeimage.host'ga yuklab to'liq URL qaytaradi (default: doimiy saqlash).
 * @param string $filePath yuklanadigan fayl (temporary path)
 * @param int $expiration Rasm o'z-o'zidan o'chish muddati (sekundda). 0 = doimiy, o'chmaydi.
 * @return string|null to'liq URL yoki xatoda null
 */
function freeimage_upload(string $filePath, int $expiration = 0): ?string {
    if (!is_file($filePath)) return null;
    if (!function_exists('curl_init')) return null;

    $mime = (string)@mime_content_type($filePath);
    $file = new CURLFile($filePath, $mime, basename($filePath));

    $fields = [
        'key'    => freeimage_api_key(),
        'source' => $file,
        'format' => 'json',
    ];
    if ($expiration > 0) $fields['expiration'] = $expiration;

    $ch = curl_init('https://freeimage.host/api/1/upload');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $fields,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        CURLOPT_TIMEOUT   => 60,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    if (!$res) return null;
    $json = json_decode(trim((string)$res), true);
    if (!is_array($json) || empty($json['image']['url'])) return null;

    return $json['image']['url'];
}

/**
 * Umumiy rasm yuklash (avatar, cover, va h.k.) — max o'lchamni parametr sifatida qabul qiladi.
 * @return string|null to'liq URL yoki null/xato
 */
function imgbb_upload_image(array $file, int $maxBytes = 2 * 1024 * 1024, int $expiration = 0): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > $maxBytes) return null;

    $ext = strtolower((string)pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) return null;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) finfo_close($finfo);
    if (!$mime || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) return null;

    $prefix = preg_replace('/[^a-z0-9]/i', '', basename($file['name'], '.' . $ext));
    if (strlen($prefix) > 20) $prefix = substr($prefix, 0, 20);
    $filename = ($prefix ?: 'upload') . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $dest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;

    $url = freeimage_upload($dest, $expiration);
    @unlink($dest);
    return $url;
}

/**
 * Profil rasmini freeimage.host'ga yuklaydi (doimiy saqlash, bepul).
 * @return string|null to'liq URL (https://iili.io/...) yoki null/xato
 */
function imgbb_upload_avatar(array $file): ?string {
    return imgbb_upload_image($file, 2 * 1024 * 1024);
}

/**
 * Cover rasm freeimage.host'ga yuklaydi (kattaroq rasm, 5MB gacha).
 * @return string|null to'liq URL yoki null/xato
 */
function imgbb_upload_cover(array $file): ?string {
    return imgbb_upload_image($file, 5 * 1024 * 1024);
}

/**
 * Chat rasmlarini freeimage.host'ga yuklash uchun yordamchi:
 * validation (ext, mime, 5MB) + freeimage.host'ga upload.
 * @return string|null to'liq URL yoki null/xato
 */
function imgbb_upload_chat(array $file): ?string {
    return imgbb_upload_image($file, 5 * 1024 * 1024);
}

/**
 * imgbb API key (chek/vaqtinchalik rasmlar uchun). .env dagi IMGBB_API_KEY dan olinadi.
 */
function imgbb_api_key(): ?string {
    if (function_exists('env')) {
        $k = env('IMGBB_API_KEY', '');
        if ($k) return trim($k);
    }
    return '140044bf3e8401deab56d55626991cb9';
}

/**
 * Rasmni imgbb'ga yuklaydi va to'liq URL qaytaradi.
 * @param array $file $_FILES dagi fayl
 * @param int $maxBytes maksimal hajm (bayt)
 * @param int $expiration rasm o'z-o'zidan o'chish vaqti (sekund, 60-15552000). 0 = doimiy.
 * @return string|null to'liq URL (https://i.ibb.co/...) yoki null/xato
 */
function imgbb_upload(array $file, int $maxBytes = 5 * 1024 * 1024, int $expiration = 0): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ((int)$file['size'] > $maxBytes) return null;

    $ext = strtolower((string)pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) return null;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) finfo_close($finfo);
    if (!$mime || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) return null;

    $key = imgbb_api_key();
    if (!$key) return null;

    $m = (string)$mime;
    $cf = new CURLFile($file['tmp_name'], $m, $file['name']);

    $fields = [
        'key'   => $key,
        'image' => $cf,
    ];
    if ($expiration > 0) $fields['expiration'] = $expiration;

    $ch = curl_init('https://api.imgbb.com/1/upload');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $fields,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/131.0.0.0',
        CURLOPT_TIMEOUT        => 60,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$res || $code !== 200) return null;
    $json = json_decode(trim((string)$res), true);
    if (empty($json['success']) || empty($json['data']['url'])) return null;

    return $json['data']['url'];
}

/**
 * Chek (to'lov skrinshoti) rasmni imgbb'ga yuklaydi — 48 soatdan keyin avtomatik o'chadi.
 * @return string|null to'liq URL yoki null/xato
 */
function imgbb_upload_screenshot(array $file): ?string {
    return imgbb_upload($file, 5 * 1024 * 1024, 172800); // 172800 soniya = 48 soat
}
