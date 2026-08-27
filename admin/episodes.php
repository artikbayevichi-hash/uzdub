<?php
$page_title = 'Qismlar boshqaruvi';
include __DIR__ . '/includes/admin_header.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_episode'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik tokeni noto\'g\'ri.';
    } else {
        $cid = (int)($_POST['content_id'] ?? 0);
        $ep_number = (int)($_POST['episode_number'] ?? 0);
        $ep_season = max(1, (int)($_POST['episode_season'] ?? 1));
        $ep_title = trim($_POST['episode_title'] ?? '');
        $ep_type = 'cloud';
        $ep_url = trim($_POST['episode_video_url'] ?? '');

        $chk = $pdo->prepare("SELECT id FROM content WHERE id = ?");
        $chk->execute([$cid]);
        if (!$chk->fetch()) {
            $error = 'Kontent topilmadi.';
        } elseif ($ep_number < 1 || $ep_url === '') {
            $error = 'Qism raqami va video havolasi kiritilishi shart.';
        } else {
            $pdo->prepare("INSERT INTO episodes (content_id, season, episode_number, title, video_type, video_url) VALUES (?,?,?,?,?,?)")
                ->execute([$cid, $ep_season, $ep_number, $ep_title ?: null, $ep_type, $ep_url]);
            $pdo->prepare("UPDATE content SET is_series = 1 WHERE id = ?")->execute([$cid]);
            $message = 'Qism qo\'shildi!';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_episode'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik tokeni noto\'g\'ri.';
    } else {
        $cid = (int)($_POST['content_id'] ?? 0);
        foreach ((array)$_POST['delete_episode'] as $ep_del_id) {
            $ep_del_id = (int)$ep_del_id;
            $pdo->prepare("DELETE FROM episodes WHERE id = ? AND content_id = ?")->execute([$ep_del_id, $cid]);
        }
        $left = $pdo->prepare("SELECT COUNT(*) FROM episodes WHERE content_id = ?");
        $left->execute([$cid]);
        if ((int)$left->fetchColumn() === 0) {
            $pdo->prepare("UPDATE content SET is_series = 0 WHERE id = ?")->execute([$cid]);
        }
        $message = 'Qismlar o\'chirildi!';
    }
}

$search = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($search !== '') {
    $where = " WHERE c.title LIKE ? OR c.title_ru LIKE ? OR c.title_en LIKE ? OR CAST(c.id AS CHAR) = ? ";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $search];
}

