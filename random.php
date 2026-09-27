<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = t('random_page_title');

$slug = $_GET['slug'] ?? '';
$allowed = ['kino', 'anime', 'multfilm'];
$no_content_msg = '';

if ($slug && in_array($slug, $allowed)) {
    // Faol katalog bo'yicha tasodifiy kontent
    try {
        $stmt = $pdo->prepare("SELECT c.id FROM content c JOIN categories cat ON c.category_id = cat.id WHERE cat.slug = ? ORDER BY RAND() LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        if ($row) {
            header('Location: ' . ROOT_URL . '/watch.php?id=' . $row['id']);
            exit;
        }
    } catch (Exception $e) {}
    $no_content_msg = t('no_content_in_category');
} else {
    // Barcha kataloglar bo'ylab tasodifiy kontent — darhol watch sahifasiga o'tadi
    try {
        $row = $pdo->query("SELECT c.id FROM content c ORDER BY RAND() LIMIT 1")->fetch();
        if ($row) {
            header('Location: ' . ROOT_URL . '/watch.php?id=' . $row['id']);
            exit;
        }
    } catch (Exception $e) {}
    $no_content_msg = t('no_content_in_category');
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.random-page {
    max-width: 520px;
    margin: 60px auto;
    padding: 0 20px;
    text-align: center;
}
.random-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 10px;
    background: linear-gradient(135deg, #2196f3, #e040fb);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.random-subtitle {
    font-size: 14px;
    color: var(--text-muted, #9aa8bd);
    margin-bottom: 24px;
}
</style>

<div class="random-page">
    <div class="random-title">🎲 <?php echo t('random_heading'); ?></div>
    <div class="random-subtitle"><?php echo t('random_subtitle'); ?></div>
    <?php if (!empty($no_content_msg)): ?>
    <div style="background:rgba(244,67,54,0.1);border:1px solid rgba(244,67,54,0.3);border-radius:10px;padding:14px 18px;color:#ef5350;font-size:14px;">
        ⚠️ <?php echo $no_content_msg; ?>
    </div>
    <p style="margin-top:14px;"><a href="<?php echo ROOT_URL; ?>/index.php" style="color:var(--acc2,#4fc3f7);">🏠 <?php echo t('home'); ?></a></p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>