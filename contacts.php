<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = t('contacts_page_title');
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/uzdub/css/legal.css">
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
