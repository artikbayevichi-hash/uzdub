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
    $title_ru = trim($_POST['title_ru'] ?? '');
    $title_en = trim($_POST['title_en'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $description_ru = trim($_POST['description_ru'] ?? '');
    $description_en = trim($_POST['description_en'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $release_year = (int)($_POST['release_year'] ?? 0);
    $rating = (float)($_POST['rating'] ?? 0);
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    $studio = trim($_POST['studio'] ?? '');
    $director = trim($_POST['director'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $status = $_POST['status'] ?? 'completed';
    $selected_genres = $_POST['genres'] ?? [];

    $allowed_statuses = ['completed', 'ongoing', 'upcoming'];
    if (!in_array($status, $allowed_statuses, true)) $status = 'completed';

    if ($title === '' || $category_id === 0) {
        $error = 'Nomi va kategoriyani to\'ldiring.';
    } else {
        // Poster: fayldan yoki internetdan (URL / Gemini nusxasi)
        $poster = null;
        $poster_url = trim($_POST['poster_url'] ?? '');
        $poster_file = upload_file('poster', __DIR__ . '/../uploads/posters/', ['jpg','jpeg','png','webp'], ['image/jpeg','image/png','image/webp']);

        if ($poster_url !== '') {
            $downloaded = download_poster($poster_url, __DIR__ . '/../uploads/posters/');
            if ($downloaded) {
                $poster = $downloaded;
                if ($poster_file) @unlink(__DIR__ . '/../uploads/posters/' . $poster_file);
            } else {
                $error = 'Poster URL dan rasm yuklab bo\'lmadi (jpg/png/webp bo\'lishi kerak).';
            }
        } elseif ($poster_file === false) {
            $error = 'Poster rasm formati noto\'g\'ri (jpg, png, webp bo\'lishi kerak).';
        } elseif ($poster_file) {
            $poster = $poster_file;
        }

        $video_type = null;
        $video_url = null;

        if (!$error) {
            $video_type = $_POST['video_type'] ?? null;
            $allowed_video_types = ['youtube', 'cloud', 'telegram'];
            if (!in_array($video_type, $allowed_video_types, true)) $video_type = 'youtube';
            if ($video_type === 'telegram') {
                $video_url = telegram_normalize_url($_POST['telegram_url'] ?? '');
                if (!$video_url) { $error = 'Telegram havolasini kiriting.'; }
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

            $stmt = $pdo->prepare("INSERT INTO content (content_code, title, title_ru, title_en, description, description_ru, description_en, poster, category_id, release_year, rating, is_premium, video_type, video_url, studio, director, duration, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$content_code, $title, $title_ru ?: null, $title_en ?: null, $description, $description_ru ?: null, $description_en ?: null, $poster ?: null, $category_id, $release_year ?: null, $rating, $is_premium, $video_type, $video_url, $studio ?: null, $director ?: null, $duration ?: null, $status]);
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

    <label>Nomi (Ruscha)</label>
    <input type="text" name="title_ru" placeholder="Русское название">

    <label>Nomi (Inglizcha)</label>
    <input type="text" name="title_en" placeholder="English title">

    <label>Tavsif</label>
    <textarea name="description"></textarea>

    <label>Tavsif (Ruscha)</label>
    <textarea name="description_ru" placeholder="Описание на русском"></textarea>

    <label>Tavsif (Inglizcha)</label>
    <textarea name="description_en" placeholder="Description in English"></textarea>

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
    <div class="poster-box">
        <div class="poster-paste" tabindex="0" id="poster_drop">
            Rasmni nusxalang (masalan Gemini 4K) va shu yerga <b>Ctrl+V</b> bosing, yoki:
            <label class="poster-choose">
                <input type="file" name="poster" accept="image/*" id="poster_file"> Fayl tanlash
            </label>
        </div>
        <input type="text" name="poster_url" id="poster_url" placeholder="Yoki internetdagi poster URL manzili: https://...">
        <img id="poster_preview" class="poster-preview" style="display:none;" alt="poster preview">
        <small style="opacity:.55;">Paste qilingan rasm yoki tanlangan fayl avtomatik yuklanadi.</small>
    </div>

    <label style="margin-top:20px;">
        <input type="checkbox" name="is_premium" id="is_premium"> Premium tavsiya
    </label>

    <div id="single-video-block">
        <label>Video manbasi</label>
        <div class="radio-group">
            <label><input type="radio" name="video_type" value="youtube" checked> YouTube</label>
            <label><input type="radio" name="video_type" value="cloud"> Cloud havola</label>
            <label><input type="radio" name="video_type" value="telegram"> Telegram video</label>
        </div>
        <div id="video_url_block">
            <label>Video havolasi (YouTube yoki Cloud link)</label>
            <input type="text" name="video_url" placeholder="https://youtube.com/watch?v=... yoki cloud havola">
        </div>
        <div id="video_telegram_block" style="display:none;">
            <label>Telegram video havolasi</label>
            <input type="text" name="telegram_url" placeholder="Telegram bot qaytargan video URL manzili (https://...)">
            <small style="opacity:.55;">Botingizga video yuborganingizda qaytgan havolani shu yerga joylang.</small>
        </div>
    </div>

    <button type="submit" class="btn">Saqlash</button>
</form>
</div>

<script>
function toggleVideoBlocks() {
    var val = document.querySelector('input[name=video_type]:checked').value;
    document.getElementById('video_url_block').style.display = val === 'youtube' || val === 'cloud' ? 'block' : 'none';
    document.getElementById('video_telegram_block').style.display = val === 'telegram' ? 'block' : 'none';
}
document.querySelectorAll('input[name=video_type]').forEach(function(radio) {
    radio.addEventListener('change', toggleVideoBlocks);
});

var posterDrop = document.getElementById('poster_drop');
var posterFile = document.getElementById('poster_file');
var posterPreview = document.getElementById('poster_preview');

function showPreview(file) {
    posterPreview.src = URL.createObjectURL(file);
    posterPreview.style.display = 'block';
}
posterFile.addEventListener('change', function() {
    if (posterFile.files && posterFile.files[0]) showPreview(posterFile.files[0]);
});
document.addEventListener('paste', function(e) {
    var items = (e.clipboardData || window.clipboardData).items;
    for (var i = 0; i < items.length; i++) {
        if (items[i].kind === 'file' && items[i].type.indexOf('image/') === 0) {
            var file = items[i].getAsFile();
            var dt = new DataTransfer();
            dt.items.add(file);
            posterFile.files = dt.files;
            showPreview(file);
            e.preventDefault();
            return;
        }
    }
});
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
