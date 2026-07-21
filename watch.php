<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

// AJAX - watchlistga qo'shish/olib tashlash
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_watchlist'])) {
    header('Content-Type: application/json');
    if (!is_user()) { echo json_encode(['ok'=>false,'msg'=>'Kirish kerak']); exit; }
    $user = current_user();
    $cid = (int)$_POST['content_id'];
    $chk = $pdo->prepare("SELECT id FROM watchlist WHERE user_id=? AND content_id=?");
    $chk->execute([$user['id'], $cid]);
    if ($row = $chk->fetch()) {
        $pdo->prepare("DELETE FROM watchlist WHERE id=?")->execute([$row['id']]);
        echo json_encode(['ok'=>true,'added'=>false]);
    } else {
        $pdo->prepare("INSERT INTO watchlist (user_id, content_id) VALUES (?,?)")->execute([$user['id'], $cid]);
        echo json_encode(['ok'=>true,'added'=>true]);
    }
    exit;
}

$stmt = $pdo->prepare("SELECT c.*, cat.name as cat_name, cat.slug as cat_slug FROM content c JOIN categories cat ON c.category_id = cat.id WHERE c.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) { header('Location: index.php'); exit; }

$page_title = $item['title'];
$pdo->prepare("UPDATE content SET views = views + 1 WHERE id = ?")->execute([$id]);

// Janrlarni olish
$genre_rows = $pdo->query("SELECT g.name, g.slug, g.color FROM genres g JOIN content_genres cg ON g.id = cg.genre_id WHERE cg.content_id = $id ORDER BY g.name")->fetchAll();

$in_watchlist = false;
if (is_user()) {
    $chk = $pdo->prepare("SELECT id FROM watchlist WHERE user_id=? AND content_id=?");
    $chk->execute([$_SESSION['user_id'], $id]);
    $in_watchlist = (bool)$chk->fetch();
}

$stmt = $pdo->prepare("SELECT * FROM content WHERE category_id = ? AND id != ? ORDER BY RAND() LIMIT 12");
$stmt->execute([$item['category_id'], $id]);
$similar = $stmt->fetchAll();

// ===== PREMIUM PAYWALL (server tomonidan majburiy tekshiruv) =====
$is_locked = (bool)$item['is_premium'] && !has_premium_access($pdo);

// ===== "Davom eting" — saqlangan pozitsiyani olish (faqat file turidagi videolar uchun) =====
$resume_position = 0;
if (is_user() && !$is_locked && $item['video_type'] === 'file') {
    $rp = $pdo->prepare("SELECT position_seconds FROM watch_progress WHERE user_id = ? AND content_id = ?");
    $rp->execute([$_SESSION['user_id'], $id]);
    $row = $rp->fetch();
    if ($row) $resume_position = (int)$row['position_seconds'];
}

include __DIR__ . '/includes/header.php';
?>
<style>
.watch-player-section { position:relative; margin-bottom:24px; }
.content-id-tag { position:absolute; top:-12px; right:0; background:var(--card-bg); border:1px solid var(--blue-primary); color:var(--blue-glow); font-size:12px; padding:4px 12px; border-radius:20px; font-family:monospace; z-index:3; }
.watch-action-bar { display:flex; gap:10px; margin-bottom:22px; flex-wrap:wrap; }
.watch-btn { display:flex; align-items:center; gap:8px; padding:10px 20px; border-radius:8px; border:1px solid rgba(33,150,243,0.3); background:var(--card-bg); color:var(--text-light); cursor:pointer; font-size:14px; font-weight:600; text-decoration:none; transition:0.2s; }
.watch-btn:hover { border-color:var(--blue-primary); background:rgba(33,150,243,0.1); }
.watch-btn.active { background:var(--blue-primary); border-color:var(--blue-primary); }
.premium-tag { background:linear-gradient(135deg,#f9a825,#ff6f00); color:#fff; font-size:11px; padding:3px 10px; border-radius:20px; font-weight:700; margin-left:8px; }
.premium-lock { position:relative; border-radius:12px; overflow:hidden; min-height:320px; display:flex; align-items:center; justify-content:center; text-align:center; padding:40px 20px; background:#0d1424; }
.premium-lock .lock-bg { position:absolute; inset:0; background-size:cover; background-position:center; filter:blur(18px) brightness(0.35); transform:scale(1.1); }
.premium-lock .lock-content { position:relative; z-index:1; max-width:420px; }
.premium-lock .lock-icon { font-size:48px; margin-bottom:14px; }
.premium-lock h3 { font-size:22px; margin-bottom:10px; color:#fff; }
.premium-lock p { color:var(--text-muted); margin-bottom:20px; font-size:14px; }
.premium-lock .btn-unlock { display:inline-block; padding:12px 28px; background:linear-gradient(135deg,#f9a825,#ff6f00); color:#fff; border-radius:8px; text-decoration:none; font-weight:700; }
</style>

<div class="detail-wrap">

    <div class="watch-player-section">
        <span class="content-id-tag">🆔 <?php echo e($item['content_code'] ?? ('ID' . $item['id'])); ?></span>
        <?php if ($is_locked): ?>
        <div class="premium-lock">
            <div class="lock-bg" style="background-image:url('<?php echo $item['poster'] ? 'uploads/posters/' . e($item['poster']) : ''; ?>');"></div>
            <div class="lock-content">
                <div class="lock-icon">🔒</div>
                <h3>Bu — Premium kontent</h3>
                <p>"<?php echo e($item['title']); ?>" ni tomosha qilish uchun Premium obuna kerak.</p>
                <?php if (is_user()): ?>
                <a href="premium.php" class="btn-unlock">⭐ Premium olish</a>
                <?php else: ?>
                <a href="auth/login.php?redirect=<?php echo urlencode('/uzdub/watch.php?id=' . $id); ?>" class="btn-unlock">Kirish va Premium olish</a>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <?php echo render_player($item['video_type'], $item['video_url'], 'uploads/videos/'); ?>
        <?php endif; ?>
    </div>

    <div class="watch-action-bar">
        <button class="watch-btn <?php echo $in_watchlist ? 'active' : ''; ?>" id="watchlistBtn" onclick="toggleWatchlist(<?php echo $id; ?>)">
            <span id="wlIcon"><?php echo $in_watchlist ? '✅' : '➕'; ?></span>
            <span id="wlText"><?php echo $in_watchlist ? 'Ro\'yxatda' : 'Keyinroq ko\'rish'; ?></span>
        </button>
        <a href="mylist.php" class="watch-btn">📋 Mening ro'yxatim</a>
    </div>

    <div class="detail-header">
        <img src="<?php echo $item['poster'] ? 'uploads/posters/' . e($item['poster']) : 'https://via.placeholder.com/300x420/121a2b/2196f3?text=' . urlencode($item['title']); ?>" alt="<?php echo e($item['title']); ?>">
        <div>
            <h1><?php echo e($item['title']); ?> <?php if ($item['is_premium']): ?><span class="premium-tag">⭐ PREMIUM TAVSIYA</span><?php endif; ?></h1>
            <div class="meta">
                <?php echo e($item['cat_name']); ?> &middot;
                <?php echo e($item['release_year']); ?> &middot;
                &#9733; <?php echo e($item['rating']); ?> &middot;
                &#128065; <?php echo e($item['views']); ?> marta ko'rilgan
            </div>

            <?php if (!empty($genre_rows)): ?>
            <div class="genre-pills" style="margin: 10px 0;">
                <?php foreach ($genre_rows as $g): ?>
                <span class="genre-pill" style="background:<?php echo e($g['color'] ?? '#7c4dff'); ?>22;border-color:<?php echo e($g['color'] ?? '#7c4dff'); ?>;color:<?php echo e($g['color'] ?? '#7c4dff'); ?>;"><?php echo e($g['name']); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($item['studio'] || $item['director'] || $item['duration']): ?>
            <div class="meta" style="margin-top:8px;">
                <?php if ($item['studio']): ?>&#127968; Studio: <?php echo e($item['studio']); ?><br><?php endif; ?>
                <?php if ($item['director']): ?>&#128100; Rejissyor: <?php echo e($item['director']); ?><br><?php endif; ?>
                <?php if ($item['duration']): ?>&#9202; Davomiylik: <?php echo e($item['duration']); ?><br><?php endif; ?>
                <?php if ($item['status']): ?>&#127922; Holati: <?php echo e(ucfirst($item['status'])); ?><?php endif; ?>
            </div>
            <?php endif; ?>

            <p class="desc"><?php echo nl2br(e($item['description'])); ?></p>
        </div>
    </div>

    <?php if (!empty($similar)): ?>
    <section class="content-section" style="padding-left:0; padding-right:0;">
        <h2>O'xshash kontentlar</h2>
        <div class="row-wrap">
            <div class="row-scroll">
                <?php foreach ($similar as $s): ?>
                <a href="watch.php?id=<?php echo $s['id']; ?>" class="card">
                    <img src="<?php echo $s['poster'] ? 'uploads/posters/' . e($s['poster']) : 'https://via.placeholder.com/300x420/121a2b/2196f3?text=' . urlencode($s['title']); ?>" alt="<?php echo e($s['title']); ?>">
                    <div class="card-info">
                        <h3><?php echo e($s['title']); ?></h3>
                        <div class="meta"><span><?php echo e($s['release_year']); ?></span><span class="badge">&#9733; <?php echo e($s['rating']); ?></span></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

</div>

<script>
function toggleWatchlist(contentId) {
    <?php if (!is_user()): ?>
    window.location.href = 'auth/login.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
    return;
    <?php endif; ?>
    var fd = new FormData();
    fd.append('toggle_watchlist', '1');
    fd.append('content_id', contentId);
    fetch('watch.php?id=<?php echo $id; ?>', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => {
            if (!r.ok) { if (window.showToast) showToast(r.msg || 'Xatolik yuz berdi', 'error'); return; }
            var btn = document.getElementById('watchlistBtn');
            var icon = document.getElementById('wlIcon');
            var text = document.getElementById('wlText');
            if (r.added) {
                btn.classList.add('active');
                icon.textContent = '✅';
                text.textContent = "Ro'yxatda";
                if (window.showToast) showToast("Ro'yxatga qo'shildi", 'success');
            } else {
                btn.classList.remove('active');
                icon.textContent = '➕';
                text.textContent = 'Keyinroq ko\'rish';
                if (window.showToast) showToast("Ro'yxatdan olib tashlandi", 'info');
            }
        });
}
</script>

<?php if (is_user() && !$is_locked && $item['video_type'] === 'file'): ?>
<script>
(function () {
    var video = document.querySelector('.watch-player-section video');
    if (!video) return;
    var contentId = <?php echo (int)$id; ?>;
    var resumeAt = <?php echo (int)$resume_position; ?>;
    var csrfToken = <?php echo json_encode(csrf_token()); ?>;
    var lastSaved = 0;

    if (resumeAt > 5) {
        video.addEventListener('loadedmetadata', function onMeta() {
            if (resumeAt < video.duration - 5) {
                video.currentTime = resumeAt;
                if (window.showToast) {
                    var mins = Math.floor(resumeAt / 60);
                    showToast("Siz to'xtagan joydan davom etyapti (" + mins + " daq.)", 'info');
                }
            }
            video.removeEventListener('loadedmetadata', onMeta);
        });
    }

    function saveProgress(useBeacon) {
        if (!video.duration || isNaN(video.duration)) return;
        var pos = Math.floor(video.currentTime);
        if (!useBeacon && Math.abs(pos - lastSaved) < 8) return; // har ~8 soniyada bir marta saqlash
        lastSaved = pos;
        var payload = JSON.stringify({
            content_id: contentId,
            position: pos,
            duration: Math.floor(video.duration),
            csrf_token: csrfToken
        });
        if (useBeacon && navigator.sendBeacon) {
            navigator.sendBeacon('/uzdub/api/save-progress.php', new Blob([payload], { type: 'application/json' }));
        } else {
            fetch('/uzdub/api/save-progress.php', { method: 'POST', body: payload }).catch(function () {});
        }
    }

    video.addEventListener('timeupdate', function () { saveProgress(false); });
    window.addEventListener('pagehide', function () { saveProgress(true); });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') saveProgress(true);
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
