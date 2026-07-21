<?php
/* ============================================================
   api/telegram-notify.php
   Telegram orqali bildirishnoma yuborish.
   
   Bu fayl config/payment.php dagi birlashtirilgan
   Telegram funksiyalaridan foydalanadi.
   
   Ishlatish:
     require_once __DIR__ . '/config/payment.php';
     tg_send_message("Matn...");
     tg_send_photo("/path/to/photo.jpg", "Caption...");
   ============================================================ */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payment.php';

// CLI orqali chaqirilsa (cron)
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $command = $argv[1] ?? '';

    if ($command === 'new_content' && isset($argv[2])) {
        $content_id = (int)$argv[2];
        $stmt = $pdo->prepare("SELECT c.*, cat.name as cat_name FROM content c JOIN categories cat ON c.category_id=cat.id WHERE c.id = ?");
        $stmt->execute([$content_id]);
        $content = $stmt->fetch();

        if ($content) {
            $message = "🎬 <b>Yangi kontent qo'shildi!</b>\n"
                . "📹 Nomi: <b>" . e($content['title']) . "</b>\n"
                . "🏷️ Kategoriya: " . e($content['cat_name']) . "\n"
                . "📅 Yil: " . ($content['release_year'] ?: 'Noma\'lum') . "\n"
                . "⭐ Reyting: " . ($content['rating'] ?: 'Noma\'lum') . "\n\n"
                . "👉 /uzdub/watch.php?id=" . $content_id;

            $photo_path = $content['poster'] ? (__DIR__ . '/../uploads/posters/' . $content['poster']) : null;
            if ($photo_path && file_exists($photo_path)) {
                tg_send_photo($photo_path, $message);
            } else {
                tg_send_message($message);
            }
            echo "Xabar yuborildi: {$content['title']}\n";
        } else {
            echo "Kontent topilmadi: ID $content_id\n";
        }
    }
}
