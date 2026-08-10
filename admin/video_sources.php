<?php
$page_title = "Video manbalari";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
include __DIR__ . '/includes/admin_header.php';

$message = '';

// O'chirish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_log'])) {
    if (validate_csrf($_POST['csrf_token'] ?? '')) {
        $pdo->exec("DELETE FROM video_source_log WHERE notified = 1");
        $message = "Xabarlar tarixi tozalandi.";
    }
}

// O'zgarishlar ro'yxati
$logs = $pdo->query("
    SELECT l.*, c.title AS content_title, e.season, e.episode_number, e.title AS episode_title
    FROM video_source_log l
    LEFT JOIN content c ON c.id = l.content_id
    LEFT JOIN episodes e ON e.id = l.episode_id
    ORDER BY l.created_at DESC
    LIMIT 200
")->fetchAll();

// Hozirgi holat
$states = $pdo->query("
    SELECT s.*, c.title AS content_title, e.season, e.episode_number, e.title AS episode_title
    FROM video_source_state s
    LEFT JOIN content c ON c.id = s.content_id
    LEFT JOIN episodes e ON e.id = s.episode_id
    ORDER BY s.updated_at DESC
")->fetchAll();

$status_bad = 0;
foreach ($states as $s) if ($s['status'] === 'broken') $status_bad++;
$label_events = ['url_changed' => '🔁 URL almashtirildi', 'broken' => '🔴 Buzilgan', 'recovered' => '✅ Tiklandi'];
?>

<h1>Video manbalari kuzatuvi</h1>
<?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>

<div class="card-box">
    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:center;">
        <div><strong><?php echo count($states); ?></strong> video kuzatilmoqda</div>
        <div><strong style="color:<?php echo $status_bad ? '#e53935' : '#43a047'; ?>;"><?php echo $status_bad; ?></strong> buzilgan</div>
        <div style="margin-left:auto;">
            <a href="../api/video-source-check.php" target="_blank" class="btn btn-sm">▶️ Hozir tekshirish</a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Xabarlar tarixini tozalaysizmi?');">
                <?php echo csrf_input(); ?>
                <button type="submit" name="clear_log" value="1" class="btn btn-sm btn-danger">🗑️ Tarixni tozalash</button>
            </form>
        </div>
    </div>
</div>

<h2 style="font-size:18px;">Joriy holat</h2>
<div class="card-box">
<table>
    <tr><th>Kontent</th><th>Qism</th><th>Manba</th><th>Video URL</th><th>Holat</th><th>Yangilandi</th></tr>
    <?php if (!$states): ?>
    <tr><td colspan="6" style="text-align:center;color:#8899bb;">Hali hech narsa kuzatilmagan — «Hozir tekshirish» tugmasini bosing.</td></tr>
    <?php endif; ?>
    <?php foreach ($states as $s): ?>
    <tr>
        <td><?php echo e($s['content_title'] ?: ('#' . $s['content_id'])); ?></td>
        <td><?php echo $s['episode_id'] ? ('S' . (int)$s['season'] . ' E' . (int)$s['episode_number'] . ' — ' . e($s['episode_title'])) : 'Kontent'; ?></td>
        <td><?php echo e($s['source_type']); ?></td>
        <td style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <a href="<?php echo e($s['video_url']); ?>" target="_blank" title="<?php echo e($s['video_url']); ?>"><?php echo e($s['video_url']); ?></a>
        </td>
        <td>
            <?php if ($s['status'] === 'ok'): ?><span class="badge" style="background:#43a047;">✅ Ishlamoqda</span>
            <?php elseif ($s['status'] === 'broken'): ?><span class="badge" style="background:#e53935;">🔴 Buzilgan</span>
            <?php else: ?><span class="badge" style="background:#546e7a;">Noma'lum</span><?php endif; ?>
        </td>
        <td><?php echo date('d.m.Y H:i', strtotime($s['updated_at'])); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div>

<h2 style="font-size:18px;">O'zgarishlar tarixi (<?php echo count($logs); ?>)</h2>
<div class="card-box">
<table>
    <tr><th>Sana</th><th>Kontent</th><th>Qism</th><th>Hodisa</th><th>Video URL</th><th>Izoh</th></tr>
    <?php if (!$logs): ?>
    <tr><td colspan="6" style="text-align:center;color:#8899bb;">Hozircha o'zgarishlar yo'q.</td></tr>
    <?php endif; ?>
    <?php foreach ($logs as $l): ?>
    <tr>
        <td><?php echo date('d.m.Y H:i', strtotime($l['created_at'])); ?></td>
        <td><?php echo e($l['content_title'] ?: ('#' . $l['content_id'])); ?></td>
        <td><?php echo $l['episode_id'] ? ('S' . (int)$l['season'] . ' E' . (int)$l['episode_number']) : 'Kontent'; ?></td>
        <td><?php echo e($label_events[$l['event']] ?? e($l['event'])); ?></td>
        <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <a href="<?php echo e($l['video_url']); ?>" target="_blank" title="<?php echo e($l['video_url']); ?>"><?php echo e($l['video_url']); ?></a>
        </td>
        <td style="max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo e($l['detail']); ?>"><?php echo e($l['detail']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
