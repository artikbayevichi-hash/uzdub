<?php
$page_title = 'Anime qo\'shish';
include __DIR__ . '/includes/admin_header.php';

$genres = $pdo->query("SELECT * FROM genres ORDER BY name")->fetchAll();
$anime_cat_id = 2; // kategoriya: anime

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik tokeni noto\'g\'ri.';
    } else {
        $title_uz  = trim($_POST['title_uz'] ?? '');
        $title_en  = trim($_POST['title_en'] ?? '');
        $title_jp  = trim($_POST['title_jp'] ?? '');
        $slug      = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $release_year = (int)($_POST['release_year'] ?? 0);
        $season    = max(1, (int)($_POST['season'] ?? 1));
        $total_episodes = (int)($_POST['total_episodes'] ?? 0);
        $studio    = trim($_POST['studio'] ?? '');
        $status    = $_POST['status'] ?? 'ongoing';
        $rating    = (float)($_POST['rating'] ?? 0);
        $poster_url = trim($_POST['poster_url'] ?? '');
        $banner_url = trim($_POST['banner_url'] ?? '');
        $selected_genres = $_POST['genres'] ?? [];
        $episodes_data = $_POST['episodes'] ?? [];

        $allowed_statuses = ['ongoing', 'completed', 'upcoming'];
        if (!in_array($status, $allowed_statuses, true)) $status = 'ongoing';
        if ($rating < 0 || $rating > 10) $rating = 0;
        if ($release_year < 1900 || $release_year > 2100) $release_year = 0;

        if ($title_uz === '') {
            $error = 'Anime nomi (o\'zbekcha) kiritilishi shart.';
        }

        if (!$error && $slug === '') {
            $slug = generate_slug($title_en !== '' ? $title_en : $title_uz);
        }
        if ($slug !== '') {
            $slug = unique_slug($pdo, $slug, 0);
        }

        // Poster: faqat internetdagi to'liq URL (https://...) — saytga yuklanmaydi
        $poster = null;
        if (!$error) {
            if ($poster_url !== '') {
                if (preg_match('#^https?://#i', $poster_url)) {
                    $poster = $poster_url;
                } else {
                    $error = 'Poster to\'liq URL manzili bo\'lishi kerak (https://... bilan boshlansin).';
                }
            }
        }

        if (!$error) {
            // Banner URL ni tekshirish (ixtiyoriy)
            if ($banner_url !== '' && !preg_match('#^https?://#i', $banner_url)) {
                $banner_url = '';
            }

            // Epizodlar ma'lumotlarini to'plash va tekshirish
            $clean_episodes = [];
            foreach ((array)$episodes_data as $i => $ep) {
                if (!is_array($ep)) continue;
                $ep_num  = (int)($ep['episode_number'] ?? 0);
                $ep_title = trim($ep['title'] ?? '');
                $ep_type = $ep['video_type'] ?? 'cloud';
                $ep_url  = trim($ep['video_url'] ?? '');
                $ep_1080 = trim($ep['video_url_1080p'] ?? '');
                $ep_720  = trim($ep['video_url_720p'] ?? '');
                $ep_embed = trim($ep['embed_code'] ?? '');
                $ep_duration = trim($ep['duration'] ?? '');
                $ep_intro_start = (int)($ep['intro_start'] ?? 0);
                $ep_intro_end = (int)($ep['intro_end'] ?? 0);

                $allowed_ep_types = ['cloud', 'embed'];
                if (!in_array($ep_type, $allowed_ep_types, true)) $ep_type = 'cloud';

                if ($ep_num >= 1 && ($ep_url !== '' || $ep_embed !== '')) {
                    $clean_episodes[] = [
                        'episode_number' => $ep_num,
                        'title' => $ep_title,
                        'video_type' => $ep_type,
                        'video_url' => $ep_url,
                        'video_url_1080p' => $ep_1080,
                        'video_url_720p' => $ep_720,
                        'embed_code' => $ep_embed,
                        'duration' => $ep_duration,
                        'intro_start' => $ep_intro_start,
                        'intro_end' => $ep_intro_end,
                    ];
                }
            }

            $pdo->beginTransaction();
            try {
                $content_code = generate_content_code($pdo, 'anime');
                $title = $title_uz;
                $stmt = $pdo->prepare("INSERT INTO content
                    (content_code, title, title_en, title_jp, slug, description, poster, banner_url, category_id,
                     release_year, season, total_episodes, rating, studio, status, is_series, video_type, video_url)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $content_code, $title, $title_en ?: null, $title_jp ?: null, $slug ?: null,
                    $description, $poster ?: null, $banner_url ?: null, $anime_cat_id,
                    $release_year ?: null, $season, $total_episodes ?: null, $rating,
                    $studio ?: null, $status, $clean_episodes ? 1 : 0,
                    'cloud', $clean_episodes[0]['video_url'] ?? null,
                ]);
                $content_id = (int)$pdo->lastInsertId();

                // Janrlar
                $genre_stmt = $pdo->prepare("INSERT INTO content_genres (content_id, genre_id) VALUES (?, ?)");
                foreach ($selected_genres as $gid) {
                    $gid = (int)$gid;
                    $chk = $pdo->prepare("SELECT id FROM genres WHERE id = ?");
                    $chk->execute([$gid]);
                    if ($chk->fetch()) {
                        $genre_stmt->execute([$content_id, $gid]);
                    }
                }

                // Epizodlar
                $ep_stmt = $pdo->prepare("INSERT INTO episodes
                    (content_id, season, episode_number, title, video_type, video_url,
                     video_url_1080p, video_url_720p, embed_code, duration, intro_start, intro_end)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                foreach ($clean_episodes as $ce) {
                    $ep_stmt->execute([
                        $content_id, $season, $ce['episode_number'], $ce['title'] ?: null,
                        $ce['video_type'], $ce['video_url'],
                        $ce['video_url_1080p'] ?: null, $ce['video_url_720p'] ?: null,
                        $ce['embed_code'] ?: null, $ce['duration'] ?: null,
                        $ce['intro_start'] ?: 0, $ce['intro_end'] ?: 0,
                    ]);
                }

                $pdo->commit();
                $message = "Anime muvaffaqiyatli qo'shildi! ID: <b>$content_code</b> " . ($clean_episodes ? '(' . count($clean_episodes) . ' qism)' : '');
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Bazaga saqlashda xatolik: ' . $e->getMessage();
            }
        }
    }
}
?>

