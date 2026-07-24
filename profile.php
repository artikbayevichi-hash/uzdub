<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$uid_param = $_GET['uid'] ?? '';
if (!$uid_param) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$uid_param]);
$profile_user = $stmt->fetch();

if (!$profile_user) { header('Location: index.php'); exit; }

check_premium_expiry($pdo, $profile_user['id']);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$profile_user['id']]);
$profile_user = $stmt->fetch();

$page_title = $profile_user['username'] . t('profile_title_suffix');
$is_own = is_user() && $_SESSION['user_id'] === $profile_user['id'];

$msg = '';
if ($is_own && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_avatar'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $msg = t('security_token_wrong');
    } else {
        $av = upload_file('avatar', __DIR__ . '/uploads/avatars/', ['jpg','jpeg','png','webp','gif'], ['image/jpeg','image/png','image/webp','image/gif']);
        if ($av) {
            $pdo->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$av, $profile_user['id']]);
            refresh_user_session($pdo, $profile_user['id']);
            $profile_user['avatar'] = $av;
            $msg = t('avatar_updated');
        } else {
            $msg = t('avatar_format_error');
        }
    }
}

$uid = $profile_user['id'];

$stat_ratings = $pdo->prepare("SELECT COUNT(*) FROM ratings WHERE user_id = ?");
$stat_ratings->execute([$uid]);
$stat_ratings_count = (int)$stat_ratings->fetchColumn();

$stat_comments = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
$stat_comments->execute([$uid]);
$stat_comments_count = (int)$stat_comments->fetchColumn();

$stat_watchlist = $pdo->prepare("SELECT COUNT(*) FROM watchlist WHERE user_id = ?");
$stat_watchlist->execute([$uid]);
$stat_watchlist_count = (int)$stat_watchlist->fetchColumn();

$stat_watched = $pdo->prepare("SELECT COUNT(DISTINCT content_id) FROM watch_history WHERE user_id = ?");
$stat_watched->execute([$uid]);
$stat_watched_count = (int)$stat_watched->fetchColumn();

$stat_watch_time = $pdo->prepare("SELECT COALESCE(SUM(position_seconds), 0) FROM watch_progress WHERE user_id = ?");
$stat_watch_time->execute([$uid]);
$stat_watch_seconds = (int)$stat_watch_time->fetchColumn();
$stat_watch_hours = floor($stat_watch_seconds / 3600);
$stat_watch_mins = floor(($stat_watch_seconds % 3600) / 60);

$stat_favorites = $pdo->prepare("SELECT COUNT(*) FROM user_content_status WHERE user_id = ? AND status = 'favorite'");
$stat_favorites->execute([$uid]);
$stat_favorites_count = (int)$stat_favorites->fetchColumn();

$stat_streak = 0;
$stat_streak_record = 0;
$streak_rows = $pdo->prepare("SELECT DISTINCT DATE(watched_at) as d FROM watch_history WHERE user_id = ? ORDER BY d DESC");
$streak_rows->execute([$uid]);
$streak_dates = $streak_rows->fetchAll(PDO::FETCH_COLUMN);
if ($streak_dates) {
    $today = new DateTime('today');
    $check = new DateTime($streak_dates[0]);
    if ($check->format('Y-m-d') === $today->format('Y-m-d') || $check->format('Y-m-d') === $today->modify('-1 day')->format('Y-m-d')) {
        $stat_streak = 1;
        $current = new DateTime($streak_dates[0]);
        for ($i = 1; $i < count($streak_dates); $i++) {
            $prev = new DateTime($streak_dates[$i]);
            $diff = $current->diff($prev)->days;
            if ($diff === 1) { $stat_streak++; $current = $prev; }
            else break;
        }
    }
    $stat_streak_record = 1;
    $current = new DateTime($streak_dates[count($streak_dates) - 1]);
    $run = 1;
    for ($i = count($streak_dates) - 2; $i >= 0; $i--) {
        $prev = new DateTime($streak_dates[$i]);
        $diff = $prev->diff($current)->days;
        if ($diff === 1) { $run++; $current = $prev; if ($run > $stat_streak_record) $stat_streak_record = $run; }
        else { $current = $prev; $run = 1; }
    }
}

$tab = $_GET['tab'] ?? 'history';
$cat = $_GET['cat'] ?? 'all';
$allowed_tabs = ['history','watching','planned','completed','paused','dropped','favorites','notifications'];
if (!in_array($tab, $allowed_tabs, true)) $tab = 'history';
$allowed_cats = ['all','kino','anime','multfilm'];
if (!in_array($cat, $allowed_cats, true)) $cat = 'all';

