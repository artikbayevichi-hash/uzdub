<?php
/**
 * Video manba URL'larini davriy tekshirish (cron).
 *
 * Barcha episodes'dagi video_url larni yig'ib, har birining manba (Rumble/RuTube/VK)
 * havolasini majburiy yangilab, avvalgi holat bilan solishtiradi. O'zgarish yoki
 * buzilish aniqlansa — video_source_log'ga yozadi va Telegram orqali xabar yuboradi.
 *
 * Ishga tushirish (kuniga bir marta tavsiya etiladi):
 *   Windows: schtasks /create /tn "UZDUB_video_check" /sc daily /st 06:00 /tr "C:\xampp\php\php.exe C:\xampp\htdocs\uzdub\api\video-source-check.php"
 *   Yoki brauzerda ochib qo'lda ishga tushirish mumkin.
 */
session_start();
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../includes/functions.php';

set_time_limit(300);

// Barcha video URL'larini yig'amiz (episodes + kontent videolari)
$episodes = $pdo->query("
    SELECT e.id, e.content_id, e.video_url
    FROM episodes e
    WHERE e.video_url IS NOT NULL AND e.video_url != ''
")->fetchAll(PDO::FETCH_ASSOC);

$contents = $pdo->query("
    SELECT c.id AS content_id, 0 AS id, c.video_url
    FROM content c
    WHERE c.video_url IS NOT NULL AND c.video_url != ''
      AND c.id NOT IN (SELECT DISTINCT content_id FROM episodes)
")->fetchAll(PDO::FETCH_ASSOC);

$all = array_merge($episodes, $contents);
$checked = 0;
$reported = 0;

foreach ($all as $row) {
    $video_url = trim($row['video_url']);
    $episode_id = (int)$row['id'];
    $content_id = (int)$row['content_id'];
    $type = video_source_type($video_url);
    if ($type === 'unknown' || $type === 'direct') continue;

    // Cache'ni majburiy o'chirib, yangi havola olamiz (o'zgarishni aniqlash uchun)
    $dir = vk_cache_dir();
    $file = null;
    if ($dir) {
        if ($type === 'rumble') {
            $k = rumble_parse_url($video_url);
            if ($k) $file = $dir . '/rumble_' . $k . '.json';
        } elseif ($type === 'rutube') {
            $k = rutube_parse_url($video_url);
            if ($k) $file = $dir . '/rutube_' . $k . '.json';
        } elseif ($type === 'vk') {
            $info = vk_parse_url($video_url);
            if ($info) $file = $dir . '/' . $info['oid'] . '_' . $info['id'] . '.json';
        }
        if ($file && is_file($file)) @unlink($file);
    }

    $hls = null;
    $ok = false;
    if ($type === 'rumble') {
        $hls = rumble_resolve_video($video_url);
        $ok = !empty($hls);
    } elseif ($type === 'rutube') {
        $res = rutube_resolve($video_url);
        $hls = $res['url'] ?? null;
        $ok = !empty($hls);
    } elseif ($type === 'vk') {
        $res = vk_resolve_video($video_url);
        $hls = $res['best'] ?? null;
        $ok = !empty($hls);
    }

    $before = video_source_get_state($episode_id, $content_id);
    video_source_track($episode_id, $content_id, $type, $video_url, $hls, $ok);
    $after = video_source_get_state($episode_id, $content_id);
    if ($before && $after && ($before['status'] !== $after['status'] || $before['video_url'] !== $after['video_url'])) {
        $reported++;
    }
    $checked++;
}

echo "Checked: $checked videos\n";
echo "Changed/broken: $reported\n";
