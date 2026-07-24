<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Maxfiylik siyosati';
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
    <h1>🔒 Maxfiylik siyosati</h1>
    <p>Sizning shaxsiy ma'lumotlaringiz xavfsizligi biz uchun muhim.</p>

    <h2>1. Yig'iladigan ma'lumotlar</h2>
    <ul>
        <li>Foydalanuvchi nomi va email manzili</li>
        <li>Profil rasmi</li>
        <li>Tomosha qilish tarixi va progressi</li>
        <li>Chat xabarlari</li>
        <li>IP manzili va qurilma ma'lumotlari</li>
    </ul>

    <h2>2. Ma'lumotlardan foydalanish</h2>
    <p>Shaxsiy ma'lumotlaringiz quyidagi maqsadlarda ishlatiladi:</p>
    <ul>
        <li>Xizmatni yaxshilash va shaxsiylashtirish</li>
        <li>Texnik muammolarni hal qilish</li>
        <li>Spam va firibgarlikni oldini olish</li>
        <li>Statistika va tahlil</li>
    </ul>

    <h2>3. Ma'lumotlarni himoya qilish</h2>
    <p>Biz sizning ma'lumotlaringizni uchinchi tomonlarga sotmaymiz yoki ijaraga bermaymiz. Barcha ma'lumotlar xavfsiz serverlarda saqlanadi.</p>

    <h2>4. Cookie fayllari</h2>
    <p>Platforma tajribasini yaxshilash uchun cookie fayllaridan foydalaniladi. Brauzer sozlamalaridan cookie fayllarini o'chirishingiz mumkin.</p>

    <h2>5. Bog'lanish</h2>
    <p>Maxfiylik siyosati bo'yicha savollaringiz bo'lsa, biz bilan bog'laning:</p>
    <p>📧 Email: <a href="mailto:privacy@uzdub.uz" style="color:var(--blue-glow);">privacy@uzdub.uz</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
