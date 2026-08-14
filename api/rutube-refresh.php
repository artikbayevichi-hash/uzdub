<?php
/**
 * RuTube HLS havolasini majburiy yangilash.
 * Keshlangan m3u8 URL muddati o'tgan bo'lsa, player buni avtomatik chaqiradi
 * va yangi variant URL olinadi — video "o'chib qolmasligi" uchun.
 *
 * ?url=<rutube video havolasi>
 * Qaytadi: {"ok":true,"url":"/stream.php?url=...&hls=1"} yoki {"ok":false}
 */
session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_user()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'login_required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$video_url = trim($_GET['url'] ?? '');
$id = rutube_parse_url($video_url);
if (!$id) {
    echo json_encode(['ok' => false, 'msg' => 'invalid_url'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Eski cache'ni o'chirib, yangisini so'raymiz
$dir = vk_cache_dir();
$file = $dir ? $dir . '/rutube_' . $id . '.json' : null;
$old_hls = null;
if ($file && is_file($file)) {
    $old_cache = @json_decode(@file_get_contents($file), true);
    if (is_array($old_cache) && !empty($old_cache['url'])) $old_hls = $old_cache['url'];
    @unlink($file);
}

$res = rutube_resolve($video_url);

// Manba o'zgarishini kuzatamiz (episode yoki content jadvalidan episode_id topamiz)
if ($res && !empty($res['url'])) {
    try {
        $ep = $pdo->prepare("SELECT e.id, e.content_id FROM episodes e JOIN content c ON c.id = e.content_id WHERE e.video_url = ? LIMIT 1");
        $ep->execute([$video_url]);
        $ep_row = $ep->fetch(PDO::FETCH_ASSOC);
        if ($ep_row) {
            video_source_track($ep_row['id'], $ep_row['content_id'], 'rutube', $video_url, $res['url'], true);
        } else {
            $ct = $pdo->prepare("SELECT id FROM content WHERE video_url = ? LIMIT 1");
            $ct->execute([$video_url]);
            $ct_row = $ct->fetch(PDO::FETCH_ASSOC);
            if ($ct_row) {
                video_source_track(0, $ct_row['id'], 'rutube', $video_url, $res['url'], true);
            }
        }
    } catch (PDOException $e) {}
}

if ($res && !empty($res['url'])) {
    echo json_encode([
        'ok' => true,
        'url' => ROOT_URL . '/stream.php?url=' . rawurlencode($res['url']) . '&hls=1',
    ], JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
}
