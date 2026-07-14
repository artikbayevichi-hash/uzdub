<?php
$page_title = 'Barcha kontent';
include __DIR__ . '/includes/admin_header.php';

$items = $pdo->query("SELECT c.*, cat.name as cat_name FROM content c JOIN categories cat ON c.category_id=cat.id ORDER BY c.created_at DESC")->fetchAll();
$genre_map = [];
foreach ($items as $it) {
    $grs = $pdo->query("SELECT g.name FROM genres g JOIN content_genres cg ON g.id = cg.genre_id WHERE cg.content_id = " . (int)$it['id'] . " ORDER BY g.name")->fetchAll(PDO::FETCH_COLUMN);
    $genre_map[$it['id']] = $grs;
}
?>

<h1>Barcha kontent (<?php echo count($items); ?>)</h1>

<div class="card-box">
<table>
    <tr>
        <th>Poster</th><th>Nomi</th><th>Kategoriya</th><th>Janrlar</th><th>Yil</th><th>Turi</th><th>Ko'rishlar</th><th>Amallar</th>
    </tr>
    <?php foreach ($items as $item): ?>
    <tr>
        <td><img src="<?php echo $item['poster'] ? '../uploads/posters/' . e($item['poster']) : 'https://via.placeholder.com/50x70/121a2b/2196f3'; ?>"></td>
        <td><?php echo e($item['title']); ?></td>
        <td><?php echo e($item['cat_name']); ?></td>
        <td><?php echo !empty($genre_map[$item['id']]) ? e(implode(', ', $genre_map[$item['id']])) : '-'; ?></td>
        <td><?php echo e($item['release_year']); ?></td>
        <td>Yagona video</td>
        <td><?php echo e($item['views']); ?></td>
        <td class="action-links">
            <a href="edit_content.php?id=<?php echo $item['id']; ?>">Tahrirlash</a>
            <a href="../watch.php?id=<?php echo $item['id']; ?>" target="_blank">Ko'rish</a>
            <a href="delete_content.php?id=<?php echo $item['id']; ?>" class="danger" onclick="return confirm('O\'chirishni tasdiqlaysizmi?');">O'chirish</a>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($items)): ?>
    <tr><td colspan="8">Hozircha kontent yo'q.</td></tr>
    <?php endif; ?>
</table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
