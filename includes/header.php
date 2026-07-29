<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? e($page_title) . ' - UZDUB PLATFORM' : t('site_title'); ?></title>
<script>if(localStorage.getItem('uzdub_splash_seen')!=='1'){window.location.replace('/uzdub/splash.php');}</script>
<link rel="stylesheet" href="/uzdub/css/style.css">
<script src="/uzdub/js/online-tracker.js" defer></script>
</head>
<body>
<script>window.UZDUB_IS_LOGGED_IN = <?php echo is_user() ? 'true' : 'false'; ?>;</script>
<script>
(function(){
    if(window.UZDUB_IS_LOGGED_IN) return;
    try {
        var acc = JSON.parse(localStorage.getItem('uzdub_current_account'));
        if(acc && acc.user_id && acc.switch_token) {
            var redirect = encodeURIComponent(window.location.pathname + window.location.search);
            window.location.replace('/uzdub/auth/switch.php?uid=' + encodeURIComponent(acc.user_id) + '&token=' + encodeURIComponent(acc.switch_token) + '&redirect=' + redirect);
        }
    } catch(e) {}
})();
</script>

<div class="ambient-dust">
    <span></span><span></span><span></span><span></span>
    <span></span><span></span><span></span><span></span>
    <span></span><span></span><span></span><span></span>
    <span></span><span></span><span></span><span></span>
    <span></span><span></span><span></span><span></span>
    <span></span><span></span><span></span><span></span>
</div>

<?php
// Register session in user_sessions if not yet tracked
if (is_user() && empty($_SESSION['session_db_id']) && !empty($pdo)) {
    record_user_session($pdo, $_SESSION['user_id']);
}
?>