$series = $pdo->prepare("
    SELECT c.id, c.title, c.title_ru, c.title_en, c.is_series, c.video_type, cat.slug AS cat_slug, cat.name AS cat_name,
           (SELECT COUNT(*) FROM episodes e WHERE e.content_id = c.id) AS ep_count
    FROM content c
    LEFT JOIN categories cat ON cat.id = c.category_id
    $where
    ORDER BY ep_count DESC, c.id DESC
");
$series->execute($params);
$series = $series->fetchAll();

$ep_map = [];
$next_map = [];
foreach ($series as $s) {
    $stmt = $pdo->prepare("SELECT * FROM episodes WHERE content_id = ? ORDER BY season, episode_number");
    $stmt->execute([$s['id']]);
    $ep_map[$s['id']] = $stmt->fetchAll();
    $next_num = 1;
    foreach ($ep_map[$s['id']] as $ep) {
        if ((int)$ep['episode_number'] >= $next_num) $next_num = (int)$ep['episode_number'] + 1;
    }
    $next_map[$s['id']] = $next_num;
}
?>

<h1>🎬 Qismlar boshqaruvi</h1>
<?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

<p style="opacity:.75;">Har bir kontent uchun qismlar (epizodlar) qo'shish, ko'rish va o'chirish. Qismlar qo'shilgan kontent seriya sifatida ko'rinadi.</p>

<div class="card-box" style="margin-bottom:16px;">
    <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="ID yoki nomi bilan qidirish..." style="flex:1;min-width:220px;">
        <button type="submit" class="btn">🔍 Qidirish</button>
        <?php if ($search !== ''): ?>
        <a href="episodes.php" class="btn" style="text-decoration:none;">✕ Tozalash</a>
        <?php endif; ?>
    </form>
    <?php if ($search !== '' && empty($series)): ?>
    <p style="opacity:.7;margin:12px 0 0;">«<?php echo e($search); ?>» bo'yicha hech narsa topilmadi.</p>
    <?php endif; ?>
</div>

<div class="card-box">
<table>
    <tr>
        <th>Kontent</th>
        <th>Kategoriya</th>
        <th>Qismlar soni</th>
        <th>Amallar</th>
    </tr>
    <?php foreach ($series as $s): ?>
    <tr>
        <td><?php echo e(t_title($s)); ?> <small style="opacity:.5;">(ID <?php echo $s['id']; ?>)</small></td>
        <td><small style="opacity:.7;"><?php echo e($s['cat_name'] ?? '-'); ?></small></td>
        <td><?php echo (int)$s['ep_count'] > 0 ? '🎬 ' . (int)$s['ep_count'] : '-'; ?></td>
        <td>
            <button type="button" class="btn" onclick="document.getElementById('ep-panel-<?php echo $s['id']; ?>').style.display = document.getElementById('ep-panel-<?php echo $s['id']; ?>').style.display === 'none' ? 'block' : 'none';">
                <?php echo (int)$s['ep_count'] > 0 ? '⚙️ Qismlarni boshqarish' : '➕ Qism qo\'shish'; ?>
            </button>
        </td>
    </tr>
    <tr id="ep-panel-<?php echo $s['id']; ?>" style="display:none;">
        <td colspan="4">
            <?php if (!empty($ep_map[$s['id']])): ?>
            <form method="post" style="margin-bottom:14px;" onsubmit="return confirm('Belgilangan qismlarni o\'chirmoqchimisiz?');">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="content_id" value="<?php echo $s['id']; ?>">
                <table style="width:100%;border-collapse:collapse;">
                    <thead><tr style="text-align:left;font-size:13px;opacity:.7;"><th style="padding:4px;">#</th><th style="padding:4px;">Fasl</th><th style="padding:4px;">Nomi</th><th style="padding:4px;">Manba</th><th style="padding:4px;">O'chirish</th></tr></thead>
                    <tbody>
                    <?php foreach ($ep_map[$s['id']] as $ep): ?>
                    <tr style="border-top:1px solid rgba(255,255,255,.08);font-size:14px;">
                        <td style="padding:4px;"><?php echo (int)$ep['episode_number']; ?></td>
                        <td style="padding:4px;"><?php echo (int)$ep['season']; ?></td>
                        <td style="padding:4px;"><?php echo e($ep['title'] ?? '-'); ?></td>
                        <td style="padding:4px;font-size:12px;opacity:.8;"><?php echo e($ep['video_type']); ?></td>
                        <td style="padding:4px;"><label><input type="checkbox" name="delete_episode[]" value="<?php echo $ep['id']; ?>"> o'chirish</label></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" class="btn" name="delete_episode_btn" value="1">Belgilanganlarni o'chirish</button>
            </form>
            <?php endif; ?>
            <form method="post">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="content_id" value="<?php echo $s['id']; ?>">
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                    <div><label>Fasl</label><br><input type="number" name="episode_season" value="1" min="1" style="width:70px;"></div>
                    <div><label>Qism raqami *</label><br><input type="number" name="episode_number" min="1" required value="<?php echo (int)($next_map[$s['id']] ?? 1); ?>" style="width:90px;"></div>
                    <div><label>Nomi</label><br><input type="text" name="episode_title" placeholder="1-qism" style="width:150px;"></div>
                    <div><label>Manba</label><br>
                        <select name="episode_video_type" style="width:140px;">
                            <option value="cloud" selected>Cloud</option>
                        </select>
                    </div>
                    <div style="flex:1;min-width:220px;"><label>Video havolasi *</label><br><input type="text" name="episode_video_url" required placeholder="https://... cloud havola (VK, Sibnet, RuTube, OK.ru, mp4)" style="width:100%;box-sizing:border-box;"></div>
                    <div><button type="submit" class="btn" name="add_episode" value="1">Qism qo'shish</button></div>
                </div>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
