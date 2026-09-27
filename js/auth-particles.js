window.ROOT_URL = window.ROOT_URL || '/uzdub';

(function () {
    'use strict';

    // Bosh sahifadagi (splash) suzuvchi zarrachalar uslubi —
    // auth sahifalari uchun konteyner yaratib, uni ls-dot'larga
    // o'xshash auth-dot'lar bilan to'ldiradi.

    function makeContainer() {
        var el = document.querySelector('.auth-particles');
        if (el) return el;
        el = document.createElement('div');
        el.className = 'auth-particles';
        el.setAttribute('aria-hidden', 'true');
        // Grid va box ortida qolishi uchun body boshiga qo'yamiz
        var grid = document.querySelector('.auth-grid');
        if (grid && grid.parentNode) {
            grid.parentNode.insertBefore(el, grid);
        } else {
            document.body.insertBefore(el, document.body.firstChild);
        }
        return el;
    }

    function fill(container, count) {
        count = count || 30;
        for (var i = 0; i < count; i++) {
            var d = document.createElement('div');
            d.className = 'auth-dot';
            var sz = Math.random() * 4 + 2;        // 2..6px
            d.style.width = sz + 'px';
            d.style.height = sz + 'px';
            d.style.left = Math.random() * 100 + '%';      // gorizontal pozitsiya
            d.style.animationDuration = (Math.random() * 10 + 8) + 's'; // 8..18s
            d.style.animationDelay = (Math.random() * 10) + 's';        // 0..10s
            d.style.opacity = Math.random() * .5 + .1;     // 0.1..0.6
            container.appendChild(d);
        }
    }

    var container = makeContainer();
    if (container) {
        fill(container, 30);
    }
})();