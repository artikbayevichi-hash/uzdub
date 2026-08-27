<?php
$page_title = "AniHub Import";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
include __DIR__ . '/includes/admin_header.php';

$anihub_api = 'https://www.anihub.top/api';
$message = '';
$error = '';

function anihub_fetch($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body ?: null;
}

// Anime qidirish
$search_query = trim($_GET['q'] ?? '');
$anime_data = null;
$selected_anime_id = $_GET['anime_id'] ?? '';
$episodes_data = null;

if ($search_query) {
    $json = anihub_fetch("$anihub_api/anime?search=" . urlencode($search_query));
    if ($json) {
        $resp = @json_decode($json, true);
        if (!empty($resp['data']['items'])) {
            $anime_data = $resp['data']['items'];
        }
    }
}

// Anime tanlangan — epizodlarni olish
if ($selected_anime_id) {
    $json = anihub_fetch("$anihub_api/anime/$selected_anime_id/page-data");
    if ($json) {
        $resp = @json_decode($json, true);
        if (!empty($resp['data']['anime'])) {
            $episodes_data = $resp['data']['anime'];
        }
    }
}

// Import qilish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_content_id'], $_POST['import_data'])) {
    if (validate_csrf($_POST['csrf_token'] ?? '')) {
        $content_id = (int)$_POST['import_content_id'];
        $import_data = json_decode($_POST['import_data'], true);
        if ($import_data && is_array($import_data)) {
            $imported = 0;
            $skipped = 0;
            foreach ($import_data as $ep) {
                $ep_num = (int)($ep['episode_number'] ?? 0);
                $source_url = $ep['source_url'] ?? '';
                $kind = $ep['kind'] ?? 'ovoz';
                if ($ep_num <= 0 || !$source_url) { $skipped++; continue; }

                // mavjud epizodni topish
                $stmt = $pdo->prepare("SELECT id, video_url FROM episodes WHERE content_id = ? AND episode_number = ?");
                $stmt->execute([$content_id, $ep_num]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // Faqat agar URL o'zgargan bo'lsa yangilash
                    if ($existing['video_url'] !== $source_url) {
                        $upd = $pdo->prepare("UPDATE episodes SET video_url = ?, video_type = 'embed' WHERE id = ?");
                        $upd->execute([$source_url, $existing['id']]);
                        $imported++;
                    } else {
                        $skipped++;
                    }
                } else {
                    // Yangi epizod yaratish
                    $ins = $pdo->prepare("INSERT INTO episodes (content_id, episode_number, season, video_url, video_type, title) VALUES (?, ?, 1, ?, 'embed', ?)");
                    $title = $kind === 'sub' ? "Episode $ep_num (Sub)" : "Episode $ep_num";
                    $ins->execute([$content_id, $ep_num, $source_url, $title]);
                    $imported++;
                }
            }
            $message = "Import tugadi: $imported yangilandi, $skipped o'tkazildi.";
        }
    }
}
?>

<h1>AniHub Import</h1>
<p style="color:var(--text-muted);margin-bottom:20px;">AniHub.top dan anime URL'larini import qilish</p>

<?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

<div class="card-box">
    <h3>1. Anime qidirish</h3>
    <form method="get" style="display:flex;gap:10px;margin-top:10px;">
        <input type="text" name="q" value="<?php echo e($search_query); ?>" placeholder="Anime nomi (masalan: One Piece)" style="flex:1;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg-secondary);color:var(--text-primary);">
        <button type="submit" class="btn btn-primary">🔍 Qidirish</button>
    </form>
</div>

<?php if ($anime_data): ?>
<div class="card-box">
    <h3>2. Anime tanlang</h3>
    <table style="margin-top:10px;">
        <tr><th></th><th>Nomi</th><th>Yili</th><th>Turi</th><th>Epizodlar</th></tr>
        <?php foreach ($anime_data as $a): ?>
        <tr>
            <td><a href="?anime_id=<?php echo e($a['id']); ?>" class="btn btn-sm">Tanlash</a></td>
            <td><strong><?php echo e($a['title_uz'] ?: $a['title_en']); ?></strong></td>
            <td><?php echo (int)($a['year'] ?? 0); ?></td>
            <td><?php echo e($a['type'] ?? ''); ?></td>
            <td><?php echo e(json_encode($a['episodes_info'] ?? [])); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($episodes_data): ?>
