<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = t('privacy_page_title');
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/uzdub/css/legal.css">
<div class="legal-wrap">
    <h1>🔒 <?php echo t('privacy_heading'); ?></h1>
    <p><?php echo t('privacy_intro'); ?></p>

    <h2>1. <?php echo t('data_collected'); ?></h2>
    <ul>
        <li><?php echo t('data_1'); ?></li>
        <li><?php echo t('data_2'); ?></li>
        <li><?php echo t('data_3'); ?></li>
        <li><?php echo t('data_4'); ?></li>
        <li><?php echo t('data_5'); ?></li>
    </ul>

    <h2>2. <?php echo t('data_usage'); ?></h2>
    <p><?php echo t('data_usage_intro'); ?></p>
    <ul>
        <li><?php echo t('data_usage_1'); ?></li>
        <li><?php echo t('data_usage_2'); ?></li>
        <li><?php echo t('data_usage_3'); ?></li>
        <li><?php echo t('data_usage_4'); ?></li>
    </ul>

    <h2>3. <?php echo t('data_protection'); ?></h2>
    <p><?php echo t('data_protection_text'); ?></p>

    <h2>4. <?php echo t('cookies'); ?></h2>
    <p><?php echo t('cookies_text'); ?></p>

    <h2>5. <?php echo t('contact_us'); ?></h2>
    <p><?php echo t('privacy_contact_text'); ?></p>
    <p>📧 Email: <a href="mailto:privacy@uzdub.uz" style="color:var(--blue-glow);">privacy@uzdub.uz</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