<header class="site-header">
    <a href="/uzdub/index.php" class="logo">UZDUB<span class="logo-sub"><span class="ls-char" style="transition-delay:0.5s">P</span><span class="ls-char" style="transition-delay:0.58s">L</span><span class="ls-char" style="transition-delay:0.66s">A</span><span class="ls-char" style="transition-delay:0.74s">T</span><span class="ls-char" style="transition-delay:0.82s">F</span><span class="ls-char" style="transition-delay:0.9s">O</span><span class="ls-char" style="transition-delay:0.98s">R</span><span class="ls-char" style="transition-delay:1.06s">M</span></span></a>
    <button class="nav-toggle" id="navToggle" aria-label="Menyu">&#9776;</button>
    <div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
    <ul class="nav-links" id="navLinks">
        <li class="drawer-header">
            <span class="drawer-title">☰ Menyu</span>
            <button class="drawer-close" onclick="closeDrawer()">&times;</button>
        </li>
        <li class="mobile-hide"><a href="/uzdub/index.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>"><?php echo t('home'); ?></a></li>
        <li class="mobile-hide"><a href="/uzdub/category.php?slug=kino"><?php echo t('movies'); ?></a></li>
        <li class="mobile-hide"><a href="/uzdub/category.php?slug=anime"><?php echo t('anime'); ?></a></li>
        <li class="mobile-hide"><a href="/uzdub/category.php?slug=multfilm"><?php echo t('cartoons'); ?></a></li>
        <li class="random-dropdown">
            <button type="button" class="random-btn" onclick="this.parentElement.classList.toggle('open')">🎲 <?php echo t('random'); ?> ▾</button>
            <div class="random-menu">
                <a href="/uzdub/random.php?slug=kino">🎬 <?php echo t('random_kino'); ?></a>
                <a href="/uzdub/random.php?slug=anime">🎭 <?php echo t('random_anime'); ?></a>
                <a href="/uzdub/random.php?slug=multfilm">🎪 <?php echo t('random_multfilm'); ?></a>
                <a href="/uzdub/random.php">🎲 <?php echo t('random_all'); ?></a>
            </div>
        </li>
        <?php
        $genre_nav_stmt = $pdo->query("
            SELECT cat.id as cat_id, cat.name as cat_name, cat.slug as cat_slug,
                   g.name as genre_name, g.slug as genre_slug, g.color,
                   COUNT(cg.content_id) as cnt
            FROM categories cat
            JOIN content c ON c.category_id = cat.id
            JOIN content_genres cg ON c.id = cg.content_id
            JOIN genres g ON cg.genre_id = g.id
            WHERE cat.slug IN ('kino','anime','multfilm')
            GROUP BY cat.id, g.id
            ORDER BY cat.name, g.name
        ");
        $genre_nav_data = [];
        while ($gnr = $genre_nav_stmt->fetch()) {
            $genre_nav_data[$gnr['cat_slug']][] = $gnr;
        }
        $cat_icons_nav = ['kino' => '🎬', 'anime' => '🎌', 'multfilm' => '🎞️'];
        ?>
        <li class="genre-dropdown" id="genreDropdown">
            <button type="button" class="random-btn" onclick="this.parentElement.classList.toggle('open')">🎵 <?php echo t('genres'); ?> ▾</button>
            <div class="genre-menu">
                <div class="genre-menu-head">
                    <a href="/uzdub/genres.php" class="genre-menu-all">📋 <?php echo t('all_genres'); ?></a>
                </div>
                <?php foreach (['kino', 'anime', 'multfilm'] as $gs): ?>
                <div class="genre-menu-cat">
                    <span class="genre-menu-cat-icon"><?php echo $cat_icons_nav[$gs]; ?></span>
                    <span class="genre-menu-cat-name"><?php echo t($gs === 'kino' ? 'movies' : ($gs === 'anime' ? 'anime' : 'cartoons')); ?></span>
                    <span class="genre-menu-arrow">›</span>
                    <div class="genre-submenu">
                        <?php if (!empty($genre_nav_data[$gs])): ?>
                        <?php foreach ($genre_nav_data[$gs] as $gn): ?>
                        <a href="/uzdub/genres.php?genre=<?php echo e($gn['genre_slug']); ?>" class="genre-sub-link">
                            <span class="gs-dot" style="background:<?php echo e($gn['color'] ?: '#2196f3'); ?>;"></span>
                            <?php echo e($gn['genre_name']); ?>
                            <span class="gs-count"><?php echo $gn['cnt']; ?></span>
                        </a>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <span class="genre-sub-empty"><?php echo t('no_content_genre'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </li>
        <li class="random-dropdown">
            <button type="button" class="random-btn" onclick="this.parentElement.classList.toggle('open')">💬 <?php echo t('chat'); ?> <span id="onlineCountHeader" class="online-badge-header">🟢 0</span> ▾</button>
            <div class="random-menu">
                <a href="/uzdub/global_chat.php?cat=kino">🎬 <?php echo t('chat_kino'); ?></a>
                <a href="/uzdub/global_chat.php?cat=anime">🎌 <?php echo t('chat_anime'); ?></a>
                <a href="/uzdub/global_chat.php?cat=multfilm">🎞️ <?php echo t('chat_multfilm'); ?></a>
            </div>
        </li>
        <?php if (is_user()): ?>
        <li><a href="/uzdub/inbox.php"><?php echo t('messages'); ?></a></li>
        <li><a href="/uzdub/premium.php" style="color:#f9a825;">⭐ <?php echo t('premium'); ?></a></li>
        <?php endif; ?>
        <li class="mobile-only-items">
            <div>
                <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;padding:8px 14px 4px;display:block;">🌐 <?php echo t('language'); ?></span>
                <a href="?lang=uz">🇺🇿 O'zbek <?php echo current_lang()==='uz'?' ✓':''; ?></a>
                <a href="?lang=ru">🇷🇺 Русский <?php echo current_lang()==='ru'?' ✓':''; ?></a>
                <a href="?lang=en">🇬🇧 English <?php echo current_lang()==='en'?' ✓':''; ?></a>
            </div>
        </li>
    </ul>
    <div class="header-right">
        <?php if (is_user()): ?>
        <div class="notif-bell-wrap" id="notifBellWrap">
            <button class="notif-bell-btn" id="notifBellBtn" title="<?php echo t('notifications'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span class="notif-badge" id="notifBadge"></span>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-dd-header">
                    <span class="notif-dd-title"><?php echo t('notifications'); ?></span>
                    <button class="notif-dd-mark-all" id="notifMarkAll"><?php echo t('mark_all_read'); ?></button>
                </div>
                <div class="notif-dd-list" id="notifList">
                    <div class="notif-dd-loading"><div class="notif-dd-spinner"></div></div>
                </div>
                <div class="notif-dd-footer" id="notifFooter" style="display:none;">
                    <button class="notif-dd-load-more" id="notifLoadMore"><?php echo t('load_more'); ?></button>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
                    <span class="acc-dd-check">✓</span>
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
    var nl = document.getElementById('navLinks');
    var ov = document.getElementById('drawerOverlay');
    nl.classList.toggle('nav-open');
    var isOpen = nl.classList.contains('nav-open');
    if(ov) ov.classList.toggle('open', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
});
function closeDrawer() {
    var nl = document.getElementById('navLinks');
    var ov = document.getElementById('drawerOverlay');
    if(nl) nl.classList.remove('nav-open');
    if(ov) ov.classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape') closeDrawer();
});
document.getElementById('navLinks').addEventListener('click', function(e) {
    if(e.target.tagName === 'A') closeDrawer();
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
    var gd = document.getElementById('genreDropdown');
    document.addEventListener('click', function(e) {
        if (rd && !rd.contains(e.target)) rd.classList.remove('open');
        if (gd && !gd.contains(e.target)) gd.classList.remove('open');
    });
})();

