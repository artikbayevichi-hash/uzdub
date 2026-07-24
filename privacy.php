<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = t('privacy_page_title');
include __DIR__ . '/includes/header.php';
?>
<style>
.legal-wrap { max-width:800px; margin:0 auto; padding:40px 20px 80px; }
.legal-wrap h1 { font-size:28px; color:var(--blue-glow); margin-bottom:20px; }
.legal-wrap p, .legal-wrap li { color:var(--text-light); font-size:15px; line-height:1.8; margin-bottom:12px; }
.legal-wrap h2 { font-size:20px; color:var(--text-light); margin:24px 0 12px; }
.legal-wrap ul { padding-left:20px; }
</style>
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
