<?php
$page_title = '💬 Chat boshqarish';
include __DIR__ . '/includes/admin_header.php';

$cat = $_GET['cat'] ?? 'kino';
if (!in_array($cat, ['kino', 'anime', 'multfilm'])) $cat = 'kino';
$cat_labels = ['kino' => '🎬 Kino', 'anime' => '🎌 Anime', 'multfilm' => '🎞️ Multfilm'];
$all_cats = ['kino', 'anime', 'multfilm'];

// Chatni tozalash
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_chat'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik tokeni noto\'g\'ri.';
    } else {
        $clear_cat = $_POST['clear_category'] ?? '';
        if ($clear_cat === 'all') {
            $pdo->exec("DELETE FROM global_messages");
            $success = 'Barcha chatlar tozalandi!';
        } elseif (in_array($clear_cat, $all_cats)) {
            $stmt = $pdo->prepare("DELETE FROM global_messages WHERE category=?");
            $stmt->execute([$clear_cat]);
            $success = $cat_labels[$clear_cat] . ' chati tozalandi! (' . $stmt->rowCount() . ' xabar o\'chirildi)';
        }
    }
}

// Statistika
$stats = [];
foreach ($all_cats as $c) {
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM global_messages WHERE category=?");
    $stmt->execute([$c]);
    $stats[$c] = $stmt->fetch()['c'];
}
$total_stmt = $pdo->query("SELECT COUNT(*) c FROM global_messages");
$total_msgs = $total_stmt->fetch()['c'];

// Oxirgi xabarlarni ko'rish
$stmt = $pdo->prepare("SELECT gm.*, u.username FROM global_messages gm JOIN users u ON gm.user_id=u.id WHERE gm.category=? ORDER BY gm.id DESC LIMIT 50");
$stmt->execute([$cat]);
$msgs = $stmt->fetchAll();
?>

