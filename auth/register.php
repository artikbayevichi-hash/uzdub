<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$new_account = isset($_GET['new']);
if (is_user() && !$new_account) { header('Location: ' . ROOT_URL . '/index.php'); exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$username || !$email || !$password) {
        $error = 'Barcha maydonlarni to\'ldiring.';
    } elseif (strlen($username) < 3 || strlen($username) > 30) {
        $error = 'Foydalanuvchi nomi 3-30 ta belgi bo\'lishi kerak.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email noto\'g\'ri.';
    } elseif (strlen($password) < 6) {
        $error = 'Parol kamida 6 ta belgi bo\'lishi kerak.';
    } elseif ($password !== $confirm) {
        $error = 'Parollar mos emas.';
    } else {
        if (!validate_csrf($_POST['csrf_token'] ?? '')) {
            $error = 'Xavfsizlik tokeni noto\'g\'ri. Sahifani yangilab qayta urinib ko\'ring.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
            $chk->execute([$username, $email]);
            if ($chk->fetch()) {
                $error = 'Bu foydalanuvchi nomi yoki email allaqachon band.';
            } else {
            $uid  = generate_user_id($pdo);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (user_id, username, email, password) VALUES (?,?,?,?)");
            $stmt->execute([$uid, $username, $email, $hash]);
            $new_id = $pdo->lastInsertId();
            $pdo->prepare("UPDATE users SET new_since = last_login_at, last_login_at = NOW() WHERE id = ?")->execute([$new_id]);
            refresh_user_session($pdo, $new_id);
            session_regenerate_id(true);
            record_user_session($pdo, $new_id);
            header('Location: ' . ROOT_URL . '/auth/save-account.php');
            exit;
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
<title>Ro'yxatdan o'tish - UZDUB PLATFORM</title>
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../css/style.css') ?: 1; ?>">
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/auth.css?v=<?php echo @filemtime(__DIR__ . '/../css/auth.css') ?: 1; ?>">
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/emoji-blue.css?v=<?php echo @filemtime(__DIR__ . '/../css/emoji-blue.css') ?: 1; ?>">
<script src="<?php echo ROOT_URL; ?>/js/emoji-blue.js" defer></script>
</head>
<body>
<div class="auth-grid"></div>
<div class="auth-wrap">
    <div class="auth-box">
        <div class="auth-logo">
            <span class="al-badge">🎬</span>
            <span class="al-title">UZDUB</span>
            <span class="al-sub">PLATFORM</span>
        </div>
        <h2>Ro'yxatdan o'tish</h2>
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php else: ?>
        <form method="post">
            <?php echo csrf_input(); ?>
            <label>Foydalanuvchi nomi</label>
            <div class="field">
                <span class="field-icon">👤</span>
                <input type="text" name="username" placeholder="Ali123" value="<?php echo e($_POST['username'] ?? ''); ?>" required autofocus>
            </div>
            <label>Email</label>
            <div class="field">
                <span class="field-icon">📧</span>
                <input type="email" name="email" placeholder="email@example.com" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
            </div>
            <label>Parol</label>
            <div class="field">
                <span class="field-icon">🔑</span>
                <input type="password" name="password" id="regPass1" class="has-toggle" placeholder="Kamida 6 belgi" required>
                <button type="button" class="pass-toggle" data-target="regPass1" aria-label="Parolni ko'rsatish">👁</button>
            </div>
            <label>Parolni tasdiqlash</label>
            <div class="field">
                <span class="field-icon">✅</span>
                <input type="password" name="confirm" id="regPass2" class="has-toggle" placeholder="Parolni qayta kiriting" required>
                <button type="button" class="pass-toggle" data-target="regPass2" aria-label="Parolni ko'rsatish">👁</button>
            </div>
            <button type="submit" class="btn">Ro'yxatdan o'tish</button>
        </form>
        <?php endif; ?>
        <div class="alt-link">Hisobingiz bormi? <a href="login.php">Kirish</a></div>
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
<script src="<?php echo ROOT_URL; ?>/js/auth-particles.js?v=<?php echo @filemtime(__DIR__ . '/../js/auth-particles.js') ?: 1; ?>" defer></script>
</body>
</html>
