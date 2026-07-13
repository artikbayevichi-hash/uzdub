<?php
$page_title = 'Qismlar ro\'yxati';
include __DIR__ . '/includes/admin_header.php';

$episodes = $pdo->query("SELECT e.*, c.title as content_title FROM episodes e JOIN content c ON e.content_id = c.id ORDER BY c.title, e.season, e.episode_number")->fetchAll();
?>

<h1>Barcha qismlar (<?php echo count($episodes); ?>)</h1>

<div class="card-box">
<table>
    <tr><th>Rasm</th><th>Anime/Serial</th><th>Fasl</th><th>Qism</th><th>Nomi</th><th>Manba</th><th>Amallar</th></tr>
    <?php foreach ($episodes as $ep): ?>
    <tr>
        <td><?php if ($ep['thumbnail']): ?><img src="../uploads/episodes/<?php echo e($ep['thumbnail']); ?>" style="width:60px;border-radius:4px;"><?php endif; ?></td>
        <td><?php echo e($ep['content_title']); ?></td>
        <td><?php echo e($ep['season']); ?></td>
        <td><?php echo e($ep['episode_number']); ?></td>
        <td><?php echo e($ep['title']); ?></td>
        <td><?php echo e($ep['video_type']); ?></td>
        <td class="action-links">
            <a href="../watch.php?id=<?php echo $ep['content_id']; ?>&ep=<?php echo $ep['id']; ?>" target="_blank">Ko'rish</a>
            <a href="delete_episode.php?id=<?php echo $ep['id']; ?>" class="danger" onclick="return confirm('O\'chirishni tasdiqlaysizmi?');">O'chirish</a>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($episodes)): ?>
    <tr><td colspan="7">Hozircha qism qo'shilmagan.</td></tr>
    <?php endif; ?>
</table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
