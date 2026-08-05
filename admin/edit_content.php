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
    $poster_url = trim($_POST['poster_url'] ?? '');
    $poster_file = upload_file('poster', __DIR__ . '/../uploads/posters/', ['jpg','jpeg','png','webp'], ['image/jpeg','image/png','image/webp']);
    if ($poster_url !== '') {
        $downloaded = download_poster($poster_url, __DIR__ . '/../uploads/posters/');
        if ($downloaded) {
            if ($poster && $poster !== $item['poster']) @unlink(__DIR__ . '/../uploads/posters/' . $poster);
            $poster = $downloaded;
        } else {
            $error = 'Poster URL dan rasm yuklab bo\'lmadi (jpg/png/webp bo\'lishi kerak).';
        }
    } elseif ($poster_file) {
        if ($poster) @unlink(__DIR__ . '/../uploads/posters/' . $poster);
        $poster = $poster_file;
    } elseif ($poster_file === false) {
        $error = 'Poster rasm formati noto\'g\'ri (jpg, png, webp bo\'lishi kerak).';
    }

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
        $allowed_video_types = ['youtube', 'cloud', 'telegram', 'file'];
        if (!in_array($video_type, $allowed_video_types, true)) $video_type = 'youtube';
        if ($video_type === 'file') {
            // Joriy fayl o'zgarishsiz qoladi (yangi fayl yuklash o'chirilgan)
            $stmt = $pdo->prepare("UPDATE content SET video_type=?, video_url=? WHERE id=?");
            $stmt->execute([$video_type, $item['video_url'], $id]);
        } elseif ($video_type === 'telegram') {
            $new_url = telegram_normalize_url($_POST['telegram_url'] ?? '');
            if (!$new_url) {
                $error = 'Telegram havolasi noto\'g\'ri yoki bo\'sh.';
            } else {
                $pdo->prepare("UPDATE content SET video_type=?, video_url=? WHERE id=?")->execute([$video_type, $new_url, $id]);
            }
        } else {
            $new_url = trim($_POST['video_url'] ?? '');
            if ($new_url === '') {
                $error = 'Video havolasi bo\'sh.';
            } else {
                $pdo->prepare("UPDATE content SET video_type=?, video_url=? WHERE id=?")->execute([$video_type, $new_url, $id]);
            }
        }
    }

    // Subtitrlarni boshqarish
    // (1) Yangi subtitle yuklash
    if (!empty($_FILES['new_sub_file']['name']) && $_FILES['new_sub_file']['error'] === UPLOAD_ERR_OK) {
        $sub_dir = __DIR__ . '/../uploads/subtitles/';
        $ext = strtolower(pathinfo($_FILES['new_sub_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['vtt','srt'])) {
            $sub_filename = $id . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['new_sub_file']['tmp_name'], $sub_dir . $sub_filename);
            $new_lang = trim($_POST['new_sub_lang'] ?? 'uz');
            $new_label = trim($_POST['new_sub_label'] ?? '');
            $stmt = $pdo->prepare("INSERT INTO content_subtitles (content_id, language, label, file_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $new_lang, $new_label, $sub_filename]);
        }
    }
    // (2) Mavjud subtitrlarni o'chirish
    if (!empty($_POST['sub_delete'])) {
        foreach ((array)$_POST['sub_delete'] as $sub_del_id) {
            $sub_del_id = (int)$sub_del_id;
            $stmt = $pdo->prepare("SELECT file_path FROM content_subtitles WHERE id = ? AND content_id = ?");
            $stmt->execute([$sub_del_id, $id]);
            $sub_file = $stmt->fetchColumn();
            if ($sub_file) {
                @unlink(__DIR__ . '/../uploads/subtitles/' . $sub_file);
                $pdo->prepare("DELETE FROM content_subtitles WHERE id = ? AND content_id = ?")->execute([$sub_del_id, $id]);
            }
        }
    }
    // (3) Mavjud subtitrlarni tahrirlash
    if (!empty($_POST['sub_id'])) {
        foreach ($_POST['sub_id'] as $i => $sub_id) {
            if (isset($_POST['sub_lang'][$i], $_POST['sub_label'][$i])) {
                $pdo->prepare("UPDATE content_subtitles SET language=?, label=? WHERE id=? AND content_id=?")
                    ->execute([trim($_POST['sub_lang'][$i]), trim($_POST['sub_label'][$i]), (int)$sub_id, $id]);
            }
        }
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

    <label>Poster (o'zgartirish uchun yangi rasm / URL / Ctrl+V)</label>
    <?php if ($item['poster']): ?><p><img src="../uploads/posters/<?php echo e($item['poster']); ?>" style="width:80px;border-radius:6px;"></p><?php endif; ?>
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
        <input type="checkbox" name="is_premium" id="is_premium" <?php echo $item['is_premium'] ? 'checked' : ''; ?>> Premium tavsiya
    </label>

    <div id="single-video-block">
        <label>Video manbasi</label>
        <div class="radio-group">
            <label><input type="radio" name="video_type" value="youtube" <?php echo $item['video_type']=='youtube'?'checked':''; ?>> YouTube</label>
            <label><input type="radio" name="video_type" value="cloud" <?php echo $item['video_type']=='cloud'?'checked':''; ?>> Cloud</label>
            <label><input type="radio" name="video_type" value="telegram" <?php echo $item['video_type']=='telegram'?'checked':''; ?>> Telegram video</label>
            <?php if ($item['video_type']=='file'): ?>
            <label><input type="radio" name="video_type" value="file" checked> Fayl: <?php echo e($item['video_url']); ?> <small style="opacity:.55;">(joriy fayl, yuklash o'chirilgan)</small></label>
            <?php endif; ?>
        </div>
        <div id="video_url_block" style="<?php echo ($item['video_type']=='youtube'||$item['video_type']=='cloud')?'':'display:none;'; ?>">
            <label>Video havolasi (YouTube yoki Cloud link)</label>
            <input type="text" name="video_url" value="<?php echo in_array($item['video_type'],['youtube','cloud']) ? e($item['video_url']) : ''; ?>" placeholder="https://youtube.com/watch?v=... yoki cloud havola">
        </div>
        <div id="video_telegram_block" style="<?php echo $item['video_type']=='telegram'?'':'display:none;'; ?>">
            <label>Telegram video havolasi</label>
            <input type="text" name="telegram_url" value="<?php echo $item['video_type']=='telegram' ? e($item['video_url']) : ''; ?>" placeholder="Telegram bot qaytargan video URL manzili (https://...)">
            <small style="opacity:.55;">Botingizga video yuborganingizda qaytgan havolani shu yerga joylang.</small>
        </div>
    </div>

    <div id="subtitles-block" style="margin-top:20px;">
        <label>Subtitrlar</label>
        <div id="subtitle-list">
            <?php
            $sub_stmt = $pdo->prepare("SELECT * FROM content_subtitles WHERE content_id = ? ORDER BY language");
            $sub_stmt->execute([$id]);
            $existing_subs = $sub_stmt->fetchAll();
            foreach ($existing_subs as $sub):
            ?>
            <div class="subtitle-row" style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
                <input type="hidden" name="sub_id[]" value="<?php echo $sub['id']; ?>">
                <input type="text" name="sub_lang[]" value="<?php echo e($sub['language']); ?>" style="width:60px;" placeholder="uz">
                <input type="text" name="sub_label[]" value="<?php echo e($sub['label']); ?>" style="width:120px;" placeholder="O'zbek">
                <span style="opacity:0.6;font-size:13px;"><?php echo e($sub['file_path']); ?></span>
                <label style="font-size:13px;"><input type="checkbox" name="sub_delete[]" value="<?php echo $sub['id']; ?>"> o'chirish</label>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:8px;align-items:center;margin-top:6px;">
            <input type="text" name="new_sub_lang" style="width:60px;" placeholder="uz" value="uz">
            <input type="text" name="new_sub_label" style="width:120px;" placeholder="O'zbek" value="O'zbek">
            <input type="file" name="new_sub_file" accept=".vtt,.srt">
            <small style="opacity:0.5;">.vtt yoki .srt</small>
        </div>
    </div>

    <button type="submit" class="btn">Saqlash</button>
</form>
</div>

<script>
function toggleVideoBlocks() {
    var val = document.querySelector('input[name=video_type]:checked').value;
    document.getElementById('video_url_block').style.display = (val === 'youtube' || val === 'cloud') ? 'block' : 'none';
    document.getElementById('video_telegram_block').style.display = val === 'telegram' ? 'block' : 'none';
}
document.querySelectorAll('input[name=video_type]').forEach(function(radio) {
    radio.addEventListener('change', toggleVideoBlocks);
});

var posterFile = document.getElementById('poster_file');
var posterPreview = document.getElementById('poster_preview');
posterFile.addEventListener('change', function() {
    if (posterFile.files && posterFile.files[0]) {
        posterPreview.src = URL.createObjectURL(posterFile.files[0]);
        posterPreview.style.display = 'block';
    }
});
document.addEventListener('paste', function(e) {
    var items = (e.clipboardData || window.clipboardData).items;
    for (var i = 0; i < items.length; i++) {
        if (items[i].kind === 'file' && items[i].type.indexOf('image/') === 0) {
            var file = items[i].getAsFile();
            var dt = new DataTransfer();
            dt.items.add(file);
            posterFile.files = dt.files;
            posterPreview.src = URL.createObjectURL(file);
            posterPreview.style.display = 'block';
            e.preventDefault();
            return;
        }
    }
});
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>