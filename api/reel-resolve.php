<?php
/**
 * Reel videosining to'g'ridan-to'g'ri mp4 havolasini oladi (VK).
 * Keshda bo'lmagan reel ijro etilishidan oldin mijoz shu endpoint'ni chaqiradi.
 * GET ?id=<reel_id>[&refresh=1]
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/reels.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'invalid_id']);
    exit;
}

if (!rate_limit_check($pdo, 'reel-resolve', 60, 60)) { rate_limit_deny_json(); }

$st = $pdo->prepare("SELECT video_url FROM reels WHERE id = ? AND is_active = 1");
$st->execute([$id]);
$reel = $st->fetch();
if (!$reel) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'not_found']);
    exit;
}

// Muddati o'tgan havola (403/404) bo'lsa mijoz refresh=1 bilan qayta so'raydi.
if (!empty($_GET['refresh'])) {
    $probe = vk_parse_url($reel['video_url']);
    $dir = vk_cache_dir();
    if ($probe && $dir) {
        $file = $dir . '/' . $probe['oid'] . '_' . $probe['id'] . '.json';
        if (is_file($file)) @unlink($file);
    }
}

$res = vk_resolve_video($reel['video_url']);
if (!$res || empty($res['best'])) {
    echo json_encode([
        'ok' => false,
        'embed' => vk_embed_src($reel['video_url'], ['autoplay' => 1, 'loop' => 1]),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'ok' => true,
    'src' => $res['best'],
    'sources' => $res['sources'] ?? [],
], JSON_UNESCAPED_SLASHES);
