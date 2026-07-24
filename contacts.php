<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = t('contacts_page_title');
include __DIR__ . '/includes/header.php';
?>
<style>
.legal-wrap { max-width:800px; margin:0 auto; padding:40px 20px 80px; }
.legal-wrap h1 { font-size:28px; color:var(--blue-glow); margin-bottom:20px; }
.legal-wrap p { color:var(--text-light); font-size:15px; line-height:1.8; margin-bottom:12px; }
.legal-wrap h2 { font-size:20px; color:var(--text-light); margin:24px 0 12px; }
.contact-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:16px; margin-top:20px; }
.contact-card { background:var(--card-bg); border:1px solid rgba(33,150,243,0.15); border-radius:14px; padding:24px; text-align:center; transition:transform .25s; }
.contact-card:hover { transform:translateY(-3px); }
.contact-card .cc-icon { font-size:32px; margin-bottom:10px; }
.contact-card .cc-label { font-size:13px; color:var(--text-muted); margin-bottom:4px; }
.contact-card .cc-value { font-size:15px; color:var(--text-light); font-weight:600; }
.contact-card a { color:var(--blue-glow); text-decoration:none; }
</style>
<div class="legal-wrap">
    <h1>📩 <?php echo t('contacts_heading'); ?></h1>
    <p><?php echo t('contacts_desc'); ?></p>

    <div class="contact-grid">
        <div class="contact-card">
            <div class="cc-icon">📧</div>
            <div class="cc-label">Email</div>
            <div class="cc-value"><a href="mailto:info@uzdub.uz">info@uzdub.uz</a></div>
        </div>
        <div class="contact-card">
            <div class="cc-icon">💬</div>
            <div class="cc-label">Telegram</div>
            <div class="cc-value"><a href="https://t.me/uzdub" target="_blank">@uzdub</a></div>
        </div>
        <div class="contact-card">
            <div class="cc-icon">📸</div>
            <div class="cc-label">Instagram</div>
            <div class="cc-value"><a href="https://instagram.com/uzdub.uz" target="_blank">@uzdub.uz</a></div>
        </div>
        <div class="contact-card">
            <div class="cc-icon">🎵</div>
            <div class="cc-label">TikTok</div>
            <div class="cc-value"><a href="https://tiktok.com/@uzdub" target="_blank">@uzdub</a></div>
        </div>
    </div>

    <h2><?php echo t('write_message_heading'); ?></h2>
    <p><?php echo t('write_message_desc'); ?></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
