<?php
// Tekshiruv: yangi udp-player markup + config
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

$GLOBALS['current_lang'] = 'uz';

$pdo = new PDO('mysql:host=127.0.0.1;dbname=uzdub;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$id = 22;
$stmt = $pdo->prepare("SELECT * FROM content WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { echo "content topilmadi\n"; exit; }

$ep_stmt = $pdo->prepare("SELECT * FROM episodes WHERE content_id = ? ORDER BY season, episode_number");
$ep_stmt->execute([$id]);
$episodes = $ep_stmt->fetchAll();

$active_episode = $episodes[0] ?? null;
$active_episode_id = $active_episode ? (int)$active_episode['id'] : 0;

// next
$next_ep = null;
foreach ($episodes as $i => $ep) {
    if ((int)$ep['id'] === $active_episode_id && isset($episodes[$i + 1])) {
        $nep = $episodes[$i + 1];
        $next_ep = ['href' => 'watch.php?id=' . $id . '&ep=' . (int)$nep['id'], 'label' => t('player_next_ep') . ': ' . ($nep['title'] ?? ('Qism ' . (int)$nep['episode_number']))];
        break;
    }
}

$type = $active_episode['video_type'];
$url  = $active_episode['video_url'];
$intro_start = (int)$active_episode['intro_start'];
$intro_end = (int)$active_episode['intro_end'];

echo "=== content: {$item['title']}  type=$type  eps=" . count($episodes) . " ===" . "\n";

// resume position (user 6)
$resume_position = 0;
$rp = $pdo->prepare("SELECT position_seconds FROM watch_progress WHERE user_id = 6 AND content_id = ? AND episode_id = ?");
$rp->execute([$id, $active_episode_id]);
if ($row = $rp->fetch()) $resume_position = (int)$row['position_seconds'];
echo "resume_position=$resume_position\n";

$title = t_title($item) . ($active_episode ? ' — ' . ($active_episode['title'] ?? ('Qism ' . (int)$active_episode['episode_number'])) : '');

$html = render_player($type, $url, 'uploads/videos/', [], 'mainVideo', $item['poster'] ? poster_url($item['poster']) : null, [], $intro_start, $intro_end, $resume_position, $title, $next_ep);
echo "HTML length: " . strlen($html) . "\n";

// config ni tekshirish
if (preg_match('#data-udp-config="([^"]*)"#', $html, $m)) {
    $cfg = html_entity_decode($m[1], ENT_QUOTES);
    $j = json_decode($cfg, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "CONFIG JSON XATO: " . json_last_error_msg() . "\n";
    } else {
        echo "CONFIG OK: title={$j['title']}\n";
        echo "  resumeAt={$j['resumeAt']} intro={$j['introStart']}-{$j['introEnd']}\n";
        echo "  autoplay=" . var_export($j['autoplay'], true) . "\n";
        echo "  next=" . json_encode($j['next'], JSON_UNESCAPED_UNICODE) . "\n";
        echo "  strings.resume_title={$j['strings']['resume_title']}\n";
        echo "  qualities keys: " . implode(',', array_keys($j['qualities'])) . "\n";
    }
} else {
    echo "data-udp-config TOPILMADI!\n";
}
echo "---\n";
echo substr($html, 0, 500) . "\n";
