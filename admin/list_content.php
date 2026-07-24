<?php
$page_title = 'Barcha kontent';
include __DIR__ . '/includes/admin_header.php';

$items = $pdo->query("SELECT c.*, cat.name as cat_name FROM content c JOIN categories cat ON c.category_id=cat.id ORDER BY c.created_at DESC")->fetchAll();
$genre_map = [];
foreach ($items as $it) {
    $grs_stmt = $pdo->prepare("SELECT g.name FROM genres g JOIN content_genres cg ON g.id = cg.genre_id WHERE cg.content_id = ? ORDER BY g.name");
    $grs_stmt->execute([$it['id']]);
    $genre_map[$it['id']] = $grs_stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<h1>Barcha kontent (<?php echo count($items); ?>)</h1>

<div class="card-box" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="?export=csv" class="btn" style="background:#4caf50;font-size:13px;">📥 CSV eksport</a>
        <a href="?export=json" class="btn" style="background:#2196f3;font-size:13px;">📥 JSON eksport</a>
    </div>
</div>

<?php
// CSV/JSON eksport
$export = $_GET['export'] ?? '';
if ($export) {
    $all = $pdo->query("SELECT c.*, cat.name as cat_name FROM content c JOIN categories cat ON c.category_id=cat.id ORDER BY c.id")->fetchAll();
    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="uzdub_content_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM
        fputcsv($out, ['ID', 'Kod', 'Nomi', 'Tavsif', 'Kategoriya', 'Yil', 'Reyting', 'Premium', 'Video turi', 'Video URL', 'Ko\'rishlar', 'Holati', 'Yaratilgan']);
        foreach ($all as $r) {
            fputcsv($out, [
                $r['id'], $r['content_code'], $r['title'], strip_tags($r['description'] ?? ''),
                $r['cat_name'], $r['release_year'], $r['rating'], $r['is_premium'] ? 'Ha' : "Yo'q",
                $r['video_type'], $r['video_url'], $r['views'], $r['status'], $r['created_at']
            ]);
        }
        fclose($out);
        exit;
    } elseif ($export === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="uzdub_content_' . date('Y-m-d') . '.json"');
        echo json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
?>

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
            <form method="post" action="delete_content.php" style="display:inline;" onsubmit="return confirm('O\'chirishni tasdiqlaysizmi?');">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                <button type="submit" class="danger" style="background:none;border:none;padding:0;font:inherit;text-decoration:underline;cursor:pointer;">O'chirish</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($items)): ?>
    <tr><td colspan="8">Hozircha kontent yo'q.</td></tr>
    <?php endif; ?>
</table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
