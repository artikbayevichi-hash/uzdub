<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = t('terms_page_title');
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
