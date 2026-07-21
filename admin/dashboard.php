<?php
$page_title = 'Boshqaruv paneli — Analitika';
include __DIR__ . '/includes/admin_header.php';

// ============================================================
// 1. Asosiy statistika (summaries)
// ============================================================
$total_content  = (int)$pdo->query("SELECT COUNT(*) c FROM content")->fetch()['c'];
$total_kino     = (int)$pdo->query("SELECT COUNT(*) c FROM content c JOIN categories cat ON c.category_id=cat.id WHERE cat.slug='kino'")->fetch()['c'];
$total_anime    = (int)$pdo->query("SELECT COUNT(*) c FROM content c JOIN categories cat ON c.category_id=cat.id WHERE cat.slug='anime'")->fetch()['c'];
$total_multfilm = (int)$pdo->query("SELECT COUNT(*) c FROM content c JOIN categories cat ON c.category_id=cat.id WHERE cat.slug='multfilm'")->fetch()['c'];
$total_views    = (int)($pdo->query("SELECT COALESCE(SUM(views),0) c FROM content")->fetch()['c']);
$total_users    = (int)$pdo->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
$premium_users  = (int)$pdo->query("SELECT COUNT(*) c FROM users WHERE is_premium=1")->fetch()['c'];
$total_payments = (int)$pdo->query("SELECT COUNT(*) c FROM premium_payments WHERE status='approved'")->fetch()['c'];
$pending_payments = (int)$pdo->query("SELECT COUNT(*) c FROM premium_payments WHERE status='pending'")->fetch()['c'];
$total_pay_sum  = (int)($pdo->query("SELECT COALESCE(SUM(amount),0) c FROM premium_payments WHERE status='approved'")->fetch()['c']);
$monthly_views  = (int)($pdo->query("SELECT COALESCE(SUM(views),0) c FROM content WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetch()['c']);
$active_today   = (int)($pdo->query("SELECT COUNT(DISTINCT user_id) c FROM watch_progress WHERE DATE(updated_at) = CURDATE()")->fetch()['c'] ?? 0);
$this_month_new = (int)($pdo->query("SELECT COUNT(*) c FROM users WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetch()['c']);

// ============================================================
// 2. Kunlik ko'rishlar (so'nggi 30 kun)
// ============================================================
$daily_views_raw = $pdo->query("
    SELECT DATE(updated_at) AS d, COUNT(*) AS cnt
    FROM watch_progress
    WHERE updated_at >= CURDATE() - INTERVAL 30 DAY
    GROUP BY DATE(updated_at)
    ORDER BY d
")->fetchAll();
$daily_views_index = [];
foreach ($daily_views_raw as $r) $daily_views_index[$r['d']] = (int)$r['cnt'];
$daily_view_data = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $daily_view_data[] = $daily_views_index[$d] ?? 0;
}

// ============================================================
// 3. Kunlik yangi foydalanuvchilar (so'nggi 30 kun)
// ============================================================
$daily_users_raw = $pdo->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS cnt
    FROM users
    WHERE created_at >= CURDATE() - INTERVAL 30 DAY
    GROUP BY DATE(created_at)
    ORDER BY d
")->fetchAll();
$daily_users_index = [];
foreach ($daily_users_raw as $r) $daily_users_index[$r['d']] = (int)$r['cnt'];
$daily_user_data = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $daily_user_data[] = $daily_users_index[$d] ?? 0;
}

// ============================================================
// 4. Eng ko'p ko'rilgan kontent (TOP 10)
// ============================================================
$top_viewed = $pdo->query("
    SELECT c.title, c.views, c.rating, c.release_year, cat.name AS cat_name, c.content_code
    FROM content c
    JOIN categories cat ON c.category_id = cat.id
    ORDER BY c.views DESC
    LIMIT 10
")->fetchAll();

// ============================================================
// 5. Eng yuqori baholangan kontent (TOP 10)
// ============================================================
$top_rated = $pdo->query("
    SELECT c.title, c.rating, c.views, c.release_year, cat.name AS cat_name, c.content_code
    FROM content c
    JOIN categories cat ON c.category_id = cat.id
    WHERE c.rating > 0
    ORDER BY c.rating DESC, c.views DESC
    LIMIT 10
")->fetchAll();

// ============================================================
// 6. Janrlar bo'yicha kontent taqsimoti
// ============================================================
$genre_dist = $pdo->query("
    SELECT g.name, g.color, COUNT(cg.content_id) AS cnt
    FROM genres g
    JOIN content_genres cg ON g.id = cg.genre_id
    GROUP BY g.id
    ORDER BY cnt DESC
    LIMIT 12
")->fetchAll();

// ============================================================
// 7. Kategoriyalar bo'yicha kontent taqsimoti (donut)
// ============================================================
$cat_dist = [
    ['label' => 'Kino',     'cnt' => $total_kino,     'color' => '#2196f3'],
    ['label' => 'Anime',    'cnt' => $total_anime,    'color' => '#e040fb'],
    ['label' => 'Multfilm', 'cnt' => $total_multfilm, 'color' => '#ffb300'],
];
$cat_total = array_sum(array_column($cat_dist, 'cnt'));

// ============================================================
// 8. Premium to'lovlar taqsimoti (oylar bo'yicha)
// ============================================================
$payments_by_month = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS mon, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
    FROM premium_payments
    WHERE status='approved'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY mon DESC
    LIMIT 12
")->fetchAll();

// ============================================================
// 9. Eng faol foydalanuvchilar (so'nggi 7 kunda)
// ============================================================
$active_users = $pdo->query("
    SELECT u.username, u.user_id, u.avatar, COUNT(DISTINCT wp.content_id) AS watched, MAX(wp.updated_at) AS last_watch
    FROM watch_progress wp
    JOIN users u ON wp.user_id = u.id
    WHERE wp.updated_at >= CURDATE() - INTERVAL 7 DAY
    GROUP BY wp.user_id
    ORDER BY watched DESC
    LIMIT 8
")->fetchAll();

// ============================================================
// 10. Eng ko'p qayta ko'rilgan kontent (watch_progress'dan)
// ============================================================
$most_resumed = $pdo->query("
    SELECT c.title, c.views, COUNT(wp.id) AS watch_count, MAX(wp.updated_at) AS last_updated
    FROM watch_progress wp
    JOIN content c ON wp.content_id = c.id
    GROUP BY wp.content_id
    ORDER BY watch_count DESC
    LIMIT 8
")->fetchAll();

// ============================================================
// 11. Premium plan taqsimoti
// ============================================================
$plan_dist = $pdo->query("
    SELECT plan, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
    FROM premium_payments
    WHERE status='approved'
    GROUP BY plan
    ORDER BY cnt DESC
")->fetchAll();
$plan_labels = ['1month' => '1 Oy', '3month' => '3 Oy', '1year' => '1 Yil'];

// ============================================================
// 12. Watch progress faollik (so'nggi 7 kun, kunlik)
// ============================================================
$daily_active_watchers = $pdo->query("
    SELECT DATE(updated_at) AS d, COUNT(DISTINCT user_id) AS cnt
    FROM watch_progress
    WHERE updated_at >= CURDATE() - INTERVAL 7 DAY
    GROUP BY DATE(updated_at)
    ORDER BY d
")->fetchAll();
$daw_idx = [];
foreach ($daily_active_watchers as $r) $daw_idx[$r['d']] = (int)$r['cnt'];
$daw_data = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $daw_data[] = $daw_idx[$d] ?? 0;
}

// ============================================================
// JSON data for charts
// ============================================================
$dv_json  = json_encode($daily_view_data);
$du_json  = json_encode($daily_user_data);
$daw_json = json_encode($daw_data);
?>
<style>
/* ---- Chart containers ---- */
.chart-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin:20px 0; }
.chart-card { background:var(--card-bg); border:1px solid var(--border); border-radius:12px; padding:24px; transition:.3s; }
.chart-card:hover { border-color:rgba(33,150,243,0.35); box-shadow:0 10px 28px rgba(0,0,0,0.3); }
.chart-card h3 { margin:0 0 16px; font-size:15px; color:var(--blue-glow); display:flex; align-items:center; gap:8px; }
.chart-card h3 .chip { font-size:11px; background:rgba(255,255,255,0.08); padding:2px 10px; border-radius:12px; color:var(--text-muted); }
.chart-canvas-wrap { position:relative; width:100%; }
.chart-canvas-wrap canvas { width:100% !important; height:200px !important; border-radius:6px; }
.donut-canvas-wrap { display:flex; align-items:center; justify-content:center; gap:24px; flex-wrap:wrap; }
.donut-canvas-wrap canvas { width:160px !important; height:160px !important; }
.donut-legend { display:flex; flex-direction:column; gap:6px; }
.donut-legend .leg-item { display:flex; align-items:center; gap:8px; font-size:13px; }
.donut-legend .leg-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }

/* ---- Compact tables ---- */
.compact-table { width:100%; border-collapse:collapse; font-size:13px; }
.compact-table th { color:var(--text-muted); font-size:11px; text-transform:uppercase; padding:6px 8px; text-align:left; border-bottom:1px solid rgba(255,255,255,0.06); }
.compact-table td { padding:8px; border-bottom:1px solid rgba(255,255,255,0.03); }
.compact-table tr:hover td { background:rgba(33,150,243,0.04); }
.compact-table .rank { color:var(--text-muted); font-size:11px; width:24px; text-align:center; }
.compact-table .gold { color:#ffb300; }
.compact-table .silver { color:#b0bec5; }
.compact-table .bronze { color:#a1887f; }
.num-badge { font-weight:700; color:var(--blue-glow); }
.num-badge.green { color:#4caf50; }
.num-badge.orange { color:#ffb300; }
.num-badge.pink { color:#e040fb; }
.mini-avatar { width:24px; height:24px; border-radius:50%; object-fit:cover; vertical-align:middle; margin-right:6px; }

/* ---- Stat links grid ---- */
.stat-grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
.stat-mini { text-align:center; padding:18px 10px; border-radius:10px; border:1px solid var(--border); background:var(--card-bg); transition:.25s; }
.stat-mini:hover { transform:translateY(-3px); border-color:rgba(33,150,243,0.4); box-shadow:0 8px 18px rgba(0,0,0,0.3); }
.stat-mini .num { font-size:24px; font-weight:800; }
.stat-mini .label { font-size:12px; color:var(--text-muted); margin-top:2px; }
.stat-mini .sub { font-size:11px; color:var(--text-muted); margin-top:4px; opacity:0.7; }

/* ---- Activity timeline ---- */
.timeline { max-height:280px; overflow-y:auto; padding-right:4px; }
.timeline::-webkit-scrollbar { width:4px; }
.timeline::-webkit-scrollbar-track { background:transparent; }
.timeline::-webkit-scrollbar-thumb { background:rgba(33,150,243,0.3); border-radius:2px; }
.tl-item { display:flex; align-items:center; gap:10px; padding:7px 0; border-bottom:1px solid rgba(255,255,255,0.03); font-size:13px; }
.tl-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.tl-time { color:var(--text-muted); font-size:11px; white-space:nowrap; flex-shrink:0; }

/* ---- Premium monthly chart ---- */
.bar-h { display:flex; flex-direction:column; gap:6px; }
.bar-h-row { display:flex; align-items:center; gap:8px; }
.bar-h-label { width:52px; font-size:11px; color:var(--text-muted); flex-shrink:0; text-align:right; }
.bar-h-track { flex:1; height:18px; background:rgba(255,255,255,0.05); border-radius:4px; overflow:hidden; position:relative; }
.bar-h-fill { height:100%; border-radius:4px; transition:width .8s ease; position:relative; }
.bar-h-fill span { position:absolute; right:6px; top:50%; transform:translateY(-50%); font-size:10px; color:#fff; font-weight:700; text-shadow:0 1px 2px rgba(0,0,0,0.4); }

/* ---- Genres bar chart ---- */
.genre-bar-grid { display:flex; flex-direction:column; gap:7px; }
.genre-bar-row { display:flex; align-items:center; gap:8px; }
.genre-bar-label { width:80px; font-size:12px; color:var(--text-light); flex-shrink:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.genre-bar-track { flex:1; height:16px; background:rgba(255,255,255,0.05); border-radius:4px; overflow:hidden; }
.genre-bar-fill { height:100%; border-radius:4px; transition:width .8s ease; }
.genre-bar-count { width:28px; font-size:11px; color:var(--text-muted); flex-shrink:0; text-align:right; }

/* ---- Quick actions ---- */
.quick-actions { display:flex; flex-wrap:wrap; gap:8px; }
.quick-actions a { padding:8px 16px; border-radius:7px; font-size:13px; text-decoration:none; background:rgba(33,150,243,0.12); color:var(--text-light); border:1px solid transparent; transition:.2s; }
.quick-actions a:hover { background:rgba(33,150,243,0.25); border-color:rgba(33,150,243,0.3); transform:translateY(-2px); }

/* ---- Responsive ---- */
@media (max-width:900px) {
    .chart-row { grid-template-columns:1fr; }
    .stat-grid-4 { grid-template-columns:repeat(2,1fr); }
}
@media (max-width:500px) {
    .stat-grid-4 { grid-template-columns:1fr; }
    .donut-canvas-wrap { flex-direction:column; }
}
</style>

<h1 style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    📊 Analitika paneli
    <span style="font-size:13px;font-weight:400;color:var(--text-muted);"><?php echo date('d.m.Y H:i'); ?></span>
</h1>

<!-- ============================================================ -->
<!-- STAT GRID -->
<!-- ============================================================ -->
<div class="stat-grid-4">
    <div class="stat-mini"><div class="num" style="color:var(--blue-glow);"><?php echo $total_views; ?></div><div class="label">👁️ Jami ko'rishlar</div><div class="sub">shu oy +<?php echo $monthly_views; ?></div></div>
    <div class="stat-mini"><div class="num" style="color:#4caf50;"><?php echo $active_today; ?></div><div class="label">📅 Bugun faol</div><div class="sub">foydalanuvchilar</div></div>
    <div class="stat-mini"><div class="num" style="color:#ffb300;"><?php echo $total_users; ?></div><div class="label">👥 Foydalanuvchilar</div><div class="sub">+<?php echo $this_month_new; ?> shu oy</div></div>
    <div class="stat-mini"><div class="num" style="color:#e040fb;"><?php echo $premium_users; ?></div><div class="label">⭐ Premium</div><div class="sub"><?php echo number_format($total_pay_sum, 0, '.', ' '); ?> so'm</div></div>
</div>

<!-- ============================================================ -->
<!-- MAIN STATS GRID (existing stat cards) -->
<!-- ============================================================ -->
<div class="stats-grid">
    <div class="stat-card"><div class="num"><?php echo $total_content; ?></div><div class="label">📦 Jami kontent</div></div>
    <div class="stat-card"><div class="num"><?php echo $total_kino; ?></div><div class="label">🎬 Kino</div></div>
    <div class="stat-card"><div class="num"><?php echo $total_anime; ?></div><div class="label">🎌 Anime</div></div>
    <div class="stat-card"><div class="num"><?php echo $total_multfilm; ?></div><div class="label">🧸 Multfilm</div></div>
    <div class="stat-card"><div class="num"><?php echo $total_views; ?></div><div class="label">👁️ Jami ko'rishlar</div></div>
    <div class="stat-card"><div class="num"><?php echo number_format($total_pay_sum, 0, '.', ' '); ?></div><div class="label">💰 Jami daromad</div></div>
    <div class="stat-card"><div class="num"><?php echo $total_payments; ?></div><div class="label">💳 Tasdiqlangan to'lovlar</div></div>
    <?php if ($pending_payments > 0): ?>
    <div class="stat-card" style="border-color:rgba(229,57,53,0.5);">
        <div class="num" style="color:#ef5350;"><?php echo $pending_payments; ?></div>
        <div class="label">⏳ Kutilayotgan to'lovlar</div>
    </div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- CHARTS ROW 1: Daily Views + User Registration -->
<!-- ============================================================ -->
<div class="chart-row">
    <div class="chart-card">
        <h3>📈 Kunlik tomosha seanslari <span class="chip">30 kun</span></h3>
        <div class="chart-canvas-wrap"><canvas id="chartDailyViews" height="200"></canvas></div>
    </div>
    <div class="chart-card">
        <h3>📊 Yangi foydalanuvchilar <span class="chip">30 kun</span></h3>
        <div class="chart-canvas-wrap"><canvas id="chartDailyUsers" height="200"></canvas></div>
    </div>
</div>

<!-- ============================================================ -->
<!-- CHARTS ROW 2: Category Donut + Daily Active Watchers -->
<!-- ============================================================ -->
<div class="chart-row">
    <div class="chart-card">
        <h3>🎯 Kategoriya taqsimoti</h3>
        <div class="donut-canvas-wrap">
            <canvas id="chartCategoryDist" width="160" height="160"></canvas>
            <div class="donut-legend">
                <?php foreach ($cat_dist as $cd): ?>
                <div class="leg-item"><span class="leg-dot" style="background:<?php echo $cd['color']; ?>"></span> <?php echo e($cd['label']); ?> <span style="color:var(--text-muted);margin-left:auto;"><?php echo $cd['cnt']; ?> (<?php echo $cat_total > 0 ? round($cd['cnt']/$cat_total*100) : 0; ?>%)</span></div>
                <?php endforeach; ?>
                <div class="leg-item" style="border-top:1px solid rgba(255,255,255,0.06);padding-top:6px;margin-top:4px;"><span class="leg-dot" style="background:var(--text-muted);"></span> Jami <span style="color:var(--text-muted);margin-left:auto;"><?php echo $cat_total; ?></span></div>
            </div>
        </div>
    </div>
    <div class="chart-card">
        <h3>🔥 Faol tomoshabinlar <span class="chip">7 kun</span></h3>
        <div class="chart-canvas-wrap"><canvas id="chartActiveWatchers" height="200"></canvas></div>
    </div>
</div>

<!-- ============================================================ -->
<!-- CHARTS ROW 3: Top Viewed + Top Rated -->
<!-- ============================================================ -->
<div class="chart-row">
    <div class="chart-card">
        <h3>🏆 Eng ko'p ko'rilganlar</h3>
        <table class="compact-table">
            <tr><th>#</th><th>Nomi</th><th>Kategoriya</th><th>Ko'rishlar</th><th>⭐</th></tr>
            <?php $i=1; foreach ($top_viewed as $tv): ?>
            <tr>
                <td class="rank <?php echo $i===1?'gold':($i===2?'silver':($i===3?'bronze':'')); ?>">#<?php echo $i; ?></td>
                <td><?php echo e($tv['title']); ?> <span style="color:var(--text-muted);font-size:10px;"><?php echo e($tv['content_code']); ?></span></td>
                <td style="color:var(--text-muted);font-size:12px;"><?php echo e($tv['cat_name']); ?></td>
                <td><span class="num-badge"><?php echo number_format($tv['views'], 0, '.', ' '); ?></span></td>
                <td><?php echo $tv['rating'] > 0 ? '⭐ ' . $tv['rating'] : '—'; ?></td>
            </tr>
            <?php $i++; endforeach; ?>
            <?php if (empty($top_viewed)): ?><tr><td colspan="5" style="color:var(--text-muted);">Ma'lumot yo'q</td></tr><?php endif; ?>
        </table>
    </div>
    <div class="chart-card">
        <h3>🌟 Eng yuqori baholangan</h3>
        <table class="compact-table">
            <tr><th>#</th><th>Nomi</th><th>Yil</th><th>⭐ Reyting</th><th>Ko'rishlar</th></tr>
            <?php $i=1; foreach ($top_rated as $tr): ?>
            <tr>
                <td class="rank <?php echo $i===1?'gold':($i===2?'silver':($i===3?'bronze':'')); ?>">#<?php echo $i; ?></td>
                <td><?php echo e($tr['title']); ?> <span style="color:var(--text-muted);font-size:10px;"><?php echo e($tr['content_code']); ?></span></td>
                <td style="font-size:12px;color:var(--text-muted);"><?php echo e($tr['release_year']); ?></td>
                <td><span class="num-badge orange">⭐ <?php echo $tr['rating']; ?></span></td>
                <td style="color:var(--text-muted);"><?php echo number_format($tr['views'], 0, '.', ' '); ?></td>
            </tr>
            <?php $i++; endforeach; ?>
            <?php if (empty($top_rated)): ?><tr><td colspan="5" style="color:var(--text-muted);">Ma'lumot yo'q</td></tr><?php endif; ?>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- CHARTS ROW 4: Genre Distribution + Premium Stats -->
<!-- ============================================================ -->
<div class="chart-row">
    <div class="chart-card">
        <h3>🏷️ Janrlar bo'yicha kontent</h3>
        <?php if (!empty($genre_dist)): 
            $max_genre = max(array_column($genre_dist, 'cnt'));    
        ?>
        <div class="genre-bar-grid">
            <?php foreach ($genre_dist as $g): 
                $gpct = $max_genre > 0 ? round($g['cnt'] / $max_genre * 100) : 0;
            ?>
            <div class="genre-bar-row">
                <span class="genre-bar-label"><?php echo e($g['name']); ?></span>
                <div class="genre-bar-track">
                    <div class="genre-bar-fill" style="width:<?php echo $gpct; ?>%;background:<?php echo e($g['color'] ?? '#7c4dff'); ?>;"></div>
                </div>
                <span class="genre-bar-count"><?php echo $g['cnt']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color:var(--text-muted);font-size:13px;">Janr ma'lumotlari mavjud emas.</p>
        <?php endif; ?>
    </div>
    <div class="chart-card">
        <h3>💰 Premium to'lovlar <span class="chip">oylik</span></h3>
        <?php if (!empty($payments_by_month)): 
            $max_pay = max(array_column($payments_by_month, 'total'));
        ?>
        <div class="bar-h">
            <?php foreach (array_reverse($payments_by_month) as $pm): 
                $ppct = $max_pay > 0 ? round($pm['total'] / $max_pay * 100) : 0;
            ?>
            <div class="bar-h-row">
                <span class="bar-h-label"><?php echo e($pm['mon']); ?></span>
                <div class="bar-h-track">
                    <div class="bar-h-fill" style="width:<?php echo $ppct; ?>%;background:linear-gradient(90deg,#f9a825,#ff6f00);">
                        <span><?php echo number_format($pm['total'], 0, '.', ' '); ?> so'm</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color:var(--text-muted);font-size:13px;">To'lov ma'lumotlari mavjud emas.</p>
        <?php endif; ?>
        
        <?php if (!empty($plan_dist)): ?>
        <h4 style="margin:16px 0 8px;font-size:13px;color:var(--text-muted);">Plan taqsimoti</h4>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <?php foreach ($plan_dist as $pd): ?>
            <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:10px 16px;text-align:center;flex:1;min-width:80px;">
                <div style="font-size:18px;font-weight:700;color:#ffb300;"><?php echo $pd['cnt']; ?></div>
                <div style="font-size:11px;color:var(--text-muted);"><?php echo e($plan_labels[$pd['plan']] ?? $pd['plan']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- ROW 5: Active Users + Most Resumed + Recent Activity -->
<!-- ============================================================ -->
<div class="chart-row">
    <div class="chart-card">
        <h3>🔥 Eng faol foydalanuvchilar <span class="chip">7 kun</span></h3>
        <?php if (!empty($active_users)): ?>
        <table class="compact-table">
            <tr><th>#</th><th>Foydalanuvchi</th><th>Ko'rgan</th><th>So'nggi</th></tr>
            <?php $i=1; foreach ($active_users as $au): ?>
            <tr>
                <td class="rank">#<?php echo $i; ?></td>
                <td>
                    <img src="<?php echo avatar_url($au['avatar']); ?>" class="mini-avatar">
                    <?php echo e($au['username']); ?>
                    <span style="color:var(--text-muted);font-size:10px;">🆔<?php echo e($au['user_id']); ?></span>
                </td>
                <td><span class="num-badge green"><?php echo $au['watched']; ?></span></td>
                <td style="font-size:11px;color:var(--text-muted);"><?php echo time_ago($au['last_watch']); ?></td>
            </tr>
            <?php $i++; endforeach; ?>
        </table>
        <?php else: ?>
        <p style="color:var(--text-muted);font-size:13px;">Hali faol foydalanuvchi yo'q.</p>
        <?php endif; ?>
    </div>
    <div class="chart-card">
        <h3>🔄 Eng ko'p qayta ko'rilgan</h3>
        <?php if (!empty($most_resumed)): ?>
        <table class="compact-table">
            <tr><th>#</th><th>Nomi</th><th>Tomoshabin</th><th>Ko'rishlar</th></tr>
            <?php $i=1; foreach ($most_resumed as $mr): ?>
            <tr>
                <td class="rank">#<?php echo $i; ?></td>
                <td><?php echo e($mr['title']); ?></td>
                <td><span class="num-badge pink"><?php echo $mr['watch_count']; ?> ta</span></td>
                <td style="color:var(--text-muted);"><?php echo number_format($mr['views'], 0, '.', ' '); ?></td>
            </tr>
            <?php $i++; endforeach; ?>
        </table>
        <?php else: ?>
        <p style="color:var(--text-muted);font-size:13px;">Ma'lumot yo'q.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- RECENT ACTIVITY + QUICK ACTIONS -->
<!-- ============================================================ -->
<div class="chart-row">
    <div class="chart-card">
        <h3>🕐 So'nggi faollik</h3>
        <?php
        $recent_activity = $pdo->query("
            (SELECT 'user' AS type, id, username AS title, created_at FROM users ORDER BY created_at DESC LIMIT 5)
            UNION ALL
            (SELECT 'content' AS type, id, title, created_at FROM content ORDER BY created_at DESC LIMIT 5)
            UNION ALL
            (SELECT 'payment' AS type, id, CONCAT('To\\'lov: ', plan) AS title, created_at FROM premium_payments WHERE status='approved' ORDER BY created_at DESC LIMIT 5)
            ORDER BY created_at DESC
            LIMIT 15
        ")->fetchAll();
        ?>
        <?php if (!empty($recent_activity)): ?>
        <div class="timeline">
            <?php foreach ($recent_activity as $ra): 
                $dot_color = $ra['type']==='user' ? '#4caf50' : ($ra['type']==='content' ? '#2196f3' : '#ffb300');
                $icon = $ra['type']==='user' ? '👤' : ($ra['type']==='content' ? '🎬' : '💳');
            ?>
            <div class="tl-item">
                <span class="tl-dot" style="background:<?php echo $dot_color; ?>"></span>
                <span style="flex-shrink:0;"><?php echo $icon; ?></span>
                <span style="flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?php echo e(mb_strimwidth($ra['title'], 0, 40, '...')); ?>
                </span>
                <span class="tl-time"><?php echo time_ago($ra['created_at']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color:var(--text-muted);font-size:13px;">Faollik yo'q.</p>
        <?php endif; ?>
    </div>
    <div class="chart-card">
        <h3>⚡ Tezkor amallar</h3>
        <div class="quick-actions">
            <a href="add_content.php">➕ Yangi kontent</a>
            <a href="list_content.php">📋 Barcha kontent</a>
            <a href="users.php">👥 Foydalanuvchilar</a>
            <a href="payments.php">💳 To'lovlar</a>
            <a href="list_content.php?export=csv">📥 CSV eksport</a>
            <a href="list_content.php?export=json">📥 JSON eksport</a>
            <a href="../index.php" target="_blank">🌐 Saytni ko'rish</a>
            <a href="../premium.php" target="_blank">⭐ Premium sahifasi</a>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- CHART JAVASCRIPT -->
<!-- ============================================================ -->
<script>
(function () {
    'use strict';

    // ---- Utility: rounded rectangle path ----
    function roundRect(ctx, x, y, w, h, r) {
        r = Math.min(r, w/2, h/2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + w - r, y);
        ctx.arcTo(x + w, y, x + w, y + r, r);
        ctx.lineTo(x + w, y + h - r);
        ctx.arcTo(x + w, y + h, x + w - r, y + h, r);
        ctx.lineTo(x + r, y + h);
        ctx.arcTo(x, y + h, x, y + h - r, r);
        ctx.lineTo(x, y + r);
        ctx.arcTo(x, y, x + r, y, r);
        ctx.closePath();
    }

    // ---- Bar chart renderer ----
    function renderBarChart(canvasId, data, options) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;
        var opts = Object.assign({
            color: '#4fc3f7',
            bgColor: 'rgba(79,195,247,0.12)',
            labelColor: '#9aa8bd',
            fillOpacity: 0.25,
            barRadius: 4,
            showLabels: true,
            yLabel: ''
        }, options || {});

        var rect = canvas.parentElement.getBoundingClientRect();
        var W = rect.width || 400;
        var dpr = window.devicePixelRatio || 1;
        canvas.width = W * dpr;
        canvas.height = 200 * dpr;
        canvas.style.width = W + 'px';
        canvas.style.height = '200px';
        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);

        var w = W;
        var h = 200;
        var pad = { top: 20, bottom: 28, left: 8, right: 8 };
        var chartW = w - pad.left - pad.right;
        var chartH = h - pad.top - pad.bottom;

        var maxVal = Math.max.apply(null, data);
        if (maxVal === 0) maxVal = 1;
        var barW = Math.max(4, Math.min(16, (chartW / data.length) - 3));
        var gap = (chartW - barW * data.length) / (data.length + 1);

        // Grid lines
        ctx.strokeStyle = 'rgba(255,255,255,0.04)';
        ctx.lineWidth = 1;
        for (var g = 0; g <= 4; g++) {
            var gy = pad.top + chartH * (1 - g/4);
            ctx.beginPath();
            ctx.moveTo(pad.left, gy);
            ctx.lineTo(w - pad.right, gy);
            ctx.stroke();
        }

        // Background bars (behind data bars)
        for (var i = 0; i < data.length; i++) {
            var x = pad.left + gap + i * (barW + gap);
            roundRect(ctx, x, pad.top, barW, chartH, opts.barRadius);
            ctx.fillStyle = opts.bgColor;
            ctx.fill();
        }

        // Data bars (on top)
        for (var i = 0; i < data.length; i++) {
            var val = data[i];
            var barH = (val / maxVal) * chartH;
            var x = pad.left + gap + i * (barW + gap);
            var y = pad.top + chartH - barH;

            var grad = ctx.createLinearGradient(x, y, x, pad.top + chartH);
            grad.addColorStop(0, opts.color);
            grad.addColorStop(1, opts.color + '33');

            roundRect(ctx, x, y, barW, barH, opts.barRadius);
            ctx.fillStyle = grad;
            ctx.fill();
        }

        // X-axis labels (show every 5th day)
        ctx.fillStyle = opts.labelColor;
        ctx.font = '10px sans-serif';
        ctx.textAlign = 'center';
        var labels = opts.labels || [];
        for (var li = 0; li < data.length; li++) {
            if (li % 5 === 0 || li === data.length - 1) {
                var lx = pad.left + gap + li * (barW + gap) + barW / 2;
                var lbl = labels[li] || '';
                ctx.fillText(lbl, lx, h - 6);
            }
        }

        // Y-axis label
        if (opts.yLabel) {
            ctx.fillStyle = opts.labelColor;
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText(opts.yLabel, pad.left, 14);
        }
    }

    // ---- Donut chart renderer ----
    function renderDonutChart(canvasId, data) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;
        var dpr = window.devicePixelRatio || 1;
        canvas.width = 160 * dpr;
        canvas.height = 160 * dpr;
        canvas.style.width = '160px';
        canvas.style.height = '160px';
        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);

        var cx = 80, cy = 80, outerR = 70, innerR = 42;
        var total = data.reduce(function (s, d) { return s + d.cnt; }, 0);
        if (total === 0) {
            ctx.strokeStyle = 'rgba(255,255,255,0.08)';
            ctx.lineWidth = 28;
            ctx.beginPath();
            ctx.arc(cx, cy, (outerR + innerR) / 2, 0, Math.PI * 2);
            ctx.stroke();
            return;
        }

        var startAngle = -Math.PI / 2;
        data.forEach(function (d) {
            var slice = (d.cnt / total) * Math.PI * 2;
            ctx.beginPath();
            ctx.arc(cx, cy, outerR, startAngle, startAngle + slice);
            ctx.arc(cx, cy, innerR, startAngle + slice, startAngle, true);
            ctx.closePath();
            ctx.fillStyle = d.color;
            ctx.fill();
            startAngle += slice;
        });

        // Center text
        ctx.fillStyle = '#e8eef5';
        ctx.font = 'bold 18px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(total, cx, cy - 6);
        ctx.fillStyle = '#9aa8bd';
        ctx.font = '10px sans-serif';
        ctx.fillText('jami', cx, cy + 14);
    }

    // ---- Prepare daily labels ----
    var dvData = <?php echo $dv_json; ?>;
    var duData = <?php echo $du_json; ?>;
    var dawData = <?php echo $daw_json; ?>;

    var labels30 = [];
    for (var li = 29; li >= 0; li--) {
        var d = new Date();
        d.setDate(d.getDate() - li);
        labels30.push(String(d.getDate()).padStart(2, '0') + '.' + String(d.getMonth() + 1).padStart(2, '0'));
    }
    var labels7 = [];
    for (var li = 6; li >= 0; li--) {
        var d = new Date();
        d.setDate(d.getDate() - li);
        labels7.push(['Yak','Dush','Sesh','Chor','Pay','Jum','Shan'][d.getDay()] || ('0'+d.getDate()).slice(-2));
    }

    // ---- Render ----
    renderBarChart('chartDailyViews', dvData, { color: '#4fc3f7', labels: labels30, yLabel: 'ko\'rishlar' });
    renderBarChart('chartDailyUsers', duData, { color: '#4caf50', labels: labels30, yLabel: 'yangi' });
    renderBarChart('chartActiveWatchers', dawData, { color: '#e040fb', labels: labels7, yLabel: 'faol' });

    renderDonutChart('chartCategoryDist', <?php echo json_encode($cat_dist); ?>);

    // ---- Resize handler ----
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            renderBarChart('chartDailyViews', dvData, { color: '#4fc3f7', labels: labels30, yLabel: 'ko\'rishlar' });
            renderBarChart('chartDailyUsers', duData, { color: '#4caf50', labels: labels30, yLabel: 'yangi' });
            renderBarChart('chartActiveWatchers', dawData, { color: '#e040fb', labels: labels7, yLabel: 'faol' });
            renderDonutChart('chartCategoryDist', <?php echo json_encode($cat_dist); ?>);
        }, 250);
    });
})();
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
