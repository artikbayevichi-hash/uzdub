<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$uid = $_SESSION['verify_switch_uid'] ?? '';
$token = $_SESSION['verify_switch_token'] ?? '';
$username = $_SESSION['verify_switch_username'] ?? '';

if (!$uid || !$token) {
    header('Location: /uzdub/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND switch_token = ?");
$stmt->execute([$uid, $token]);
$user = $stmt->fetch();

if (!$user) {
    unset($_SESSION['verify_switch_uid'], $_SESSION['verify_switch_token'], $_SESSION['verify_switch_username']);
    header('Location: /uzdub/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik tokeni noto\'g\'ri. Sahifani yangilab qayta urinib ko\'ring.';
    } else {
        $password = $_POST['password'] ?? '';
        if (password_verify($password, $user['password'])) {
            unset($_SESSION['verify_switch_uid'], $_SESSION['verify_switch_token'], $_SESSION['verify_switch_username']);
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
            check_premium_expiry($pdo, $user['id']);
            refresh_user_session($pdo, $user['id']);
            header('Location: /uzdub/index.php');
            exit;
        }
        $error = 'Parol noto\'g\'ri.';
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Xavfsizlik tekshiruvi - UZDUB PLATFORM</title>
<link rel="stylesheet" href="/uzdub/css/style.css">
<style>
.auth-wrap { display:flex; align-items:center; justify-content:center; min-height:100vh; position:relative; overflow:hidden; }
.auth-orb { position:fixed; width:420px; height:420px; border-radius:50%; filter:blur(110px); opacity:.22; z-index:0; pointer-events:none; animation:authOrbFloat 14s ease-in-out infinite alternate; }
.auth-orb.o1 { background:var(--blue-primary); top:-140px; left:-120px; }
.auth-orb.o2 { background:#7c4dff; bottom:-160px; right:-120px; animation-delay:-7s; }
@keyframes authOrbFloat { from { transform:translate(0,0) scale(1); } to { transform:translate(36px,26px) scale(1.15); } }
.auth-box { width:380px; background:rgba(18,26,43,0.85); backdrop-filter:blur(14px); border:1px solid rgba(33,150,243,0.3); border-radius:16px; padding:38px; position:relative; z-index:1; box-shadow:0 24px 60px rgba(0,0,0,0.45); animation:authPop .5s cubic-bezier(.2,.8,.2,1) both; transition:transform .3s ease, box-shadow .3s ease; }
.auth-box:hover { transform:translateY(-3px); box-shadow:0 28px 70px rgba(0,0,0,0.5), 0 0 30px rgba(33,150,243,0.15); }
@keyframes authPop { from { opacity:0; transform:translateY(24px) scale(.96); } to { opacity:1; transform:translateY(0) scale(1); } }
.auth-box h1 { text-align:center; color:var(--blue-glow); margin-bottom:6px; font-size:26px; text-shadow:0 0 20px rgba(79,195,247,0.45); }
.auth-box h2 { text-align:center; font-size:17px; margin-bottom:22px; color:var(--text-muted); font-weight:400; }
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
.alert-info { background:rgba(33,150,243,0.15); border:1px solid #2196f3; color:#90caf9; }
.verify-icon { text-align:center; font-size:48px; margin-bottom:12px; }
.verify-sub { text-align:center; font-size:13px; color:var(--text-muted); margin-bottom:20px; line-height:1.5; }
</style>
</head>
<body>
<canvas id="stars-canvas"></canvas>
<div class="auth-orb o1"></div>
<div class="auth-orb o2"></div>
<div class="auth-wrap">
    <div class="auth-box">
        <div class="verify-icon">🔒</div>
        <h1>Xavfsizlik tekshiruvi</h1>
        <h2><?php echo e($username); ?></h2>
        <div class="alert alert-info">Bu akkauntga 7 kundan ortiq vaqt kirilmagan. Davom etish uchun parolni kiriting.</div>
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <form method="post">
            <?php echo csrf_input(); ?>
            <label>Parol</label>
            <input type="password" name="password" placeholder="Parolni kiriting" required autofocus>
            <button type="submit" class="btn">Tekshirish</button>
        </form>
        <div class="alt-link"><a href="/uzdub/index.php">← Bosh sahifaga qaytish</a></div>
    </div>
</div>
<script src="/uzdub/js/stars.js"></script>
</body>
</html>
