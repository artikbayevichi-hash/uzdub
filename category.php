<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$allowed_sites = ['kino', 'anime', 'multfilm'];
if (!in_array($slug, $allowed_sites, true)) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
$stmt->execute([$slug]);
$category = $stmt->fetch();

if (!$category) {
    header('Location: index.php');
    exit;
}

$active_site = $slug;
$page_title = $category['name'];
$cat_id = (int)$category['id'];

// ---- To'liq katalog (grid uchun) ----
$items_stmt = $pdo->prepare("SELECT * FROM content WHERE category_id = ? ORDER BY created_at DESC");
$items_stmt->execute([$cat_id]);
$items = $items_stmt->fetchAll();

// ---- Yangi qo'shilganlar (oxirgi 12) ----
$new_items = [];
try {
    $st = $pdo->prepare("SELECT * FROM content WHERE category_id = ? ORDER BY created_at DESC LIMIT 12");
    $st->execute([$cat_id]);
    $new_items = $st->fetchAll();
} catch (PDOException $e) { $new_items = []; }

// ---- Eng yuqori reyting ----
$top_items = [];
try {
    $st = $pdo->prepare("SELECT * FROM content WHERE category_id = ? AND rating > 0 ORDER BY rating DESC, views DESC LIMIT 12");
    $st->execute([$cat_id]);
    $top_items = $st->fetchAll();
} catch (PDOException $e) { $top_items = []; }

// ---- Davom etish (faqat shu toifadagi kontent) ----
$continue_items = [];
if (is_user()) {
    try {
        $cw = $pdo->prepare(
            "SELECT c.*, wp.position_seconds, wp.duration_seconds, wp.episode_id
             FROM watch_progress wp
             JOIN content c ON wp.content_id = c.id
             JOIN (SELECT MAX(id) mid FROM watch_progress WHERE user_id = ? AND is_completed = 0 AND (duration_seconds <= 0 OR duration_seconds - position_seconds > 600) GROUP BY content_id) lastw ON lastw.mid = wp.id
             WHERE c.category_id = ?
             ORDER BY wp.updated_at DESC
             LIMIT 12"
        );
        $cw->execute([$_SESSION['user_id'], $cat_id]);
        $continue_items = $cw->fetchAll();
    } catch (PDOException $e) { $continue_items = []; }
}

// ---- Shu toifaning janrlari (soni bo'yicha) ----
$cat_genres = [];
try {
    $st = $pdo->prepare(
        "SELECT g.id, g.name, g.slug, g.color, COUNT(cg.content_id) AS cnt
         FROM genres g
         JOIN content_genres cg ON cg.genre_id = g.id
         JOIN content c ON c.id = cg.content_id
         WHERE c.category_id = ?
         GROUP BY g.id ORDER BY cnt DESC LIMIT 12"
    );
    $st->execute([$cat_id]);
    $cat_genres = $st->fetchAll();
} catch (PDOException $e) { $cat_genres = []; }

