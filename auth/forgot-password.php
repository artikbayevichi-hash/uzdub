<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/lang.php';

if (is_user()) { header('Location: /uzdub/index.php'); exit; }

$error = '';
$success = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = t('security_token_wrong');
    } else {
        $step = (int)($_POST['step'] ?? 1);
        $email = trim($_POST['email'] ?? '');

        if ($step === 1) {
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email noto\'g\'ri';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    $code = rand(100000, 999999);
                    $_SESSION['fp_code'] = $code;
                    $_SESSION['fp_user_id'] = $user['id'];
                    $_SESSION['fp_expires'] = time() + 300;

                    $subject = "UZDUB — Parolni tiklash kodi";
                    $message = "Tasdiqlash kodi: $code\n\nBu kod 5 daqiqa amal qiladi.\n\nUZDUB Platform";
                    $htmlMessage = email_layout('Parolni tiklash', email_paragraph('Parolingizni tiklash uchun quyidagi <b>tasdiqlash kodini</b> kiriting.') . email_code_card('Tasdiqlash kodi', $code, 'Bu kod 5 daqiqa amal qiladi.'));
                    send_email($email, $subject, $message, $htmlMessage);

                    $masked = substr($email, 0, 2) . str_repeat('*', max(0, strlen($email) - 6)) . substr($email, -4);
                    $_SESSION['fp_email_masked'] = $masked;
                    $step = 2;
                } else {
                    $step = 2;
                    $_SESSION['fp_email_masked'] = $email;
                }
            }
        } elseif ($step === 2) {
            $code = trim($_POST['code'] ?? '');
            if (empty($_SESSION['fp_code']) || empty($_SESSION['fp_user_id'])) {
                $error = t('otp_expired');
                $step = 1;
            } elseif (time() > ($_SESSION['fp_expires'] ?? 0)) {
                unset($_SESSION['fp_code'], $_SESSION['fp_user_id'], $_SESSION['fp_expires']);
                $error = t('otp_expired');
                $step = 1;
            } elseif ((string)$code !== (string)$_SESSION['fp_code']) {
                $error = t('otp_invalid');
                $step = 2;
            } else {
                unset($_SESSION['fp_code']);
                $_SESSION['fp_verified'] = true;
                $step = 3;
            }
        } elseif ($step === 3) {
            if (empty($_SESSION['fp_verified']) || empty($_SESSION['fp_user_id'])) {
                $error = 'Avval tasdiqlash kodini kiriting.';
                $step = 1;
            } else {
                $new_pass = $_POST['new_password'] ?? '';
                $conf_pass = $_POST['confirm_password'] ?? '';
                if (!$new_pass) {
                    $error = 'Yangi parol kiriting';
                    $step = 3;
                } elseif ($new_pass !== $conf_pass) {
                    $error = 'Parollar mos kelmaydi';
                    $step = 3;
                } elseif (mb_strlen($new_pass) < 6) {
                    $error = 'Parol kamida 6 ta belgi bo\'lishi kerak';
                    $step = 3;
                } else {
                    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $_SESSION['fp_user_id']]);
                    unset($_SESSION['fp_code'], $_SESSION['fp_user_id'], $_SESSION['fp_expires'], $_SESSION['fp_verified'], $_SESSION['fp_email_masked']);
                    $success = 'Parol muvaffaqiyatli yangilandi!';
                    $step = 4;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parolni tiklash - UZDUB PLATFORM</title>
<link rel="stylesheet" href="/uzdub/css/style.css">
<link rel="stylesheet" href="/uzdub/css/auth.css">
<link rel="stylesheet" href="/uzdub/css/emoji-blue.css">
<script src="/uzdub/js/emoji-blue.js" defer></script>
</head>
<body>
<div class="auth-grid"></div>
<div class="auth-wrap">
    <div class="auth-box">
        <div class="auth-logo">
            <span class="al-badge"><?php if ($step === 4): ?>✅<?php else: ?>🔒<?php endif; ?></span>
            <span class="al-title">UZDUB</span>
            <span class="al-sub">PLATFORM</span>
        </div>
        <h2>
            <?php if ($step === 1): ?>Parolni unutdingizmi?
            <?php elseif ($step === 2): ?>Tasdiqlash kodi
            <?php elseif ($step === 3): ?>Yangi parol o'rnating
            <?php elseif ($step === 4): ?>Tayyor!
            <?php endif; ?>
        </h2>

        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>

        <?php if ($step === 1): ?>
        <p class="auth-sub-text">Email manzilingizni kiriting. Sizga tasdiqlash kodi yuboramiz.</p>
        <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="step" value="1">
            <label>Email</label>
            <div class="field">
                <span class="field-icon">📧</span>
                <input type="email" name="email" placeholder="email@example.com" required autofocus>
            </div>
            <button type="submit" class="btn">Davom etish</button>
        </form>

        <?php elseif ($step === 2): ?>
        <p class="auth-sub-text">
            Kod <b style="color:var(--blue-primary,#2196f3);"><?php echo e($_SESSION['fp_email_masked'] ?? ''); ?></b> manziliga yuborildi.
        </p>
        <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="step" value="2">
            <label>6 xonali tasdiqlash kodi</label>
            <input type="text" name="code" class="code-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus autocomplete="one-time-code">
            <button type="submit" class="btn">Tasdiqlash</button>
        </form>
        <div class="alt-link" style="margin-top:10px;"><a href="forgot-password.php">Qaytadan yuborish</a></div>

        <?php elseif ($step === 3): ?>
        <p class="auth-sub-text">Yangi parolni kiriting.</p>
        <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="step" value="3">
            <label>Yangi parol</label>
            <div class="field">
                <span class="field-icon">🔑</span>
                <input type="password" name="new_password" id="fpPass1" class="has-toggle" required minlength="6" autocomplete="new-password">
                <button type="button" class="pass-toggle" data-target="fpPass1" aria-label="Parolni ko'rsatish">👁</button>
            </div>
            <label>Parolni tasdiqlash</label>
            <div class="field">
                <span class="field-icon">✅</span>
                <input type="password" name="confirm_password" id="fpPass2" class="has-toggle" required minlength="6" autocomplete="new-password">
                <button type="button" class="pass-toggle" data-target="fpPass2" aria-label="Parolni ko'rsatish">👁</button>
            </div>
            <button type="submit" class="btn">Saqlash</button>
        </form>

        <?php elseif ($step === 4): ?>
        <div class="verify-icon">✅</div>
        <p class="auth-sub-text">Parolingiz muvaffaqiyatli yangilandi. Endi yangi parol bilan kiring.</p>
        <a href="login.php" class="btn" style="display:block;text-decoration:none;text-align:center;">Tizimga kirish</a>

        <?php endif; ?>

        <div class="alt-link" style="margin-top:12px;"><a href="login.php">⬅️ Kirish sahifasiga qaytish</a></div>
    </div>
</div>
<script>
document.querySelectorAll('.pass-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var inp = document.getElementById(this.dataset.target);
        if (!inp) return;
        var show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        this.textContent = show ? '🙈' : '👁';
    });
});
</script>
</body>
</html>
