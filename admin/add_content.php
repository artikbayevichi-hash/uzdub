<?php
$page_title = 'Kontent qo\'shish';
include __DIR__ . '/includes/admin_header.php';

$categories = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();
$genres = $pdo->query("SELECT * FROM genres ORDER BY name")->fetchAll();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik tokeni noto\'g\'ri.';
    } else {
        $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $release_year = (int)($_POST['release_year'] ?? 0);
    $rating = (float)($_POST['rating'] ?? 0);
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    $studio = trim($_POST['studio'] ?? '');
    $director = trim($_POST['director'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $status = $_POST['status'] ?? 'completed';
    $selected_genres = $_POST['genres'] ?? [];

    if ($title === '' || $category_id === 0) {
        $error = 'Nomi va kategoriyani to\'ldiring.';
    } else {
        // Poster yuklash
        $poster = upload_file('poster', __DIR__ . '/../uploads/posters/', ['jpg','jpeg','png','webp'], ['image/jpeg','image/png','image/webp']);
        if ($poster === false) { $error = 'Poster rasm formati noto\'g\'ri (jpg, png, webp bo\'lishi kerak).'; }

        $video_type = null;
        $video_url = null;

        if (!$error) {
            $video_type = $_POST['video_type'] ?? null;
            if ($video_type === 'file') {
                $video_url = upload_file('video_file', __DIR__ . '/../uploads/videos/', ['mp4','webm','mkv','ogg']);
                if (!$video_url) { $error = 'Video fayl yuklashda xatolik (mp4, webm, mkv, ogg bo\'lishi kerak).'; }
            } else {
                $video_url = trim($_POST['video_url'] ?? '');
                if ($video_url === '') { $error = 'Video havolasini kiriting.'; }
            }
        }

        if (!$error) {
            $cat_stmt = $pdo->prepare("SELECT slug FROM categories WHERE id = ?");
            $cat_stmt->execute([$category_id]);
            $cat_slug = $cat_stmt->fetch()['slug'] ?? 'kino';
            $content_code = generate_content_code($pdo, $cat_slug);

            $stmt = $pdo->prepare("INSERT INTO content (content_code, title, description, poster, category_id, release_year, rating, is_premium, video_type, video_url, studio, director, duration, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$content_code, $title, $description, $poster ?: null, $category_id, $release_year ?: null, $rating, $is_premium, $video_type, $video_url, $studio ?: null, $director ?: null, $duration ?: null, $status]);
            $content_id = (int)$pdo->lastInsertId();

            // Janrlarni saqlash
            if (!empty($selected_genres)) {
                $stmt = $pdo->prepare("INSERT INTO content_genres (content_id, genre_id) VALUES (?, ?)");
                foreach ($selected_genres as $gid) {
                    $stmt->execute([$content_id, (int)$gid]);
                }
            }

            $message = "Kontent muvaffaqiyatli qo'shildi! ID: <b>$content_code</b>";
        }
    }
    }
}
?>

<h1>Yangi kino / anime / multfilm qo'shish</h1>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

<div class="card-box">
<form method="post" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
    <label>Nomi *</label>
    <input type="text" name="title" required>

    <label>Tavsif</label>
    <textarea name="description"></textarea>

    <label>Kategoriya *</label>
    <select name="category_id" required>
        <option value="">-- tanlang --</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
        <?php endforeach; ?>
    </select>

    <label>Janrlar</label>
    <div class="genre-pills">
        <?php foreach ($genres as $g): ?>
        <label class="genre-pill">
            <input type="checkbox" name="genres[]" value="<?php echo $g['id']; ?>"> <?php echo e($g['name']); ?>
        </label>
        <?php endforeach; ?>
    </div>

    <label>Studio</label>
    <input type="text" name="studio" placeholder="Masalan: White Fox, MAPPA">

    <label>Rejissyor</label>
    <input type="text" name="director" placeholder="Masalan: Masashi Kishimoto">

    <label>Davomiylik</label>
    <input type="text" name="duration" placeholder="Masalan: 24 daqiqa, 1 soat 45 daqiqa">

    <label>Holati</label>
    <select name="status">
        <option value="completed">Tugallangan</option>
        <option value="ongoing">Davom etmoqda</option>
        <option value="upcoming">Yangi</option>
    </select>

    <label>Chiqqan yili</label>
    <input type="number" name="release_year" min="1950" max="2100">

    <label>Reyting (0 - 10)</label>
    <input type="number" name="rating" step="0.1" min="0" max="10">

    <label>Poster rasm</label>
    <input type="file" name="poster" accept="image/*">

    <label style="margin-top:20px;">
        <input type="checkbox" name="is_premium" id="is_premium"> Premium tavsiya
    </label>

    <div id="single-video-block">
        <label>Video manbasi</label>
        <div class="radio-group">
            <label><input type="radio" name="video_type" value="youtube" checked> YouTube</label>
            <label><input type="radio" name="video_type" value="cloud"> Cloud havola</label>
            <label><input type="radio" name="video_type" value="file"> Fayl yuklash</label>
        </div>
        <div id="video_url_block">
            <label>Video havolasi (YouTube yoki Cloud link)</label>
            <input type="text" name="video_url" placeholder="https://youtube.com/watch?v=... yoki cloud havola">
        </div>
        <div id="video_file_block" style="display:none;">
            <label>Video fayl</label>
            <input type="file" name="video_file" accept="video/*">
        </div>
    </div>

    <button type="submit" class="btn">Saqlash</button>
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

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
