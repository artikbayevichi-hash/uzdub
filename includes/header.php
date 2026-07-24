<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? e($page_title) . ' - UZDUB PLATFORM' : t('site_title'); ?></title>
<link rel="stylesheet" href="/uzdub/css/style.css">
<link rel="stylesheet" href="/uzdub/css/skeleton.css">
<link rel="stylesheet" href="/uzdub/css/splash.css">
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
        <li class="random-dropdown">
            <button type="button" class="random-btn" onclick="this.parentElement.classList.toggle('open')">🎲 <?php echo t('random'); ?> ▾</button>
            <div class="random-menu">
                <a href="/uzdub/random.php?slug=kino">🎬 <?php echo t('random_kino'); ?></a>
                <a href="/uzdub/random.php?slug=anime">🎭 <?php echo t('random_anime'); ?></a>
                <a href="/uzdub/random.php?slug=multfilm">🎪 <?php echo t('random_multfilm'); ?></a>
                <a href="/uzdub/random.php">🎲 <?php echo t('random_all'); ?></a>
            </div>
        </li>
        <li><a href="/uzdub/global_chat.php"><?php echo t('chat'); ?></a></li>
        <?php if (is_user()): ?>
        <li><a href="/uzdub/inbox.php"><?php echo t('messages'); ?></a></li>
        <li><a href="/uzdub/premium.php" style="color:#f9a825;">⭐ <?php echo t('premium'); ?></a></li>
        <?php endif; ?>
    </ul>
    <div class="header-right">
        <div class="lang-switcher">
            <button type="button" class="lang-current" onclick="document.getElementById('langMenu').classList.toggle('active')">
                <?php echo strtoupper(current_lang()); ?> ▾
            </button>
            <div class="lang-menu" id="langMenu">
                <?php $lang_params = $_GET; unset($lang_params['lang']); $lang_qs = $lang_params ? '&' . http_build_query($lang_params) : ''; ?>
                <a href="?lang=uz<?php echo $lang_qs; ?>" class="<?php echo current_lang()=='uz'?'active':''; ?>">🇺🇿 O'zbek</a>
                <a href="?lang=ru<?php echo $lang_qs; ?>" class="<?php echo current_lang()=='ru'?'active':''; ?>">🇷🇺 Русский</a>
                <a href="?lang=en<?php echo $lang_qs; ?>" class="<?php echo current_lang()=='en'?'active':''; ?>">🇬🇧 English</a>
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
                        suggestions.innerHTML = '<div class="search-suggestion-nores"><?php echo t('search_no_results'); ?></div>';
                    } else {
                        data.forEach(function(item) {
                            var a = document.createElement('a');
                            a.className = 'search-suggestion-item';
                            a.href = '/uzdub/watch.php?id=' + item.id;
                            var lang = '<?php echo current_lang(); ?>';
                            var displayTitle = (lang === 'ru' && item.title_ru) ? item.title_ru : (lang === 'en' && item.title_en) ? item.title_en : item.title;
                            var poster = item.poster ? '/uzdub/uploads/posters/' + item.poster : 'https://via.placeholder.com/28x40/121a2b/2196f3?text=' + encodeURIComponent(displayTitle.slice(0,1));
                            a.innerHTML = '<img src="' + poster + '" alt="" loading="lazy">' +
                                '<div class="sug-info">' +
                                    '<div class="sug-title">' + escHtml(displayTitle) + '</div>' +
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
        <a href="/uzdub/profile.php?uid=<?php echo e($u['user_id']); ?>" class="header-avatar-link">
            <img src="<?php echo avatar_url($u['avatar']); ?>" class="header-avatar-img" alt="">
            <?php if ($u['is_premium']): ?><span class="header-premium-badge">⭐</span><?php endif; ?>
        </a>
        <div class="acc-switcher-wrap">
            <button class="acc-switcher-btn" id="accSwitcherBtn" title="<?php echo t('accounts'); ?>">⋮</button>
            <div class="acc-switcher-dropdown" id="accSwitcherDropdown">
                <div class="acc-dropdown-current">
                    <img src="<?php echo avatar_url($u['avatar']); ?>" class="acc-dd-avatar" alt="">
                    <div class="acc-dd-info">
                        <span class="acc-dd-name"><?php echo e($u['username']); ?></span>
                        <?php if ($u['is_premium']): ?><span class="acc-dd-premium">⭐ Premium</span><?php endif; ?>
                        <span class="acc-dd-id">ID: <?php echo e($u['user_id']); ?></span>
                    </div>
                </div>
                <div class="acc-dropdown-divider"></div>
                <div class="acc-dropdown-list" id="accDropdownList"></div>
                <a href="/uzdub/auth/login.php?new=1" class="acc-dropdown-add">
                    <span class="acc-add-icon">+</span>
                    <?php echo t('add_account'); ?>
                </a>
            </div>
        </div>
        <?php else: ?>
        <a href="/uzdub/auth/login.php" class="header-login-btn"><?php echo t('login'); ?></a>
        <?php endif; ?>
    </div>
</header>

<?php $__cur_page = basename($_SERVER['PHP_SELF']); ?>
<nav class="bottom-nav" aria-label="<?php echo t('main_nav'); ?>">
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

// Akkaunt switcher
(function() {
    var btn = document.getElementById('accSwitcherBtn');
    var dd = document.getElementById('accSwitcherDropdown');
    var list = document.getElementById('accDropdownList');
    if (!btn || !dd || !list) return;

    var currentUserId = <?php echo is_user() ? json_encode(current_user()['user_id']) : 'null'; ?>;

    function getAccounts() {
        try { return JSON.parse(localStorage.getItem('uzdub_accounts')) || []; } catch(e) { return []; }
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    function renderAccounts() {
        var accounts = getAccounts();
        list.innerHTML = '';
        accounts.forEach(function(acc) {
            if (String(acc.user_id) === String(currentUserId)) return;
            var item = document.createElement('a');
            item.className = 'acc-dd-item';
            item.href = '/uzdub/auth/switch.php?uid=' + encodeURIComponent(acc.user_id) + '&token=' + encodeURIComponent(acc.switch_token || '');
            var avatarSrc = escHtml(acc.avatar || '/uzdub/uploads/avatars/default.png');
            var displayName = escHtml(acc.username || '');
            item.innerHTML = '<img src="' + avatarSrc + '" class="acc-dd-item-avatar" alt="">' +
                '<div class="acc-dd-item-info">' +
                    '<span class="acc-dd-item-name">' + displayName + '</span>' +
                    (acc.is_premium ? '<span class="acc-dd-item-premium">⭐ Premium</span>' : '') +
                '</div>';
            list.appendChild(item);
        });
    }

    renderAccounts();

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        dd.classList.toggle('open');
        renderAccounts();
    });

    document.addEventListener('click', function(e) {
        if (!dd.contains(e.target) && e.target !== btn) dd.classList.remove('open');
    });
})();

// Tasodifiy dropdown
(function() {
    var rd = document.querySelector('.random-dropdown');
    if (!rd) return;
    document.addEventListener('click', function(e) {
        if (!rd.contains(e.target)) rd.classList.remove('open');
    });
})();
</script>
