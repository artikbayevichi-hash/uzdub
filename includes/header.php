<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? e($page_title) . ' - UZDUB' : 'UZDUB - Kino, Anime, Multfilm'; ?></title>
<link rel="stylesheet" href="/uzdub/css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="/uzdub/js/3d-loader.js"></script>
<script src="/uzdub/js/3d-effects.js"></script>
<script src="/uzdub/js/3d-cards.js"></script>
<script src="/uzdub/js/3d-hero.js"></script>
<script src="/uzdub/js/3d-animations.js"></script>
</head>
<body>
<div class="floating-orb"></div>
<div class="floating-orb"></div>
<div class="floating-orb"></div>
<canvas id="stars-canvas"></canvas>

<header class="site-header">
    <a href="/uzdub/index.php" class="logo">UZDUB</a>
    <button class="nav-toggle" id="navToggle" aria-label="Menyu">&#9776;</button>
    <ul class="nav-links" id="navLinks">
        <li><a href="/uzdub/index.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>"><?php echo t('home'); ?></a></li>
        <li><a href="/uzdub/category.php?slug=kino"><?php echo t('movies'); ?></a></li>
        <li><a href="/uzdub/category.php?slug=anime"><?php echo t('anime'); ?></a></li>
        <li><a href="/uzdub/category.php?slug=multfilm"><?php echo t('cartoons'); ?></a></li>
        <li><a href="/uzdub/global_chat.php"><?php echo t('chat'); ?></a></li>
        <?php if (is_user()): ?>
        <li><a href="/uzdub/inbox.php"><?php echo t('messages'); ?></a></li>
        <li><a href="/uzdub/mylist.php"><?php echo t('my_list'); ?></a></li>
        <li><a href="/uzdub/premium.php" style="color:#f9a825;">⭐ <?php echo t('premium'); ?></a></li>
        <?php endif; ?>
    </ul>
    <div class="header-right">
        <div class="lang-switcher">
            <button type="button" class="lang-current" onclick="document.getElementById('langMenu').classList.toggle('active')">
                <?php echo strtoupper(current_lang()); ?> ▾
            </button>
            <div class="lang-menu" id="langMenu">
                <a href="?lang=uz<?php echo isset($_GET['q']) ? '&q='.urlencode($_GET['q']) : ''; ?>" class="<?php echo current_lang()=='uz'?'active':''; ?>">🇺🇿 O'zbek</a>
                <a href="?lang=ru<?php echo isset($_GET['q']) ? '&q='.urlencode($_GET['q']) : ''; ?>" class="<?php echo current_lang()=='ru'?'active':''; ?>">🇷🇺 Русский</a>
                <a href="?lang=en<?php echo isset($_GET['q']) ? '&q='.urlencode($_GET['q']) : ''; ?>" class="<?php echo current_lang()=='en'?'active':''; ?>">🇬🇧 English</a>
            </div>
        </div>
        <form action="/uzdub/search.php" method="get" class="search-box">
            <input type="text" name="q" placeholder="<?php echo t('search_placeholder'); ?>" value="<?php echo e($_GET['q'] ?? ''); ?>">
            <button type="submit">&#128269;</button>
        </form>
        <?php if (is_user()): $u = current_user(); ?>
        <a href="/uzdub/profile.php?uid=<?php echo e($u['user_id']); ?>" style="display:flex;align-items:center;gap:6px;text-decoration:none;color:var(--text-light);">
            <img src="<?php echo avatar_url($u['avatar']); ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid var(--blue-primary);" alt="">
            <?php if ($u['is_premium']): ?><span style="font-size:12px;">⭐</span><?php endif; ?>
        </a>
        <?php else: ?>
        <a href="/uzdub/auth/login.php" style="padding:8px 16px;background:var(--blue-primary);color:#fff;border-radius:20px;text-decoration:none;font-size:13px;white-space:nowrap;"><?php echo t('login'); ?></a>
        <?php endif; ?>
    </div>
</header>

<?php $__cur_page = basename($_SERVER['PHP_SELF']); ?>
<nav class="bottom-nav" aria-label="Asosiy navigatsiya">
    <a href="/uzdub/index.php" class="<?php echo $__cur_page=='index.php' ? 'active' : ''; ?>">
        <span class="bn-icon">🏠</span><span class="bn-label"><?php echo t('home'); ?></span>
    </a>
    <a href="/uzdub/category.php?slug=kino" class="<?php echo ($__cur_page=='category.php' && ($_GET['slug'] ?? '')=='kino') ? 'active' : ''; ?>">
        <span class="bn-icon">🎬</span><span class="bn-label"><?php echo t('movies'); ?></span>
    </a>
    <a href="/uzdub/category.php?slug=anime" class="<?php echo ($__cur_page=='category.php' && ($_GET['slug'] ?? '')=='anime') ? 'active' : ''; ?>">
        <span class="bn-icon">🎌</span><span class="bn-label"><?php echo t('anime'); ?></span>
    </a>
    <a href="/uzdub/category.php?slug=multfilm" class="<?php echo ($__cur_page=='category.php' && ($_GET['slug'] ?? '')=='multfilm') ? 'active' : ''; ?>">
        <span class="bn-icon">🧸</span><span class="bn-label"><?php echo t('cartoons'); ?></span>
    </a>
    <?php if (is_user()): $__u = current_user(); ?>
    <a href="/uzdub/profile.php?uid=<?php echo e($__u['user_id']); ?>" class="<?php echo $__cur_page=='profile.php' ? 'active' : ''; ?>">
        <span class="bn-icon">👤</span><span class="bn-label"><?php echo t('profile'); ?></span>
    </a>
    <?php else: ?>
    <a href="/uzdub/auth/login.php" class="<?php echo $__cur_page=='login.php' ? 'active' : ''; ?>">
        <span class="bn-icon">👤</span><span class="bn-label"><?php echo t('login'); ?></span>
    </a>
    <?php endif; ?>
</nav>

<script>
document.addEventListener('click', function(e) {
    var menu = document.getElementById('langMenu');
    var btn = document.querySelector('.lang-current');
    if (menu && !menu.contains(e.target) && e.target !== btn) menu.classList.remove('active');
});
document.getElementById('navToggle').addEventListener('click', function() {
    document.getElementById('navLinks').classList.toggle('nav-open');
});
</script>
