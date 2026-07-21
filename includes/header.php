<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? e($page_title) . ' - UZDUB' : 'UZDUB - Kino, Anime, Multfilm'; ?></title>
<link rel="stylesheet" href="/uzdub/css/style.css">
<link rel="stylesheet" href="/uzdub/css/skeleton.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="/uzdub/js/3d-loader.js"></script>
<script src="/uzdub/js/3d-effects.js"></script>
<script src="/uzdub/js/3d-cards.js"></script>
<script src="/uzdub/js/3d-hero.js"></script>
<script src="/uzdub/js/3d-animations.js"></script>
<script src="/uzdub/js/mini-player.js" defer></script>
<style>
    .pip-button {
        position: absolute;
        bottom: 20px;
        right: 60px;
        background: rgba(0,0,0,0.6);
        border: none;
        color: white;
        padding: 8px;
        border-radius: 50%;
        cursor: pointer;
        z-index: 10;
    }
</style>
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
        <form action="/uzdub/search.php" method="get" class="search-box" id="searchForm" autocomplete="off" style="position:relative;">
            <input type="text" name="q" id="searchInput" placeholder="<?php echo t('search_placeholder'); ?>" value="<?php echo e($_GET['q'] ?? ''); ?>" data-autocomplete="1">
            <button type="submit">&#128269;</button>
            <div class="search-suggestions" id="searchSuggestions"></div>
        </form>

<style>
.search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--card-bg, #121a2b);
    border: 1px solid rgba(33,150,243,0.3);
    border-top: none;
    border-radius: 0 0 12px 12px;
    box-shadow: 0 12px 36px rgba(0,0,0,0.4);
    z-index: 1000;
    display: none;
    overflow: hidden;
}
.search-suggestions.active { display: block; }
.search-suggestion-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    cursor: pointer;
    transition: background 0.15s;
    text-decoration: none;
    color: var(--text-light, #e8eef5);
}
.search-suggestion-item:hover { background: rgba(33,150,243,0.12); }
.search-suggestion-item img {
    width: 28px;
    height: 40px;
    object-fit: cover;
    border-radius: 4px;
    background: #1a2438;
}
.search-suggestion-item .sug-info { flex: 1; min-width: 0; }
.search-suggestion-item .sug-title {
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.search-suggestion-item .sug-meta { font-size: 11px; color: var(--text-muted, #9aa8bd); }
.search-suggestion-item .sug-meta .sug-badge {
    background: var(--blue-deep, #0d47a1);
    color: #fff;
    font-size: 9px;
    padding: 1px 6px;
    border-radius: 8px;
    margin-left: 4px;
}
.search-suggestion-nores {
    padding: 16px 14px;
    text-align: center;
    color: var(--text-muted, #9aa8bd);
    font-size: 13px;
}
</style>

<script>
(function() {
    var input = document.getElementById('searchInput');
    var suggestions = document.getElementById('searchSuggestions');
    if (!input || !suggestions) return;

    var timer = null;
    var selectedIndex = -1;

    function closeSuggestions() {
        suggestions.classList.remove('active');
        selectedIndex = -1;
    }

    input.addEventListener('input', function() {
        var val = this.value.trim();
        if (val.length < 2) { closeSuggestions(); return; }

        if (timer) clearTimeout(timer);
        timer = setTimeout(function() {
            fetch('/uzdub/search.php?ajax_autocomplete=1&q=' + encodeURIComponent(val))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    suggestions.innerHTML = '';
                    if (!data || data.length === 0) {
                        suggestions.innerHTML = '<div class="search-suggestion-nores">Natija topilmadi</div>';
                    } else {
                        data.forEach(function(item) {
                            var a = document.createElement('a');
                            a.className = 'search-suggestion-item';
                            a.href = '/uzdub/watch.php?id=' + item.id;
                            var poster = item.poster ? '/uzdub/uploads/posters/' + item.poster : 'https://via.placeholder.com/28x40/121a2b/2196f3?text=' + encodeURIComponent(item.title.slice(0,1));
                            a.innerHTML = '<img src="' + poster + '" alt="" loading="lazy">' +
                                '<div class="sug-info">' +
                                    '<div class="sug-title">' + escHtml(item.title) + '</div>' +
                                    '<div class="sug-meta">' + (item.release_year || '') + ' <span class="sug-badge">' + (item.content_code || '') + '</span></div>' +
                                '</div>';
                            suggestions.appendChild(a);
                        });
                    }
                    suggestions.classList.add('active');
                })
                .catch(function() { closeSuggestions(); });
        }, 250);
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeSuggestions(); return; }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            var items = suggestions.querySelectorAll('.search-suggestion-item');
            if (items.length === 0) return;
            if (e.key === 'ArrowDown') {
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
            } else {
                selectedIndex = Math.max(selectedIndex - 1, 0);
            }
            items.forEach(function(el, i) {
                el.style.background = i === selectedIndex ? 'rgba(33,150,243,0.2)' : '';
            });
            if (items[selectedIndex]) {
                items[selectedIndex].scrollIntoView({ block: 'nearest' });
            }
        }
        if (e.key === 'Enter' && selectedIndex >= 0) {
            e.preventDefault();
            var items = suggestions.querySelectorAll('.search-suggestion-item');
            if (items[selectedIndex]) window.location.href = items[selectedIndex].href;
        }
    });

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !suggestions.contains(e.target)) {
            closeSuggestions();
        }
    });

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }
})();
</script>
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
