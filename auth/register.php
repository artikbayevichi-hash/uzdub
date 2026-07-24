<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$new_account = isset($_GET['new']);
if (is_user() && !$new_account) { header('Location: /uzdub/index.php'); exit; }

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
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$new_id]);
            refresh_user_session($pdo, $new_id);
            session_regenerate_id(true);
            $token = generate_switch_token($pdo, $new_id);
            $_SESSION['switch_token'] = $token;
            $_SESSION['switch_user_id'] = $uid;
            header('Location: /uzdub/auth/save-account.php');
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
<link rel="stylesheet" href="/uzdub/css/style.css">
<link rel="stylesheet" href="/uzdub/css/auth.css">
</head>
<body>
<canvas id="stars-canvas"></canvas>
<div class="auth-orb o1"></div>
<div class="auth-orb o2"></div>
<div class="auth-wrap">
    <div class="auth-box">
        <h1>🎬 UZDUB PLATFORM</h1>
        <h2 style="text-align:center;font-size:18px;margin-bottom:20px;color:var(--text-muted);">Ro'yxatdan o'tish</h2>
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php else: ?>
        <form method="post">
            <?php echo csrf_input(); ?>
            <label>Foydalanuvchi nomi</label>
            <input type="text" name="username" placeholder="Ali123" value="<?php echo e($_POST['username'] ?? ''); ?>" required>
            <label>Email</label>
            <input type="email" name="email" placeholder="email@example.com" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
            <label>Parol</label>
            <input type="password" name="password" placeholder="Kamida 6 belgi" required>
            <label>Parolni tasdiqlash</label>
            <input type="password" name="confirm" placeholder="Parolni qayta kiriting" required>
            <button type="submit" class="btn">Ro'yxatdan o'tish</button>
        </form>
        <?php endif; ?>
        <div class="alt-link">Hisobingiz bormi? <a href="login.php">Kirish</a></div>
    </div>
</div>
<script src="/uzdub/js/stars.js"></script>
</body>
</html>
