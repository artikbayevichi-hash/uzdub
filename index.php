<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Bosh sahifa';

// Hero uchun eng so'nggi 5 ta kontent (aylanuvchi banner)
$hero_items = $pdo->query("SELECT c.*, cat.name as cat_name FROM content c JOIN categories cat ON c.category_id=cat.id ORDER BY c.created_at DESC LIMIT 5")->fetchAll();

// Faqat Kino, Anime, Multfilm (Serial olib tashlandi)
$categories = $pdo->query("SELECT * FROM categories WHERE slug != 'serial' ORDER BY id")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php if (!empty($hero_items)): ?>
<section class="hero-carousel">
    <?php foreach ($hero_items as $i => $hero): ?>
    <div class="hero-slide <?php echo $i === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo $hero['poster'] ? 'uploads/posters/' . e($hero['poster']) : 'https://via.placeholder.com/1400x800/0a0e17/2196f3?text=UZDUB'; ?>');">
        <div class="hero-content">
            <div class="hero-tags">
                <span class="hero-tag"><?php echo e($hero['cat_name']); ?></span>
                
            </div>
            <h1><?php echo e($hero['title']); ?></h1>
            <div class="hero-meta">
                <span>&#9733; <?php echo e($hero['rating']); ?></span>
                <span>&middot;</span>
                <span>&#128197; <?php echo e($hero['release_year']); ?></span>
                <span>&middot;</span>
                <span><?php echo e($hero['content_code'] ?? ''); ?></span>
            </div>
            <p><?php echo e(mb_strimwidth($hero['description'] ?? '', 0, 200, '...')); ?></p>
            <div>
                <a href="watch.php?id=<?php echo $hero['id']; ?>" class="btn btn-primary">&#9654; Tomosha qilish</a>
                <a href="watch.php?id=<?php echo $hero['id']; ?>" class="btn btn-outline">&#8505; Batafsil</a>
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
<?php endif; ?>

<?php foreach ($categories as $cat):
    $stmt = $pdo->prepare("SELECT * FROM content WHERE category_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$cat['id']]);
    $items = $stmt->fetchAll();
    if (empty($items)) continue;
?>
<section class="content-section">
    <h2><?php echo e($cat['name']); ?></h2>
    <div class="row-wrap">
        <div class="row-scroll">
            <?php foreach ($items as $item): ?>
            <a href="watch.php?id=<?php echo $item['id']; ?>" class="card">
                <img src="<?php echo $item['poster'] ? 'uploads/posters/' . e($item['poster']) : 'https://via.placeholder.com/300x420/121a2b/2196f3?text=' . urlencode($item['title']); ?>" alt="<?php echo e($item['title']); ?>">
                <div class="card-info">
                    <h3><?php echo e($item['title']); ?></h3>
                    <div class="meta">
                        <span><?php echo e($item['release_year']); ?></span>
                        <span class="badge">&#9733; <?php echo e($item['rating']); ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endforeach; ?>

<?php if (empty($categories)): ?>
<div class="content-section"><p>Hozircha kontent qo'shilmagan. Admin paneldan qo'shing.</p></div>
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
    function resetTimer() { clearInterval(timer); timer = setInterval(nextSlide, 5000); }

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

<?php include __DIR__ . '/includes/footer.php'; ?>
