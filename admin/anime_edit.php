<?php
$page_title = 'Anime tahrirlash';
include __DIR__ . '/includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM content WHERE id = ? AND category_id = 2");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    echo '<div class="alert alert-error">Anime topilmadi.</div>';
    include __DIR__ . '/includes/admin_footer.php';
    exit;
}

$genres = $pdo->query("SELECT * FROM genres ORDER BY name")->fetchAll();

$stmt = $pdo->prepare("SELECT genre_id FROM content_genres WHERE content_id = ?");
$stmt->execute([$id]);
$selected_genres = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT * FROM episodes WHERE content_id = ? ORDER BY episode_number");
$stmt->execute([$id]);
$episodes = $stmt->fetchAll();

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
        $banner_url = trim($_POST['banner_url'] ?? '');
        $selected_genres = $_POST['genres'] ?? [];

        $allowed_statuses = ['ongoing', 'completed', 'upcoming'];
        if (!in_array($status, $allowed_statuses, true)) $status = 'ongoing';
        if ($rating < 0 || $rating > 10) $rating = 0;
        if ($release_year < 1900 || $release_year > 2100) $release_year = 0;
        if ($banner_url !== '' && !preg_match('#^https?://#i', $banner_url)) $banner_url = '';

        if ($title_uz === '') {
            $error = 'Anime nomi (o\'zbekcha) kiritilishi shart.';
        }

        if (!$error && $slug === '') {
            $slug = generate_slug($title_en !== '' ? $title_en : $title_uz);
        }
        if ($slug !== '') {
            $slug = unique_slug($pdo, $slug, $id);
        }

        // Poster: faqat internetdagi to'liq URL (https://...) — saytga yuklanmaydi
        $poster = $item['poster'];
        if (!$error) {
            $poster_url = trim($_POST['poster_url'] ?? '');
            if ($poster_url !== '') {
                if (preg_match('#^https?://#i', $poster_url)) {
                    $poster = $poster_url;
                } else {
                    $error = 'Poster to\'liq URL manzili bo\'lishi kerak (https://... bilan boshlansin).';
                }
            }
        }

        if (!$error) {
            // Epizodlar: eski ID larni o'chirish + yangilarini saqlash
            $clean_episodes = [];
            $ep_ids = [];
            foreach ((array)($_POST['episodes'] ?? []) as $i => $ep) {
                if (!is_array($ep)) continue;
                $ep_id  = (int)($ep['ep_id'] ?? 0);
                $ep_num = (int)($ep['episode_number'] ?? 0);
                $ep_title = trim($ep['title'] ?? '');
                $ep_type = $ep['video_type'] ?? 'cloud';
                $ep_url  = trim($ep['video_url'] ?? '');
                $ep_1080 = trim($ep['video_url_1080p'] ?? '');
                $ep_720  = trim($ep['video_url_720p'] ?? '');
                $ep_tg_id = trim($ep['telegram_file_id'] ?? '');
                $ep_embed = trim($ep['embed_code'] ?? '');
                $ep_duration = trim($ep['duration'] ?? '');
                $ep_intro_start = (int)($ep['intro_start'] ?? 0);
                $ep_intro_end = (int)($ep['intro_end'] ?? 0);

                // Formadan faqat cloud / embed tanlanadi; 'telegram' turi eski qismlar
                // o'zgarishsiz saqlanishi uchun yashirin maydon orqali o'tkaziladi.
                $allowed_ep_types = ['cloud', 'embed', 'telegram'];
                if (!in_array($ep_type, $allowed_ep_types, true)) $ep_type = 'cloud';

                if ($ep_num >= 1 && ($ep_url !== '' || $ep_embed !== '')) {
                    if ($ep_id) $ep_ids[] = $ep_id;
                    $clean_episodes[] = [
                        'ep_id' => $ep_id,
                        'episode_number' => $ep_num,
                        'title' => $ep_title,
                        'video_type' => $ep_type,
                        'video_url' => $ep_url,
                        'video_url_1080p' => $ep_1080,
                        'video_url_720p' => $ep_720,
                        'telegram_file_id' => $ep_tg_id,
                        'embed_code' => $ep_embed,
                        'duration' => $ep_duration,
                        'intro_start' => $ep_intro_start,
                        'intro_end' => $ep_intro_end,
                    ];
                }
            }

            // Boshqa o'chirilgan (sahifada ko'rsatilmagan) eski qismlarni o'chirish
            if ($ep_ids) {
                $place = implode(',', array_fill(0, count($ep_ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM episodes WHERE content_id = ? AND id NOT IN ($place)");
                $stmt->execute(array_merge([$id], $ep_ids));
            } else {
                $pdo->prepare("DELETE FROM episodes WHERE content_id = ?")->execute([$id]);
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE content SET
                    title=?, title_en=?, title_jp=?, slug=?, description=?, poster=?, banner_url=?,
                    release_year=?, season=?, total_episodes=?, rating=?, studio=?, status=?,
                    is_series=?, video_type=?, video_url=? WHERE id=?");
                $first_url = $clean_episodes[0]['video_url'] ?? null;
                $stmt->execute([
                    $title_uz, $title_en ?: null, $title_jp ?: null, $slug ?: null, $description,
                    $poster ?: null, $banner_url ?: null,
                    $release_year ?: null, $season, $total_episodes ?: null, $rating,
                    $studio ?: null, $status, $clean_episodes ? 1 : 0,
                    'cloud', $first_url, $id,
                ]);

                $pdo->prepare("DELETE FROM content_genres WHERE content_id = ?")->execute([$id]);
                $genre_stmt = $pdo->prepare("INSERT INTO content_genres (content_id, genre_id) VALUES (?, ?)");
                foreach ($selected_genres as $gid) {
                    $gid = (int)$gid;
                    $chk = $pdo->prepare("SELECT id FROM genres WHERE id = ?");
                    $chk->execute([$gid]);
                    if ($chk->fetch()) $genre_stmt->execute([$id, $gid]);
                }

                $upd_stmt = $pdo->prepare("UPDATE episodes SET season=?, episode_number=?, title=?, video_type=?, video_url=?, video_url_1080p=?, video_url_720p=?, telegram_file_id=?, embed_code=?, duration=?, intro_start=?, intro_end=? WHERE id=? AND content_id=?");
                $ins_stmt = $pdo->prepare("INSERT INTO episodes (content_id, season, episode_number, title, video_type, video_url, video_url_1080p, video_url_720p, telegram_file_id, embed_code, duration, intro_start, intro_end) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                foreach ($clean_episodes as $ce) {
                    $vals = [$season, $ce['episode_number'], $ce['title'] ?: null, $ce['video_type'], $ce['video_url'],
                             $ce['video_url_1080p'] ?: null, $ce['video_url_720p'] ?: null, $ce['telegram_file_id'] ?: null,
                             $ce['embed_code'] ?: null, $ce['duration'] ?: null, $ce['intro_start'] ?: 0, $ce['intro_end'] ?: 0];
                    if ($ce['ep_id']) {
                        $upd_stmt->execute(array_merge($vals, [$ce['ep_id'], $id]));
                    } else {
                        $ins_stmt->execute(array_merge([$id], $vals));
                    }
                }

                $pdo->commit();
                $message = 'Anime saqlandi!' . ($clean_episodes ? ' (' . count($clean_episodes) . ' qism)' : '');

                // Sahifani ko'rsatish uchun qayta o'qish
                $stmt = $pdo->prepare("SELECT * FROM content WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch();
                $stmt = $pdo->prepare("SELECT genre_id FROM content_genres WHERE content_id = ?");
                $stmt->execute([$id]);
                $selected_genres = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $stmt = $pdo->prepare("SELECT * FROM episodes WHERE content_id = ? ORDER BY episode_number");
                $stmt->execute([$id]);
                $episodes = $stmt->fetchAll();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Bazaga saqlashda xatolik: ' . $e->getMessage();
            }
        }
    }
}
?>

<h1>✏️ Tahrirlash: <?php echo e($item['title']); ?></h1>
<p style="opacity:.6;margin-top:-8px;">Kod: <b><?php echo e($item['content_code']); ?></b> · Yaratilgan: <?php echo $item['created_at']; ?></p>

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
        <button type="button" class="admin-tab" data-tab="tab-episodes">🎬 Qismlar (<?php echo count($episodes); ?>)</button>
    </div>

    <!-- ================= TAB 1: Asosiy ma'lumot ================= -->
    <div class="card-box admin-tab-panel active" id="tab-basic">
        <div class="two-col">
            <div>
                <label>Nomi (o'zbekcha) *</label>
                <input type="text" name="title_uz" required value="<?php echo e($item['title']); ?>">

                <label>Nomi (inglizcha)</label>
                <input type="text" name="title_en" value="<?php echo e($item['title_en']); ?>">

                <label>Nomi (yaponcha)</label>
                <input type="text" name="title_jp" value="<?php echo e($item['title_jp']); ?>">

                <label>Slug (URL) — bo'sh qoldirilsa avtomatik</label>
                <input type="text" name="slug" value="<?php echo e($item['slug']); ?>">

                <label>Studiya</label>
                <input type="text" name="studio" value="<?php echo e($item['studio']); ?>">

                <label>Holati</label>
                <select name="status">
                    <?php foreach (['ongoing' => 'Davom etmoqda (ongoing)', 'completed' => 'Tugallangan (completed)', 'upcoming' => 'Yangi (upcoming)'] as $st => $label): ?>
                    <option value="<?php echo $st; ?>" <?php echo $item['status'] === $st ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Chiqqan yili</label>
                <input type="number" name="release_year" min="1900" max="2100" value="<?php echo (int)$item['release_year']; ?>">

                <label>Fasl</label>
                <input type="number" name="season" min="1" value="<?php echo max(1, (int)$item['season']); ?>">

                <label>Jami qismlar soni</label>
                <input type="number" name="total_episodes" min="0" value="<?php echo (int)$item['total_episodes']; ?>">

                <label>Reyting (0 - 10)</label>
                <input type="number" name="rating" step="0.1" min="0" max="10" value="<?php echo (float)$item['rating']; ?>">

                <label>Tavsif</label>
                <textarea name="description" rows="6"><?php echo e($item['description']); ?></textarea>
            </div>
        </div>
    </div>

    <!-- ================= TAB 2: Poster va banner ================= -->
    <div class="card-box admin-tab-panel" id="tab-media">
        <label>Poster rasm</label>
        <div class="poster-box">
            <?php if ($item['poster']): ?>
            <img src="<?php echo e(poster_url($item['poster'])); ?>" class="poster-preview" alt="poster">
            <?php endif; ?>
            <input type="text" name="poster_url" id="poster_url" value="<?php echo (strpos((string)$item['poster'], 'http') === 0) ? e($item['poster']) : ''; ?>" placeholder="Internetdagi poster URL manzili: https://... (masalan Gemini orqali)">
            <img id="poster_preview" class="poster-preview" style="display:none;" alt="yangi poster preview">
            <small style="opacity:.55;">Poster to'liq URL bo'lishi kerak (https://... bilan boshlansin). Saytga yuklanmaydi.</small>
        </div>

        <label>Banner / fon URL (ixtiyoriy)</label>
        <input type="url" name="banner_url" value="<?php echo e($item['banner_url']); ?>" placeholder="https://example.com/banner.jpg">
        <small style="opacity:.55;">Keng ekran fon rasmi. Havola sifatida saqlanadi.</small>
    </div>

    <!-- ================= TAB 3: Janrlar ================= -->
    <div class="card-box admin-tab-panel" id="tab-genres">
        <label>Janrlar</label>
        <div class="genre-pills">
            <?php foreach ($genres as $g): ?>
            <label class="genre-pill">
                <input type="checkbox" name="genres[]" value="<?php echo $g['id']; ?>" <?php echo in_array($g['id'], $selected_genres) ? 'checked' : ''; ?>> <?php echo e($g['name']); ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ================= TAB 4: Qismlar ================= -->
    <div class="card-box admin-tab-panel" id="tab-episodes">
        <p style="opacity:.75;margin-top:0;">Qismlarni qo'shing yoki tahrirlang. Bo'sh havolali qatorlar saqlanmaydi.</p>
        <div id="episodesContainer"></div>
        <button type="button" class="btn" id="addEpisodeBtn" style="margin-top:6px;">➕ Qism qo'shish</button>
    </div>

    <div style="margin-top:20px;">
        <button type="submit" class="btn" style="font-size:16px;padding:14px 36px;">💾 Saqlash</button>
        <a href="list_content.php" class="btn" style="text-decoration:none;">&#8592; Ro'yxatga qaytish</a>
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
    var type = document.querySelector('input[name="' + prefix + '[' + index + '][video_type]"]:checked');
    if (!type) return;
    var val = type.value;
    var urlRow = document.getElementById('src-url-' + index);
    var embedRow = document.getElementById('src-embed-' + index);
    if (urlRow) urlRow.style.display = (val === 'embed') ? 'none' : 'block';
    if (embedRow) embedRow.style.display = (val === 'embed') ? 'block' : 'none';
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
            (data.ep_id ? '<input type="hidden" name="episodes[' + i + '][ep_id]" value="' + data.ep_id + '">' : '') +
            '<div><label>Raqam *</label><input type="number" name="episodes[' + i + '][episode_number]" min="1" required value="' + (data.episode_number || (i + 1)) + '"></div>' +
            '<div><label>Qism nomi</label><input type="text" name="episodes[' + i + '][title]" placeholder="1-qism" value="' + (data.title || '') + '"></div>' +
            '<div><label>Davomiyligi</label><input type="text" name="episodes[' + i + '][duration]" placeholder="24 daqiqa" value="' + (data.duration || '') + '"></div>' +
            '<div><label>Intro boshlanishi</label><input type="number" min="0" max="3600" name="episodes[' + i + '][intro_start]" value="' + (data.intro_start || 0) + '"></div>' +
            '<div><label>Intro tugashi</label><input type="number" min="0" max="3600" name="episodes[' + i + '][intro_end]" value="' + (data.intro_end || 0) + '"></div>' +
        '</div>' +
        '<div class="ep-type-toggle">' +
            (['cloud', 'embed'].indexOf(data.video_type || 'cloud') === -1 ? '<input type="hidden" name="episodes[' + i + '][video_type]" value="' + (data.video_type || 'telegram') + '">' : '') +
            '<label><input type="radio" name="episodes[' + i + '][video_type]" value="cloud" ' + ((!data.video_type || data.video_type === 'cloud') ? 'checked' : '') + ' onchange="epFieldSource(\'episodes\',' + i + ')"> Direct URL</label>' +
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
            (data.telegram_file_id ? '<input type="hidden" name="episodes[' + i + '][telegram_file_id]" value="' + data.telegram_file_id + '">' : '') +
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

// Mavjud qismlarni yuklash
<?php foreach ($episodes as $ep): ?>
addEpisodeRow({
    ep_id: <?php echo (int)$ep['id']; ?>,
    episode_number: <?php echo (int)$ep['episode_number']; ?>,
    title: <?php echo json_encode($ep['title'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
    video_type: <?php echo json_encode($ep['video_type'] ?? 'cloud'); ?>,
    video_url: <?php echo json_encode($ep['video_url'] ?? ''); ?>,
    video_url_1080p: <?php echo json_encode($ep['video_url_1080p'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
    video_url_720p: <?php echo json_encode($ep['video_url_720p'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
    telegram_file_id: <?php echo json_encode($ep['telegram_file_id'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
    embed_code: <?php echo json_encode($ep['embed_code'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
    duration: <?php echo json_encode($ep['duration'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
    intro_start: <?php echo (int)($ep['intro_start'] ?? 0); ?>,
    intro_end: <?php echo (int)($ep['intro_end'] ?? 0); ?>
});
<?php endforeach; ?>

if (!epContainer.children.length) addEpisodeRow();
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
