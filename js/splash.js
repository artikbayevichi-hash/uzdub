(function() {
    var splash = document.getElementById('splash-screen');
    if (!splash) return;

    // === SPLASH COOLDOWN: 1 soat yoki tab yopilguncha ===
    // sessionStorage tab yopilganda tozalanadi — tab yopilgan bo'lsa, splash ko'rsatiladi
    // localStorage 1 soat ichida qayta ko'rsatmaslik uchun
    var COOLDOWN_MS = 60 * 60 * 1000; // 1 soat
    var now = Date.now();
    var lastShow = parseInt(localStorage.getItem('uzdub_splash_last') || '0', 10);
    var tabMarked = sessionStorage.getItem('uzdub_splash_shown');

    // Agar shu tabda allaqachon ko'rsatilgan BO'LSA (tab yopilmagan) VA 1 soat o'tmagan bo'lsa — o'tkazib yuborish
    if (tabMarked === '1' && (now - lastShow) < COOLDOWN_MS) {
        splash.style.display = 'none';
        document.body.style.overflow = '';
        return;
    }

    // Splash ko'rsatilayapti — belgi qo'yamiz
    sessionStorage.setItem('uzdub_splash_shown', '1');
    localStorage.setItem('uzdub_splash_last', String(now));

    // === AKKAUNTLAR BOSHQARUVI (localStorage) ===
    var ACCOUNTS_KEY = 'uzdub_accounts';
    var CURRENT_KEY = 'uzdub_current_account';

    function getAccounts() {
        try { return JSON.parse(localStorage.getItem(ACCOUNTS_KEY)) || []; } catch(e) { return []; }
    }
    function setAccounts(arr) {
        localStorage.setItem(ACCOUNTS_KEY, JSON.stringify(arr));
    }
    function getCurrentAccount() {
        try { return JSON.parse(localStorage.getItem(CURRENT_KEY)); } catch(e) { return null; }
    }
    function setCurrentAccount(acc) {
        localStorage.setItem(CURRENT_KEY, JSON.stringify(acc));
    }

    // Splash'dagi user ma'lumotlarini localStorage'ga saqlash (agar PHP'dan kelgan bo'lsa)
    var splashData = splash.getAttribute('data-user');
    if (splashData) {
        try {
            var userData = JSON.parse(splashData);
            setCurrentAccount(userData);

            // Akkauntlar ro'yxatiga qo'shish (agar yo'q bo'lsa)
            var accounts = getAccounts();
            var exists = accounts.find(function(a) { return a.user_id === userData.user_id; });
            if (!exists) {
                accounts.push(userData);
                setAccounts(accounts);
            }
        } catch(e) {}
    }

    // === AKKAUNTLAR RO'YXATINI CHIQARISH ===
    var accountsList = splash.querySelector('.splash-accounts-list');
    if (accountsList) {
        var accounts = getAccounts();
        var current = getCurrentAccount();
        if (accounts.length > 1) {
            accountsList.innerHTML = '';
            accounts.forEach(function(acc) {
                var isActive = current && acc.user_id === current.user_id;
                var div = document.createElement('div');
                div.className = 'splash-account-item' + (isActive ? ' active' : '');
                div.innerHTML =
                    '<img src="' + (acc.avatar || '/uzdub/uploads/avatars/default.png') + '" alt="" class="splash-account-avatar">' +
                    '<div class="splash-account-info">' +
                        '<span class="splash-account-name">' + (acc.username || '') + '</span>' +
                        (acc.is_premium ? '<span class="splash-account-premium">⭐ Premium</span>' : '') +
                    '</div>' +
                    (isActive ? '<span class="splash-account-badge">Joriy</span>' : '');
                if (!isActive && acc.switch_token) {
                    div.style.cursor = 'pointer';
                    div.setAttribute('data-uid', acc.user_id);
                    div.setAttribute('data-token', acc.switch_token);
                    div.addEventListener('click', function() {
                        window.location.href = '/uzdub/auth/switch.php?uid=' + acc.user_id + '&token=' + encodeURIComponent(acc.switch_token);
                    });
                }
                accountsList.appendChild(div);
            });
            accountsList.style.display = 'flex';
        }
    }

    // Particles yaratish
    var particlesContainer = document.getElementById('splashParticles');
    if (particlesContainer) {
        for (var i = 0; i < 30; i++) {
            var p = document.createElement('div');
            p.className = 'particle';
            var size = Math.random() * 4 + 2;
            p.style.width = size + 'px';
            p.style.height = size + 'px';
            p.style.left = Math.random() * 100 + '%';
            p.style.animationDuration = (Math.random() * 10 + 8) + 's';
            p.style.animationDelay = (Math.random() * 10) + 's';
            p.style.opacity = Math.random() * 0.5 + 0.1;
            particlesContainer.appendChild(p);
        }
    }

    // Scroll-based reveal for feature cards
    var featureCards = splash.querySelectorAll('.splash-feature-card');
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.2 });

    featureCards.forEach(function(card) { observer.observe(card); });

    // Counter animation for stats
    function animateCounter(el, target, suffix) {
        suffix = suffix || '';
        var isFloat = target % 1 !== 0;
        var current = 0;
        var step = target / 60;
        var timer = setInterval(function() {
            current += step;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            el.textContent = isFloat ? current.toFixed(1) + suffix : Math.floor(current) + suffix;
        }, 25);
    }

    var statsObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                animateCounter(document.getElementById('statContent'), 500, '+');
                animateCounter(document.getElementById('statUsers'), 10, 'K+');
                animateCounter(document.getElementById('statRating'), 4.8, '');
                statsObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });

    var statsEl = splash.querySelector('.splash-stats');
    if (statsEl) statsObserver.observe(statsEl);

    // Escape tugmasi bilan o'tish
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') dismissSplash();
    });
})();

function dismissSplash() {
    var splash = document.getElementById('splash-screen');
    if (!splash) return;
    splash.classList.add('hiding');
    document.body.style.overflow = '';
    setTimeout(function() {
        splash.classList.add('hidden');
    }, 800);
}

// Har safar kirganda splash ochiladi
document.addEventListener('DOMContentLoaded', function() {
    var splash = document.getElementById('splash-screen');
    if (!splash || splash.style.display === 'none') return;

    document.body.style.overflow = 'hidden';

    // Splash bosqichma-bosqich ochilishi
    setTimeout(function() { splash.style.opacity = '1'; }, 100);
});