// Notification Bell
(function() {
    var bellBtn = document.getElementById('notifBellBtn');
    var dropdown = document.getElementById('notifDropdown');
    var badge = document.getElementById('notifBadge');
    var list = document.getElementById('notifList');
    var markAll = document.getElementById('notifMarkAll');
    var loadMore = document.getElementById('notifLoadMore');
    var footer = document.getElementById('notifFooter');
    if (!bellBtn || !dropdown) return;

    var csrf = <?php echo json_encode(csrf_token()); ?>;
    var notifPage = 1;
    var notifTotal = 0;
    var pollTimer = null;

    function escHtml(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }

    function updateBadge(count) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.add('active');
        } else {
            badge.classList.remove('active');
        }
    }

    function fetchUnreadCount() {
        fetch('/uzdub/api/notifications.php?action=unread_count')
            .then(function(r) { return r.json(); })
            .then(function(d) { updateBadge(d.count || 0); })
            .catch(function() {});
    }

    function renderNotifItem(n) {
        var a = document.createElement('a');
        a.className = 'notif-dd-item' + (n.is_read ? '' : ' unread');
        a.href = n.target_url || '#';
        a.dataset.id = n.id;
        a.dataset.read = n.is_read ? '1' : '0';

        var avatarHtml;
        if (n.sender_avatar_url) {
            avatarHtml = '<img src="' + escHtml(n.sender_avatar_url) + '" class="notif-dd-avatar" alt="">';
        } else {
            var icon = n.type === 'system_update' ? '🔔' : (n.type === 'comment_reply' ? '💬' : (n.type === 'reaction' ? '❤️' : '📨'));
            avatarHtml = '<div class="notif-dd-avatar system-avatar">' + icon + '</div>';
        }

        a.innerHTML = avatarHtml +
            '<div class="notif-dd-body">' +
                '<div class="notif-dd-title-text">' + escHtml(n.title) + '</div>' +
                (n.message ? '<div class="notif-dd-message">' + escHtml(n.message) + '</div>' : '') +
                '<div class="notif-dd-time">' + escHtml(n.time_ago) + '</div>' +
            '</div>';

        a.addEventListener('click', function(e) {
            e.preventDefault();
            if (n.is_read) {
                window.location.href = n.target_url || '#';
                return;
            }
            fetch('/uzdub/api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'mark_read', notification_id: n.id, csrf_token: csrf})
            }).then(function() {
                a.classList.remove('unread');
                a.dataset.read = '1';
                var current = parseInt(badge.textContent) || 0;
                updateBadge(Math.max(0, current - 1));
                window.location.href = n.target_url || '#';
            });
        });
        return a;
    }

    function loadNotifications(reset) {
        if (reset) { notifPage = 1; list.innerHTML = ''; }
        fetch('/uzdub/api/notifications.php?action=list&page=' + notifPage)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (notifPage === 1 && (!data.notifications || data.notifications.length === 0)) {
                    var lang = <?php echo json_encode(current_lang()); ?>;
                    var emptyMsg = lang === 'uz' ? 'Hozircha bildirishnoma yo\'q' : (lang === 'ru' ? 'Пока нет уведомлений' : 'No notifications yet');
                    list.innerHTML = '<div class="notif-dd-empty">' + escHtml(emptyMsg) + '</div>';
                    footer.style.display = 'none';
                    return;
                }
                data.notifications.forEach(function(n) {
                    list.appendChild(renderNotifItem(n));
                });
                notifTotal = data.total || 0;
                var shown = list.querySelectorAll('.notif-dd-item').length;
                footer.style.display = shown < notifTotal ? 'block' : 'none';
            })
            .catch(function() {});
    }

    bellBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        var isOpen = dropdown.classList.contains('open');
        dropdown.classList.toggle('open');
        if (!isOpen) {
            notifPage = 1;
            list.innerHTML = '<div class="notif-dd-loading"><div class="notif-dd-spinner"></div></div>';
            loadNotifications(true);
        }
    });

    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target) && e.target !== bellBtn && !bellBtn.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    if (markAll) {
        markAll.addEventListener('click', function() {
            fetch('/uzdub/api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'mark_all_read', csrf_token: csrf})
            }).then(function() {
                list.querySelectorAll('.notif-dd-item.unread').forEach(function(el) {
                    el.classList.remove('unread');
                });
                updateBadge(0);
            });
        });
    }

    if (loadMore) {
        loadMore.addEventListener('click', function() {
            notifPage++;
            loadNotifications(false);
        });
    }

    fetchUnreadCount();
    pollTimer = setInterval(fetchUnreadCount, 30000);
})();
</script>
