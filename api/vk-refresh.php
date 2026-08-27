<?php
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
$probe = vk_parse_url($video_url);
if (!$probe) {
    echo json_encode(['ok' => false, 'msg' => 'invalid_url'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dir = vk_cache_dir();
$key = $probe['oid'] . '_' . $probe['id'];
$file = $dir ? $dir . '/' . $key . '.json' : null;
if ($file && is_file($file)) @unlink($file);

$res = vk_resolve_video($video_url);

if ($res && !empty($res['best'])) {
    echo json_encode([
        'ok' => true,
        'url' => $res['best'],
    ], JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
}
