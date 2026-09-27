<?php
/**
 * UZDUB — Reels (qisqa vertikal videolar) yordamchi funksiyalari.
 * Videolar VK Video'da saqlanadi; playerga to'g'ridan-to'g'ri mp4 havolasi beriladi
 * (vk_resolve_video), keshda bo'lmasa mijoz api/reel-resolve.php orqali so'raydi.
 */

require_once __DIR__ . '/functions.php';

const REELS_PAGE_SIZE = 5;

const REELS_SELECT = "SELECT r.id, r.title, r.description, r.video_url, r.thumb, r.content_id, r.views,
               (SELECT COUNT(*) FROM reel_likes rl WHERE rl.reel_id = r.id) AS likes,
               c.title AS content_title, c.poster AS content_poster
        FROM reels r
        LEFT JOIN content c ON c.id = r.content_id";

function reels_fetch(PDO $pdo, int $offset = 0, int $limit = REELS_PAGE_SIZE, ?int $user_id = null): array {
    $limit = max(1, min(20, $limit));
    $offset = max(0, $offset);

    $sql = REELS_SELECT . " WHERE r.is_active = 1
            ORDER BY r.sort_order DESC, r.id DESC
            LIMIT $limit OFFSET $offset";

    try {
        $rows = $pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        error_log('reels_fetch error: ' . $e->getMessage());
        return [];
    }

    return reels_map_rows($pdo, $rows, $user_id);
}

// Chuqur havola (?id=) uchun bitta reel — lentaning boshiga qo'yiladi.
function reels_fetch_one(PDO $pdo, int $id, ?int $user_id = null): ?array {
    try {
        $st = $pdo->prepare(REELS_SELECT . " WHERE r.is_active = 1 AND r.id = ?");
        $st->execute([$id]);
        $row = $st->fetch();
    } catch (PDOException $e) {
        error_log('reels_fetch_one error: ' . $e->getMessage());
        return null;
    }
    if (!$row) return null;
    $mapped = reels_map_rows($pdo, [$row], $user_id);
    return $mapped[0] ?? null;
}

function reels_map_rows(PDO $pdo, array $rows, ?int $user_id): array {
    if (!$rows) return [];

    $liked = [];
    if ($user_id) {
        $ids = array_column($rows, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT reel_id FROM reel_likes WHERE user_id = ? AND reel_id IN ($in)");
        $st->execute(array_merge([$user_id], $ids));
        $liked = array_flip(array_column($st->fetchAll(), 'reel_id'));
    }

    $out = [];
    foreach ($rows as $r) {
        $cached = vk_cached_video($r['video_url']);
        $out[] = [
            'id' => (int)$r['id'],
            'title' => (string)$r['title'],
            'description' => (string)($r['description'] ?? ''),
            'thumb' => reels_thumb_url($r),
            'content_id' => $r['content_id'] ? (int)$r['content_id'] : null,
            'content_title' => $r['content_title'] ?? null,
            'content_url' => $r['content_id'] ? ROOT_URL . '/watch.php?id=' . (int)$r['content_id'] : null,
            'views' => (int)$r['views'],
            'likes' => (int)$r['likes'],
            'liked' => isset($liked[$r['id']]),
            'src' => $cached['best'] ?? null,
            'embed' => vk_embed_src($r['video_url'], ['autoplay' => 1, 'loop' => 1]),
        ];
    }
    return $out;
}

function reels_thumb_url(array $row): ?string {
    $thumb = trim((string)($row['thumb'] ?? ''));
    if ($thumb !== '') {
        return preg_match('#^https?://#i', $thumb) ? $thumb : ROOT_URL . '/' . ltrim($thumb, '/');
    }
    $poster = trim((string)($row['content_poster'] ?? ''));
    return $poster !== '' ? poster_url($poster) : null;
}

function reels_count(PDO $pdo): int {
    try {
        return (int)$pdo->query("SELECT COUNT(*) c FROM reels WHERE is_active = 1")->fetch()['c'];
    } catch (PDOException $e) {
        error_log('reels_count error: ' . $e->getMessage());
        return 0;
    }
}
