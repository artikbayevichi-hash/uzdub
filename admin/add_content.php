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
    $intro_start = (int)($_POST['intro_start'] ?? 0);
    $intro_end = (int)($_POST['intro_end'] ?? 0);
    $status = $_POST['status'] ?? 'completed';
    $selected_genres = $_POST['genres'] ?? [];
    $is_anime = isset($_POST['is_anime_series']) ? 1 : 0;

    $allowed_statuses = ['completed', 'ongoing', 'upcoming'];
    if (!in_array($status, $allowed_statuses, true)) $status = 'completed';

    if ($title === '' || ($category_id === 0 && !$is_anime)) {
        $error = 'Nomi va kategoriyani to\'ldiring.';
    } else {
        // Poster: faqat internetdagi to'liq URL (https://...) — saytga yuklanmaydi
        $poster = null;
        $poster_url = trim($_POST['poster_url'] ?? '');
        if ($poster_url !== '') {
            if (preg_match('#^https?://#i', $poster_url)) {
                $poster = $poster_url;
            } else {
                $error = 'Poster to\'liq URL manzili bo\'lishi kerak (https://... bilan boshlansin).';
            }
        }

        $is_series = 0;
        $video_type = null;
        $video_url = null;

        if ($is_anime) {
            // Ko'p qismli anime — qismlar alohida "Qismlar boshqaruvi"dan qo'shiladi
            $cat_stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = 'anime'");
            $cat_stmt->execute();
            $category_id = (int)$cat_stmt->fetchColumn();
            if (!$category_id) $error = 'Anime kategoriyasi topilmadi.';
            $is_series = 1;
            $video_type = 'cloud';
            $video_url = null;
            $status = 'ongoing';
        } elseif (!$error) {
            $video_type = 'cloud';
            $video_url = trim($_POST['video_url'] ?? '');
            if ($video_url === '') { $error = 'Video havolasini kiriting.'; }
        }

        if (!$error) {
            $cat_stmt = $pdo->prepare("SELECT slug FROM categories WHERE id = ?");
            $cat_stmt->execute([$category_id]);
            $cat_slug = $cat_stmt->fetch()['slug'] ?? 'kino';
            $content_code = generate_content_code($pdo, $cat_slug);

            $stmt = $pdo->prepare("INSERT INTO content (content_code, title, title_ru, title_en, description, description_ru, description_en, poster, category_id, release_year, rating, is_premium, is_series, video_type, video_url, studio, director, duration, intro_start, intro_end, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$content_code, $title, $title_ru ?: null, $title_en ?: null, $description, $description_ru ?: null, $description_en ?: null, $poster ?: null, $category_id, $release_year ?: null, $rating, $is_premium, $is_series, $video_type, $video_url, $studio ?: null, $director ?: null, $duration ?: null, $intro_start, $intro_end, $status]);
            $content_id = (int)$pdo->lastInsertId();

            // Janrlarni saqlash
            if (!empty($selected_genres)) {
                $stmt = $pdo->prepare("INSERT INTO content_genres (content_id, genre_id) VALUES (?, ?)");
                foreach ($selected_genres as $gid) {
                    $stmt->execute([$content_id, (int)$gid]);
                }
            }

            if ($is_anime) {
                $message = "Kontent muvaffaqiyatli qo'shildi! ID: <b>$content_code</b> — endi <a href='episodes.php' style='color:#2196f3;'>Qismlar boshqaruvi</a> dan qismlarni cloud havola bilan qo'shing.";
            } else {
                $message = "Kontent muvaffaqiyatli qo'shildi! ID: <b>$content_code</b>";
            }
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

    <div class="card-box anime-mode-card" style="margin-bottom:18px;background:rgba(224,64,251,0.06);border:1px solid rgba(224,64,251,0.25);">
        <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;margin:0;">
            <input type="checkbox" name="is_anime_series" id="is_anime_series" style="width:18px;height:18px;margin-top:2px;">
            <span>
                <strong>🎌 Bu ko'p qismli anime bo'lishi mumkin</strong>
                <small style="display:block;opacity:.75;font-size:12px;font-weight:normal;margin-top:2px;">
                    Belgilansa: faqat asosiy ma'lumot saqlanadi (poster, nom, janrlar, studiya, rejissyor, davomiylik, yil, reyting).
                    Qismlar alohida «Qismlar boshqaruvi» bo'limida 1-qismdan boshlab qo'shiladi.
                </small>
            </span>
        </label>
        <div id="animeModeNote" class="alert" style="display:none;margin:12px 0 0;">
            🎬 Kontent saqlangach, <a href="episodes.php"><b>Qismlar boshqaruvi</b></a> bo'limidan qismlarni cloud havola bilan qo'shing.
        </div>
    </div>

    <label>Nomi *</label>
    <input type="text" name="title" required>

    <label>Nomi (Ruscha)</label>
    <input type="text" name="title_ru" placeholder="Русское название">

    <label>Nomi (Inglizcha)</label>
    <input type="text" name="title_en" placeholder="English title">

    <label class="regular-only">Tavsif</label>
    <textarea name="description" class="regular-only"></textarea>

    <label class="regular-only">Tavsif (Ruscha)</label>
    <textarea name="description_ru" class="regular-only" placeholder="Описание на русском"></textarea>

    <label class="regular-only">Tavsif (Inglizcha)</label>
    <textarea name="description_en" class="regular-only" placeholder="Description in English"></textarea>

    <label class="regular-only">Kategoriya *</label>
    <select name="category_id" class="regular-only" required>
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

    <label class="regular-only">Intro oralig'i (soniyada)</label>
    <div class="regular-only ep-fields">
        <div><label>Boshlanishi</label><input type="number" name="intro_start" min="0" max="3600" value="0" placeholder="15"></div>
        <div><label>Tugashi</label><input type="number" name="intro_end" min="0" max="3600" value="0" placeholder="40"></div>
    </div>
    <p class="regular-only" style="margin-top:4px;font-size:12px;opacity:.7;">Intro yo'q bo'lsa ikkalasini ham 0 qoldiring. Misol: 15-40 → pleyer 15-40 soniya orasida "Intro'ni o'tkazish" tugmasini ko'rsatadi.</p>

    <label class="regular-only">Holati</label>
    <select name="status" class="regular-only">
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
        <input type="text" name="poster_url" id="poster_url" placeholder="Internetdagi poster URL manzili: https://... (masalan Gemini orqali)">
        <img id="poster_preview" class="poster-preview" style="display:none;" alt="poster preview">
        <small style="opacity:.55;">Poster to'liq URL bo'lishi kerak (https://... bilan boshlansin). Saytga yuklanmaydi.</small>
    </div>

    <label style="margin-top:20px;">
        <input type="checkbox" name="is_premium" id="is_premium"> Premium tavsiya
    </label>

    <div id="single-video-block" class="regular-only">
        <input type="hidden" name="video_type" value="cloud">
        <label>Video havolasi *</label>
        <input type="text" name="video_url" placeholder="https://... cloud havola (VK, mp4, RuTube va h.k.)" style="width:100%;box-sizing:border-box;">
        <small style="opacity:.55;">Cloud havola: to'g'ridan-to'g'ri mp4, VK video yoki RuTube havolasi.</small>
    </div>

    <button type="submit" class="btn">Saqlash</button>
</form>
</div>

<script>
// ===== Ko'p qismli anime rejimi =====
var animeToggle = document.getElementById('is_anime_series');
var animeModeNote = document.getElementById('animeModeNote');
var regularOnly = document.querySelectorAll('.regular-only');

function applyAnimeMode() {
    var on = animeToggle.checked;
    regularOnly.forEach(function(el) { el.style.display = on ? 'none' : ''; });
    if (animeModeNote) animeModeNote.style.display = on ? 'block' : 'none';
    var cat = document.querySelector('select[name="category_id"]');
    if (cat) cat.required = !on;
}
if (animeToggle) animeToggle.addEventListener('change', applyAnimeMode);

var posterPreview = document.getElementById('poster_preview');
var posterUrl = document.getElementById('poster_url');

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
