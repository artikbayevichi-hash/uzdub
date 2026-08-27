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

// ===== Qismlar (episode) boshqaruvi =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_episode']) || isset($_POST['delete_episode']) || isset($_POST['delete_episode_btn']))) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik tokeni noto\'g\'ri.';
    } else {
        if (isset($_POST['add_episode'])) {
            $ep_number = (int)($_POST['episode_number'] ?? 0);
            $ep_season = max(1, (int)($_POST['episode_season'] ?? 1));
            $ep_title = trim($_POST['episode_title'] ?? '');
            $ep_type = 'cloud';
            $ep_url = trim($_POST['episode_video_url'] ?? '');

            if ($ep_number < 1 || $ep_url === '') {
                $error = 'Qism raqami va video havolasi kiritilishi shart.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO episodes (content_id, season, episode_number, title, video_type, video_url) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$id, $ep_season, $ep_number, $ep_title ?: null, $ep_type, $ep_url]);
                $pdo->prepare("UPDATE content SET is_series = 1 WHERE id = ?")->execute([$id]);
                $message = 'Qism qo\'shildi!';
            }
        }
        if (isset($_POST['delete_episode'])) {
            foreach ((array)$_POST['delete_episode'] as $ep_del_id) {
                $ep_del_id = (int)$ep_del_id;
                $pdo->prepare("DELETE FROM episodes WHERE id = ? AND content_id = ?")->execute([$ep_del_id, $id]);
            }
            $left = $pdo->prepare("SELECT COUNT(*) FROM episodes WHERE content_id = ?");
            $left->execute([$id]);
            if ((int)$left->fetchColumn() === 0) {
                $pdo->prepare("UPDATE content SET is_series = 0 WHERE id = ?")->execute([$id]);
            }
            $message = 'Qismlar o\'chirildi!';
        }
        // Sahifani ko'rsatish uchun kontentni qayta o'qish
        $stmt = $pdo->prepare("SELECT * FROM content WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
    }
}

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
    $intro_start = (int)($_POST['intro_start'] ?? 0);
    $intro_end = (int)($_POST['intro_end'] ?? 0);
    $status = $_POST['status'] ?? 'completed';
    $post_genres = $_POST['genres'] ?? [];

    $allowed_statuses = ['completed', 'ongoing', 'upcoming'];
    if (!in_array($status, $allowed_statuses, true)) $status = 'completed';

    $poster = $item['poster'];
    $poster_url = trim($_POST['poster_url'] ?? '');
    if ($poster_url !== '') {
        if (preg_match('#^https?://#i', $poster_url)) {
            $poster = $poster_url;
        } else {
            $error = 'Poster to\'liq URL manzili bo\'lishi kerak (https://... bilan boshlansin).';
        }
    }

    $is_premium = isset($_POST['is_premium']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE content SET title=?, title_ru=?, title_en=?, description=?, description_ru=?, description_en=?, category_id=?, release_year=?, rating=?, poster=?, studio=?, director=?, duration=?, intro_start=?, intro_end=?, status=?, is_premium=? WHERE id=?");
    $stmt->execute([$title, $title_ru ?: null, $title_en ?: null, $description, $description_ru ?: null, $description_en ?: null, $category_id, $release_year ?: null, $rating, $poster, $studio ?: null, $director ?: null, $duration ?: null, $intro_start, $intro_end, $status, $is_premium, $id]);

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
        $allowed_video_types = ['cloud', 'file'];
        if (!in_array($video_type, $allowed_video_types, true)) $video_type = 'cloud';
        if ($video_type === 'file') {
            // Joriy fayl o'zgarishsiz qoladi (yangi fayl yuklash o'chirilgan)
            $pdo->prepare("UPDATE content SET video_type=?, video_url=? WHERE id=?")->execute([$video_type, $item['video_url'], $id]);
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
<?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

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

    <label>Intro oralig'i (soniyada)</label>
    <div class="ep-fields">
        <div><label>Boshlanishi</label><input type="number" name="intro_start" min="0" max="3600" value="<?php echo (int)($item['intro_start'] ?? 0); ?>" placeholder="15"></div>
        <div><label>Tugashi</label><input type="number" name="intro_end" min="0" max="3600" value="<?php echo (int)($item['intro_end'] ?? 0); ?>" placeholder="40"></div>
    </div>
    <p style="margin-top:4px;font-size:12px;opacity:.7;">Intro yo'q bo'lsa ikkalasini ham 0 qoldiring. Misol: 15-40 → pleyer 15-40 soniya orasida "Intro'ni o'tkazish" tugmasini ko'rsatadi.</p>

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

    <label>Poster (o'zgartirish uchun yangi URL)</label>
    <?php if ($item['poster']): ?><p><img src="<?php echo e(poster_url($item['poster'])); ?>" style="width:80px;border-radius:6px;"></p><?php endif; ?>
    <div class="poster-box">
        <input type="text" name="poster_url" id="poster_url" value="<?php echo (strpos((string)$item['poster'], 'http') === 0) ? e($item['poster']) : ''; ?>" placeholder="Internetdagi poster URL manzili: https://... (masalan Gemini orqali)">
        <img id="poster_preview" class="poster-preview" style="display:none;" alt="poster preview">
        <small style="opacity:.55;">Poster to'liq URL bo'lishi kerak (https://... bilan boshlansin). Saytga yuklanmaydi.</small>
    </div>

    <label style="margin-top:20px;">
        <input type="checkbox" name="is_premium" id="is_premium" <?php echo $item['is_premium'] ? 'checked' : ''; ?>> Premium tavsiya
    </label>

    <div id="single-video-block">
        <label>Video manbasi</label>
        <div class="radio-group">
            <label><input type="radio" name="video_type" value="cloud" <?php echo $item['video_type']=='cloud'?'checked':''; ?>> Cloud</label>
            <?php if ($item['video_type']=='file'): ?>
            <label><input type="radio" name="video_type" value="file" checked> Fayl: <?php echo e($item['video_url']); ?> <small style="opacity:.55;">(joriy fayl, yuklash o'chirilgan)</small></label>
            <?php endif; ?>
        </div>
        <div id="video_url_block" style="<?php echo $item['video_type']=='cloud'?'':'display:none;'; ?>">
            <label>Video havolasi (Cloud link)</label>
            <input type="text" name="video_url" value="<?php echo $item['video_type']=='cloud' ? e($item['video_url']) : ''; ?>" placeholder="https://... cloud havola (VK, Sibnet, RuTube, OK.ru, Anibla, mp4 va h.k.)">
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

<div class="card-box" id="episodes">
    <h2 style="margin-top:0;">🎬 Qismlar</h2>
    <p style="opacity:.7;margin-top:0;">Agar qismlar qo'shilsa, kontent seriya sifatida ko'rinadi va tomoshabinlar qismlar ro'yxatidan tanlay oladi.</p>

    <?php
    $ep_stmt = $pdo->prepare("SELECT * FROM episodes WHERE content_id = ? ORDER BY season, episode_number");
    $ep_stmt->execute([$id]);
    $episodes = $ep_stmt->fetchAll();
    ?>

    <?php if ($episodes): ?>
    <form method="post" onsubmit="return confirm('Belgilangan qismlarni o\'chirmoqchimisiz?');">
        <?php echo csrf_input(); ?>
        <table style="width:100%;border-collapse:collapse;margin-bottom:10px;">
            <thead>
            <tr style="text-align:left;font-size:13px;opacity:.7;">
                <th style="padding:6px;">#</th>
                <th style="padding:6px;">Fasl</th>
                <th style="padding:6px;">Nomi</th>
                <th style="padding:6px;">Manba</th>
                <th style="padding:6px;">O'chirish</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($episodes as $ep): ?>
            <tr style="border-top:1px solid rgba(255,255,255,.08);font-size:14px;">
                <td style="padding:6px;"><?php echo (int)$ep['episode_number']; ?></td>
                <td style="padding:6px;"><?php echo (int)$ep['season']; ?></td>
                <td style="padding:6px;"><?php echo e($ep['title'] ?? '-'); ?></td>
                <td style="padding:6px;font-size:12px;opacity:.8;"><?php echo e($ep['video_type']); ?></td>
                <td style="padding:6px;"><label><input type="checkbox" name="delete_episode[]" value="<?php echo $ep['id']; ?>"> o'chirish</label></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" class="btn" name="delete_episode_btn" value="1">Belgilanganlarni o'chirish</button>
    </form>
    <?php endif; ?>

    <form method="post" style="margin-top:<?php echo $episodes ? '20px' : '0'; ?>;">
        <?php echo csrf_input(); ?>
        <label>Fasl</label>
        <input type="number" name="episode_season" value="1" min="1" style="width:80px;">
        <label>Qism raqami *</label>
        <input type="number" name="episode_number" min="1" required style="width:100px;">
        <label>Qism nomi</label>
        <input type="text" name="episode_title" placeholder="Masalan: 1-qism" style="width:200px;">
        <input type="hidden" name="episode_video_type" value="cloud">
        <label>Video havolasi *</label>
        <input type="text" name="episode_video_url" required placeholder="https://... cloud havola (VK, Sibnet, RuTube, OK.ru, mp4)" style="width:100%;box-sizing:border-box;">
        <button type="submit" class="btn" name="add_episode" value="1">Qism qo'shish</button>
    </form>
</div>

<script>
var posterUrl = document.getElementById('poster_url');
var posterPreview = document.getElementById('poster_preview');
function showPosterUrlPreview(url) {
    posterPreview.src = url;
    posterPreview.style.display = 'block';
}
if (posterUrl) {
    posterUrl.addEventListener('change', function() { if (posterUrl.value.trim() !== '') showPosterUrlPreview(posterUrl.value.trim()); });
    posterUrl.addEventListener('input', function() { if (posterUrl.value.trim() !== '') showPosterUrlPreview(posterUrl.value.trim()); });
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>