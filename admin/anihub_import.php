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

// SSR (sayt) sahifasidan mover/sibnet source'larini epizod raqamiga bog'lab olish
function anihub_parse_ssr_links($html) {
    $out = [];
    if (!$html) return $out;
    $patterns = [
        'mover'  => 'mover\.uz\/video\/embed\/[A-Za-z0-9]+',
        'sibnet' => 'sibnet[^"\\\\]*',
    ];
    foreach ($patterns as $prov => $pat) {
        preg_match_all('/episode_number\\\\":(\d+).{0,1400}?(' . $pat . ')/', $html, $m, PREG_SET_ORDER);
        foreach ($m as $p) {
            $ep = (int)$p[1];
            $raw = $p[2];
            $url = (strpos($raw, 'http') === 0) ? $raw : 'https://' . $raw;
            if ($ep <= 0 || !$url) continue;
            $out[$ep][] = ['provider' => $prov, 'url' => $url];
        }
    }
    return $out;
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
            // SSR sahifadan ham mover/sibnet manbalarini olish (API ularni qaytarmasligi mumkin)
            $slug = $episodes_data['slug'] ?? '';
            if ($slug) {
                $ssr_html = anihub_fetch("https://www.anihub.top/anime/$selected_anime_id-$slug");
                $ssr_links = anihub_parse_ssr_links($ssr_html);
            }
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

                // Mover embed manbasini to'g'ridan-to'g'ri mp4 ga aylantiramiz
                // (iframe o'rniga HTML5 player'da ishonchli ko'rinishi uchun).
                $video_type = 'embed';
                if (preg_match('#mover\.uz/video/embed/([A-Za-z0-9]+)#i', $source_url, $mm)) {
                    $source_url = 'https://v.mover.uz/' . $mm[1] . '_m.mp4';
                    $video_type = 'cloud';
                }

                // mavjud epizodni topish
                $stmt = $pdo->prepare("SELECT id, video_url FROM episodes WHERE content_id = ? AND episode_number = ?");
                $stmt->execute([$content_id, $ep_num]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // Faqat agar URL o'zgargan bo'lsa yangilash
                    if ($existing['video_url'] !== $source_url) {
                        $upd = $pdo->prepare("UPDATE episodes SET video_url = ?, video_type = ? WHERE id = ?");
                        $upd->execute([$source_url, $video_type, $existing['id']]);
                        $imported++;
                    } else {
                        $skipped++;
                    }
                } else {
                    // Yangi epizod yaratish
                    $ins = $pdo->prepare("INSERT INTO episodes (content_id, episode_number, season, video_url, video_type, title) VALUES (?, ?, 1, ?, ?, '')");
                    $ins->execute([$content_id, $ep_num, $source_url, $video_type]);
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
    // DB'dagi content lar ro'yxatini olish (qidiruv uchun)
    $contents = $pdo->query("SELECT id, title, title_ru, title_en FROM content ORDER BY title")->fetchAll();
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
            <label><strong>Uzdub kontentini qidirib tanlang:</strong></label>
            <input type="text" id="contentSearch" placeholder="Kontent nomini yozing... (masalan: Naruto)" autocomplete="off" style="margin-top:6px;width:100%;box-sizing:border-box;padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg-secondary);color:var(--text-primary);">
            <div id="contentResults" style="margin-top:8px;max-height:260px;overflow-y:auto;border:1px solid rgba(255,255,255,.1);border-radius:8px;display:none;"></div>
            <input type="hidden" name="import_content_id" id="importContentId" required>
            <div id="contentSelected" style="margin-top:8px;display:none;padding:8px 12px;border-radius:8px;background:rgba(76,175,80,.12);border:1px solid rgba(76,175,80,.4);"></div>
        </div>

        <div style="margin:12px 0;max-height:400px;overflow-y:auto;">
            <table>
                <tr><th>Qism</th><th>Manba URL</th><th>Turi</th><th>Sifat</th><th></th></tr>
                <?php
                $ssr_links = $ssr_links ?? [];
                $import_items = [];
                $added = [];
                foreach ($anime['episodes'] ?? [] as $ep) {
                    $ep_num = (int)$ep['episode_number'];
                    // 1) API manbalaridan reklamasiz resolve bo'ladiganlarni olish:
                    //    Sibnet, Mover, VK (video_ext), OK.ru — hammasi UZDUB player'ida HTML5 mp4 qilib ko'rsatiladi.
                    foreach ($ep['sources'] ?? [] as $src) {
                        $provider = strtolower(trim($src['provider'] ?? ''));
                        $url = $src['url'] ?? '';
                        $is_sibnet  = $provider === 'sibnet'  || strpos($url, 'sibnet')  !== false;
                        $is_mover   = $provider === 'mover'   || strpos($url, 'mover')   !== false;
                        $is_vk      = $provider === 'vk'      || strpos($url, 'vk.com/video_ext') !== false || strpos($url, 'vkvideo.ru/video_ext') !== false;
                        $is_okru    = $provider === 'ok'      || strpos($url, 'ok.ru') !== false;
                        $is_uqload  = $provider === 'uqload'  || strpos($url, 'uqload.') !== false;
                        $is_dood    = $provider === 'doodstream' || $provider === 'dood' || strpos($url, 'dood') !== false || strpos($url, 'd000d') !== false;
                        if (!$is_sibnet && !$is_mover && !$is_vk && !$is_okru && !$is_uqload && !$is_dood) continue;
                        if (isset($added[$ep_num][$url])) continue;
                        $added[$ep_num][$url] = true;
                        $import_items[] = [
                            'episode_number' => $ep_num,
                            'source_url' => $url,
                            'kind' => $src['kind'] ?? 'ovoz',
                            'quality' => $src['quality'] ?? '',
                            'provider' => $provider,
                        ];
                    }
                    // 2) SSR sahifadan topilgan Mover/Sibnet manbalari
                    foreach ($ssr_links[$ep_num] ?? [] as $link) {
                        if (isset($added[$ep_num][$link['url']])) continue;
                        $added[$ep_num][$link['url']] = true;
                        $import_items[] = [
                            'episode_number' => $ep_num,
                            'source_url' => $link['url'],
                            'kind' => 'ovoz',
                            'quality' => '',
                            'provider' => $link['provider'],
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
// Uzdub kontentlari qidiruv tizimi
var CONTENTS = <?php echo json_encode($contents, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
var contentSearch = document.getElementById('contentSearch');
var contentResults = document.getElementById('contentResults');
var contentSelected = document.getElementById('contentSelected');
var contentId = document.getElementById('importContentId');

function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, function(m) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
    });
}

function renderContentResults(q) {
    q = (q || '').toLowerCase().trim();
    var list = [];
    if (q.length >= 1) {
        list = CONTENTS.filter(function(c) {
            var ok = false;
            if ((c.title || '').toLowerCase().indexOf(q) !== -1) ok = true;
            if ((c.title_ru || '').toLowerCase().indexOf(q) !== -1) ok = true;
            if ((c.title_en || '').toLowerCase().indexOf(q) !== -1) ok = true;
            if (/^\d+$/.test(q) && String(c.id) === q) ok = true;
            return ok;
        });
    }
    list = list.slice(0, 30);
    contentResults.innerHTML = '';
    if (!list.length) {
        contentResults.style.display = 'block';
        contentResults.innerHTML = '<div style="padding:10px;opacity:.7;">' + (q ? 'Hech narsa topilmadi' : 'Nomini yozing...') + '</div>';
        return;
    }
    list.forEach(function(c) {
        var d = document.createElement('div');
        d.style.cssText = 'padding:9px 12px;cursor:pointer;border-bottom:1px solid rgba(255,255,255,.06);';
        d.innerHTML = escHtml(c.title) + ' <small style="opacity:.5;">(#' + c.id + ')</small>';
        d.onmouseover = function(){ d.style.background='rgba(33,150,243,.12)'; };
        d.onmouseout = function(){ d.style.background='transparent'; };
        d.onclick = function(){
            contentId.value = c.id;
            contentResults.style.display = 'none';
            contentSearch.value = c.title;
            contentSelected.style.display = 'block';
            contentSelected.innerHTML = '✅ Tanlandi: <strong>' + escHtml(c.title) + '</strong> (#' + c.id + ') <span style="cursor:pointer;margin-left:10px;color:#ef5350;" onclick="clearSelected()">&#10005; bekor qilish</span>';
        };
        contentResults.appendChild(d);
    });
    contentResults.style.display = 'block';
}

function clearSelected() {
    contentId.value = '';
    contentSearch.value = '';
    contentSelected.style.display = 'none';
    renderContentResults('');
}

contentSearch.addEventListener('input', function() {
    contentResults.style.display = 'block';
    renderContentResults(this.value);
});
document.addEventListener('click', function(e) {
    if (!contentResults.contains(e.target) && e.target !== contentSearch) {
        contentResults.style.display = 'none';
    }
});

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