// ---- Epizod diapazonlari: ko'rsatiladigan barcha kontent uchun ----
$ep_ranges = [];
$all_ids = [];
foreach ([$items, $new_items, $top_items, $continue_items] as $group) {
    foreach ($group as $it) $all_ids[(int)$it['id']] = 1;
}
if (!empty($all_ids)) {
    $ids = array_keys($all_ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $ep_stmt = $pdo->prepare("SELECT content_id, MIN(episode_number) AS first_ep, MAX(episode_number) AS last_ep, COUNT(*) AS ep_count, MAX(created_at) AS last_ep_created_at FROM episodes WHERE content_id IN ($placeholders) GROUP BY content_id");
    $ep_stmt->execute($ids);
    while ($er = $ep_stmt->fetch(PDO::FETCH_ASSOC)) $ep_ranges[(int)$er['content_id']] = $er;
}

// ---- Sevimlilar ----
$favs = [];
if (is_user()) {
    $uid = $_SESSION['user_id'];
    $fav_stmt = $pdo->prepare("SELECT content_id FROM watchlist WHERE user_id = ?");
    $fav_stmt->execute([$uid]);
    $favs = array_flip($fav_stmt->fetchAll(PDO::FETCH_COLUMN));
}

// ---- Hero: shu toifadagi eng ko'p ko'rilgan 10 ta ----
$hero_items = [];
try {
    $hero_stmt = $pdo->prepare("SELECT c.*, cat.name as cat_name FROM content c JOIN categories cat ON c.category_id=cat.id WHERE c.category_id=? ORDER BY c.views DESC, c.release_year DESC LIMIT 10");
    $hero_stmt->execute([$cat_id]);
    $hero_items = $hero_stmt->fetchAll();
} catch (PDOException $e) { $hero_items = []; }

$site_info_row = [
    'kino'     => ['icon' => '🎬', 'slog' => "Eng so'nggi filmlar va jahon premyeralari — faqat bu yerda."],
    'anime'    => ['icon' => '🎌', 'slog' => "Yangi sessonlar, mashhur seriyalar va anime klassikasi."],
    'multfilm' => ['icon' => '🧸', 'slog' => "Bolalar uchun xavfsiz multfilmlar va oilaviy animatsiya."],
][$slug];

include __DIR__ . '/includes/header.php';
?>

<?php if (!empty($hero_items)): ?>
<section class="hero-carousel">
    <?php foreach ($hero_items as $i => $hero): ?>
        <div class="hero-slide <?php echo $i === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo $hero['poster'] ? e(poster_url($hero['poster'])) : 'https://via.placeholder.com/1400x800/0a0e17/2196f3?text=UZDUB+PLATFORM'; ?>');">
        <div class="hero-content">
            <div class="hero-tags">
                <span class="hero-tag"><?php echo $site_info_row['icon']; ?> <?php echo e($category['name']); ?></span>
            </div>
            <h1><?php echo e(t_title($hero)); ?></h1>
            <div class="hero-meta">
                <span>&#9733; <?php echo e($hero['rating']); ?></span>
                <span>&middot;</span>
                <span><?php echo e($hero['release_year']); ?></span>
                <span>&middot;</span>
                <span><?php echo e($hero['content_code'] ?? ''); ?></span>
            </div>
            <p><?php echo e(mb_strimwidth(t_desc($hero) ?? '', 0, 200, '...')); ?></p>
            <div>
                <a href="watch.php?id=<?php echo $hero['id']; ?>" class="btn btn-primary">&#9654; <?php echo t('watch'); ?></a>
                <a href="watch.php?id=<?php echo $hero['id']; ?>" class="btn btn-outline">&#9432; <?php echo t('details'); ?></a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (count($hero_items) > 1): ?>
    <div class="hero-dots">
        <?php foreach ($hero_items as $i => $hero): ?>
        <span class="hero-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></span>
        <?php endforeach; ?>
    </div>
    <button class="hero-arrow hero-arrow-prev" aria-label="Oldingi">&#10094;</button>
    <button class="hero-arrow hero-arrow-next" aria-label="Keyingi">&#10095;</button>
    <?php endif; ?>
</section>

<div class="site-strip reveal">
    <div class="site-strip-box">
        <span class="site-strip-icon"><?php echo $site_info_row['icon']; ?></span>
        <div class="site-strip-text">
            <span class="site-strip-name"><?php echo e($category['name']); ?> sayti</span>
            <span class="site-strip-slog"><?php echo $site_info_row['slog']; ?> <a href="<?php echo ROOT_URL; ?>/index.php" class="site-strip-back">&#8592; Portalga</a></span>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    var slides = document.querySelectorAll('.hero-slide');
    var dots = document.querySelectorAll('.hero-dot');
    var prevBtn = document.querySelector('.hero-arrow-prev');
    var nextBtn = document.querySelector('.hero-arrow-next');
    if (slides.length <= 1) return;
    var current = 0;
    var timer;

    slides.forEach(function(s) {
        s.style.opacity = '';
        s.style.transform = '';
        s.style.transition = '';
    });

    function showSlide(idx) {
        slides.forEach(function(s, i) { s.classList.toggle('active', i === idx); });
        dots.forEach(function(d, i) { d.classList.toggle('active', i === idx); });
        current = idx;
    }
    function nextSlide() { showSlide((current + 1) % slides.length); }
    function prevSlide() { showSlide((current - 1 + slides.length) % slides.length); }
    function resetTimer() { clearInterval(timer); timer = setInterval(nextSlide, 6000); }

    if (prevBtn) prevBtn.addEventListener('click', function() { prevSlide(); resetTimer(); });
    if (nextBtn) nextBtn.addEventListener('click', function() { nextSlide(); resetTimer(); });
    dots.forEach(function(dot) {
        dot.addEventListener('click', function() {
            showSlide(parseInt(dot.dataset.index));
            resetTimer();
        });
    });

    var touchStartX = 0;
    var touchEndX = 0;
    var carousel = document.querySelector('.hero-carousel');
    if (carousel) {
        carousel.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        carousel.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, { passive: true });
    }

    function handleSwipe() {
        var diff = touchStartX - touchEndX;
        if (Math.abs(diff) > 50) {
            if (diff > 0) {
                nextSlide();
            } else {
                prevSlide();
            }
            resetTimer();
        }
    }

    resetTimer();
})();
</script>

