// ================================================================
// UZDUB — Kontent kartalari uchun haqiqiy 3D "tilt" (og'ish) effekti
// CSS (.card) allaqachon --rx/--ry/--mx/--my/--cs/--cy o'zgaruvchilariga
// tayyor edi, lekin ularni hech kim o'rnatmagan edi — shu skript
// sichqoncha holatiga qarab kartani haqiqiy 3D obyekt kabi burab beradi.
// ================================================================
(function () {
    const MAX_TILT = 5;       // maksimal burilish burchagi (daraja) — nafis va yumshoq bo'lishi uchun kamaytirildi
    const HOVER_SCALE = 1.035;
    const HOVER_LIFT = -3;    // px, tepaga ko'tarilish

    const hasHover = window.matchMedia && window.matchMedia('(hover: hover)').matches;
    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!hasHover || reduceMotion) return;

    function bindTilt(el) {
        el.addEventListener('mousemove', function (e) {
            const rect = el.getBoundingClientRect();
            const px = (e.clientX - rect.left) / rect.width;   // 0..1
            const py = (e.clientY - rect.top) / rect.height;   // 0..1

            const ry = (px - 0.5) * MAX_TILT;   // Y o'qi bo'ylab burilish (chap-o'ng harakatdan)
            const rx = (0.5 - py) * MAX_TILT;   // X o'qi bo'ylab burilish (tepa-past harakatdan)

            el.style.setProperty('--rx', ry.toFixed(2) + 'deg');
            el.style.setProperty('--ry', rx.toFixed(2) + 'deg');
            el.style.setProperty('--mx', (px * 100).toFixed(1) + '%');
            el.style.setProperty('--my', (py * 100).toFixed(1) + '%');
            el.style.setProperty('--cs', HOVER_SCALE);
            el.style.setProperty('--cy', HOVER_LIFT + 'px');
        });

        el.addEventListener('mouseleave', function () {
            el.style.setProperty('--rx', '0deg');
            el.style.setProperty('--ry', '0deg');
            el.style.setProperty('--cs', '1');
            el.style.setProperty('--cy', '0px');
        });
    }

    document.querySelectorAll('.card').forEach(bindTilt);

    // Sahifaga keyinchalik (AJAX/JS orqali) qo'shiladigan kartalar uchun ham ishlashi uchun kuzatuvchi
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            m.addedNodes.forEach(function (node) {
                if (!(node instanceof HTMLElement)) return;
                if (node.classList && node.classList.contains('card')) bindTilt(node);
                node.querySelectorAll && node.querySelectorAll('.card').forEach(bindTilt);
            });
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });

    // ===== Hero banner uchun yengil parallaks (3D chuqurlik) =====
    const hero = document.querySelector('.hero-carousel');
    if (hero) {
        hero.addEventListener('mousemove', function (e) {
            const rect = hero.getBoundingClientRect();
            const px = (e.clientX - rect.left) / rect.width - 0.5;
            const py = (e.clientY - rect.top) / rect.height - 0.5;
            const activeContent = hero.querySelector('.hero-slide.active .hero-content');
            if (activeContent) {
                activeContent.style.transform = `translate3d(${px * -14}px, ${py * -8}px, 0)`;
            }
        });
        hero.addEventListener('mouseleave', function () {
            const activeContent = hero.querySelector('.hero-slide.active .hero-content');
            if (activeContent) activeContent.style.transform = 'translate3d(0,0,0)';
        });
    }
})();