<style>
.chat-admin-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px; }
.stat-card { background:var(--card-bg,#121a2b); border:1px solid rgba(33,150,243,0.2); border-radius:12px; padding:20px; text-align:center; }
.stat-card .stat-num { font-size:32px; font-weight:900; color:var(--blue-primary,#2196f3); }
.stat-card .stat-label { font-size:13px; color:var(--text-muted,#8892a4); margin-top:4px; }
.chat-admin-tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.chat-admin-tab { padding:10px 20px; border-radius:8px; background:rgba(33,150,243,0.08); border:1px solid rgba(33,150,243,0.2); color:var(--text-light,#e0e6ed); text-decoration:none; font-size:14px; font-weight:600; transition:all .2s; }
.chat-admin-tab.active { background:var(--blue-primary,#2196f3); color:#fff; }
.chat-admin-tab:hover:not(.active) { border-color:var(--blue-primary); }
.clear-section { background:rgba(229,57,53,0.06); border:1px solid rgba(229,57,53,0.3); border-radius:12px; padding:20px; margin-bottom:24px; }
.clear-section h3 { margin:0 0 12px; color:#ef5350; font-size:16px; }
.clear-btns { display:flex; gap:8px; flex-wrap:wrap; }
.clear-btn { padding:10px 20px; border-radius:8px; border:1px solid rgba(229,57,53,0.4); background:rgba(229,57,53,0.1); color:#ef5350; font-weight:600; cursor:pointer; font-size:13px; transition:all .2s; }
.clear-btn:hover { background:#ef5350; color:#fff; }
.msg-list-table { width:100%; border-collapse:collapse; font-size:13px; }
.msg-list-table th { padding:10px 12px; text-align:left; background:rgba(33,150,243,0.08); color:var(--blue-glow,#4fc3f7); border-bottom:2px solid rgba(33,150,243,0.2); }
.msg-list-table td { padding:10px 12px; border-bottom:1px solid rgba(255,255,255,0.05); color:var(--text-light,#e0e6ed); max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.msg-list-table tr:hover { background:rgba(33,150,243,0.05); }
.msg-list-table .deleted { opacity:0.4; font-style:italic; }
.msg-list-table .pinned { color:var(--blue-glow,#4fc3f7); font-weight:600; }
.msg-list-table .btn-del { background:rgba(229,57,53,0.15); border:1px solid rgba(229,57,53,0.3); color:#ef5350; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:12px; }
.msg-list-table .btn-del:hover { background:#ef5350; color:#fff; }
</style>

<h1 style="margin-bottom:20px;">💬 Chat boshqarish</h1>

<?php if (!empty($success)): ?><div style="background:rgba(76,175,80,0.1);border:1px solid rgba(76,175,80,0.3);color:#4caf50;padding:12px 16px;border-radius:8px;margin-bottom:16px;"><?php echo e($success); ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div style="background:rgba(229,57,53,0.1);border:1px solid rgba(229,57,53,0.3);color:#ef5350;padding:12px 16px;border-radius:8px;margin-bottom:16px;"><?php echo e($error); ?></div><?php endif; ?>

<div class="chat-admin-stats">
    <?php foreach ($all_cats as $c): ?>
    <div class="stat-card">
        <div class="stat-num"><?php echo number_format($stats[$c]); ?></div>
        <div class="stat-label"><?php echo $cat_labels[$c]; ?> xabar</div>
    </div>
    <?php endforeach; ?>
    <div class="stat-card">
        <div class="stat-num"><?php echo number_format($total_msgs); ?></div>
        <div class="stat-label">Jami xabarlar</div>
    </div>
</div>

<div class="chat-admin-tabs">
    <?php foreach ($all_cats as $c): ?>
    <a href="?cat=<?php echo $c; ?>" class="chat-admin-tab <?php echo $c === $cat ? 'active' : ''; ?>"><?php echo $cat_labels[$c]; ?> (<?php echo $stats[$c]; ?>)</a>
    <?php endforeach; ?>
</div>

<div class="clear-section">
    <h3>🗑️ Chatni tozalash</h3>
    <form method="post" onsubmit="return confirm('Rostdan ham bu chatni tozalamoqchimisiz? Barcha xabarlar o\'chiriladi!');">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="clear_category" value="<?php echo $cat; ?>">
        <input type="hidden" name="clear_chat" value="1">
        <div class="clear-btns">
            <button type="submit" class="clear-btn"><?php echo $cat_labels[$cat]; ?> ni tozalash (<?php echo $stats[$cat]; ?>)</button>
        </div>
    </form>
    <form method="post" onsubmit="return confirm('DIQQAT! Barcha 3 ta chatdagi hamma xabarlar o\'chiriladi. Qaytarib bo\'lmaydi!');" style="margin-top:12px;">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="clear_category" value="all">
        <input type="hidden" name="clear_chat" value="1">
        <button type="submit" class="clear-btn" style="background:rgba(229,57,53,0.25);border-color:rgba(229,57,53,0.5);">⚡ Barcha chatlarni tozalash (<?php echo number_format($total_msgs); ?>)</button>
    </form>
</div>

<h2 style="margin-bottom:12px;">📋 <?php echo $cat_labels[$cat]; ?> — oxirgi xabarlar</h2>
<?php if (empty($msgs)): ?>
<p style="color:var(--text-muted);">Bu chatda xabarlar yo'q.</p>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="msg-list-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Foydalanuvchi</th>
            <th>Xabar</th>
            <th>Vaqt</th>
            <th>Holat</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($msgs as $m): ?>
        <tr class="<?php echo $m['is_deleted'] ? 'deleted' : ($m['is_pinned'] ? 'pinned' : ''); ?>">
            <td><?php echo $m['id']; ?></td>
            <td><?php echo e($m['username']); ?></td>
            <td><?php echo $m['is_deleted'] ? '🚫 O\'chirilgan' : e(mb_substr($m['message'] ?: '[rasm/gif]', 0, 60)); ?></td>
            <td><?php echo $m['created_at']; ?></td>
            <td>
                <?php if ($m['is_pinned']): ?>📌 Pin<?php endif; ?>
                <?php if ($m['is_edited']): ?> ✏️<?php endif; ?>
                <?php if ($m['reply_to']): ?> ↩️<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
