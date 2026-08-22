<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

// AJAX - Sevimlilar (favorites) ga qo'shish/olib tashlash
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_fav'])) {
    header('Content-Type: application/json');
    if (!is_user()) { echo json_encode(['ok'=>false,'msg'=>t('login_required')]); exit; }
    if (!validate_csrf($_POST['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'msg'=>t('security_token_wrong')]); exit; }
    $user = current_user();
    $cid = (int)$_POST['content_id'];

    $chk = $pdo->prepare("SELECT id FROM user_content_status WHERE user_id=? AND content_id=? AND status='favorite'");
    $chk->execute([$user['id'], $cid]);
    if ($row = $chk->fetch()) {
        $pdo->prepare("DELETE FROM user_content_status WHERE id=?")->execute([$row['id']]);
        $pdo->prepare("DELETE FROM watchlist WHERE user_id=? AND content_id=?")->execute([$user['id'], $cid]);
        echo json_encode(['ok'=>true,'added'=>false]);
    } else {
        $pdo->prepare("INSERT INTO user_content_status (user_id, content_id, status) VALUES (?,?, 'favorite')")->execute([$user['id'], $cid]);
        $pdo->prepare("INSERT IGNORE INTO watchlist (user_id, content_id) VALUES (?,?)")->execute([$user['id'], $cid]);
        echo json_encode(['ok'=>true,'added'=>true]);
    }
    exit;
}

$stmt = $pdo->prepare("SELECT c.*, cat.name as cat_name, cat.slug as cat_slug FROM content c JOIN categories cat ON c.category_id = cat.id WHERE c.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) { header('Location: index.php'); exit; }

$page_title = t_title($item);
$page_desc = t_desc($item);
$page_image = $item['poster'] ? poster_url($item['poster']) : '';

// Janrlarni olish
$genre_rows = [];
try {
    $genre_rows_stmt = $pdo->prepare("SELECT g.name, g.slug, g.color FROM genres g JOIN content_genres cg ON g.id = cg.genre_id WHERE cg.content_id = ? ORDER BY g.name");
    $genre_rows_stmt->execute([$id]);
    $genre_rows = $genre_rows_stmt->fetchAll();
} catch (PDOException $e) {
    error_log('watch.php genre_rows error: ' . $e->getMessage());
}

$in_watchlist = false;
if (is_user()) {
    try {
        $chk = $pdo->prepare("SELECT id FROM user_content_status WHERE user_id=? AND content_id=? AND status='favorite'");
        $chk->execute([$_SESSION['user_id'], $id]);
        $in_watchlist = (bool)$chk->fetch();
    } catch (PDOException $e) {
        error_log('watch.php watchlist check error: ' . $e->getMessage());
    }
}

$similar = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM content WHERE category_id = ? AND id != ? ORDER BY RAND() LIMIT 12");
    $stmt->execute([$item['category_id'], $id]);
    $similar = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('watch.php similar content error: ' . $e->getMessage());
}

// Aktyorlar va treyler
$actors = [];
try {
    $act = $pdo->prepare("SELECT * FROM content_actors WHERE content_id = ? ORDER BY sort_order ASC");
    $act->execute([$id]);
    $actors = $act->fetchAll();
} catch (PDOException $e) {
    error_log('watch.php actors error: ' . $e->getMessage());
}

$trailer_url = $item['trailer_url'] ?? '';

// Related content (agar related_content jadvalida ma'lumot bo'lsa)
$related = [];
try {
    $rel = $pdo->prepare("SELECT c.*, cat.name as cat_name FROM related_content rc JOIN content c ON rc.related_id = c.id LEFT JOIN categories cat ON c.category_id = cat.id WHERE rc.content_id = ? LIMIT 12");
    $rel->execute([$id]);
    $related = $rel->fetchAll();
} catch (PDOException $e) {
    error_log('watch.php related_content error: ' . $e->getMessage());
}

// ===== Qismlar (episodes) =====
$episodes = [];
$ep_stmt = $pdo->prepare("SELECT * FROM episodes WHERE content_id = ? ORDER BY season, episode_number");
$ep_stmt->execute([$id]);
$episodes = $ep_stmt->fetchAll();

$active_episode = null;
$active_episode_id = 0;
if ($episodes) {
    $req_ep = (int)($_GET['ep'] ?? 0);
    foreach ($episodes as $ep) {
        if ($ep['id'] === $req_ep) { $active_episode = $ep; break; }
    }
    if (!$active_episode) $active_episode = $episodes[0];
    $active_episode_id = (int)$active_episode['id'];
}

// ===== Keyingi qism (next episode) =====
$next_ep = null;
if ($episodes) {
    foreach ($episodes as $i => $ep) {
        if ((int)$ep['id'] === $active_episode_id && isset($episodes[$i + 1])) {
            $nep = $episodes[$i + 1];
            $next_ep = [
                'href' => 'watch.php?id=' . $id . '&ep=' . (int)$nep['id'],
                'label' => t('player_next_ep') . ': ' . ($nep['title'] ?? ('Qism ' . (int)$nep['episode_number'])),
            ];
            break;
        }
    }
}

// ===== PREMIUM PAYWALL (server tomonidan majburiy tekshiruv) =====
$ep_locked = (bool)$active_episode && !empty($active_episode['is_premium']) && !has_premium_access($pdo);
$is_locked = ((bool)$item['is_premium'] && !has_premium_access($pdo)) || $ep_locked;

// ===== "Davom eting" — saqlangan pozitsiyani olish (file/telegram turidagi videolar uchun) =====
$resume_position = 0;
$active_video_type = $active_episode ? $active_episode['video_type'] : $item['video_type'];
$active_video_url = $active_episode ? $active_episode['video_url'] : $item['video_url'];
if (is_user() && !$is_locked && in_array($active_video_type, ['file', 'telegram'], true)) {
    $rp = $pdo->prepare("SELECT position_seconds FROM watch_progress WHERE user_id = ? AND content_id = ? AND episode_id = ?");
    $rp->execute([$_SESSION['user_id'], $id, $active_episode_id]);
    $row = $rp->fetch();
    if ($row) $resume_position = (int)$row['position_seconds'];
}

// ===== Skip Intro oralig'i (qism bo'lsa qismniki, aks holda kontentniki) =====
$intro_start = $active_episode ? (int)($active_episode['intro_start'] ?? 0) : (int)($item['intro_start'] ?? 0);
$intro_end = $active_episode ? (int)($active_episode['intro_end'] ?? 0) : (int)($item['intro_end'] ?? 0);

include __DIR__ . '/includes/header.php';
?>
<style>
.watch-player-section { position:relative; margin-bottom:24px; }
.content-id-tag { position:absolute; top:-12px; right:0; background:var(--card-bg); border:1px solid var(--blue-primary); color:var(--blue-glow); font-size:12px; padding:4px 12px; border-radius:20px; font-family:monospace; z-index:60; box-shadow:0 4px 12px rgba(0,0,0,0.6); }
.watch-action-bar { display:flex; gap:10px; margin-bottom:22px; flex-wrap:wrap; }
.watch-btn { display:flex; align-items:center; gap:8px; padding:10px 20px; border-radius:8px; border:1px solid rgba(33,150,243,0.3); background:var(--card-bg); color:var(--text-light); cursor:pointer; font-size:14px; font-weight:600; text-decoration:none; transition:0.2s; }
.watch-btn:hover { border-color:var(--blue-primary); background:rgba(33,150,243,0.1); }
.watch-btn.active { background:var(--blue-primary); border-color:var(--blue-primary); }
.fav-btn { display:inline-flex; align-items:center; justify-content:center; width:44px; height:44px; border-radius:50%; border:1px solid rgba(255,255,255,0.15); background:var(--card-bg); color:var(--text-muted); cursor:pointer; transition:all 0.3s ease; padding:0; }
.fav-btn svg { width:22px; height:22px; fill:none; stroke:currentColor; stroke-width:2; transition:all 0.3s ease; }
.fav-btn:hover { border-color:rgba(239,83,80,0.4); color:#e57373; transform:scale(1.1); }
.fav-btn:hover svg { stroke:#e57373; }
.fav-btn.active { border-color:rgba(239,83,80,0.5); background:rgba(239,83,80,0.1); color:#ef5350; }
.fav-btn.active svg { fill:#ef5350; stroke:#ef5350; }
.premium-tag { background:linear-gradient(135deg,#f9a825,#ff6f00); color:#fff; font-size:11px; padding:3px 10px; border-radius:20px; font-weight:700; margin-left:8px; }
.premium-lock { position:relative; border-radius:12px; overflow:hidden; min-height:320px; display:flex; align-items:center; justify-content:center; text-align:center; padding:40px 20px; background:#0d1424; }
.premium-lock .lock-bg { position:absolute; inset:0; background-size:cover; background-position:center; filter:blur(18px) brightness(0.35); transform:scale(1.1); }
.premium-lock .lock-content { position:relative; z-index:1; max-width:420px; }
.premium-lock .lock-icon { font-size:48px; margin-bottom:14px; }
.premium-lock h3 { font-size:22px; margin-bottom:10px; color:#fff; }
.premium-lock p { color:var(--text-muted); margin-bottom:20px; font-size:14px; }
.premium-lock .btn-unlock { display:inline-block; padding:12px 28px; background:linear-gradient(135deg,#f9a825,#ff6f00); color:#fff; border-radius:8px; text-decoration:none; font-weight:700; }
.episodes-panel { background:var(--card-bg); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:16px; margin-bottom:22px; }
.episodes-header h3 { font-size:15px; margin:0 0 12px; color:var(--text-light); }
.episodes-list { display:flex; flex-wrap:wrap; gap:8px; }
.episodes-season { width:100%; font-size:12px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; margin:6px 0 2px; }
.episode-chip { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:8px; border:1px solid rgba(33,150,243,0.25); background:rgba(33,150,243,0.05); color:var(--text-light); text-decoration:none; font-size:13px; transition:0.2s; box-sizing:border-box; flex:0 0 calc((100% - 16px) / 3); }
@media screen and (min-width: 993px) {
    .episode-chip { flex:0 0 calc((100% - 88px) / 12); }
}
.episode-chip:hover { border-color:var(--blue-primary); background:rgba(33,150,243,0.12); }
.episode-chip.active { background:var(--blue-primary); border-color:var(--blue-primary); }
.episode-num { display:inline-flex; align-items:center; justify-content:center; min-width:26px; height:26px; padding:0 6px; border-radius:6px; background:rgba(33,150,243,0.15); color:var(--blue-glow); font-weight:700; font-size:12px; }
.episode-chip.active .episode-num { background:rgba(255,255,255,0.25); color:#fff; }
.episode-title { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
</style>
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/player.css?v=<?php echo @filemtime(__DIR__ . '/css/player.css') ?: 1; ?>">

<div class="detail-wrap">

    <div class="watch-player-section">
        <span class="content-id-tag">🆔 <?php echo e($item['content_code'] ?? ('ID' . $item['id'])); ?></span>
        <?php if ($is_locked): ?>
        <div class="premium-lock">
            <div class="lock-bg" style="background-image:url('<?php echo $item['poster'] ? e(poster_url($item['poster'])) : ''; ?>');"></div>
            <div class="lock-content">
                <div class="lock-icon">🔒</div>
                <h3><?php echo $ep_locked ? t('premium_episode') : t('premium_content'); ?></h3>
                <p><?php echo $ep_locked
                    ? e(($active_episode['title'] ?? ('Qism ' . (int)$active_episode['episode_number']))) . ' — ' . t('premium_episode_needed')
                    : '"' . e(t_title($item)) . '" ' . t('premium_needed'); ?></p>
                <?php if (is_user()): ?>
                <a href="premium.php" class="btn-unlock">👑 <?php echo t('get_premium'); ?></a>
                <?php else: ?>
                <a href="auth/login.php?redirect=<?php echo urlencode(ROOT_URL . '/watch.php?id=' . $id); ?>" class="btn-unlock"><?php echo t('login_and_premium'); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <?php
        $subtitles = [];
        try {
            $subs_data = $pdo->prepare("SELECT * FROM content_subtitles WHERE content_id = ?");
            $subs_data->execute([$id]);
            $subtitles = $subs_data->fetchAll();
        } catch (PDOException $e) {}
        echo render_player($active_video_type, $active_video_url, 'uploads/videos/', $subtitles, 'mainVideo', $item['poster'] ? poster_url($item['poster']) : null, [], $intro_start, $intro_end, $resume_position, t_title($item) . ($active_episode ? ' — ' . ($active_episode['title'] ?? ('Qism ' . (int)$active_episode['episode_number'])) : ''), $next_ep);
        ?>
        <?php endif; ?>
    </div>

    <?php if ($episodes): ?>
    <div class="episodes-panel">
        <div class="episodes-header">
            <h3><?php echo t('episodes'); ?> (<?php echo count($episodes); ?>)</h3>
        </div>
        <div class="episodes-list">
            <?php
            $season = null;
            foreach ($episodes as $ep):
                if ($ep['season'] !== $season):
                    $season = $ep['season'];
                    echo '<div class="episodes-season">' . ($season > 1 ? 'Fasl ' . (int)$season : 'Fasl ' . (int)$season) . '</div>';
                endif;
                $is_active = (int)$ep['id'] === $active_episode_id;
            ?>
            <a href="watch.php?id=<?php echo $id; ?>&ep=<?php echo $ep['id']; ?>" class="episode-chip <?php echo $is_active ? 'active' : ''; ?>">
                <span class="episode-num"><?php echo (int)$ep['episode_number']; ?></span>
                <span class="episode-title"><?php echo e($ep['title'] ?? ('Qism ' . (int)$ep['episode_number'])); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($trailer_url): ?>
    <div class="trailer-section" style="margin:20px 0;border-radius:12px;overflow:hidden;">
        <h3 style="font-size:16px;margin-bottom:10px;">&#127916; <?php echo t('trailer'); ?></h3>
        <div style="position:relative;padding-bottom:56.25%;height:0;border-radius:12px;overflow:hidden;background:#000;">
            <iframe src="<?php echo e($trailer_url); ?>" style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;" allowfullscreen loading="lazy"></iframe>
        </div>
    </div>
    <?php endif; ?>

    <div class="watch-action-bar">
        <button class="fav-btn <?php echo $in_watchlist ? 'active' : ''; ?>" id="watchlistBtn" onclick="toggleFav(<?php echo $id; ?>)" title="<?php echo $in_watchlist ? t('removed_from_watchlist') : t('added_to_watchlist'); ?>">
            <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </button>
    </div>

    <div class="detail-header">
        <img src="<?php echo $item['poster'] ? e(poster_url($item['poster'])) : 'https://via.placeholder.com/300x420/121a2b/2196f3?text=' . urlencode(t_title($item)); ?>" alt="<?php echo e(t_title($item)); ?>">
        <div>
            <h1><?php echo e(t_title($item)); ?> <?php if ($item['is_premium']): ?><span class="premium-tag">👑 <?php echo t('premium_tag'); ?></span><?php endif; ?></h1>
            <div class="meta">
                <?php echo e($item['cat_name']); ?> &middot;
                <?php echo e($item['release_year']); ?> &middot;
                &#9733; <?php echo e($item['rating']); ?> &middot;
                &#128065; <?php echo e($item['views']); ?> <?php echo t('views_count'); ?>
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
                <?php if ($item['studio']): ?>&#127968; <?php echo t('studio'); ?> <?php echo e($item['studio']); ?><br><?php endif; ?>
                <?php if ($item['director']): ?>&#128100; <?php echo t('director'); ?> <?php echo e($item['director']); ?><br><?php endif; ?>
                <?php if ($item['duration']): ?>&#9202; <?php echo t('duration'); ?> <?php echo e($item['duration']); ?><br><?php endif; ?>
                <?php if ($item['status']): ?>&#127922; <?php echo t('status'); ?> <?php echo e(ucfirst($item['status'])); ?><?php endif; ?>
            </div>
            <?php endif; ?>

            <p class="desc"><?php echo nl2br(e(t_desc($item))); ?></p>

            <?php if (!empty($actors)): ?>
            <div style="margin-top:16px;">
                <h4 style="font-size:14px;color:var(--text-muted);margin-bottom:8px;"><?php echo t('actors'); ?></h4>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($actors as $a): ?>
                    <span style="display:inline-flex;align-items:center;gap:6px;background:rgba(33,150,243,0.08);border:1px solid rgba(33,150,243,0.15);border-radius:8px;padding:4px 10px;font-size:13px;">
                        <?php if ($a['image']): ?><img src="<?php echo e($a['image']); ?>" alt="" style="width:20px;height:20px;border-radius:50%;object-fit:cover;"><?php endif; ?>
                        <?php echo e($a['name']); ?>
                        <?php if ($a['role']): ?><span style="color:var(--text-muted);font-size:11px;">(<?php echo e($a['role']); ?>)</span><?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/comments.css?v=<?php echo @filemtime(__DIR__ . '/css/comments.css') ?: 1; ?>">
    <section class="cmt-section emoji-keep" id="commentSection">
        <div class="cmt-header">
            <h3><?php echo t('comments'); ?></h3>
            <span class="cmt-count" id="cmtCount">0</span>
        </div>

        <?php if (is_user()): ?>
        <?php $cu = current_user(); ?>
        <div class="cmt-input-box">
            <img src="<?php echo avatar_url($cu['avatar']); ?>" class="cmt-avatar" alt="">
            <div class="cmt-input-wrap">
                <textarea class="cmt-textarea" id="cmtInput" placeholder="<?php echo t('write_comment'); ?>" rows="2"></textarea>
                <div class="cmt-toolbar">
                    <button type="button" class="cmt-tool-btn" data-cmd="bold" title="Bold"><b>B</b></button>
                    <button type="button" class="cmt-tool-btn" data-cmd="italic" title="Italic"><i>I</i></button>
                    <button type="button" class="cmt-tool-btn" data-cmd="underline" title="Underline"><u>U</u></button>
                    <button type="button" class="cmt-tool-btn" data-cmd="strike" title="Strikethrough"><s>S</s></button>
                    <span class="cmt-tool-sep"></span>
                    <button type="button" class="cmt-tool-btn" data-cmd="code" title="Code">&lt;/&gt;</button>
                    <button type="button" class="cmt-send-btn" id="cmtSendBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        <?php echo t('send'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="cmt-login-prompt">
            <?php echo t('login_to_comment'); ?> <a href="auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"><?php echo t('login_btn'); ?></a>
        </div>
        <?php endif; ?>

        <div class="cmt-list" id="cmtList">
            <div class="cmt-loading"><div class="cmt-spinner"></div></div>
        </div>
    </section>

    <?php if (!empty($similar)): ?>
    <section class="content-section" style="padding-left:0; padding-right:0;">
        <h2><?php echo t('similar_content'); ?></h2>
        <div class="row-wrap">
            <div class="row-scroll">
                <?php foreach ($similar as $s): ?>
                <a href="watch.php?id=<?php echo $s['id']; ?>" class="card">
                    <img src="<?php echo $s['poster'] ? e(poster_url($s['poster'])) : 'https://via.placeholder.com/300x420/121a2b/2196f3?text=' . urlencode(t_title($s)); ?>" alt="<?php echo e(t_title($s)); ?>">
                    <div class="card-info">
                        <h3><?php echo e(t_title($s)); ?></h3>
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
var WT = <?php echo json_encode([
    'removed_from_watchlist' => t('removed_from_watchlist'),
    'added_to_watchlist' => t('added_to_watchlist'),
], JSON_UNESCAPED_UNICODE); ?>;
function toggleFav(contentId) {
    <?php if (!is_user()): ?>
    window.location.href = 'auth/login.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
    return;
    <?php endif; ?>
    var fd = new FormData();
    fd.append('toggle_fav', '1');
    fd.append('content_id', contentId);
    fd.append('csrf_token', '<?php echo e(csrf_token()); ?>');
    fetch('watch.php?id=<?php echo $id; ?>', {method:'POST', body:fd})
        .then(function(r) { return r.json(); })
        .then(function(r) {
            if (!r.ok) { if (window.showToast) showToast(r.msg || 'Xatolik', 'error'); return; }
            var btn = document.getElementById('watchlistBtn');
            if (r.added) {
                btn.classList.add('active');
                btn.title = WT.removed_from_watchlist;
                if (window.showToast) showToast(WT.added_to_watchlist, 'success');
            } else {
                btn.classList.remove('active');
                btn.title = WT.added_to_watchlist;
                if (window.showToast) showToast(WT.removed_from_watchlist, 'info');
            }
        })
        .catch(function() { if (window.showToast) showToast('Xatolik', 'error'); });
}
</script>

<script>
(function() {
    var CONTENT_ID = <?php echo $id; ?>;
    var CSRF = '<?php echo e(csrf_token()); ?>';
    var IS_USER = <?php echo is_user() ? 'true' : 'false'; ?>;
    var translations = <?php echo json_encode([
        'write_comment' => t('write_comment'),
        'comment_posted' => t('comment_posted'),
        'reply_posted' => t('reply_posted'),
        'comment_deleted' => t('comment_deleted'),
        'delete_confirm' => t('delete_confirm'),
        'no_comments' => t('no_comments'),
        'reply' => t('reply'),
        'reply_to' => t('reply_to'),
        'like' => t('like'),
        'dislike' => t('dislike'),
    ], JSON_UNESCAPED_UNICODE); ?>;

    var list = document.getElementById('cmtList');
    var countEl = document.getElementById('cmtCount');
    var input = document.getElementById('cmtInput');
    var sendBtn = document.getElementById('cmtSendBtn');
    var replyingTo = null;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function fmtText(s) {
        return esc(s)
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/__(.+?)__/g, '<u>$1</u>')
            .replace(/~~(.+?)~~/g, '<s>$1</s>')
            .replace(/`(.+?)`/g, '<code>$1</code>');
    }

    function toolbarCmd(cmd, ta) {
        var start = ta.selectionStart, end = ta.selectionEnd, val = ta.value, sel = val.substring(start, end);
        var wraps = {
            bold: ['**', '**'], italic: ['*', '*'], underline: ['__', '__'],
            strike: ['~~', '~~'], code: ['`', '`']
        };
        var w = wraps[cmd];
        if (!w) return;
        if (sel) {
            ta.value = val.substring(0, start) + w[0] + sel + w[1] + val.substring(end);
            ta.selectionStart = start + w[0].length;
            ta.selectionEnd = start + w[0].length + sel.length;
        } else {
            ta.value = val.substring(0, start) + w[0] + w[1] + val.substring(end);
            ta.selectionStart = ta.selectionEnd = start + w[0].length;
        }
        ta.focus();
    }

    document.querySelectorAll('.cmt-tool-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var ta = input;
            if (btn.closest('.cmt-reply-input')) {
                ta = btn.closest('.cmt-reply-input').querySelector('.cmt-textarea');
            }
            toolbarCmd(btn.dataset.cmd, ta);
        });
    });

    function buildComment(c, isReply) {
        var html = '<div class="cmt-card" data-id="' + c.id + '">';
        html += '<img src="' + esc(c.avatar_url) + '" class="cmt-avatar" alt="">';
        html += '<div class="cmt-body">';
        html += '<div class="cmt-meta">';
        html += '<span class="cmt-author' + (c.is_premium ? ' premium' : '') + '">' + esc(c.username) + '</span>';
        html += '<span class="cmt-time">' + esc(c.time_ago) + '</span>';
        html += '</div>';
        html += '<div class="cmt-text">' + fmtText(c.comment) + '</div>';
        html += '<div class="cmt-actions">';

        var lClass = c.user_like === 'like' ? ' liked' : '';
        html += '<button class="cmt-action-btn like-btn' + lClass + '" data-id="' + c.id + '" data-type="like">';
        html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3H14z"/><path d="M7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>';
        html += '<span class="cmt-action-count">' + (c.like_count || '') + '</span></button>';

        var dClass = c.user_like === 'dislike' ? ' disliked' : '';
        html += '<button class="cmt-action-btn dislike-btn' + dClass + '" data-id="' + c.id + '" data-type="dislike">';
        html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3H10z"/><path d="M17 2h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-3"/></svg>';
        html += '<span class="cmt-action-count">' + (c.dislike_count || '') + '</span></button>';

        if (IS_USER) {
            html += '<button class="cmt-action-btn reply-btn" data-id="' + c.id + '" data-user="' + esc(c.username) + '">';
            html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>';
            html += translations.reply + '</button>';
        }

        html += '</div>';

        if (c.replies && c.replies.length) {
            html += '<div class="cmt-replies">';
            c.replies.forEach(function(r) { html += buildComment(r, true); });
            html += '</div>';
        }

        if (IS_USER) {
            html += '<div class="cmt-reply-input" id="replyBox-' + c.id + '" style="display:none">';
            html += '<img src="<?php echo is_user() ? e(avatar_url(current_user()['avatar'])) : ''; ?>" class="cmt-avatar" alt="">';
            html += '<div class="cmt-input-wrap">';
            html += '<textarea class="cmt-textarea" placeholder="' + esc(c.username) + ' ' + translations.reply_to + '" rows="1"></textarea>';
            html += '<div class="cmt-toolbar">';
            html += '<button type="button" class="cmt-tool-btn" data-cmd="bold"><b>B</b></button>';
            html += '<button type="button" class="cmt-tool-btn" data-cmd="italic"><i>I</i></button>';
            html += '<button type="button" class="cmt-tool-btn" data-cmd="underline"><u>U</u></button>';
            html += '<button type="button" class="cmt-tool-btn" data-cmd="strike"><s>S</s></button>';
            html += '<span class="cmt-tool-sep"></span>';
            html += '<button type="button" class="cmt-tool-btn" data-cmd="code">&lt;/&gt;</button>';
            html += '<button type="button" class="cmt-send-btn reply-send" data-parent="' + c.id + '">';
            html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
            html += translations.send + '</button></div></div></div>';
        }

        html += '</div></div>';
        return html;
    }

    function renderComments(data) {
        if (!data.comments || !data.comments.length) {
            list.innerHTML = '<div class="cmt-empty"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><br>' + translations.no_comments + '</div>';
            countEl.textContent = '0';
            return;
        }
        var html = '';
        data.comments.forEach(function(c) { html += buildComment(c, false); });
        list.innerHTML = html;
        countEl.textContent = data.total || data.comments.length;
        attachEvents();
    }

    function loadComments() {
        list.innerHTML = '<div class="cmt-loading"><div class="cmt-spinner"></div></div>';
        fetch(ROOT_URL + '/api/comments.php?content_id=' + CONTENT_ID)
            .then(function(r) { return r.json(); })
            .then(renderComments)
            .catch(function() { list.innerHTML = '<div class="cmt-empty">' + esc(translations.no_comments) + '</div>'; });
    }

    function postComment(text, parentId) {
        var body = {
            content_id: CONTENT_ID,
            comment: text,
            csrf_token: CSRF
        };
        if (parentId) body.parent_id = parentId;
        return fetch(ROOT_URL + '/api/comment-post.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body)
        }).then(function(r) { return r.json(); });
    }

    function toggleLike(commentId, type) {
        return fetch(ROOT_URL + '/api/like-toggle.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({comment_id: commentId, type: type, csrf_token: CSRF})
        }).then(function(r) { return r.json(); });
    }

    function updateLikeUI(commentId, action, likes, dislikes, userLike) {
        var card = list.querySelector('.cmt-card[data-id="' + commentId + '"]');
        if (!card) return;
        var likeBtn = card.querySelector('.like-btn');
        var dislikeBtn = card.querySelector('.dislike-btn');
        if (likeBtn) {
            likeBtn.classList.toggle('liked', userLike === 'like');
            var lc = likeBtn.querySelector('.cmt-action-count');
            if (lc) lc.textContent = likes || '';
        }
        if (dislikeBtn) {
            dislikeBtn.classList.toggle('disliked', userLike === 'dislike');
            var dc = dislikeBtn.querySelector('.cmt-action-count');
            if (dc) dc.textContent = dislikes || '';
        }
    }

    function attachEvents() {
        list.querySelectorAll('.reply-btn').forEach(function(btn) {
            btn.onclick = function() {
                var id = btn.dataset.id;
                var box = document.getElementById('replyBox-' + id);
                if (!box) return;
                var showing = box.style.display !== 'none';
                list.querySelectorAll('.cmt-reply-input').forEach(function(b) { b.style.display = 'none'; });
                if (!showing) box.style.display = 'flex';
            };
        });

        list.querySelectorAll('.reply-send').forEach(function(btn) {
            btn.onclick = function() {
                var ta = btn.closest('.cmt-reply-input').querySelector('.cmt-textarea');
                var text = ta.value.trim();
                if (!text) return;
                btn.disabled = true;
                postComment(text, parseInt(btn.dataset.parent)).then(function(r) {
                    btn.disabled = false;
                    if (r.ok) {
                        ta.value = '';
                        btn.closest('.cmt-reply-input').style.display = 'none';
                        if (window.showToast) showToast(translations.reply_posted, 'success');
                        loadComments();
                    }
                });
            };
        });

        list.querySelectorAll('.like-btn, .dislike-btn').forEach(function(btn) {
            btn.onclick = function() {
                if (!IS_USER) { window.location.href = ROOT_URL + '/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname); return; }
                var id = parseInt(btn.dataset.id);
                var type = btn.dataset.type;
                toggleLike(id, type).then(function(r) {
                    if (r.ok) updateLikeUI(id, r.action, r.likes, r.dislikes, r.user_like);
                });
            };
        });
    }

    function pollCommentReactions() {
        var cards = list.querySelectorAll('.cmt-card[data-id]');
        if (cards.length === 0) return;
        var ids = [];
        cards.forEach(function(c) { ids.push(c.dataset.id); });
        fetch(ROOT_URL + '/api/comment-reactions.php?ids=' + ids.join(','))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.updates) return;
                data.updates.forEach(function(u) { updateLikeUI(u.comment_id, null, u.likes, u.dislikes, u.user_like); });
            })
            .catch(function() {});
    }

    if (sendBtn) {
        sendBtn.onclick = function() {
            var text = input.value.trim();
            if (!text) return;
            sendBtn.disabled = true;
            postComment(text, null).then(function(r) {
                sendBtn.disabled = false;
                if (r.ok) {
                    input.value = '';
                    if (window.showToast) showToast(translations.comment_posted, 'success');
                    loadComments();
                }
            });
        };
    }

    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                if (sendBtn) sendBtn.click();
            }
        });
    }

    loadComments();
    setInterval(pollCommentReactions, 5000);
})();
</script>
<?php if (is_user() && !$is_locked && in_array($active_video_type, ['file', 'telegram'], true)): ?>
<script>
(function () {
    var video = document.querySelector('.watch-player-section video');
    if (!video) return;
    var contentId = <?php echo (int)$id; ?>;
    var episodeId = <?php echo (int)$active_episode_id; ?>;
    var csrfToken = <?php echo json_encode(csrf_token()); ?>;
    var lastSaved = 0;

    function saveProgress(useBeacon) {
        if (!video.duration || isNaN(video.duration)) return;
        var pos = Math.floor(video.currentTime);
        if (!useBeacon && Math.abs(pos - lastSaved) < 8) return; // har ~8 soniyada bir marta saqlash
        lastSaved = pos;
        var payload = JSON.stringify({
            content_id: contentId,
            episode_id: episodeId,
            position: pos,
            duration: Math.floor(video.duration),
            csrf_token: csrfToken
        });
        if (useBeacon && navigator.sendBeacon) {
            navigator.sendBeacon(ROOT_URL + '/api/save-progress.php', new Blob([payload], { type: 'application/json' }));
        } else {
            fetch(ROOT_URL + '/api/save-progress.php', { method: 'POST', body: payload }).catch(function () {});
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

<script>
(function() {
    var hash = window.location.hash;
    if (!hash || !hash.startsWith('#comment-')) return;
    var commentId = hash.replace('#comment-', '');
    function tryScroll() {
        var el = document.querySelector('.cmt-card[data-id="' + commentId + '"]');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.style.background = 'rgba(33,150,243,0.12)';
            el.style.borderRadius = '10px';
            el.style.transition = 'background 0.3s';
            setTimeout(function() { el.style.background = ''; }, 2500);
        } else {
            setTimeout(tryScroll, 500);
        }
    }
    setTimeout(tryScroll, 800);
})();
</script>

<script src="<?php echo ROOT_URL; ?>/js/player.js?v=<?php echo @filemtime(__DIR__ . '/js/player.js') ?: 1; ?>"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
