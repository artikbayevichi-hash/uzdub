<?php
$page_title = 'Qism qo\'shish';
include __DIR__ . '/includes/admin_header.php';

$series_list = $pdo->query("SELECT c.*, cat.name as cat_name FROM content c JOIN categories cat ON c.category_id=cat.id WHERE c.is_series = 1 ORDER BY c.title")->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content_id = (int)($_POST['content_id'] ?? 0);
    $season = (int)($_POST['season'] ?? 1);
    $episode_number = (int)($_POST['episode_number'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $video_type = $_POST['video_type'] ?? '';

    if (!$content_id || !$episode_number || !$video_type) {
        $error = 'Kontent, qism raqami va video manbasini to\'ldiring.';
    } else {
        $video_url = null;
        if ($video_type === 'file') {
            $video_url = upload_file('video_file', __DIR__ . '/../uploads/videos/', ['mp4','webm','mkv','ogg']);
            if (!$video_url) { $error = 'Video fayl yuklashda xatolik.'; }
        } else {
            $video_url = trim($_POST['video_url'] ?? '');
            if ($video_url === '') { $error = 'Video havolasini kiriting.'; }
        }

        $thumbnail = upload_file('thumbnail', __DIR__ . '/../uploads/episodes/', ['jpg','jpeg','png','webp']);
        if (!$thumbnail && $_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error = 'Epizod rasmi yuklashda xatolik.';
        }

        if (!$error) {
            $stmt = $pdo->prepare("INSERT INTO episodes (content_id, season, episode_number, title, video_type, video_url, thumbnail) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$content_id, $season ?: 1, $episode_number, $title ?: null, $video_type, $video_url, $thumbnail ?: null]);
            $message = 'Qism muvaffaqiyatli qo\'shildi!';
        }
    }
}
?>

<h1>Anime / Serialga qism qo'shish</h1>

<?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

<?php if (empty($series_list)): ?>
<div class="alert alert-error">Avval "Kontent qo'shish" bo'limida "Bu ko'p qismli" belgisi bilan anime yoki serial qo'shing.</div>
<?php else: ?>

<div class="card-box">
<form method="post" enctype="multipart/form-data">
    <label>Anime / Serial *</label>
    <select name="content_id" required>
        <option value="">-- tanlang --</option>
        <?php foreach ($series_list as $s): ?>
        <option value="<?php echo $s['id']; ?>"><?php echo e($s['cat_name'] . ' — ' . $s['title']); ?></option>
        <?php endforeach; ?>
    </select>

    <label>Fasl raqami</label>
    <input type="number" name="season" value="1" min="1">

    <label>Qism raqami *</label>
    <input type="number" name="episode_number" required min="1">

    <label>Qism nomi (ixtiyoriy)</label>
    <input type="text" name="title">

    <label>Video manbasi *</label>
    <div class="radio-group">
        <label><input type="radio" name="video_type" value="youtube" checked> YouTube</label>
        <label><input type="radio" name="video_type" value="cloud"> Cloud havola</label>
        <label><input type="radio" name="video_type" value="file"> Fayl yuklash</label>
    </div>
    <div id="video_url_block">
        <label>Video havolasi</label>
        <input type="text" name="video_url" placeholder="https://youtube.com/watch?v=... yoki cloud havola">
    </div>
    <div id="video_file_block" style="display:none;">
        <label>Video fayl</label>
        <input type="file" name="video_file" accept="video/*">
    </div>

    <label>Epizod rasmi (ixtiyoriy)</label>
    <input type="file" name="thumbnail" accept="image/*">

    <button type="submit" class="btn">Qismni saqlash</button>
</form>
</div>

<script>
document.querySelectorAll('input[name=video_type]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.getElementById('video_url_block').style.display = this.value === 'file' ? 'none' : 'block';
        document.getElementById('video_file_block').style.display = this.value === 'file' ? 'block' : 'none';
    });
});
</script>

<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
