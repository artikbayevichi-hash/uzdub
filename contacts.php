<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = t('contacts_page_title');
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/legal.css?v=<?php echo @filemtime(__DIR__ . '/css/legal.css') ?: 1; ?>">
<div class="legal-wrap">
    <h1>рџ“© <?php echo t('contacts_heading'); ?></h1>
    <p><?php echo t('contacts_desc'); ?></p>

    <div class="contact-grid">
        <div class="contact-card">
            <div class="cc-icon">рџ“§</div>
            <div class="cc-label">Email</div>
            <div class="cc-value"><a href="mailto:info@uzdub.uz">info@uzdub.uz</a></div>
        </div>
        <div class="contact-card">
            <div class="cc-icon">рџ’¬</div>
            <div class="cc-label">Telegram</div>
            <div class="cc-value"><a href="https://t.me/uzdub_platform" target="_blank">@uzdub_platform</a></div>
        </div>
        <div class="contact-card">
            <div class="cc-icon">рџ“ё</div>
            <div class="cc-label">Instagram</div>
            <div class="cc-value"><a href="https://instagram.com/UZDUB_PLATFORM" target="_blank">@UZDUB_PLATFORM</a></div>
        </div>
        <div class="contact-card">
            <div class="cc-icon">рџЋµ</div>
            <div class="cc-label">TikTok</div>
            <div class="cc-value"><a href="https://tiktok.com/@uzdub.platform" target="_blank">@uzdub.platform</a></div>
        </div>
    </div>

    <h2><?php echo t('write_message_heading'); ?></h2>
    <p><?php echo t('write_message_desc'); ?></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
