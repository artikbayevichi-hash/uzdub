<?php
$page_title = 'Boshqaruv paneli';
include __DIR__ . '/includes/admin_header.php';

$total_content = $pdo->query("SELECT COUNT(*) c FROM content")->fetch()['c'];
$total_kino = $pdo->query("SELECT COUNT(*) c FROM content ct JOIN categories cat ON ct.category_id=cat.id WHERE cat.slug='kino'")->fetch()['c'];
$total_anime = $pdo->query("SELECT COUNT(*) c FROM content ct JOIN categories cat ON ct.category_id=cat.id WHERE cat.slug='anime'")->fetch()['c'];
$total_multfilm = $pdo->query("SELECT COUNT(*) c FROM content ct JOIN categories cat ON ct.category_id=cat.id WHERE cat.slug='multfilm'")->fetch()['c'];
$total_serial = $pdo->query("SELECT COUNT(*) c FROM content ct JOIN categories cat ON ct.category_id=cat.id WHERE cat.slug='serial'")->fetch()['c'];
$total_episodes = $pdo->query("SELECT COUNT(*) c FROM episodes")->fetch()['c'];
$total_views = $pdo->query("SELECT SUM(views) c FROM content")->fetch()['c'] ?? 0;
$total_genres = $pdo->query("SELECT COUNT(*) c FROM genres")->fetch()['c'];
$top_genres = $pdo->query("SELECT g.name, g.color, COUNT(cg.content_id) as cnt FROM genres g JOIN content_genres cg ON g.id = cg.genre_id GROUP BY g.id ORDER BY cnt DESC LIMIT 8")->fetchAll();
?>

<h1>Xush kelibsiz, <?php echo e($_SESSION['admin_username']); ?>!</h1>

<div class="stats-grid">
    <div class="stat-card"><div class="num"><?php echo $total_content; ?></div><div class="label">Jami kontent</div></div>
    <div class="stat-card"><div class="num"><?php echo $total_kino; ?></div><div class="label">Kino</div></div>
    <div class="stat-card"><div class="num"><?php echo $total_anime; ?></div><div class="label">Anime</div></div>
    <div class="stat-card"><div class="num"><?php echo $total_multfilm; ?></div><div class="label">Multfilm</div></div>
    <div class="stat-card"><div class="num"><?php echo $total_serial; ?></div><div class="label">Serial</div></div>
    <div class="stat-card"><div class="num"><?php echo $total_episodes; ?></div><div class="label">Jami qismlar</div></div>
    <div class="stat-card"><div class="num"><?php echo $total_views; ?></div><div class="label">Jami ko'rishlar</div></div>
    <div class="stat-card"><div class="num"><?php echo $total_genres; ?></div><div class="label">Janrlar</div></div>
</div>

<?php if (!empty($top_genres)): ?>
<div class="card-box" style="margin-top:20px;">
    <h2 style="margin-bottom:14px;">Eng ko'p janrlar</h2>
    <div class="genre-pills">
        <?php foreach ($top_genres as $g): ?>
        <span class="genre-pill" style="background:<?php echo e($g['color'] ?? '#7c4dff'); ?>22;border-color:<?php echo e($g['color'] ?? '#7c4dff'); ?>;color:<?php echo e($g['color'] ?? '#7c4dff'); ?>;"><?php echo e($g['name']); ?> (<?php echo $g['cnt']; ?>)</span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card-box">
    <h2 style="margin-bottom:14px;">Tezkor amallar</h2>
    <a href="add_content.php" class="btn">+ Yangi kino/anime/multfilm qo'shish</a>
    <a href="add_episode.php" class="btn" style="margin-left:10px;">+ Yangi qism qo'shish</a>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
