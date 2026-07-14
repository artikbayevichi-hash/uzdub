<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
if ($slug === 'serial') { header('Location: /uzdub/index.php'); exit; }
$stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
$stmt->execute([$slug]);
$category = $stmt->fetch();

if (!$category) {
    header('Location: index.php');
    exit;
}

$page_title = $category['name'];

$stmt = $pdo->prepare("SELECT * FROM content WHERE category_id = ? ORDER BY created_at DESC");
$stmt->execute([$category['id']]);
$items = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="content-section" style="margin-top: 90px;">
    <h2><?php echo e($category['name']); ?></h2>
</div>

<div class="grid-wrap">
    <?php foreach ($items as $item): ?>
    <a href="watch.php?id=<?php echo $item['id']; ?>" class="card">
        <img src="<?php echo $item['poster'] ? 'uploads/posters/' . e($item['poster']) : 'https://via.placeholder.com/300x420/121a2b/2196f3?text=' . urlencode($item['title']); ?>" alt="<?php echo e($item['title']); ?>">
        <div class="card-info">
            <h3><?php echo e($item['title']); ?></h3>
            <div class="meta">
                <span><?php echo e($item['release_year']); ?></span>
                <span class="badge">&#9733; <?php echo e($item['rating']); ?></span>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
    <?php if (empty($items)): ?>
        <p>Bu bo'limda hozircha kontent yo'q.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
