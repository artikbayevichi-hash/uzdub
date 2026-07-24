<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = t('terms_page_title');
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/uzdub/css/legal.css">
<div class="legal-wrap">
    <h1>📜 <?php echo t('terms_heading'); ?></h1>
    <p><?php echo t('terms_intro'); ?></p>

    <h2>1. <?php echo t('service_desc'); ?></h2>
    <p><?php echo t('service_desc_text'); ?></p>

    <h2>2. <?php echo t('user_obligations'); ?></h2>
    <ul>
        <li><?php echo t('obligation_1'); ?></li>
        <li><?php echo t('obligation_2'); ?></li>
        <li><?php echo t('obligation_3'); ?></li>
        <li><?php echo t('obligation_4'); ?></li>
    </ul>

    <h2>3. <?php echo t('premium_section'); ?></h2>
    <p><?php echo t('premium_terms_text'); ?></p>

    <h2>4. <?php echo t('responsibility'); ?></h2>
    <p><?php echo t('responsibility_text'); ?></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
