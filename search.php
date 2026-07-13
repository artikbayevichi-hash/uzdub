<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$q = trim($_GET['q'] ?? '');
$page_title = 'Qidiruv: ' . $q;
$items = [];
$found_user = null;
$found_content = null;

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

    // Umumiy kontent qidiruvi (nomi yoki kodi bo'yicha)
    $stmt = $pdo->prepare("SELECT * FROM content WHERE title LIKE ? OR content_code LIKE ? ORDER BY created_at DESC");
    $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
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
    <h2>"<?php echo e($q); ?>" bo'yicha natijalar</h2>
</div>

<?php if ($found_user): ?>
<div style="padding:0 40px;">
    <div class="user-result-card">
        <img src="<?php echo avatar_url($found_user['avatar']); ?>" alt="">
        <div class="info">
            <h3><?php echo e($found_user['username']); ?> <?php if ($found_user['is_premium']): ?>⭐<?php endif; ?></h3>
            <div class="uid">🆔 <?php echo e($found_user['user_id']); ?></div>
        </div>
        <a href="profile.php?uid=<?php echo e($found_user['user_id']); ?>" class="view-profile">Profilni ko'rish</a>
    </div>
</div>
<?php endif; ?>

<?php if ($found_content && empty($items)): $items = [$found_content]; endif; ?>

<div class="grid-wrap">
    <?php foreach ($items as $item): ?>
    <a href="watch.php?id=<?php echo $item['id']; ?>" class="card">
        <img src="<?php echo $item['poster'] ? 'uploads/posters/' . e($item['poster']) : 'https://via.placeholder.com/300x420/121a2b/2196f3?text=' . urlencode($item['title']); ?>" alt="<?php echo e($item['title']); ?>">
        <div class="card-info">
            <h3><?php echo e($item['title']); ?></h3>
            <div class="meta">
                <span><?php echo e($item['content_code'] ?? ''); ?></span>
                <span class="badge">&#9733; <?php echo e($item['rating']); ?></span>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
    <?php if ($q !== '' && empty($items) && !$found_user): ?>
        <p>Hech narsa topilmadi.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
