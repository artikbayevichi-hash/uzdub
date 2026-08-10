<?php
/**
 * UZDUB — Video URL olish sahifasi (VK + Archive.org)
 * Videolar maxsus Telegram botlar orqali CDN'ga yuklanadi va URL qaytariladi.
 * Botlar quyida ko'rsatilgan. Olingan URL'ni qismning "Video havolasi" maydoniga qo'ying.
 */

$page_title = 'Video URL (VK / Archive.org)';
include __DIR__ . '/includes/admin_header.php';

$vk_bot = 'VK yuklovchi bot';
$archive_bot = 'Archive.org yuklovchi bot';

$vk_bot_user = str_replace('@', '', env('TG_VK_BOT_USERNAME', 'uzdub_platform_vk_and_sibnet_bot'));
$vk_ready = true;
?>
<style>
.card-box { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:18px; margin-bottom:14px; }
.card-box h3 { margin:0 0 10px; }
.bot-item { background:rgba(33,150,243,.08); border:1px solid rgba(33,150,243,.3); border-radius:8px; padding:12px 14px; margin-top:10px; }
.bot-item h4 { margin:0 0 6px; }
.bot-item p { margin:4px 0; font-size:13px; opacity:.8; }
.steps { margin:8px 0 0; padding-left:18px; font-size:14px; }
.steps li { margin:3px 0; }
.url-result { background:rgba(76,175,80,.12); border:1px solid rgba(76,175,80,.4); color:#a5d6a7; padding:12px 14px; border-radius:8px; margin-top:12px; word-break:break-all; }
.url-result code { color:#fff; }
</style>

<h1>&#11014; Video URL olish</h1>
<p style="opacity:.8;margin-bottom:16px;">Video CDN'ga yuklash uchun quyidagi Telegram botlardan birini ishlating. Bot sizga tayyor URL qaytaradi — o'sha URL'ni qismning "Video havolasi" maydoniga qo'yasiz (video_type: <b>Direct URL</b> yoki <b>cloud</b>).</p>

<div class="card-box">
    <h3>&#128250; VK Video (o'z botimiz)</h3>
    <?php if (!$vk_ready): ?>
        <div class="bot-item" style="border-color:rgba(255,167,38,.4);">
            <p><b>&#9888; VK access token sozlanmagan.</b></p>
            <p>Bot ishlashi uchun <code>.env</code> faylida <code>VK_ACCESS_TOKEN=...</code> (video scope bilan) ko'rsatilishi kerak.</p>
        </div>
    <?php endif; ?>
    <div class="bot-item">
        <h4>Telegram: <a href="https://t.me/<?php echo e($vk_bot_user); ?>"><?php echo e($vk_bot_user); ?></a></h4>
        <p>Video faylni botga yuboring — VK'ga yuklab, embed URL qaytaradi. Bot loyihaning o'zi boshqaradi (localhost'da ishlaydi).</p>
        <ol class="steps">
            <li>Botga video faylni yuboring (mp4, mkv, avi, webm...) yoki kanaldan forward qiling</li>
            <li>Bot <b>VK embed URL</b> qaytaradi</li>
            <li>URL'ni qismning "Video havolasi" maydoniga qo'ying (video_type: Direct URL)</li>
        </ol>
        <p><b>Afzalligi:</b> cheksiz tomoshabin, sayt diski band bo'lmaydi.</p>
    </div>
</div>

<div class="card-box">
    <h3>&#128190; Archive.org</h3>
    <div class="bot-item">
        <h4>Telegram: <a href="https://t.me/<?php echo e(str_replace('@','',env('ARCHIVE_BOT_USERNAME','archive_org_uploader_bot'))); ?>"><?php echo e(str_replace('@','',env('ARCHIVE_BOT_USERNAME','archive_org_uploader_bot'))); ?></a></h4>
        <p>Video faylni botga yuboring — Archive.org'ga yuklab, to'g'ridan-to'g'ri mp4 URL qaytaradi.</p>
        <ol class="steps">
            <li>Botga video faylni yuboring (mp4, mkv, avi, webm...)</li>
            <li>Bot <b>to'g'ridan-to'g'ri mp4 URL</b> qaytaradi</li>
            <li>URL'ni qismning "Video havolasi" maydoniga qo'ying (video_type: Direct URL)</li>
        </ol>
        <p><b>Afzalligi:</b> cheksiz tomoshabin, bepul, fayl hajmi cheklovsiz.</p>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
