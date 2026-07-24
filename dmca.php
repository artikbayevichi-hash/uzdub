<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = t('dmca_page_title');
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
    <h1>📋 <?php echo t('dmca_heading'); ?></h1>
    <p><?php echo t('dmca_intro'); ?></p>

    <h2><?php echo t('complaint_process'); ?></h2>
    <p><?php echo t('complaint_intro'); ?></p>
    <ul>
        <li><?php echo t('complaint_1'); ?></li>
        <li><?php echo t('complaint_2'); ?></li>
        <li><?php echo t('complaint_3'); ?></li>
        <li><?php echo t('complaint_4'); ?></li>
    </ul>

    <h2><?php echo t('dmca_contact'); ?></h2>
    <p><?php echo t('dmca_contact_text'); ?></p>
    <p>📧 Email: <a href="mailto:dmca@uzdub.uz" style="color:var(--blue-glow);">dmca@uzdub.uz</a></p>
    <p><?php echo t('dmca_response'); ?></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
