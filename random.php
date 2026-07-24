<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = t('random_page_title');

$slug = $_GET['slug'] ?? '';
if ($slug) {
    $allowed = ['kino', 'anime', 'multfilm', 'serial'];
    if (in_array($slug, $allowed)) {
        try {
            $item = $pdo->prepare("SELECT c.id FROM content c JOIN categories cat ON c.category_id = cat.id WHERE cat.slug = ? ORDER BY RAND() LIMIT 1");
            $item->execute([$slug]);
            $row = $item->fetch();
            if ($row) {
                header('Location: /uzdub/watch.php?id=' . $row['id']);
                exit;
            }
        } catch (Exception $e) {}
    }
    $no_content_msg = t('no_content_in_category');
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.random-page {
    max-width: 600px;
    margin: 40px auto;
    padding: 0 20px;
    text-align: center;
}
.random-title {
    font-size: 28px;
    font-weight: 800;
    margin-bottom: 8px;
    background: linear-gradient(135deg, #2196f3, #e040fb);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.random-subtitle {
    font-size: 14px;
    color: var(--text-muted, #9aa8bd);
    margin-bottom: 36px;
}
.random-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}
.random-card {
    background: var(--card-bg, #121a2b);
    border: 1px solid rgba(33,150,243,0.15);
    border-radius: 16px;
    padding: 28px 16px;
    text-decoration: none;
    color: var(--text-light, #e8eef5);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}
.random-card:hover {
    transform: translateY(-6px) scale(1.03);
    border-color: var(--accent, #2196f3);
    box-shadow: 0 8px 32px rgba(33,150,243,0.25);
}
.random-card:active {
    transform: scale(0.97);
}
.random-card-icon {
    font-size: 42px;
    line-height: 1;
    filter: drop-shadow(0 2px 8px rgba(33,150,243,0.3));
}
.random-card-label {
    font-size: 16px;
    font-weight: 700;
}
.random-card-count {
    font-size: 12px;
    color: var(--text-muted, #9aa8bd);
}
@media (max-width: 480px) {
    .random-grid { grid-template-columns: 1fr; gap: 12px; }
    .random-title { font-size: 22px; }
}
</style>

<div class="random-page">
    <div class="random-title">🎲 <?php echo t('random_heading'); ?></div>
    <div class="random-subtitle"><?php echo t('random_subtitle'); ?></div>
    <?php if (!empty($no_content_msg)): ?>
    <div style="background:rgba(244,67,54,0.1);border:1px solid rgba(244,67,54,0.3);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#ef5350;font-size:13px;">
        ⚠️ <?php echo $no_content_msg; ?>
    </div>
    <?php endif; ?>
    <div class="random-grid">
        <?php
        $cats = [
            ['slug' => 'kino',    'icon' => '🎬', 'label' => t('random_kino_label'),    'color' => '#2196f3'],
            ['slug' => 'anime',   'icon' => '🎌', 'label' => t('random_anime_label'),   'color' => '#e040fb'],
            ['slug' => 'multfilm','icon' => '🧸', 'label' => t('random_multfilm_label'),'color' => '#4caf50'],
        ];
        foreach ($cats as $cat) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM content c JOIN categories cat ON c.category_id = cat.id WHERE cat.slug = ?");
            $stmt->execute([$cat['slug']]);
            $count = $stmt->fetchColumn();
            echo '<a href="/uzdub/random.php?slug=' . $cat['slug'] . '" class="random-card" style="--card-accent:' . $cat['color'] . ';">';
            echo '<div class="random-card-icon">' . $cat['icon'] . '</div>';
            echo '<div class="random-card-label">' . $cat['label'] . '</div>';
            echo '<div class="random-card-count">' . $count . ' ' . t('content_count') . '</div>';
            echo '</a>';
        }
        ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
