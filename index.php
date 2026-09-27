<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = t('home') . ' — Kino · Anime · Multfilm';

// Uchala sub-sayt statistikasi (har biridagi kontent soni)
$cat_stats = [];
try {
    $st = $pdo->query("SELECT c.category_id, cat.slug AS cat_slug, cat.name AS cat_name, COUNT(*) AS cnt FROM content c JOIN categories cat ON c.category_id = cat.id GROUP BY c.category_id ORDER BY cat.id");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) $cat_stats[$r['cat_slug']] = (int)$r['cnt'];
} catch (PDOException $e) { $cat_stats = []; }

include __DIR__ . '/includes/header.php';
?>

<div class="portal-wrap">
    <div class="portal-hero reveal">
        <div class="portal-brand">🎬 UZDUB PLATFORM</div>
        <h1>Bitta platforma — uchta sayt</h1>
        <p>Kino, Anime va Multfilm — har biri o'z mustaqil katalogi bilan. O'zingizga keraklisini tanlang va shu saytga kiring.</p>
    </div>

    <div class="portal-grid">
        <?php
        $portal_cards = [
            [
                'slug' => 'kino',
                'icon' => '🎬',
                'label' => t('movies'),
                'grad' => '--pc1:#0a1f44;--pc2:#1565c0;--pc3:#2196f3;',
                'desc' => "Eng so'nggi filmlar, jahon premyeralari va sevimli janrlardagi kinolar — faqat Kino saytida.",
            ],
            [
                'slug' => 'anime',
                'icon' => '🎌',
                'label' => t('anime'),
                'grad' => '--pc1:#2a0f3a;--pc2:#7b1fa2;--pc3:#ce93d8;',
                'desc' => "Yapon animatsiyasi: yangi sessonlar, mashhur seriyalar va abadiy klassikalar — faqat Anime saytida.",
            ],
            [
                'slug' => 'multfilm',
                'icon' => '🧸',
                'label' => t('cartoons'),
                'grad' => '--pc1:#3a1d00;--pc2:#e65100;--pc3:#ffb300;',
                'desc' => "Bolalar uchun xavfsiz multfilmlar va oilaviy animatsion filmlar — faqat Multfilm saytida.",
            ],
        ];
        $total_content = array_sum($cat_stats);
        foreach ($portal_cards as $pc):
        ?>
        <a class="portal-card portal-<?php echo e($pc['slug']); ?>" href="<?php echo ROOT_URL; ?>/<?php echo e($pc['slug']); ?>" style="<?php echo $pc['grad']; ?>">
            <span class="portal-emoji"><?php echo $pc['icon']; ?></span>
            <span class="portal-name"><?php echo e($pc['label']); ?></span>
            <span class="portal-desc"><?php echo $pc['desc']; ?></span>
            <span class="portal-foot">
                <span class="portal-count"><?php echo isset($cat_stats[$pc['slug']]) ? (int)$cat_stats[$pc['slug']] . ' ta kontent' : 'Katalog'; ?></span>
                <span class="portal-go">Kirish &#8594;</span>
            </span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($total_content > 0): ?>
    <div class="portal-note reveal">
        <p>Jami <strong><?php echo $total_content; ?></strong> ta kontent uchala saytda alohida ajratilgan — <a href="<?php echo ROOT_URL; ?>/genres.php">Janrlar bo'yicha ko'rish</a> ham doim ochiq.</p>
    </div>
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