<?php
    // DB'dagi content lar ro'yxatini olish
    $contents = $pdo->query("SELECT id, title FROM content ORDER BY title")->fetchAll();
    $anime = $episodes_data;
    $anime_id = $selected_anime_id;
    $anime_title = $anime['title_uz'] ?: $anime['title_en'];
?>

<div class="card-box">
    <h3>3. Import qilish — <?php echo e($anime_title); ?></h3>
    <p style="color:var(--text-muted);"><?php echo count($anime['episodes'] ?? []); ?> ta epizod topildi</p>

    <form method="post" id="importForm">
        <?php echo csrf_input(); ?>
        <div style="margin:12px 0;">
            <label><strong>Uzdub kontentini tanlang:</strong></label>
            <select name="import_content_id" required style="padding:8px;border-radius:8px;border:1px solid var(--border);background:var(--bg-secondary);color:var(--text-primary);min-width:300px;">
                <option value="">— Kontent tanlang —</option>
                <?php foreach ($contents as $c): ?>
                <option value="<?php echo $c['id']; ?>"><?php echo e($c['title']); ?> (#<?php echo $c['id']; ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin:12px 0;max-height:400px;overflow-y:auto;">
            <table>
                <tr><th>Qism</th><th>Manba URL</th><th>Turi</th><th>Sifat</th><th></th></tr>
                <?php
                $import_items = [];
                foreach ($anime['episodes'] ?? [] as $ep) {
                    foreach ($ep['sources'] ?? [] as $src) {
                        $provider = strtolower(trim($src['provider'] ?? ''));
                        $url = $src['url'] ?? '';
                        // Faqat Sibnet manbalarini qabul qilish
                        if ($provider !== 'sibnet' && strpos($url, 'sibnet') === false) continue;
                        $import_items[] = [
                            'episode_number' => $ep['episode_number'],
                            'source_url' => $url,
                            'kind' => $src['kind'] ?? 'ovoz',
                            'quality' => $src['quality'] ?? '',
                            'provider' => $provider,
                        ];
                    }
                }
                ?>
                <?php foreach ($import_items as $item): ?>
                <tr>
                    <td><?php echo (int)$item['episode_number']; ?></td>
                    <td style="max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><a href="<?php echo e($item['source_url']); ?>" target="_blank"><?php echo e($item['source_url']); ?></a></td>
                    <td><?php echo e($item['kind']); ?></td>
                    <td><?php echo e($item['quality']); ?> (<?php echo e($item['provider']); ?>)</td>
                    <td><input type="checkbox" class="ep-check" data-url="<?php echo e($item['source_url']); ?>" data-ep="<?php echo (int)$item['episode_number']; ?>" data-kind="<?php echo e($item['kind']); ?>" checked></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <input type="hidden" name="import_data" id="importDataInput">
        <div style="margin-top:12px;display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary" onclick="prepareImport()">📥 Import qilish (<?php echo count($import_items); ?> ta)</button>
            <span style="color:var(--text-muted);line-height:36px;" id="importCount"></span>
        </div>
    </form>
</div>

<script>
function prepareImport() {
    var items = [];
    document.querySelectorAll('.ep-check:checked').forEach(function(cb) {
        items.push({
            episode_number: parseInt(cb.dataset.ep),
            source_url: cb.dataset.url,
            kind: cb.dataset.kind
        });
    });
    document.getElementById('importDataInput').value = JSON.stringify(items);
    document.getElementById('importCount').textContent = items.length + ' ta import qilinadi';
}
document.querySelectorAll('.ep-check').forEach(function(cb) {
    cb.addEventListener('change', prepareImport);
});
prepareImport();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
