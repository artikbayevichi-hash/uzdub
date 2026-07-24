<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = t('dmca_page_title');
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/uzdub/css/legal.css">
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
