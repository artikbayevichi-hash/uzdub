<?php
/**
 * Pre-resolve endpoint — barcha episode URL'larini oldindan cache'laydi.
 * watch.php sahifasi yuklanganda background AJAX orqali chaqiriladi.
 * Barcha video turlari: VK, Sibnet, Mover, Rutube, Rumble, Odysee, Archive, OK.ru, Vimo, YouTube va h.k.
 *
 * POST: { urls: [{url:"https://...", type:"cloud"}, ...] }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$items = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $items = $input['urls'] ?? [];
} elseif (!empty($_GET['urls'])) {
    $items = json_decode($_GET['urls'], true);
}

if (!is_array($items) || empty($items)) {
    echo json_encode(['ok' => true, 'resolved' => 0]);
    exit;
}

$resolved = 0;
foreach ($items as $item) {
    $url = is_array($item) ? trim($item['url'] ?? '') : trim((string)$item);
    if ($url === '' || !preg_match('#^https?://#i', $url)) continue;

    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

    // Sibnet
    if (preg_match('#sibnet\.ru$#i', $host)) {
        if (sibnet_resolve_video($url)) $resolved++;
        continue;
    }
    // VK
    if (vk_parse_url($url)) {
        if (vk_resolve_video($url)) $resolved++;
        continue;
    }
    // Mover.uz
    if (mover_parse_url($url)) {
        if (mover_resolve_video($url)) $resolved++;
        continue;
    }
    // OK.ru
    if (okru_parse_url($url)) {
        if (okru_resolve_video($url)) $resolved++;
        continue;
    }
    // Vimeo
    if (vimo_parse_url($url)) {
        if (vimo_resolve_video($url)) $resolved++;
        continue;
    }
    // Rumble
    if (rumble_parse_url($url)) {
        if (rumble_resolve_video($url)) $resolved++;
        continue;
    }
    // Odysee
    if (odysee_parse_url($url)) {
        if (odysee_resolve_video($url)) $resolved++;
        continue;
    }
    // Archive.org
    if (archive_parse_url($url)) {
        if (archive_resolve_video($url)) $resolved++;
        continue;
    }
    // YouTube / Vimeo / Dailymotion (generic via yt-dlp)
    if (youtube_parse_url($url) || vimeo_parse_url($url) || dailymotion_parse_url($url)) {
        if (generic_resolve_video($url)) $resolved++;
        continue;
    }
}

echo json_encode(['ok' => true, 'resolved' => $resolved]);
