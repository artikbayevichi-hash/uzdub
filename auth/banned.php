<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$reason = urldecode($_GET['reason'] ?? 'Qoidabuzarlik');
$expires = $_GET['expires'] ?? '';
$is_permanent = ($expires === 'permanent');
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hisob bloklangan - UZDUB PLATFORM</title>
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/style.css">
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/auth.css">
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/emoji-blue.css">
<style>
.ban-box { text-align:center; max-width:480px; }
.ban-icon { font-size:72px; margin-bottom:16px; display:block; animation:banPulse 2s ease-in-out infinite; }
@keyframes banPulse { 0%,100%{transform:scale(1);} 50%{transform:scale(1.08);} }
.ban-title { font-size:24px; font-weight:800; color:#ef5350; margin-bottom:12px; }
.ban-reason { background:rgba(239,83,80,0.08); border:1px solid rgba(239,83,80,0.25); border-radius:10px; padding:14px 18px; margin:16px 0; font-size:14px; color:var(--text-light); }
.ban-reason strong { color:#ef5350; display:block; margin-bottom:6px; }
.ban-expires { font-size:13px; color:var(--text-muted); margin:12px 0; }
.ban-evidence { margin-top:20px; padding:14px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:10px; font-size:12px; color:var(--text-muted); text-align:left; }
.ban-evidence h4 { margin:0 0 8px; color:var(--text-light); font-size:13px; }
</style>
</head>
<body>
<div class="auth-grid"></div>
<div class="auth-wrap">
    <div class="auth-box ban-box">
        <div class="auth-logo">
            <span class="al-badge">🚫</span>
            <span class="al-title">UZDUB</span>
            <span class="al-sub">PLATFORM</span>
        </div>
        <span class="ban-icon">⛔</span>
        <h2 class="ban-title">Hisobingiz bloklangan</h2>
        <div class="ban-reason">
            <strong>Sabab:</strong>
            <?php echo e($reason); ?>
        </div>
        <?php if ($is_permanent): ?>
        <p class="ban-expires">⛔ Bu blok <b>doimiy</b>. Admin bilan bog'laning.</p>
        <?php elseif ($expires): ?>
        <p class="ban-expires">⏰ Blok tugash vaqti: <b><?php echo e(date('d.m.Y H:i', strtotime($expires))); ?></b></p>
        <?php else: ?>
        <p class="ban-expires">Blok vaqti noma'lum. Admin bilan bog'laning.</p>
        <?php endif; ?>

        <div class="ban-evidence">
            <h4>📋 Dalillar:</h4>
            <p><b>Sabab:</b> <?php echo e($reason); ?></p>
            <p><b>Blok turi:</b> <?php echo $is_permanent ? 'Doimiy' : 'Vaqtinchalik'; ?></p>
            <p><b>Sana:</b> <?php echo date('d.m.Y H:i'); ?></p>
            <p><b>IP manzil:</b> <?php echo e(client_ip()); ?></p>
        </div>

        <div class="alt-link" style="margin-top:20px;">
            <a href="<?php echo ROOT_URL; ?>/auth/login.php?reset=1">Boshqa hisob bilan kirish</a>
        </div>
    </div>
</div>
</body>
</html>
