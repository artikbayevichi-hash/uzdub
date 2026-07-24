<?php
$page_title = 'Tahrirlash';
include __DIR__ . '/includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM content WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    echo '<div class="alert alert-error">Kontent topilmadi.</div>';
    include __DIR__ . '/includes/admin_footer.php';
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();
$genres = $pdo->query("SELECT * FROM genres ORDER BY name")->fetchAll();

// TUZATILDI: PDO::query() parametr bog'lashni qo'llamaydi.
// prepare() + execute() orqali to'g'ri ishlatildi.
$stmt = $pdo->prepare("SELECT genre_id FROM content_genres WHERE content_id = ?");
$stmt->execute([$id]);
$selected_genres = $stmt->fetchAll(PDO::FETCH_COLUMN);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik tokeni noto\'g\'ri.';
    } else {
        $title = trim($_POST['title'] ?? '');
    $title_ru = trim($_POST['title_ru'] ?? '');
    $title_en = trim($_POST['title_en'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $description_ru = trim($_POST['description_ru'] ?? '');
    $description_en = trim($_POST['description_en'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $release_year = (int)($_POST['release_year'] ?? 0);
    $rating = (float)($_POST['rating'] ?? 0);
    $studio = trim($_POST['studio'] ?? '');
    $director = trim($_POST['director'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $status = $_POST['status'] ?? 'completed';
    $post_genres = $_POST['genres'] ?? [];

    $allowed_statuses = ['completed', 'ongoing', 'upcoming'];
    if (!in_array($status, $allowed_statuses, true)) $status = 'completed';

    $poster = $item['poster'];
    $new_poster = upload_file('poster', __DIR__ . '/../uploads/posters/', ['jpg','jpeg','png','webp'], ['image/jpeg','image/png','image/webp']);
    if ($new_poster) $poster = $new_poster;

    $is_premium = isset($_POST['is_premium']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE content SET title=?, title_ru=?, title_en=?, description=?, description_ru=?, description_en=?, category_id=?, release_year=?, rating=?, poster=?, studio=?, director=?, duration=?, status=?, is_premium=? WHERE id=?");
    $stmt->execute([$title, $title_ru ?: null, $title_en ?: null, $description, $description_ru ?: null, $description_en ?: null, $category_id, $release_year ?: null, $rating, $poster, $studio ?: null, $director ?: null, $duration ?: null, $status, $is_premium, $id]);

    // Janrlarni yangilash
    $pdo->prepare("DELETE FROM content_genres WHERE content_id = ?")->execute([$id]);
    if (!empty($post_genres)) {
        $stmt = $pdo->prepare("INSERT INTO content_genres (content_id, genre_id) VALUES (?, ?)");
        foreach ($post_genres as $gid) {
            $stmt->execute([$id, (int)$gid]);
        }
    }

    // Video ma'lumotlarini yangilash
    if (isset($_POST['video_type'])) {
        $video_type = $_POST['video_type'];
        $allowed_video_types = ['youtube', 'cloud', 'file'];
        if (!in_array($video_type, $allowed_video_types, true)) $video_type = 'youtube';
        $video_url = $item['video_url'];
        if ($video_type === 'file') {
            $new_video = upload_file('video_file', __DIR__ . '/../uploads/videos/', ['mp4','webm','mkv','ogg'], ['video/mp4','video/webm','video/x-matroska','video/ogg']);
            if ($new_video) $video_url = $new_video;
        } else {
            $video_url = trim($_POST['video_url'] ?? $video_url);
        }
        $stmt = $pdo->prepare("UPDATE content SET video_type=?, video_url=? WHERE id=?");
        $stmt->execute([$video_type, $video_url, $id]);
    }

    $message = 'Muvaffaqiyatli yangilandi!';
    $stmt = $pdo->prepare("SELECT * FROM content WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    // TUZATILDI: shu yerda ham to'g'ri usul bilan qayta o'qildi
    $stmt = $pdo->prepare("SELECT genre_id FROM content_genres WHERE content_id = ?");
    $stmt->execute([$id]);
    $selected_genres = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>

<h1>Tahrirlash: <?php echo e($item['title']); ?></h1>
<?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>

<div class="card-box">
<form method="post" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
    <label>Nomi *</label>
    <input type="text" name="title" value="<?php echo e($item['title']); ?>" required>

    <label>Nomi (Ruscha)</label>
    <input type="text" name="title_ru" value="<?php echo e($item['title_ru'] ?? ''); ?>" placeholder="Русское название">

    <label>Nomi (Inglizcha)</label>
    <input type="text" name="title_en" value="<?php echo e($item['title_en'] ?? ''); ?>" placeholder="English title">

    <label>Tavsif</label>
    <textarea name="description"><?php echo e($item['description']); ?></textarea>

    <label>Tavsif (Ruscha)</label>
    <textarea name="description_ru" placeholder="Описание на русском"><?php echo e($item['description_ru'] ?? ''); ?></textarea>

    <label>Tavsif (Inglizcha)</label>
    <textarea name="description_en" placeholder="Description in English"><?php echo e($item['description_en'] ?? ''); ?></textarea>

    <label>Kategoriya *</label>
    <select name="category_id" required>
        <?php foreach ($categories as $cat): ?>
        <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id']==$item['category_id']?'selected':''; ?>><?php echo e($cat['name']); ?></option>
        <?php endforeach; ?>
    </select>

    <label>Janrlar</label>
    <div class="genre-pills">
        <?php foreach ($genres as $g): ?>
        <label class="genre-pill">
            <input type="checkbox" name="genres[]" value="<?php echo $g['id']; ?>" <?php echo in_array($g['id'], $selected_genres) ? 'checked' : ''; ?>> <?php echo e($g['name']); ?>
        </label>
        <?php endforeach; ?>
    </div>

    <label>Studio</label>
    <input type="text" name="studio" value="<?php echo e($item['studio'] ?? ''); ?>" placeholder="Masalan: White Fox, MAPPA">

    <label>Rejissyor</label>
    <input type="text" name="director" value="<?php echo e($item['director'] ?? ''); ?>" placeholder="Masalan: Masashi Kishimoto">

    <label>Davomiylik</label>
    <input type="text" name="duration" value="<?php echo e($item['duration'] ?? ''); ?>" placeholder="Masalan: 24 daqiqa">

    <label>Holati</label>
    <select name="status">
        <option value="completed" <?php echo ($item['status'] ?? 'completed') == 'completed' ? 'selected' : ''; ?>>Tugallangan</option>
        <option value="ongoing" <?php echo ($item['status'] ?? '') == 'ongoing' ? 'selected' : ''; ?>>Davom etmoqda</option>
        <option value="upcoming" <?php echo ($item['status'] ?? '') == 'upcoming' ? 'selected' : ''; ?>>Yangi</option>
    </select>

    <label>Chiqqan yili</label>
    <input type="number" name="release_year" value="<?php echo e($item['release_year']); ?>">

    <label>Reyting</label>
    <input type="number" name="rating" step="0.1" value="<?php echo e($item['rating']); ?>">

    <label>Poster (o'zgartirish uchun yangi rasm tanlang)</label>
    <?php if ($item['poster']): ?><p><img src="../uploads/posters/<?php echo e($item['poster']); ?>" style="width:80px;border-radius:6px;"></p><?php endif; ?>
    <input type="file" name="poster" accept="image/*">

    <label style="margin-top:20px;">
        <input type="checkbox" name="is_premium" id="is_premium" <?php echo $item['is_premium'] ? 'checked' : ''; ?>> Premium tavsiya
    </label>

    <div id="single-video-block">
        <label>Video manbasi</label>
        <div class="radio-group">
            <label><input type="radio" name="video_type" value="youtube" <?php echo $item['video_type']=='youtube'?'checked':''; ?>> YouTube</label>
            <label><input type="radio" name="video_type" value="cloud" <?php echo $item['video_type']=='cloud'?'checked':''; ?>> Cloud</label>
            <label><input type="radio" name="video_type" value="file" <?php echo $item['video_type']=='file'?'checked':''; ?>> Fayl</label>
        </div>
        <label>Video havolasi (agar YouTube/Cloud bo'lsa)</label>
        <input type="text" name="video_url" value="<?php echo $item['video_type']!='file' ? e($item['video_url']) : ''; ?>">
        <label>Yoki yangi video fayl yuklash</label>
        <input type="file" name="video_file" accept="video/*">
    </div>

    <button type="submit" class="btn">Saqlash</button>
</form>
</div>

<style>
.genre-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
.genre-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: var(--card-bg); border: 1px solid rgba(33,150,243,0.3); border-radius: 20px; cursor: pointer; transition: 0.2s; font-size: 13px; color: var(--text-light); }
.genre-pill:hover { border-color: var(--blue-primary); }
.genre-pill input[type="checkbox"] { accent-color: var(--blue-primary); }
</style>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>