<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_user()) { header('Location: /uzdub/index.php'); exit; }

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
        // Mavjudligini tekshirish
        $chk = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $chk->execute([$username, $email]);
        if ($chk->fetch()) {
            $error = 'Bu foydalanuvchi nomi yoki email allaqachon band.';
        } else {
            $uid  = generate_user_id($pdo);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (user_id, username, email, password) VALUES (?,?,?,?)");
            $stmt->execute([$uid, $username, $email, $hash]);
            $success = "Muvaffaqiyatli ro'yxatdan o'tdingiz! ID: <b>$uid</b><br><a href='login.php'>Kirish &rarr;</a>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ro'yxatdan o'tish - UZDUB</title>
<link rel="stylesheet" href="/uzdub/css/style.css">
<style>
.auth-wrap { display:flex; align-items:center; justify-content:center; min-height:100vh; }
.auth-box { width:400px; background:var(--card-bg); border:1px solid rgba(33,150,243,0.3); border-radius:14px; padding:38px; }
.auth-box h1 { text-align:center; color:var(--blue-glow); margin-bottom:24px; font-size:26px; }
.auth-box label { display:block; margin:12px 0 5px; font-size:13px; color:var(--text-muted); }
.auth-box input { width:100%; padding:11px 14px; background:#0d1424; border:1px solid rgba(33,150,243,0.25); border-radius:7px; color:var(--text-light); font-size:14px; outline:none; }
.auth-box input:focus { border-color:var(--blue-primary); }
.auth-box .btn { width:100%; padding:12px; background:var(--blue-primary); color:#fff; border:none; border-radius:7px; font-size:15px; font-weight:600; cursor:pointer; margin-top:18px; }
.auth-box .btn:hover { background:var(--blue-glow); }
.auth-box .alt-link { text-align:center; margin-top:16px; font-size:13px; color:var(--text-muted); }
.auth-box .alt-link a { color:var(--blue-glow); text-decoration:none; }
.alert { padding:11px 15px; border-radius:7px; margin-bottom:14px; font-size:13px; }
.alert-error { background:rgba(229,57,53,0.15); border:1px solid #e53935; color:#ef9a9a; }
.alert-success { background:rgba(33,150,243,0.12); border:1px solid #2196f3; color:#90caf9; }
</style>
</head>
<body>
<canvas id="stars-canvas"></canvas>
<div class="auth-wrap">
    <div class="auth-box">
        <h1>🎬 UZDUB</h1>
        <h2 style="text-align:center;font-size:18px;margin-bottom:20px;color:var(--text-muted);">Ro'yxatdan o'tish</h2>
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php else: ?>
        <form method="post">
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
