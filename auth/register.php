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
<style>
.auth-wrap { display:flex; align-items:center; justify-content:center; min-height:100vh; position:relative; overflow:hidden; }
.auth-orb { position:fixed; width:420px; height:420px; border-radius:50%; filter:blur(110px); opacity:.22; z-index:0; pointer-events:none; animation:authOrbFloat 14s ease-in-out infinite alternate; }
.auth-orb.o1 { background:var(--blue-primary); top:-140px; left:-120px; }
.auth-orb.o2 { background:#7c4dff; bottom:-160px; right:-120px; animation-delay:-7s; }
@keyframes authOrbFloat { from { transform:translate(0,0) scale(1); } to { transform:translate(36px,26px) scale(1.15); } }
.auth-box { width:400px; background:rgba(18,26,43,0.85); backdrop-filter:blur(14px); border:1px solid rgba(33,150,243,0.3); border-radius:16px; padding:38px; position:relative; z-index:1; box-shadow:0 24px 60px rgba(0,0,0,0.45); animation:authPop .5s cubic-bezier(.2,.8,.2,1) both; transition:transform .3s ease, box-shadow .3s ease; }
.auth-box:hover { transform:translateY(-3px); box-shadow:0 28px 70px rgba(0,0,0,0.5), 0 0 30px rgba(33,150,243,0.15); }
@keyframes authPop { from { opacity:0; transform:translateY(24px) scale(.96); } to { opacity:1; transform:translateY(0) scale(1); } }
.auth-box h1 { text-align:center; color:var(--blue-glow); margin-bottom:24px; font-size:26px; text-shadow:0 0 20px rgba(79,195,247,0.45); }
.auth-box label { display:block; margin:12px 0 5px; font-size:13px; color:var(--text-muted); }
.auth-box input { width:100%; padding:11px 14px; background:#0d1424; border:1px solid rgba(33,150,243,0.25); border-radius:7px; color:var(--text-light); font-size:14px; outline:none; transition:border-color .2s ease, box-shadow .2s ease; }
.auth-box input:focus { border-color:var(--blue-primary); box-shadow:0 0 0 3px rgba(33,150,243,0.15); }
.auth-box .btn { width:100%; padding:12px; background:var(--blue-primary); color:#fff; border:none; border-radius:7px; font-size:15px; font-weight:600; cursor:pointer; margin-top:18px; transition:transform .2s ease, box-shadow .2s ease, background .2s ease; }
.auth-box .btn:hover { background:var(--blue-glow); transform:translateY(-2px); box-shadow:0 10px 24px rgba(33,150,243,0.35); }
.auth-box .btn:active { transform:translateY(0); }
.auth-box .alt-link { text-align:center; margin-top:16px; font-size:13px; color:var(--text-muted); }
.auth-box .alt-link a { color:var(--blue-glow); text-decoration:none; }
.alert { padding:11px 15px; border-radius:7px; margin-bottom:14px; font-size:13px; animation:authPop .3s ease both; }
.alert-error { background:rgba(229,57,53,0.15); border:1px solid #e53935; color:#ef9a9a; }
.alert-success { background:rgba(33,150,243,0.12); border:1px solid #2196f3; color:#90caf9; }
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation:none !important; transition:none !important; } }
</style>
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
