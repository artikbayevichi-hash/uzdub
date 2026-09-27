<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="footer-logo-wrap">
                    <span class="footer-logo">🎬 UZDUB PLATFORM</span>
                </div>
                <p class="footer-desc"><?php echo t('footer_desc'); ?></p>
                <div class="footer-social">
                    <a href="https://t.me/uzdub_platform" target="_blank" title="Telegram" class="social-tg">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.499 1.201-.82 1.23-.697.065-1.226-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635.099-.002.321.023.465.141.12.099.153.232.168.334.016.102.035.33.02.51z"/></svg>
                    </a>
                    <a href="https://instagram.com/UZDUB_PLATFORM" target="_blank" title="Instagram" class="social-ig">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </a>
                    <a href="https://tiktok.com/@uzdub.platform" target="_blank" title="TikTok" class="social-tt">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 00-.79-.05A6.34 6.34 0 003.15 15.2a6.34 6.34 0 0010.86-4.43v-7a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-.81-.07l.62.03z"/></svg>
                    </a>
                </div>
            </div>
            <div class="footer-links">
                <h4><?php echo t('useful_links'); ?></h4>
                <a href="<?php echo ROOT_URL; ?>/index.php"><?php echo t('home'); ?></a>
                <a href="<?php echo ROOT_URL; ?>/category.php?slug=kino"><?php echo t('movies'); ?></a>
                <a href="<?php echo ROOT_URL; ?>/category.php?slug=anime"><?php echo t('anime'); ?></a>
                <a href="<?php echo ROOT_URL; ?>/category.php?slug=multfilm"><?php echo t('cartoons'); ?></a>
            </div>
            <div class="footer-links">
                <h4><?php echo t('legal'); ?></h4>
                <a href="<?php echo ROOT_URL; ?>/dmca.php">DMCA</a>
                <a href="<?php echo ROOT_URL; ?>/terms.php"><?php echo t('terms'); ?></a>
                <a href="<?php echo ROOT_URL; ?>/privacy.php"><?php echo t('privacy'); ?></a>
                <a href="<?php echo ROOT_URL; ?>/contacts.php"><?php echo t('contacts'); ?></a>
            </div>
        </div>
        <div class="footer-divider"></div>
        <div class="footer-disclaimer">
            <span class="disclaimer-icon">ℹ️</span>
            <p><?php echo t('disclaimer'); ?></p>
        </div>
        <div class="footer-bottom">
            &copy; <?php echo date('Y'); ?> UZDUB PLATFORM.UZ — <?php echo t('all_rights'); ?>
        </div>
    </div>
</footer>

