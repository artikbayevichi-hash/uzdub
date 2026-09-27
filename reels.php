<?php
/**
 * UZDUB Reels — qisqa vertikal videolar lentasi (VK Video orqali).
 * Birinchi sahifa server tomondan beriladi, qolganini js/reels.js yuklaydi.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/reels.php';

$page_title = t('reels');
$page_desc = t('reels_desc');

$user_id = is_user() ? (int)$_SESSION['user_id'] : null;
$items = reels_fetch($pdo, 0, REELS_PAGE_SIZE, $user_id);
$page_count = count($items);

// ?id=<reel> bilan kelingan bo'lsa — o'sha reel lentaning boshida turadi.
$deep_id = (int)($_GET['id'] ?? 0);
if ($deep_id > 0) {
    $ids = array_column($items, 'id');
    if (!in_array($deep_id, $ids, true)) {
        $one = reels_fetch_one($pdo, $deep_id, $user_id);
        if ($one) array_unshift($items, $one);
    } else {
        $pos = array_search($deep_id, $ids, true);
        $picked = array_splice($items, $pos, 1);
        array_unshift($items, $picked[0]);
    }
}

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/reels.css?v=<?php echo @filemtime(__DIR__ . '/css/reels.css') ?: 1; ?>">

<div class="reels-page">
    <?php if (!$items): ?>
        <div class="reels-empty">
            <h2>🎞️ <?php echo t('reels'); ?></h2>
            <p><?php echo t('reels_empty'); ?></p>
        </div>
    <?php else: ?>
        <div class="reels-feed" id="reelsFeed"></div>
    <?php endif; ?>
</div>
<div class="reels-toast" id="reelsToast"></div>

<script>
window.__REELS__ = <?php echo json_encode([
    'items' => $items,
    'offset' => $page_count,
    'has_more' => $page_count >= REELS_PAGE_SIZE,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.__REELS_CSRF__ = <?php echo json_encode(csrf_token()); ?>;
</script>
<script src="<?php echo ROOT_URL; ?>/js/reels.js?v=<?php echo @filemtime(__DIR__ . '/js/reels.js') ?: 1; ?>" defer></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