$collection_items = [];
$cat_filter = '';
if ($cat !== 'all') {
    $cat_filter = " AND cat.slug = " . $pdo->quote($cat);
}

if ($tab === 'history') {
    $sql = "SELECT DISTINCT wh.content_id, c.title, c.poster, c.release_year, cat.slug as category, cat.name as category_name, MAX(wh.watched_at) as last_watched
            FROM watch_history wh
            JOIN content c ON c.id = wh.content_id
            JOIN categories cat ON cat.id = c.category_id
            WHERE wh.user_id = ? $cat_filter
            GROUP BY wh.content_id
            ORDER BY last_watched DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid]);
    $collection_items = $stmt->fetchAll();
} elseif ($tab === 'favorites') {
    $sql = "SELECT ucs.content_id, c.title, c.poster, c.release_year, cat.slug as category, cat.name as category_name
            FROM user_content_status ucs
            JOIN content c ON c.id = ucs.content_id
            JOIN categories cat ON cat.id = c.category_id
            WHERE ucs.user_id = ? AND ucs.status = 'favorite' $cat_filter
            ORDER BY ucs.created_at DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid]);
    $collection_items = $stmt->fetchAll();
} elseif ($tab === 'notifications') {
    if ($is_own) {
        $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid]);
        $collection_items = $stmt->fetchAll();
    }
} else {
    $status_map = [
        'watching' => 'watching',
        'planned' => 'planned',
        'completed' => 'completed',
        'paused' => 'paused',
        'dropped' => 'dropped',
    ];
    $status_val = $status_map[$tab] ?? $tab;
    $sql = "SELECT ucs.content_id, c.title, c.poster, c.release_year, cat.slug as category, cat.name as category_name
            FROM user_content_status ucs
            JOIN content c ON c.id = ucs.content_id
            JOIN categories cat ON cat.id = c.category_id
            WHERE ucs.user_id = ? AND ucs.status = ? $cat_filter
            ORDER BY ucs.updated_at DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid, $status_val]);
    $collection_items = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="/uzdub/css/profile.css">

<div class="profile-page">