<?php include __DIR__ . '/ai-widget.php'; ?>
<style>
#resumeToast{position:fixed;right:16px;bottom:84px;z-index:999998;width:340px;max-width:calc(100vw - 32px);background:rgba(18,26,43,0.96);border:1px solid rgba(33,150,243,0.25);border-radius:16px;box-shadow:0 16px 48px rgba(0,0,0,0.55);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);overflow:hidden;transform:translateX(120%);opacity:0;transition:transform .38s cubic-bezier(.18,.89,.32,1.2),opacity .3s ease;pointer-events:auto}
#resumeToast.show{transform:translateX(0);opacity:1}
#resumeToast.hide{transform:translateX(120%);opacity:0;transition:transform .28s ease,opacity .25s ease}
#resumeToast .rt-inner{display:flex;gap:12px;padding:12px}
#resumeToast .rt-poster{width:56px;height:80px;flex:0 0 56px;border-radius:8px;object-fit:cover;background:#0d1424}
#resumeToast .rt-body{flex:1;min-width:0;display:flex;flex-direction:column;justify-content:center;gap:6px}
#resumeToast .rt-title{font-size:13px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#resumeToast .rt-msg{font-size:13px;color:#9aa8bd}
#resumeToast .rt-btn{display:inline-flex;align-items:center;gap:6px;align-self:flex-start;padding:7px 16px;border-radius:20px;background:var(--blue-primary,#2196f3);color:#fff;font-size:13px;font-weight:600;border:none;cursor:pointer;text-decoration:none;transition:background .2s}
#resumeToast .rt-btn:hover{background:#1976d2}
#resumeToast .rt-close{position:absolute;top:6px;right:8px;width:26px;height:26px;display:flex;align-items:center;justify-content:center;border:none;background:rgba(255,255,255,0.08);border-radius:50%;color:#9aa8bd;font-size:15px;cursor:pointer;transition:background .2s,color .2s}
#resumeToast .rt-close:hover{background:rgba(239,83,80,0.25);color:#ef5350}
@media(min-width:769px){#resumeToast{bottom:20px}}
</style>
<script>
(function(){
    var data = window.__UZDUB_RESUME__;
    if (!data || !data.content_id) return;
    try {
        var u = new URL(location.href);
        if (u.pathname.indexOf('watch.php') !== -1 && parseInt(u.searchParams.get('id'), 10) === data.content_id) return;
    } catch (e) {}
    var SK = 'uzdub_resume_toast';
    try { if (sessionStorage.getItem(SK)) return; } catch (e) {}
    var COOL = 2 * 60 * 60 * 1000;
    var CK = 'uzdub_resume_skip_' + data.content_id;
    try {
        var skipTs = parseInt(localStorage.getItem(CK), 10) || 0;
        if (skipTs && (Date.now() - skipTs) < COOL) return;
    } catch (e) {}

    function esc(s){ var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    var t = <?php echo json_encode(t('continue_watching_question'), JSON_UNESCAPED_UNICODE); ?>;
    var btnTxt = <?php echo json_encode(t('continue_watching_btn'), JSON_UNESCAPED_UNICODE); ?>;
    var closeTxt = <?php echo json_encode(t('continue_watching_close'), JSON_UNESCAPED_UNICODE); ?>;
    var href = ROOT_URL + '/watch.php?id=' + data.content_id + (data.episode_id ? '&ep=' + data.episode_id : '');

    var el = document.createElement('div');
    el.id = 'resumeToast';
    el.innerHTML =
        '<button type="button" class="rt-close" aria-label="' + esc(closeTxt) + '" title="' + esc(closeTxt) + '">&times;</button>' +
        '<div class="rt-inner">' +
            (data.poster ? '<img class="rt-poster" src="' + esc(data.poster) + '" alt="">' : '<div class="rt-poster" style="display:flex;align-items:center;justify-content:center;font-size:22px">&#127916;</div>') +
            '<div class="rt-body">' +
                '<div class="rt-title">' + esc(data.title || '') + '</div>' +
                '<div class="rt-msg">' + esc(t) + '</div>' +
                '<a class="rt-btn" href="' + href + '">&#9654; ' + esc(btnTxt) + '</a>' +
            '</div>' +
        '</div>';
    document.body.appendChild(el);

    var timer = null;
    function dismiss() {
        if (timer) clearTimeout(timer);
        el.classList.add('hide');
        try { sessionStorage.setItem(SK, '1'); } catch (e) {}
        try { localStorage.setItem(CK, String(Date.now())); } catch (e) {}
        setTimeout(function(){ if (el.parentNode) el.parentNode.removeChild(el); }, 300);
    }
    el.querySelector('.rt-close').addEventListener('click', function(e){ e.stopPropagation(); dismiss(); });
    el.querySelector('.rt-btn').addEventListener('click', dismiss);
    setTimeout(function() {
        el.classList.add('show');
        // Bir tab sessiyasida navigatsiyada takror chiqmasligi uchun darhol belgilaymiz
        try { sessionStorage.setItem(SK, '1'); } catch (e) {}
    }, 900);
    timer = setTimeout(dismiss, 120000);
})();
</script>
<script src="<?php echo ROOT_URL; ?>/js/main.js"></script>
</body>
</html>
