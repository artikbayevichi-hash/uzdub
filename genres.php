<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$sort = $_GET['sort'] ?? 'newest';
$cat_filter = $_GET['cat'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 18;
$offset = ($page - 1) * $per_page;

$selected_slugs = [];
if (!empty($_GET['genres'])) {
    $raw = $_GET['genres'];
    if (is_string($raw)) $raw = explode(',', $raw);
    $raw = array_map('trim', (array)$raw);
    $raw = array_filter($raw);
    $selected_slugs = array_values($raw);
}

$all_genres = $pdo->query("
    SELECT g.id, g.name, g.slug, g.color,
           COUNT(cg.content_id) AS content_count
    FROM genres g
    LEFT JOIN content_genres cg ON g.id = cg.genre_id
    GROUP BY g.id ORDER BY g.name
")->fetchAll();

$all_genres_by_slug = [];
foreach ($all_genres as $ag) $all_genres_by_slug[$ag['slug']] = $ag;

$selected_ids = [];
foreach ($selected_slugs as $s) {
    if (isset($all_genres_by_slug[$s])) $selected_ids[] = $all_genres_by_slug[$s]['id'];
}
$selected_count = count($selected_ids);

$content_items = [];
$total = 0;
$total_pages = 1;
$cat_counts = [];
$head_title = t('all_genres');

function build_genre_url($extras = []) {
    global $selected_slugs, $sort, $cat_filter, $all_genres_by_slug;
    $params = [];
    foreach ($selected_slugs as $s) $params['genres'][] = $s;
    if (!empty($extras['sort'])) $params['sort'] = $extras['sort'];
    elseif ($sort !== 'newest') $params['sort'] = $sort;
    if (!empty($extras['cat'])) $params['cat'] = $extras['cat'];
    elseif ($cat_filter) $params['cat'] = $cat_filter;
    if (!empty($extras['page'])) $params['page'] = $extras['page'];
    return '/uzdub/genres.php?' . http_build_query($params);
}

if ($selected_count > 0) {
    $names = [];
    foreach ($selected_slugs as $s) {
        if (isset($all_genres_by_slug[$s])) $names[] = $all_genres_by_slug[$s]['name'];
    }
    $head_title = implode(' + ', $names);

    $placeholders = implode(',', array_fill(0, $selected_count, '?'));
    $params = $selected_ids;

    $where_extra = "";
    $allowed = ['kino', 'anime', 'multfilm'];
    if (in_array($cat_filter, $allowed)) {
        $where_extra = " AND cat.slug = ?";
        $params[] = $cat_filter;
    }

    $order = match($sort) {
        'rating' => 'c.rating DESC, c.title ASC',
        'year_desc' => 'c.release_year DESC, c.title ASC',
        'year_asc' => 'c.release_year ASC, c.title ASC',
        'popular' => 'c.views DESC, c.title ASC',
        'title' => 'c.title ASC',
        default => 'c.created_at DESC, c.title ASC',
    };

    $sql_base = "
        FROM content c
        JOIN content_genres cg ON c.id = cg.content_id
        JOIN categories cat ON c.category_id = cat.id
        WHERE cg.genre_id IN ($placeholders) $where_extra
        GROUP BY c.id
        HAVING COUNT(DISTINCT cg.genre_id) = $selected_count
    ";

    $cnt = $pdo->prepare("SELECT COUNT(*) $sql_base");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();
    $total_pages = max(1, (int)ceil($total / $per_page));

    $data = $pdo->prepare("SELECT c.*, cat.name AS cat_name, cat.slug AS cat_slug $sql_base ORDER BY $order LIMIT $per_page OFFSET $offset");
    $data->execute($params);
    $content_items = $data->fetchAll();

    $cc = $pdo->prepare("SELECT cat.slug, COUNT(DISTINCT c.id) AS cnt FROM content c JOIN content_genres cg ON c.id=cg.content_id JOIN categories cat ON c.category_id=cat.id WHERE cg.genre_id IN ($placeholders) $where_extra GROUP BY cat.id");
    $cc->execute($params);
    while ($r = $cc->fetch()) $cat_counts[$r['slug']] = (int)$r['cnt'];
}

include __DIR__ . '/includes/header.php';
?>

<style>
.genre-page{max-width:1100px;margin:80px auto 40px;padding:0 16px;display:flex;gap:24px;position:relative;z-index:1}
.genre-sidebar{width:230px;flex-shrink:0}
.genre-sidebar-inner{position:sticky;top:80px;background:var(--card-bg);border:1px solid rgba(33,150,243,0.2);border-radius:12px;overflow:hidden}
.genre-sidebar-title{padding:14px 16px 10px;font-size:13px;font-weight:700;color:var(--blue-glow);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid rgba(33,150,243,0.12)}
.genre-sidebar-list{max-height:55vh;overflow-y:auto;padding:4px 0}
.genre-sidebar-list::-webkit-scrollbar{width:4px}
.genre-sidebar-list::-webkit-scrollbar-thumb{background:var(--blue-deep);border-radius:10px}
.genre-item{display:flex;align-items:center;padding:8px 14px;font-size:13px;color:var(--text-light);cursor:pointer;transition:background .15s;gap:0;position:relative}
.genre-item:hover{background:rgba(33,150,243,0.08)}
.genre-item input[type=checkbox]{display:none}
.genre-item .g-check{width:16px;height:16px;border:2px solid rgba(255,255,255,0.2);border-radius:4px;margin-right:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .15s}
.genre-item input:checked + .g-check{background:var(--blue-primary);border-color:var(--blue-primary)}
.genre-item input:checked + .g-check::after{content:'✓';color:#fff;font-size:11px;font-weight:700}
.genre-item input:checked ~ .g-name{color:var(--blue-glow);font-weight:600}
.g-dot{width:8px;height:8px;border-radius:50%;margin-right:8px;flex-shrink:0}
.g-name{flex:1}
.g-count{font-size:11px;color:var(--text-muted);background:rgba(255,255,255,0.06);padding:1px 7px;border-radius:8px;margin-left:8px}
.genre-apply-bar{padding:10px 14px;border-top:1px solid rgba(33,150,243,0.12)}
.genre-apply-btn{width:100%;padding:9px;border:none;border-radius:8px;background:var(--blue-primary);color:#fff;font-size:13px;font-weight:600;cursor:pointer;transition:background .2s}
.genre-apply-btn:hover{background:var(--blue-glow)}
.genre-apply-btn:disabled{opacity:.4;cursor:default}
.genre-clear-btn{width:100%;padding:6px;border:none;border-radius:6px;background:none;color:var(--text-muted);font-size:12px;cursor:pointer;margin-top:4px;transition:color .15s}
.genre-clear-btn:hover{color:#ef5350}
.genre-selected-tags{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}
.genre-tag{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:14px;font-size:12px;font-weight:600;border:1px solid rgba(33,150,243,0.25);background:rgba(33,150,243,0.1);color:var(--blue-glow);text-decoration:none;transition:all .15s}
.genre-tag:hover{background:rgba(239,83,80,0.15);border-color:#ef5350;color:#ef5350}
.genre-main{flex:1;min-width:0}
.genre-head{margin-bottom:18px}
.genre-head h2{font-size:22px;margin:0 0 12px;color:var(--text-light)}
.genre-head h2 .gh-dot{margin-right:8px}
.genre-controls{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.genre-cat-tabs{display:flex;gap:6px;flex:1;flex-wrap:wrap}
.genre-cat-tab{padding:7px 14px;border-radius:8px;background:var(--card-bg);border:1px solid rgba(33,150,243,0.15);color:var(--text-muted);font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s;white-space:nowrap}
.genre-cat-tab.active{background:var(--blue-primary);color:#fff;border-color:var(--blue-primary)}
.genre-cat-tab:hover:not(.active){border-color:var(--blue-primary);color:var(--blue-glow)}
.genre-cat-tab .tab-count{margin-left:4px;font-size:10px;opacity:.7}
.genre-sort select{padding:7px 28px 7px 10px;border-radius:8px;background:var(--card-bg);border:1px solid rgba(33,150,243,0.15);color:var(--text-light);font-size:12px;cursor:pointer;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%239aa8bd'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center}
.genre-sort select:focus{outline:none;border-color:var(--blue-primary)}
.genre-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:14px}
.genre-grid .card{background:var(--card-bg);border-radius:10px;overflow:hidden;text-decoration:none;color:var(--text-light);border:1px solid rgba(255,255,255,0.06);transition:transform .2s,box-shadow .2s}
.genre-grid .card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(0,0,0,0.4)}
.genre-grid .card img{width:100%;aspect-ratio:2/3;object-fit:cover;background:#1a2438}
.genre-grid .card-info{padding:10px 12px}
.genre-grid .card-info h3{margin:0;font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.genre-grid .card-info .meta{margin-top:4px;font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:6px}
.genre-grid .card-info .badge{font-size:10px;background:rgba(33,150,243,0.15);padding:1px 6px;border-radius:6px;color:var(--blue-glow)}
.genre-cat-badge{font-size:10px;padding:1px 6px;border-radius:6px;background:rgba(33,150,243,0.12);color:var(--blue-glow);font-weight:600}
.genre-premium{position:absolute;top:6px;right:6px;font-size:10px;background:linear-gradient(135deg,#f9a825,#ff6f00);color:#fff;padding:2px 6px;border-radius:6px}
.genre-empty{text-align:center;padding:40px 20px;color:var(--text-muted);font-size:15px}
.genre-empty .ge-icon{font-size:48px;margin-bottom:12px;display:block}
.genre-pagination{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:24px}
.genre-pagination a,.genre-pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 10px;border-radius:8px;font-size:13px;text-decoration:none;transition:all .15s;border:1px solid rgba(33,150,243,0.15)}
.genre-pagination a{background:var(--card-bg);color:var(--text-light)}
.genre-pagination a:hover{background:var(--blue-primary);color:#fff;border-color:var(--blue-primary)}
.genre-pagination .active{background:var(--blue-primary);color:#fff;border-color:var(--blue-primary);font-weight:600}
.genre-pagination .disabled{opacity:.35;pointer-events:none}
.genre-all-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
.genre-all-card{display:flex;align-items:center;gap:12px;padding:16px;background:var(--card-bg);border:1px solid rgba(33,150,243,0.15);border-radius:10px;text-decoration:none;color:var(--text-light);transition:all .2s}
.genre-all-card:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,0.3)}
.genre-all-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;flex-shrink:0}
.genre-result-info{font-size:13px;color:var(--text-muted);margin-bottom:14px}
@media(max-width:768px){.genre-page{flex-direction:column}.genre-sidebar{width:100%}.genre-sidebar-inner{position:static}.genre-sidebar-list{max-height:none}.genre-grid{grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px}.genre-controls{flex-direction:column;align-items:stretch}.genre-cat-tabs{overflow-x:auto;-webkit-overflow-scrolling:touch}}
</style>

<div class="genre-page">
    <aside class="genre-sidebar">
        <div class="genre-sidebar-inner">
            <div class="genre-sidebar-title">🎵 <?php echo t('genres'); ?></div>
            <form id="genreFilterForm">
                <div class="genre-sidebar-list">
                    <?php foreach ($all_genres as $g): ?>
                    <label class="genre-item">
                        <input type="checkbox" name="genres[]" value="<?php echo e($g['slug']); ?>" <?php echo in_array($g['slug'], $selected_slugs) ? 'checked' : ''; ?>>
                        <span class="g-check"></span>
                        <span class="g-dot" style="background:<?php echo e($g['color'] ?: '#2196f3'); ?>;"></span>
                        <span class="g-name"><?php echo e($g['name']); ?></span>
                        <?php if ($g['content_count'] > 0): ?>
                        <span class="g-count"><?php echo $g['content_count']; ?></span>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="genre-apply-bar">
                    <button type="submit" class="genre-apply-btn" id="genreApplyBtn">
                        🔍 <?php echo t('browse_by_genre'); ?>
                    </button>
                    <?php if ($selected_count > 0): ?>
                    <button type="button" class="genre-clear-btn" onclick="window.location.href='/uzdub/genres.php'">✕ <?php echo t('all_genres'); ?></button>
                    <?php endif; ?>
                </div>
                <input type="hidden" name="sort" value="<?php echo e($sort); ?>">
            </form>
        </div>
    </aside>

    <div class="genre-main">
        <?php if ($selected_count > 0): ?>
        <div class="genre-selected-tags">
            <?php foreach ($selected_slugs as $s):
                $sg = $all_genres_by_slug[$s] ?? null;
                if (!$sg) continue;
                $remove_url = '/uzdub/genres.php?';
                $remaining = array_values(array_diff($selected_slugs, [$s]));
                $params = ['genres' => $remaining, 'sort' => $sort];
                if ($cat_filter) $params['cat'] = $cat_filter;
                $remove_url .= http_build_query($params);
            ?>
            <a href="<?php echo $remove_url; ?>" class="genre-tag" title="<?php echo e($sg['name']); ?> — ✕">
                <span style="color:<?php echo e($sg['color'] ?: '#2196f3'); ?>;">●</span>
                <?php echo e($sg['name']); ?> ✕
            </a>
            <?php endforeach; ?>
        </div>

        <div class="genre-head">
            <h2>
                <?php foreach ($selected_slugs as $i => $s):
                    $sg = $all_genres_by_slug[$s] ?? null;
                    if (!$sg) continue;
                    if ($i > 0) echo ' <span style="color:var(--text-muted);font-weight:400;">+</span> ';
                ?>
                <span class="gh-dot" style="color:<?php echo e($sg['color'] ?: '#2196f3'); ?>;">●</span><?php echo e($sg['name']); ?>
                <?php endforeach; ?>
            </h2>
            <div class="genre-controls">
                <div class="genre-cat-tabs">
                    <?php
                    $base_all = '/uzdub/genres.php?' . http_build_query(array_filter(['genres' => $selected_slugs, 'sort' => $sort]));
                    ?>
                    <a href="<?php echo $base_all; ?>" class="genre-cat-tab <?php echo !$cat_filter ? 'active' : ''; ?>">
                        📋 <?php echo t('all_genres'); ?><span class="tab-count"><?php echo $total; ?></span>
                    </a>
                    <?php foreach (['kino' => '🎬', 'anime' => '🎌', 'multfilm' => '🎞️'] as $cs => $ci): ?>
                    <?php if (!empty($cat_counts[$cs])): ?>
                    <?php $tab_url = '/uzdub/genres.php?' . http_build_query(array_filter(['genres' => $selected_slugs, 'cat' => $cs, 'sort' => $sort])); ?>
                    <a href="<?php echo $tab_url; ?>" class="genre-cat-tab <?php echo $cat_filter === $cs ? 'active' : ''; ?>">
                        <?php echo $ci; ?> <?php echo t($cs === 'kino' ? 'movies' : ($cs === 'anime' ? 'anime' : 'cartoons')); ?><span class="tab-count"><?php echo $cat_counts[$cs]; ?></span>
                    </a>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="genre-sort">
                    <?php $sort_base = '/uzdub/genres.php?' . http_build_query(array_filter(['genres' => $selected_slugs, 'cat' => $cat_filter])); ?>
                    <select onchange="window.location.href='<?php echo e($sort_base); ?>&sort='+this.value">
                        <?php
                        $sorts = ['newest' => t('newest'), 'popular' => t('most_viewed'), 'rating' => t('top_rated'), 'year_desc' => '↓ ' . t('release_year'), 'year_asc' => '↑ ' . t('release_year'), 'title' => 'A-Z'];
                        foreach ($sorts as $sv => $sl):
                        ?>
                        <option value="<?php echo $sv; ?>" <?php echo $sort === $sv ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <?php if (!empty($content_items)): ?>
        <div class="genre-result-info"><?php echo $total; ?> <?php echo t('content_count'); ?></div>
        <div class="genre-grid">
            <?php foreach ($content_items as $item): ?>
            <a href="/uzdub/watch.php?id=<?php echo $item['id']; ?>" class="card" style="position:relative;">
                <?php if ($item['is_premium']): ?><span class="genre-premium">⭐</span><?php endif; ?>
                <img src="<?php echo $item['poster'] ? 'uploads/posters/' . e($item['poster']) : 'https://via.placeholder.com/300x420/121a2b/2196f3?text=' . urlencode(t_title($item)); ?>" alt="<?php echo e(t_title($item)); ?>" loading="lazy">
                <div class="card-info">
                    <h3><?php echo e(t_title($item)); ?></h3>
                    <div class="meta">
                        <span><?php echo e($item['release_year']); ?></span>
                        <span class="badge">&#9733; <?php echo e($item['rating']); ?></span>
                        <span class="genre-cat-badge"><?php echo e($item['cat_name']); ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="genre-pagination">
            <?php
            $pp = '/uzdub/genres.php?' . http_build_query(array_filter(['genres' => $selected_slugs, 'sort' => $sort, 'cat' => $cat_filter]));
            ?>
            <a href="<?php echo $pp . '&page=' . ($page - 1); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">‹</a>
            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            if ($start > 1): ?>
                <a href="<?php echo $pp . '&page=1'; ?>">1</a>
                <?php if ($start > 2): ?><span class="disabled">…</span><?php endif; ?>
            <?php endif; ?>
            <?php for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?php echo $pp . '&page=' . $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($end < $total_pages): ?>
                <?php if ($end < $total_pages - 1): ?><span class="disabled">…</span><?php endif; ?>
                <a href="<?php echo $pp . '&page=' . $total_pages; ?>"><?php echo $total_pages; ?></a>
            <?php endif; ?>
            <a href="<?php echo $pp . '&page=' . ($page + 1); ?>" class="<?php echo $page >= $total_pages ? 'disabled' : ''; ?>">›</a>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="genre-empty">
            <span class="ge-icon">🎭</span>
            <?php echo t('no_content_genre'); ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <h2>📋 <?php echo t('all_genres'); ?></h2>
        <p style="color:var(--text-muted);margin:0 0 20px;font-size:14px;"><?php echo t('browse_by_genre'); ?></p>
        <div class="genre-all-grid">
            <?php foreach ($all_genres as $g): ?>
            <a href="/uzdub/genres.php?genres[]=<?php echo e($g['slug']); ?>" class="genre-all-card">
                <span class="genre-all-icon" style="background:<?php echo e($g['color'] ?: '#2196f3'); ?>22;color:<?php echo e($g['color'] ?: '#2196f3'); ?>;"><?php echo mb_substr($g['name'], 0, 2); ?></span>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:14px;font-weight:600;"><?php echo e($g['name']); ?></div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?php echo $g['content_count']; ?> <?php echo t('content_count'); ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('genreFilterForm');
    var checkboxes = form.querySelectorAll('input[name="genres[]"]');
    var btn = document.getElementById('genreApplyBtn');

    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', function() {
            var checked = form.querySelectorAll('input[name="genres[]"]:checked').length;
            btn.disabled = checked === 0;
        });
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var params = new URLSearchParams();
        form.querySelectorAll('input[name="genres[]"]:checked').forEach(function(cb) {
            params.append('genres[]', cb.value);
        });
        var sortVal = form.querySelector('input[name="sort"]');
        if (sortVal && sortVal.value !== 'newest') params.set('sort', sortVal.value);
        window.location.href = '/uzdub/genres.php?' + params.toString();
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
