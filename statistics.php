<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = t('stats_page_title');

$stats = [
    'content'   => $pdo->query("SELECT COUNT(*) FROM content")->fetchColumn(),
    'users'     => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'views'     => $pdo->query("SELECT COALESCE(SUM(views),0) FROM content")->fetchColumn(),
    'premium'   => $pdo->query("SELECT COUNT(*) FROM users WHERE is_premium=1 AND premium_expires_at > NOW()")->fetchColumn(),
    'kino'      => $pdo->query("SELECT COUNT(*) FROM content c JOIN categories cat ON c.category_id=cat.id WHERE cat.slug='kino'")->fetchColumn(),
    'anime'     => $pdo->query("SELECT COUNT(*) FROM content c JOIN categories cat ON c.category_id=cat.id WHERE cat.slug='anime'")->fetchColumn(),
    'multfilm'  => $pdo->query("SELECT COUNT(*) FROM content c JOIN categories cat ON c.category_id=cat.id WHERE cat.slug='multfilm'")->fetchColumn(),
    'comments'  => $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
    'messages'  => $pdo->query("SELECT COUNT(*) FROM global_messages")->fetchColumn(),
    'ratings'   => $pdo->query("SELECT COUNT(*) FROM ratings")->fetchColumn(),
    'episodes'  => $pdo->query("SELECT COUNT(*) FROM episodes")->fetchColumn(),
    'top_viewed'=> $pdo->query("SELECT c.title, c.views, c.rating FROM content c ORDER BY c.views DESC LIMIT 10")->fetchAll(),
    'top_rated' => $pdo->query("SELECT c.id, c.title, c.rating, c.views FROM content c WHERE c.rating > 0 ORDER BY c.rating DESC, c.views DESC LIMIT 10")->fetchAll(),
    'newest'    => $pdo->query("SELECT c.title, c.created_at, c.rating FROM content c ORDER BY c.created_at DESC LIMIT 10")->fetchAll(),
    'cat_stats' => $pdo->query("SELECT cat.name, cat.slug, COUNT(c.id) as cnt FROM categories cat LEFT JOIN content c ON c.category_id=cat.id GROUP BY cat.id ORDER BY cnt DESC")->fetchAll(),
];

