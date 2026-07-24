<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Foydalanish shartlari';
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
    <h1>📜 Foydalanish shartlari</h1>
    <p>UZDUB PLATFORM'ni ishlatish orqali siz ushbu shartlarga rozilik bildirasiz.</p>

    <h2>1. Xizmat tavsifi</h2>
    <p>UZDUB PLATFORM — bu kino, anime va multfilmlarni o'zbek tilida tomosha qilish uchun platforma. Barcha kontentlar dublyaj jamoalari va manbalaridan yig'ilgan.</p>

    <h2>2. Foydalanuvchi majburiyatlari</h2>
    <ul>
        <li>Platformadan faqat shaxsiy maqsadlarda foydalanish</li>
        <li>Kontentni qayta tarqatmaslik yoki ko'chirmaslik</li>
        <li>Boshqa foydalanuvchilarga hurmat bilan munosabatda bo'lish</li>
        <li>Hacklash, spam yoki boshqa noqonuniy harakatlar qilmaslik</li>
    </ul>

    <h2>3. Premium obuna</h2>
    <p>Premium obuna pullik xizmat bo'lib, to'lov qilingandan keyin qaytarilmaydi. Obuna muddati tugaganidan keyin premium imkoniyatlar cheklangan holda ishlashda davom etadi.</p>

    <h2>4. Javobgarlik</h2>
    <p>UZDUB PLATFORM kontentning to'g'riligi, to'liqligi yoki ishonchliligi uchun javobgar emas. Foydalanuvchilar o'z xavfiga foydalanadi.</p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
