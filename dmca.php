<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'DMCA';
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
    <h1>📋 DMCA — Raqamli mingillik mualliflik huquqi qonuni</h1>
    <p>UZDUB PLATFORM raqamli mingillik mualliflik huquqi qonuniga muvofiq ishlaydi. Agar sizning mualliflik huquqingiz buzilgan deb hisoblasangiz, biz bilan bog'laning.</p>

    <h2>Shikoyat jarayoni</h2>
    <p>DMCA shikoyatini yuborish uchun quyidagi ma'lumotlarni taqdim eting:</p>
    <ul>
        <li>Mualliflik huquqi egasi yoki ularning vakili tomonidan imzolangan shikoyat</li>
        <li>Buzilgan deb da'vo qilingan materialning aniqlanishi</li>
        <li>Sizning aloqa ma'lumotlaringiz</li>
        <li>Yaxshi niyat bilan e'lon qilingan bayonot</li>
    </ul>

    <h2>Bog'lanish</h2>
    <p>Shikoyatlaringizni quyidagi manzilga yuboring:</p>
    <p>📧 Email: <a href="mailto:dmca@uzdub.uz" style="color:var(--blue-glow);">dmca@uzdub.uz</a></p>
    <p>Biz 24 soat ichida javob berishga harakat qilamiz.</p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