<?php if (!empty($continue_items)): ?>
<section class="content-section reveal">
    <h2>&#9199; <?php echo t('continue'); ?> <span class="sec-sub">(<?php echo e($category['name']); ?>)</span></h2>
    <div class="row-wrap">
        <div class="row-scroll">
            <?php foreach ($continue_items as $item):
                $pct = $item['duration_seconds'] > 0 ? min(100, round($item['position_seconds'] / $item['duration_seconds'] * 100)) : 0;
                $cur_ep = null;
                if (!empty($item['episode_id'])) {
                    try {
                        $ep_stmt = $pdo->prepare("SELECT episode_number FROM episodes WHERE id = ?");
                        $ep_stmt->execute([(int)$item['episode_id']]);
                        $ep_row = $ep_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($ep_row) $cur_ep = (int)$ep_row['episode_number'];
                    } catch (PDOException $e) {}
                }
                echo render_card($item, [
                    'is_favorite' => isset($favs[$item['id']]),
                    'episode_id' => !empty($item['episode_id']) ? (int)$item['episode_id'] : null,
                    'progress' => (int)$pct,
                    'is_continue' => true,
                    'current_episode' => $cur_ep,
                    'total_episodes' => $item['total_episodes'] ?? null,
                    'aired_episodes' => $ep_ranges[$item['id']]['last_ep'] ?? null,
                    'last_ep_created_at' => $ep_ranges[$item['id']]['last_ep_created_at'] ?? null,
                ]);
            endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($new_items)): ?>
<section class="content-section reveal">
    <h2>&#128195; <?php echo t('newest'); ?></h2>
    <div class="row-wrap">
        <div class="row-scroll">
            <?php foreach ($new_items as $item): ?>
            <?php echo render_card($item, ['is_favorite' => isset($favs[$item['id']]), 'total_episodes' => $item['total_episodes'] ?? null, 'aired_episodes' => $ep_ranges[$item['id']]['last_ep'] ?? null, 'last_ep_created_at' => $ep_ranges[$item['id']]['last_ep_created_at'] ?? null]); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($top_items)): ?>
<section class="content-section reveal">
    <h2>&#127942; <?php echo t('top_rated'); ?></h2>
    <div class="row-wrap">
        <div class="row-scroll">
            <?php foreach ($top_items as $item): ?>
            <?php echo render_card($item, ['is_favorite' => isset($favs[$item['id']]), 'total_episodes' => $item['total_episodes'] ?? null, 'aired_episodes' => $ep_ranges[$item['id']]['last_ep'] ?? null, 'last_ep_created_at' => $ep_ranges[$item['id']]['last_ep_created_at'] ?? null]); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($cat_genres)): ?>
<div class="site-genre-chips reveal">
    <span class="sgc-label">&#127925; Janrlar:</span>
    <?php foreach ($cat_genres as $g): ?>
    <a class="sgc-chip" href="<?php echo ROOT_URL; ?>/genres.php?genre=<?php echo e($g['slug']); ?>&amp;cat=<?php echo e($slug); ?>">
        <span class="sgc-dot" style="background:<?php echo e($g['color'] ?: '#2196f3'); ?>;"></span>
        <?php echo e($g['name']); ?>
        <span class="sgc-cnt"><?php echo (int)$g['cnt']; ?></span>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="content-section" style="margin-top: 34px;">
    <h2><?php echo $site_info_row['icon']; ?> <?php echo e($category['name']); ?> katalogi — <?php echo count($items); ?> ta</h2>
</div>

<div class="grid-wrap">
    <?php foreach ($items as $item):
        $id = $item['id'];
        $er = $ep_ranges[$id] ?? null;
        echo render_card($item, [
            'show_ep'       => true,
            'first_ep'      => $er['first_ep'] ?? null,
            'last_ep'       => $er['last_ep'] ?? null,
            'total_episodes'=> $er['ep_count'] ?? null,
            'aired_episodes'=> $er['last_ep'] ?? null,
            'last_ep_created_at' => $er['last_ep_created_at'] ?? null,
            'is_favorite'   => isset($favs[$id]),
        ]);
    endforeach; ?>
    <?php if (empty($items)): ?>
        <p><?php echo t('no_content_in_section'); ?></p>
    <?php endif; ?>
</div>

<script>
/* Scroll-reveal */
(function() {
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) return;
    var items = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window)) {
        items.forEach(function(el) { el.classList.add('in-view'); });
        return;
    }
    var io = new IntersectionObserver(function(entries) {
        entries.forEach(function(en) {
            if (en.isIntersecting) {
                en.target.classList.add('in-view');
                io.unobserve(en.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    items.forEach(function(el) { io.observe(el); });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>