/* ============================================================
   js/main.js
   UZDUB — asosiy funksionallik: header, toast bildirishnomalar,
   qator navigatsiyasi, qidiruv, mobil skroll indikatori va boshqalar
   ============================================================ */

/* ---- Global Toast bildirishnomalar tizimi ---- */
(function() {
    if (window.showToast) return;

    var toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = 'position:fixed;bottom:90px;left:50%;transform:translateX(-50%);z-index:9999999;display:flex;flex-direction:column;pointer-events:none;max-width:90vw;';
        document.body.appendChild(toastContainer);
    }

    var activeToasts = [];
    var stylesInjected = false;

    function injectStyles() {
        if (stylesInjected) return;
        stylesInjected = true;
        var style = document.createElement('style');
        style.textContent = '.uzdub-toast{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:11px 18px;border-radius:10px;font-size:13.5px;font-weight:500;line-height:1.4;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);box-shadow:0 8px 28px rgba(0,0,0,0.45);pointer-events:auto;animation:toastSlideIn .32s cubic-bezier(.18,.89,.32,1.28) both;text-align:center;max-width:420px;word-break:break-word;border:1px solid rgba(255,255,255,0.1)}.uzdub-toast.toast-success{background:rgba(46,125,50,0.92);color:#fff;border-color:rgba(76,175,80,0.3)}.uzdub-toast.toast-error{background:rgba(183,28,28,0.92);color:#fff;border-color:rgba(229,57,53,0.3)}.uzdub-toast.toast-info{background:rgba(13,71,161,0.92);color:#fff;border-color:rgba(33,150,243,0.3)}.uzdub-toast.toast-warning{background:rgba(230,126,34,0.92);color:#fff;border-color:rgba(249,168,37,0.3)}.uzdub-toast.removing{opacity:0;transform:translateY(-10px) scale(0.92);transition:opacity .25s ease,transform .25s ease}@keyframes toastSlideIn{from{opacity:0;transform:translateY(16px) scale(0.92)}to{opacity:1;transform:translateY(0) scale(1)}}@media(max-width:480px){.uzdub-toast{font-size:12.5px;padding:10px 14px;max-width:90vw}}';
        document.head.appendChild(style);
    }

    window.showToast = function showToast(text, type, duration) {
        if (typeof text !== 'string' || text === '') return;
        type = type || 'info';
        duration = typeof duration === 'number' ? duration : (type === 'error' ? 4000 : 2800);
        injectStyles();

        var el = document.createElement('div');
        el.className = 'uzdub-toast toast-' + type;
        el.textContent = text;
        toastContainer.appendChild(el);

        var obj = { el: el, timer: null };
        activeToasts.push(obj);

        updatePositions();

        function remove() {
            if (obj.el.parentNode) {
                obj.el.classList.add('removing');
                setTimeout(function() {
                    if (obj.el.parentNode) obj.el.parentNode.removeChild(obj.el);
                    activeToasts = activeToasts.filter(function(t) { return t !== obj; });
                    updatePositions();
                }, 260);
            }
        }

        if (obj.timer) clearTimeout(obj.timer);
        obj.timer = setTimeout(remove, duration);

        el.addEventListener('click', function() {
            if (obj.timer) clearTimeout(obj.timer);
            remove();
        });
    };

    function updatePositions() {
        var yOffset = 0;
        activeToasts.forEach(function(t) {
            t.el.style.transform = 'translateY(' + yOffset + 'px)';
            yOffset += t.el.offsetHeight + 8;
        });
    }

    window.showSuccess = function(msg) { window.showToast(msg, 'success'); };
    window.showError = function(msg) { window.showToast(msg, 'error'); };
    window.showInfo = function(msg) { window.showToast(msg, 'info'); };
    window.showWarning = function(msg) { window.showToast(msg, 'warning'); };

    // Toastlarni mobil pastki navigatsiyadan yuqorida ko'rsatish
    var mq = window.matchMedia('(max-width: 768px)');
    function adjustToastPosition() {
        toastContainer.style.bottom = mq.matches ? '82px' : '90px';
    }
    if (mq.addEventListener) { mq.addEventListener('change', adjustToastPosition); }
    adjustToastPosition();
})();

/* ---- Header scroll effekti ---- */
window.addEventListener('scroll', function () {
    var header = document.querySelector('.site-header');
    if (!header) return;
    if (window.scrollY > 50) header.classList.add('scrolled');
    else header.classList.remove('scrolled');
});

/* ---- Row navigation arrows ---- */
document.querySelectorAll('.row-arrow').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var wrap = btn.closest('.row-wrap').querySelector('.row-scroll');
        var dir = btn.dataset.dir === 'left' ? -1 : 1;
        wrap.scrollBy({ left: dir * 600, behavior: 'smooth' });
    });
});

/* ---- Mobil kontent qatorlari skroll indikatori ---- */
document.addEventListener('DOMContentLoaded', function () {
    if (window.matchMedia('(max-width: 768px)').matches) {
        document.querySelectorAll('.row-wrap').forEach(function (wrap) {
            var scroll = wrap.querySelector('.row-scroll');
            if (!scroll) return;

            function checkScroll() {
                var atEnd = scroll.scrollLeft + scroll.clientWidth >= scroll.scrollWidth - 4;
                wrap.classList.toggle('scrolled-right', atEnd);
            }

            scroll.addEventListener('scroll', checkScroll);
            // Boshlang'ich holatni tekshirish (agar kontent sig'sa, indikatorni yo'qotish)
            setTimeout(checkScroll, 100);
        });
    }
});

/* ---- Search form enter submit ---- */
document.querySelectorAll('.search-box input').forEach(function (input) {
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            input.closest('form').submit();
        }
    });
});

/* ---- Content lazy loading ---- */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('img[loading="lazy"]').forEach(function (img) {
        if (img.complete) {
            img.classList.add('loaded');
        } else {
            img.addEventListener('load', function () {
                img.classList.add('loaded');
            });
        }
    });
});