<div class="profile-header">
    <div class="profile-banner"></div>
    <div class="profile-header-inner">
        <div class="profile-avatar-section">
            <div class="profile-avatar-wrap">
                <img src="<?php echo avatar_url($profile_user['avatar']); ?>" alt="Avatar" id="avatar-img" class="profile-avatar-img">
                <?php if ($profile_user['is_premium']): ?>
                <div class="avatar-crown">⭐</div>
                <?php endif; ?>
                <div class="online-dot"></div>
                <?php if ($is_own): ?>
                <button class="avatar-change-btn" onclick="document.getElementById('avatarModal').classList.add('active')" title="<?php echo t('change_avatar'); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.85 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="profile-info-section">
            <div class="profile-name-row">
                <h1 class="profile-username"><?php echo e($profile_user['username']); ?></h1>
                <?php if ($profile_user['is_premium']): ?>
                <span class="premium-badge-sm">⭐ <?php echo t('premium_badge'); ?></span>
                <?php endif; ?>
            </div>
            <div class="profile-meta-row">
                <span class="meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                    <?php echo date('d.m.Y', strtotime($profile_user['created_at'])); ?>
                </span>
                <span class="meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span id="sessionTimer">00m 00s</span>
                </span>
                <span class="meta-item meta-role"><?php echo $profile_user['is_premium'] ? t('premium_badge') : t('user_role'); ?></span>
                <span class="meta-item meta-id"><?php echo t('id_label'); ?><?php echo e($profile_user['user_id']); ?></span>
            </div>
            <div class="profile-actions-row">
                <?php if ($is_own): ?>
                <a href="premium.php" class="pf-btn pf-btn-gold">⭐ <?php echo t('get_premium_btn'); ?></a>
                <a href="auth/logout.php" class="pf-btn pf-btn-ghost">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                    <?php echo t('logout_btn'); ?>
                </a>
                <?php elseif (!is_user()): ?>
                <a href="auth/login.php" class="pf-btn pf-btn-blue"><?php echo t('login_btn'); ?></a>
                <?php else: ?>
                <a href="chat.php?with=<?php echo e($profile_user['user_id']); ?>" class="pf-btn pf-btn-blue">💬 <?php echo t('send_message'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="profile-stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-value"><?php echo number_format($stat_watched_count); ?></div>
        <div class="stat-label"><?php echo t('watched'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⭐</div>
        <div class="stat-value"><?php echo number_format($stat_ratings_count); ?></div>
        <div class="stat-label"><?php echo t('ratings'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⏱️</div>
        <div class="stat-value"><?php echo $stat_watch_hours > 0 ? $stat_watch_hours . t('hours_abbrev') . $stat_watch_mins . t('minutes_abbrev_short') : $stat_watch_mins . t('minutes_abbrev_short'); ?></div>
        <div class="stat-label"><?php echo t('total_time'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🔥</div>
        <div class="stat-value"><?php echo $stat_streak; ?></div>
        <div class="stat-label"><?php echo t('streak'); ?> (<?php echo $stat_streak_record; ?><?php echo t('records'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">❤️</div>
        <div class="stat-value"><?php echo number_format($stat_favorites_count); ?></div>
        <div class="stat-label"><?php echo t('favorites'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💬</div>
        <div class="stat-value"><?php echo number_format($stat_comments_count); ?></div>
        <div class="stat-label"><?php echo t('comments'); ?></div>
    </div>
</div>

<div class="profile-tabs-section">
    <div class="profile-tabs">
        <a href="?uid=<?php echo e($uid_param); ?>&tab=history&cat=<?php echo e($cat); ?>" class="pf-tab <?php echo $tab==='history'?'active':''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?php echo t('tab_history'); ?>
        </a>
        <a href="?uid=<?php echo e($uid_param); ?>&tab=watching&cat=<?php echo e($cat); ?>" class="pf-tab <?php echo $tab==='watching'?'active':''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            <?php echo t('tab_watching'); ?>
        </a>
        <a href="?uid=<?php echo e($uid_param); ?>&tab=planned&cat=<?php echo e($cat); ?>" class="pf-tab <?php echo $tab==='planned'?'active':''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            <?php echo t('tab_planned'); ?>
        </a>
        <a href="?uid=<?php echo e($uid_param); ?>&tab=completed&cat=<?php echo e($cat); ?>" class="pf-tab <?php echo $tab==='completed'?'active':''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <?php echo t('tab_completed'); ?>
        </a>
        <a href="?uid=<?php echo e($uid_param); ?>&tab=paused&cat=<?php echo e($cat); ?>" class="pf-tab <?php echo $tab==='paused'?'active':''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="4" height="16" x="6" y="4"/><rect width="4" height="16" x="14" y="4"/></svg>
            <?php echo t('tab_on_hold'); ?>
        </a>
        <a href="?uid=<?php echo e($uid_param); ?>&tab=dropped&cat=<?php echo e($cat); ?>" class="pf-tab <?php echo $tab==='dropped'?'active':''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" x2="9" y1="9" y2="15"/><line x1="9" x2="15" y1="9" y2="15"/></svg>
            <?php echo t('tab_dropped'); ?>
        </a>
        <a href="?uid=<?php echo e($uid_param); ?>&tab=favorites&cat=<?php echo e($cat); ?>" class="pf-tab <?php echo $tab==='favorites'?'active':''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <?php echo t('tab_favorite'); ?>
        </a>
        <?php if ($is_own): ?>
        <a href="?uid=<?php echo e($uid_param); ?>&tab=notifications&cat=<?php echo e($cat); ?>" class="pf-tab <?php echo $tab==='notifications'?'active':''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            Bildirishnomalar
        </a>
        <?php endif; ?>
    </div>

    <?php if ($tab !== 'notifications'): ?>
    <div class="profile-cat-filter">
        <a href="?uid=<?php echo e($uid_param); ?>&tab=<?php echo e($tab); ?>&cat=all" class="cat-btn <?php echo $cat==='all'?'active':''; ?>"><?php echo t('cat_all'); ?></a>
        <a href="?uid=<?php echo e($uid_param); ?>&tab=<?php echo e($tab); ?>&cat=kino" class="cat-btn <?php echo $cat==='kino'?'active':''; ?>">🎬 <?php echo t('cat_movies'); ?></a>
        <a href="?uid=<?php echo e($uid_param); ?>&tab=<?php echo e($tab); ?>&cat=anime" class="cat-btn <?php echo $cat==='anime'?'active':''; ?>">🎭 <?php echo t('cat_anime'); ?></a>
        <a href="?uid=<?php echo e($uid_param); ?>&tab=<?php echo e($tab); ?>&cat=multfilm" class="cat-btn <?php echo $cat==='multfilm'?'active':''; ?>">🎪 <?php echo t('cat_cartoons'); ?></a>
    </div>
    <?php endif; ?>

    <div class="profile-collection">
        <?php if ($tab === 'notifications'): ?>
            <?php if (!$is_own): ?>
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".3"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <p><?php echo t('notifications_profile_only'); ?></p>
            </div>
            <?php elseif (empty($collection_items)): ?>
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".3"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <p><?php echo t('no_notifications'); ?></p>
            </div>
            <?php else: ?>
            <?php foreach ($collection_items as $n): ?>
            <div class="notif-item <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                <div class="notif-icon"><?php
                    $icons = ['like' => '❤️', 'comment' => '💬', 'message' => '📩', 'system' => '🔔', 'premium' => '⭐'];
                    echo $icons[$n['type']] ?? '🔔';
                ?></div>
                <div class="notif-content">
                    <div class="notif-title"><?php echo e($n['title']); ?></div>
                    <?php if ($n['message']): ?><div class="notif-msg"><?php echo e($n['message']); ?></div><?php endif; ?>
                    <div class="notif-time"><?php echo date('d.m.Y H:i', strtotime($n['created_at'])); ?></div>
                </div>
                <?php if (!$n['is_read']): ?>
                <div class="notif-unread-dot"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <?php if (empty($collection_items)): ?>
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".3"><rect width="18" height="18" x="3" y="3" rx="2"/><line x1="12" x2="12" y1="8" y2="16"/><line x1="8" x2="16" y1="12" y2="12"/></svg>
                <p><?php echo t('section_empty'); ?></p>
            </div>
            <?php else: ?>
            <div class="collection-grid">
                <?php foreach ($collection_items as $item): ?>
                <a href="watch.php?id=<?php echo $item['content_id']; ?>" class="collection-card">
                    <div class="collection-poster">
                        <?php if ($item['poster']): ?>
                        <img src="/uzdub/uploads/posters/<?php echo e($item['poster']); ?>" alt="<?php echo e(t_title($item)); ?>" loading="lazy">
                        <?php else: ?>
                        <div class="no-poster">🎬</div>
                        <?php endif; ?>
                        <div class="collection-cat-badge"><?php echo e($item['category_name']); ?></div>
                    </div>
                    <div class="collection-info">
                        <div class="collection-title"><?php echo e(t_title($item)); ?></div>
                        <div class="collection-meta">
                            <?php if ($item['release_year']): ?>
                            <span><?php echo e($item['release_year']); ?></span>
                            <?php endif; ?>
                            <?php if ($tab === 'history' && isset($item['last_watched'])): ?>
                            <span>· <?php echo date('d.m', strtotime($item['last_watched'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</div>

<?php if ($is_own): ?>
<div class="avatar-modal" id="avatarModal">
    <div class="avatar-modal-box">
        <h3><?php echo t('change_avatar_modal'); ?></h3>
        <form method="post" enctype="multipart/form-data" id="avatarForm">
            <?php echo csrf_input(); ?>
            <input type="file" name="avatar" accept="image/*" required>
            <div class="avatar-modal-btns">
                <button type="submit" name="update_avatar" class="pf-btn pf-btn-blue"><?php echo t('upload_btn'); ?></button>
                <button type="button" class="pf-btn pf-btn-ghost" onclick="document.getElementById('avatarModal').classList.remove('active')"><?php echo t('cancel_btn'); ?></button>
            </div>
        </form>
    </div>
</div>
<script>
(function() {
    var form = document.getElementById('avatarForm');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var fd = new FormData(form);
        fetch(window.location.href, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(function(r){return r.json();})
        .then(function(d){
            if(window.showToast) showToast(d.msg, d.ok?'success':'error');
            if(d.ok && d.avatar_url){document.getElementById('avatar-img').src=d.avatar_url;document.getElementById('avatarModal').classList.remove('active');}
        })
        .catch(function(){if(window.showToast)showToast("Xatolik.",'error');});
    });
})();
</script>
<?php endif; ?>

<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',function(){if(window.showToast)showToast(<?php echo json_encode($msg,JSON_UNESCAPED_UNICODE); ?>,<?php echo json_encode(strpos($msg,'Xatolik')===0?'error':'success'); ?>);});</script>
<?php endif; ?>

<script>
(function(){
    var sec = 0;
    var el = document.getElementById('sessionTimer');
    if(!el) return;
    setInterval(function(){
        sec++;
        var h = Math.floor(sec/3600);
        var m = Math.floor((sec%3600)/60);
        var s = sec%60;
        var parts = [];
        if(h>0) parts.push(h+'<?php echo t('hours_unit'); ?>');
        parts.push((m<10?'0':'')+m+'m');
        parts.push((s<10?'0':'')+s+'s');
        el.textContent = parts.join(' ');
    },1000);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
