<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$q = trim($_GET['q'] ?? '');
$cat_filter = $_GET['cat'] ?? '';
$page_title = t('search_results_for') . $q;
$page_desc = t('search_results_for') . ' ' . $q . ' — ' . t('site_title');
$items = [];
$found_user = null;
$found_content = null;

// AJAX - avtotugallash (autocomplete) so'rovlari
if (isset($_GET['ajax_autocomplete']) && $q !== '') {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT id, title, title_ru, title_en, poster, release_year, rating, content_code FROM content WHERE title LIKE ? OR title_ru LIKE ? OR title_en LIKE ? ORDER BY views DESC, rating DESC LIMIT 6");
    $stmt->execute(['%' . $q . '%', '%' . $q . '%', '%' . $q . '%']);
    $results = $stmt->fetchAll();
    foreach ($results as &$r) {
        $r['display_title'] = search_display_title($r, $q);
        $r['poster'] = $r['poster'] ? poster_url($r['poster']) : null;
    }
    unset($r);
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($q !== '') {
    // Agar 8 xonali raqam bo'lsa -> foydalanuvchi ID
    if (preg_match('/^\d{8}$/', $q)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$q]);
        $found_user = $stmt->fetch();
    }
    // Agar kontent kodi formatida bo'lsa (masalan KN0001, AN0002)
    elseif (preg_match('/^[A-Za-z]{2}\d{4}$/', $q)) {
        $stmt = $pdo->prepare("SELECT * FROM content WHERE content_code = ?");
        $stmt->execute([strtoupper($q)]);
        $found_content = $stmt->fetch();
    }

    // Kategoriya filtri bilan qidiruv
    if ($cat_filter && in_array($cat_filter, ['kino', 'anime', 'multfilm'])) {
        $stmt = $pdo->prepare("SELECT c.*, cat.name as cat_name FROM content c JOIN categories cat ON c.category_id=cat.id WHERE cat.slug = ? AND (c.title LIKE ? OR c.title_ru LIKE ? OR c.title_en LIKE ? OR c.content_code LIKE ?) ORDER BY c.views DESC, c.rating DESC");
        $stmt->execute([$cat_filter, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%']);
    } else {
        $stmt = $pdo->prepare("SELECT c.*, cat.name as cat_name FROM content c JOIN categories cat ON c.category_id=cat.id WHERE c.title LIKE ? OR c.title_ru LIKE ? OR c.title_en LIKE ? OR c.content_code LIKE ? ORDER BY c.views DESC, c.rating DESC");
        $stmt->execute(['%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%']);
    }
    $items = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>
<style>
.user-result-card { display:flex; align-items:center; gap:16px; background:var(--card-bg); border:1px solid rgba(33,150,243,0.3); border-radius:12px; padding:20px 24px; margin-bottom:24px; }
.user-result-card img { width:64px; height:64px; border-radius:50%; object-fit:cover; border:2px solid var(--blue-primary); }
.user-result-card .info h3 { font-size:18px; margin-bottom:4px; }
.user-result-card .info .uid { font-size:13px; color:var(--text-muted); }
.user-result-card a.view-profile { margin-left:auto; padding:9px 20px; background:var(--blue-primary); color:#fff; border-radius:7px; text-decoration:none; font-size:14px; font-weight:600; }
.user-result-card a.view-profile:hover { background:var(--blue-glow); }
</style>

<div class="content-section" style="margin-top: 90px;">
    <h2>🔍 "<?php echo e($q ?: t('all_content')); ?>" <?php echo t('results_for'); ?></h2>
    
    <!-- Kategoriya filtrlari -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 8px;">
        <a href="search.php?q=<?php echo e(urlencode($q)); ?>" class="btn" style="font-size:12px;padding:6px 14px;background:<?php echo $cat_filter ? 'rgba(33,150,243,0.15)' : 'var(--blue-primary)'; ?>;border-radius:20px;">🏠 <?php echo t('all'); ?></a>
        <a href="search.php?q=<?php echo e(urlencode($q)); ?>&cat=kino" class="btn" style="font-size:12px;padding:6px 14px;background:<?php echo $cat_filter==='kino' ? 'var(--blue-primary)' : 'rgba(33,150,243,0.15)'; ?>;border-radius:20px;">🎬 <?php echo t('movies'); ?></a>
        <a href="search.php?q=<?php echo e(urlencode($q)); ?>&cat=anime" class="btn" style="font-size:12px;padding:6px 14px;background:<?php echo $cat_filter==='anime' ? 'var(--blue-primary)' : 'rgba(33,150,243,0.15)'; ?>;border-radius:20px;">🎌 <?php echo t('anime'); ?></a>
        <a href="search.php?q=<?php echo e(urlencode($q)); ?>&cat=multfilm" class="btn" style="font-size:12px;padding:6px 14px;background:<?php echo $cat_filter==='multfilm' ? 'var(--blue-primary)' : 'rgba(33,150,243,0.15)'; ?>;border-radius:20px;">🧸 <?php echo t('cartoons'); ?></a>
    </div>
</div>

<div style="padding:0 40px;font-size:12px;color:var(--text-muted);">
    <?php echo count($items) . ' ' . t('contents_found'); ?>
    <?php if ($found_user): ?> · <?php echo t('users_found'); ?><?php endif; ?>
</div>

<?php if ($found_user): ?>
<div style="padding:0 40px;">
    <div class="user-result-card">
        <img src="<?php echo avatar_url($found_user['avatar']); ?>" alt="">
        <div class="info">
            <h3><?php echo e($found_user['username']); ?> <?php if ($found_user['is_premium']): ?>👑<?php endif; ?></h3>
            <div class="uid">🆔 <?php echo e($found_user['user_id']); ?></div>
        </div>
        <a href="profile.php?uid=<?php echo e($found_user['user_id']); ?>" class="view-profile"><?php echo t('view_profile'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($found_content && empty($items)): $items = [$found_content]; endif; ?>

<div class="grid-wrap">
    <?php foreach ($items as $item): $dtitle = search_display_title($item, $q); ?>
    <a href="watch.php?id=<?php echo $item['id']; ?>" class="card">
        <img src="<?php echo $item['poster'] ? e(poster_url($item['poster'])) : 'https://via.placeholder.com/300x420/121a2b/2196f3?text=' . urlencode($dtitle); ?>" alt="<?php echo e($dtitle); ?>">
        <div class="card-info">
            <h3><?php echo e($dtitle); ?></h3>
            <div class="meta">
                <span><?php echo e($item['content_code'] ?? ''); ?></span>
                <span class="badge">&#9733; <?php echo e($item['rating']); ?></span>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
    <?php if ($q !== '' && empty($items) && !$found_user): ?>
        <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--text-muted);">
            <div style="font-size:48px;margin-bottom:16px;">🔍</div>
            <p style="font-size:18px;font-weight:600;color:var(--text-light);margin-bottom:6px;"><?php echo t('nothing_found'); ?></p>
            <p style="font-size:14px;">"<?php echo e($q); ?>" — topilmadi. Boshqa so'z bilan urinib ko'ring.</p>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