include __DIR__ . '/includes/header.php';
?>
<style>
.stats-hero { text-align:center; padding:60px 20px 40px; }
.stats-hero h1 { font-size:36px; color:var(--blue-glow); margin-bottom:8px; text-shadow:0 0 20px rgba(79,195,247,0.4); }
.stats-hero p { color:var(--text-muted); font-size:16px; }
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; padding:0 20px 40px; max-width:900px; margin:0 auto; }
.stat-card { background:var(--card-bg); border:1px solid rgba(33,150,243,0.15); border-radius:14px; padding:24px 16px; text-align:center; transition:transform .25s,box-shadow .25s; }
.stat-card:hover { transform:translateY(-4px); box-shadow:0 12px 36px rgba(0,0,0,0.3); }
.stat-card .stat-icon { font-size:28px; margin-bottom:8px; }
.stat-card .stat-num { font-size:28px; font-weight:900; color:var(--blue-glow); display:block; }
.stat-card .stat-label { font-size:13px; color:var(--text-muted); margin-top:4px; }
.stats-section { max-width:900px; margin:0 auto; padding:0 20px 40px; }
.stats-section h2 { font-size:22px; color:var(--text-light); margin-bottom:16px; display:flex; align-items:center; gap:10px; }
.stats-table { width:100%; border-collapse:collapse; background:var(--card-bg); border-radius:12px; overflow:hidden; border:1px solid rgba(33,150,243,0.12); }
.stats-table th { background:rgba(33,150,243,0.1); padding:12px 16px; text-align:left; font-size:13px; color:var(--text-muted); font-weight:600; border-bottom:1px solid rgba(33,150,243,0.12); }
.stats-table td { padding:11px 16px; border-bottom:1px solid rgba(255,255,255,0.04); font-size:14px; color:var(--text-light); }
.stats-table tr:last-child td { border-bottom:none; }
.stats-table tr:hover td { background:rgba(33,150,243,0.05); }
.badge-sm { background:var(--blue-deep); color:#fff; font-size:11px; padding:2px 8px; border-radius:10px; font-weight:600; }
.cat-bars { display:flex; flex-direction:column; gap:12px; }
.cat-bar { display:flex; align-items:center; gap:12px; }
.cat-bar-label { width:120px; font-size:14px; color:var(--text-light); text-align:right; }
.cat-bar-track { flex:1; height:28px; background:rgba(255,255,255,0.05); border-radius:14px; overflow:hidden; position:relative; }
.cat-bar-fill { height:100%; border-radius:14px; display:flex; align-items:center; padding-left:12px; font-size:13px; font-weight:700; color:#fff; transition:width .8s cubic-bezier(.4,0,.2,1); }
.cat-bar-fill.kino { background:linear-gradient(90deg,#2196f3,#0d47a1); }
.cat-bar-fill.anime { background:linear-gradient(90deg,#e91e63,#880e4f); }
.cat-bar-fill.multfilm { background:linear-gradient(90deg,#4caf50,#1b5e20); }
.cat-bar-fill.other { background:linear-gradient(90deg,#ff9800,#e65100); }
</style>

<div class="stats-hero">
    <h1>📊 <?php echo t('stats_heading'); ?></h1>
    <p><?php echo t('stats_desc'); ?></p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">🎬</div>
        <span class="stat-num" id="sContent"><?php echo $stats['content']; ?></span>
        <div class="stat-label"><?php echo t('stat_content'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <span class="stat-num" id="sUsers"><?php echo $stats['users']; ?></span>
        <div class="stat-label"><?php echo t('stat_users'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👁️</div>
        <span class="stat-num" id="sViews"><?php echo number_format($stats['views']); ?></span>
        <div class="stat-label"><?php echo t('stat_views'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⭐</div>
        <span class="stat-num"><?php echo $stats['premium']; ?></span>
        <div class="stat-label"><?php echo t('stat_premium'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🎥</div>
        <span class="stat-num"><?php echo $stats['episodes']; ?></span>
        <div class="stat-label"><?php echo t('stat_episodes'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💬</div>
        <span class="stat-num"><?php echo $stats['comments']; ?></span>
        <div class="stat-label"><?php echo t('stat_comments'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⭐</div>
        <span class="stat-num"><?php echo $stats['ratings']; ?></span>
        <div class="stat-label"><?php echo t('stat_ratings'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">✉️</div>
        <span class="stat-num"><?php echo $stats['messages']; ?></span>
        <div class="stat-label"><?php echo t('stat_messages'); ?></div>
    </div>
</div>

<div class="stats-section">
    <h2>📂 <?php echo t('by_category'); ?></h2>
    <div class="cat-bars">
        <?php
        $max_cnt = max(array_column($stats['cat_stats'], 'cnt') ?: [1]);
        foreach ($stats['cat_stats'] as $cs):
            $pct = $max_cnt > 0 ? round($cs['cnt'] / $max_cnt * 100) : 0;
            $cls = $cs['slug'] ?? 'other';
        ?>
        <div class="cat-bar">
            <div class="cat-bar-label"><?php echo e($cs['name']); ?></div>
            <div class="cat-bar-track">
                <div class="cat-bar-fill <?php echo e($cls); ?>" style="width:<?php echo $pct; ?>%"><?php echo $cs['cnt']; ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($stats['top_rated']): ?>
<div class="stats-section">
    <h2>🏆 <?php echo t('top_rated'); ?></h2>
    <table class="stats-table">
        <thead><tr><th>#</th><th><?php echo t('name_col'); ?></th><th><?php echo t('rating_col'); ?></th><th><?php echo t('views_col'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($stats['top_rated'] as $i => $r): ?>
        <tr>
            <td><?php echo $i + 1; ?></td>
            <td><a href="/uzdub/watch.php?id=<?php echo $r['id']; ?>" style="color:var(--blue-glow);text-decoration:none;"><?php echo e($r['title']); ?></a></td>
            <td><span class="badge-sm">⭐ <?php echo e($r['rating']); ?></span></td>
            <td><?php echo number_format($r['views']); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($stats['top_viewed']): ?>
<div class="stats-section">
    <h2>👀 <?php echo t('most_viewed'); ?></h2>
    <table class="stats-table">
        <thead><tr><th>#</th><th><?php echo t('name_col'); ?></th><th><?php echo t('views_col'); ?></th><th><?php echo t('rating_col'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($stats['top_viewed'] as $i => $v): ?>
        <tr>
            <td><?php echo $i + 1; ?></td>
            <td><?php echo e($v['title']); ?></td>
            <td><?php echo number_format($v['views']); ?></td>
            <td><span class="badge-sm">⭐ <?php echo e($v['rating']); ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($stats['newest']): ?>
<div class="stats-section">
    <h2>🆕 <?php echo t('newest'); ?></h2>
    <table class="stats-table">
        <thead><tr><th>#</th><th><?php echo t('name_col'); ?></th><th><?php echo t('date_col'); ?></th><th><?php echo t('rating_col'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($stats['newest'] as $i => $n): ?>
        <tr>
            <td><?php echo $i + 1; ?></td>
            <td><?php echo e($n['title']); ?></td>
            <td><?php echo date('d.m.Y', strtotime($n['created_at'])); ?></td>
            <td><span class="badge-sm">⭐ <?php echo e($n['rating']); ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