<h1>➕ Anime qo'shish</h1>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

<style>
.admin-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
.admin-tab { padding:10px 22px; border-radius:8px; background:rgba(33,150,243,0.08); border:1px solid rgba(33,150,243,0.2); color:var(--text-light); cursor:pointer; font-size:14px; font-weight:600; transition:.2s; }
.admin-tab:hover { border-color:var(--blue-primary); }
.admin-tab.active { background:var(--blue-primary); border-color:var(--blue-primary); color:#fff; }
.admin-tab-panel { display:none; }
.admin-tab-panel.active { display:block; animation:adminFadeUp .3s ease both; }
.ep-row { border:1px solid rgba(255,255,255,.1); border-radius:8px; padding:12px; margin-bottom:12px; background:rgba(255,255,255,.02); }
.ep-row .ep-fields { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; }
.ep-row label { margin:6px 0 4px; font-size:12px; }
.ep-row input, .ep-row select, .ep-row textarea { width:100%; box-sizing:border-box; }
.ep-remove { background:#e53935; border:none; color:#fff; border-radius:6px; padding:6px 14px; cursor:pointer; font-size:13px; margin-top:8px; }
.ep-remove:hover { background:#c62828; }
.ep-type-toggle { display:flex; gap:8px; flex-wrap:wrap; margin:8px 0; }
.ep-type-toggle label { display:inline-flex; align-items:center; gap:5px; margin:0; font-size:13px; cursor:pointer; color:var(--text-light); }
.ep-field-hidden { display:none; }
.two-col { display:grid; grid-template-columns:1fr 1fr; gap:0 18px; }
@media (max-width:800px){ .two-col { grid-template-columns:1fr; } }
</style>

<form method="post" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>

    <div class="admin-tabs" id="adminTabs">
        <button type="button" class="admin-tab active" data-tab="tab-basic">📄 Asosiy ma'lumot</button>
        <button type="button" class="admin-tab" data-tab="tab-media">🖼️ Poster va banner</button>
        <button type="button" class="admin-tab" data-tab="tab-genres">🏷️ Janrlar</button>
        <button type="button" class="admin-tab" data-tab="tab-episodes">🎬 Qismlar</button>
    </div>

    <!-- ================= TAB 1: Asosiy ma'lumot ================= -->
    <div class="card-box admin-tab-panel active" id="tab-basic">
        <div class="two-col">
            <div>
                <label>Nomi (o'zbekcha) *</label>
                <input type="text" name="title_uz" required placeholder="Masalan: Attack on Titan">

                <label>Nomi (inglizcha)</label>
                <input type="text" name="title_en" placeholder="English title">

                <label>Nomi (yaponcha)</label>
                <input type="text" name="title_jp" placeholder="進撃の巨人 (Shingeki no Kyojin)">

                <label>Slug (URL) — bo'sh qoldirilsa avtomatik</label>
                <input type="text" name="slug" placeholder="attack-on-titan">

                <label>Studiya</label>
                <input type="text" name="studio" placeholder="Masalan: MAPPA, Wit Studio">

                <label>Holati</label>
                <select name="status">
                    <option value="ongoing">Davom etmoqda (ongoing)</option>
                    <option value="completed">Tugallangan (completed)</option>
                    <option value="upcoming">Yangi (upcoming)</option>
                </select>
            </div>
            <div>
                <label>Chiqqan yili</label>
                <input type="number" name="release_year" min="1900" max="2100" placeholder="2024">

                <label>Fasl</label>
                <input type="number" name="season" min="1" value="1">

                <label>Jami qismlar soni</label>
                <input type="number" name="total_episodes" min="0" placeholder="24">

                <label>Reyting (0 - 10)</label>
                <input type="number" name="rating" step="0.1" min="0" max="10" placeholder="9.5">

                <label>Tavsif</label>
                <textarea name="description" rows="6" placeholder="Anime haqida qisqacha ma'lumot..."></textarea>
            </div>
        </div>
    </div>

    <!-- ================= TAB 2: Poster va banner ================= -->
    <div class="card-box admin-tab-panel" id="tab-media">
        <label>Poster rasm</label>
        <div class="poster-box">
            <input type="text" name="poster_url" id="poster_url" placeholder="Internetdagi poster URL manzili: https://... (masalan Gemini orqali)">
            <img id="poster_preview" class="poster-preview" style="display:none;" alt="poster preview">
            <small style="opacity:.55;">Poster to'liq URL bo'lishi kerak (https://... bilan boshlansin). Saytga yuklanmaydi.</small>
        </div>

        <label>Banner / fon URL (ixtiyoriy)</label>
        <input type="url" name="banner_url" placeholder="https://example.com/banner.jpg">
        <small style="opacity:.55;">Keng ekran fon rasmi. Havola sifatida saqlanadi.</small>
    </div>

    <!-- ================= TAB 3: Janrlar ================= -->
    <div class="card-box admin-tab-panel" id="tab-genres">
        <label>Janrlar</label>
        <div class="genre-pills">
            <?php foreach ($genres as $g): ?>
            <label class="genre-pill">
                <input type="checkbox" name="genres[]" value="<?php echo $g['id']; ?>"> <?php echo e($g['name']); ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ================= TAB 4: Qismlar ================= -->
    <div class="card-box admin-tab-panel" id="tab-episodes">
        <p style="opacity:.75;margin-top:0;">Qismlarni qo'shish uchun «Qism qo'shish» tugmasini bosing. Har bir qismga video manbasini kiriting.</p>
        <div id="episodesContainer"></div>
        <button type="button" class="btn" id="addEpisodeBtn" style="margin-top:6px;">➕ Qism qo'shish</button>
    </div>

    <div style="margin-top:20px;">
        <button type="submit" class="btn" style="font-size:16px;padding:14px 36px;">💾 Saqlash</button>
        <a href="episodes.php" class="btn" style="text-decoration:none;">🎬 Qismlar boshqaruvi</a>
    </div>
</form>

<script>
// ===== Tab boshqaruvi =====
document.querySelectorAll('.admin-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.admin-tab').forEach(function(b){ b.classList.remove('active'); });
        document.querySelectorAll('.admin-tab-panel').forEach(function(p){ p.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});

// ===== Poster URL preview =====
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

// ===== Dinamik episode qo'shish =====
var epCounter = 0;
var epContainer = document.getElementById('episodesContainer');

function epFieldSource(prefix, index) {
    // Manba turiga qarab maydonlarni ko'rsatish/yashirish
    var type = document.querySelector('input[name="' + prefix + '[' + index + '][video_type]"]:checked');
    if (!type) return;
    var val = type.value;
    var urlRow = document.getElementById('src-url-' + index);
    var embedRow = document.getElementById('src-embed-' + index);
    if (val === 'embed') {
        urlRow.style.display = 'none';
        embedRow.style.display = 'block';
    } else {
        urlRow.style.display = 'block';
        embedRow.style.display = 'none';
    }
}

function addEpisodeRow(data) {
    data = data || {};
    var i = epCounter++;
    var row = document.createElement('div');
    row.className = 'ep-row';
    row.id = 'ep-row-' + i;
    row.innerHTML =
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">' +
            '<strong style="font-size:14px;">Qism #' + (data.episode_number || (i + 1)) + '</strong>' +
            '<button type="button" class="ep-remove" onclick="document.getElementById(\'ep-row-' + i + '\').remove(); renumberEpisodes();">✕ O\'chirish</button>' +
        '</div>' +
        '<div class="ep-fields">' +
            '<div><label>Raqam *</label><input type="number" name="episodes[' + i + '][episode_number]" min="1" required value="' + (data.episode_number || (i + 1)) + '"></div>' +
            '<div><label>Qism nomi</label><input type="text" name="episodes[' + i + '][title]" placeholder="1-qism" value="' + (data.title || '') + '"></div>' +
            '<div><label>Davomiyligi</label><input type="text" name="episodes[' + i + '][duration]" placeholder="24 daqiqa" value="' + (data.duration || '') + '"></div>' +
            '<div><label>Intro boshlanishi</label><input type="number" min="0" max="3600" name="episodes[' + i + '][intro_start]" value="' + (data.intro_start || 0) + '"></div>' +
            '<div><label>Intro tugashi</label><input type="number" min="0" max="3600" name="episodes[' + i + '][intro_end]" value="' + (data.intro_end || 0) + '"></div>' +
        '</div>' +
        '<div class="ep-type-toggle">' +
            '<label><input type="radio" name="episodes[' + i + '][video_type]" value="cloud" ' + (data.video_type === 'embed' ? '' : 'checked') + ' onchange="epFieldSource(\'episodes\',' + i + ')"> Direct URL</label>' +
            '<label><input type="radio" name="episodes[' + i + '][video_type]" value="embed" ' + (data.video_type === 'embed' ? 'checked' : '') + ' onchange="epFieldSource(\'episodes\',' + i + ')"> Embed code</label>' +
        '</div>' +
        '<div class="ep-fields">' +
            '<div id="src-url-' + i + '" style="grid-column:1/-1;">' +
                '<label>Video havolasi *</label>' +
                '<input type="text" name="episodes[' + i + '][video_url]" placeholder="https://... cloud havola (VK, mp4, RuTube)" value="' + (data.video_url || '') + '">' +
            '</div>' +
            '<div id="src-embed-' + i + '" class="ep-field-hidden" style="grid-column:1/-1;">' +
                '<label>Embed code / iframe URL</label>' +
                '<input type="text" name="episodes[' + i + '][embed_code]" placeholder="https://player.example.com/video/123" value="' + (data.embed_code || '') + '">' +
            '</div>' +
            '<div><label>1080p URL (ixtiyoriy)</label><input type="text" name="episodes[' + i + '][video_url_1080p]" placeholder="https://...1080.mp4" value="' + (data.video_url_1080p || '') + '"></div>' +
            '<div><label>720p URL (ixtiyoriy)</label><input type="text" name="episodes[' + i + '][video_url_720p]" placeholder="https://...720.mp4" value="' + (data.video_url_720p || '') + '"></div>' +
        '</div>';
    epContainer.appendChild(row);
    epFieldSource('episodes', i);
    renumberEpisodes();
}

function renumberEpisodes() {
    document.querySelectorAll('#episodesContainer .ep-row').forEach(function(row, idx) {
        var numInput = row.querySelector('input[name*="[episode_number]"]');
        if (numInput && !numInput.dataset.touched) numInput.value = idx + 1;
        var badge = row.querySelector('strong');
        if (badge) badge.textContent = 'Qism #' + (idx + 1);
    });
}

document.getElementById('addEpisodeBtn').addEventListener('click', function() { addEpisodeRow(); });

// Xatolikdan keyin qayta yuklanganda ham birinchi bo'sh qism qo'shilsin
if (!epContainer.children.length) addEpisodeRow();
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
