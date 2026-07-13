<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_user();

$page_title = "Mening ro'yxatim";
$user = current_user();

$stmt = $pdo->prepare("SELECT c.*, cat.name as cat_name FROM watchlist w JOIN content c ON w.content_id = c.id JOIN categories cat ON c.category_id = cat.id WHERE w.user_id = ? ORDER BY w.created_at DESC");
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="content-section" style="margin-top: 90px;">
    <h2>📋 Mening ro'yxatim (<?php echo count($items); ?>)</h2>
</div>

<div class="grid-wrap">
    <?php foreach ($items as $item): ?>
    <a href="watch.php?id=<?php echo $item['id']; ?>" class="card">
        <img src="<?php echo $item['poster'] ? 'uploads/posters/' . e($item['poster']) : 'https://via.placeholder.com/300x420/121a2b/2196f3?text=' . urlencode($item['title']); ?>" alt="<?php echo e($item['title']); ?>">
        <div class="card-info">
            <h3><?php echo e($item['title']); ?></h3>
            <div class="meta">
                <span><?php echo e($item['cat_name']); ?></span>
                <span class="badge">&#9733; <?php echo e($item['rating']); ?></span>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
    <?php if (empty($items)): ?>
        <p>Ro'yxatingiz bo'sh. Kino yoki anime sahifasida "Keyinroq ko'rish" tugmasini bosing.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
