<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$new_account = isset($_GET['new']);
if (is_user() && !$new_account) { header('Location: /uzdub/index.php'); exit; }

$error = '';
$redirect = $_GET['redirect'] ?? '/uzdub/index.php';
if (!preg_match('#^/uzdub/[^/\\\\].*#', $redirect) && $redirect !== '/uzdub/index.php') {
    $redirect = '/uzdub/index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $attempt_id = 'user:' . client_ip() . ':' . mb_strtolower($login);

    if (login_is_locked($pdo, $attempt_id)) {
        $error = 'Juda ko\'p muvaffaqiyatsiz urinish. ' . LOGIN_LOCKOUT_MINUTES . ' daqiqadan so\'ng qayta urinib ko\'ring.';
    } else {

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? OR email=?");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        login_clear_attempts($pdo, $attempt_id);
        $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
        check_premium_expiry($pdo, $user['id']);
        refresh_user_session($pdo, $user['id']);
        $token = generate_switch_token($pdo, $user['id']);
        $_SESSION['switch_token'] = $token;
        $_SESSION['switch_user_id'] = $user['user_id'];
        $_SESSION['login_redirect'] = $redirect;
        header('Location: /uzdub/auth/save-account.php');
        exit;
    }

    $admin_stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $admin_stmt->execute([$login]);
    $admin = $admin_stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        login_clear_attempts($pdo, $attempt_id);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        header('Location: /uzdub/admin/dashboard.php');
        exit;
    }

    login_register_failed($pdo, $attempt_id);
    $error = 'Login yoki parol noto\'g\'ri.';
    }
}

$google_client_id = env('GOOGLE_CLIENT_ID', '');
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kirish - UZDUB PLATFORM</title>
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
.alert-warning { background:rgba(255,152,0,0.15); border:1px solid #ff9800; color:#ffcc80; }
.auth-divider { display:flex; align-items:center; gap:12px; margin:20px 0; color:var(--text-muted); font-size:13px; }
.auth-divider::before, .auth-divider::after { content:''; flex:1; height:1px; background:rgba(33,150,243,0.2); }
.google-btn { display:flex; align-items:center; justify-content:center; gap:10px; width:100%; padding:12px; background:#fff; color:#333; border:1px solid #ddd; border-radius:7px; font-size:15px; font-weight:600; cursor:pointer; transition:all .2s ease; text-decoration:none; }
.google-btn:hover { background:#f5f5f5; transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,0.15); }
.google-btn.disabled { opacity:0.6; cursor:not-allowed; pointer-events:none; }
.google-btn svg { width:20px; height:20px; }
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
        <h2>Tizimga kirish</h2>
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <?php if (!$google_client_id): ?>
        <div class="alert alert-warning">⚠ Google kirish hali sozlanmagan. Admin panel'dan .env faylini to'ldiring.</div>
        <?php endif; ?>
        <a class="google-btn <?php if (!$google_client_id) echo 'disabled'; ?>" href="<?php echo $google_client_id ? '/uzdub/auth/google-login.php' : '#'; ?>" <?php if (!$google_client_id): ?>onclick="alert('Google OAuth sozlanmagan. Admin bilan bog\'laning.'); return false;"<?php endif; ?>>
            <svg viewBox="0 0 24 24" width="20" height="20"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            Google orqali kirish
        </a>
        <div class="auth-divider"><span>yoki</span></div>
        <form method="post">
            <label>Login yoki Email</label>
            <input type="text" name="login" placeholder="ali123 yoki email@example.com" required autofocus>
            <label>Parol</label>
            <input type="password" name="password" required>
            <button type="submit" class="btn">Kirish</button>
        </form>
        <div class="alt-link">Hisobingiz yo'qmi? <a href="register.php<?php echo $new_account ? '?new=1' : ''; ?>">Ro'yxatdan o'tish</a></div>
        <div class="alt-link" style="margin-top:6px;"><a href="/uzdub/admin/login.php" style="color:var(--text-muted);">🔐 Men adminman</a></div>
    </div>
</div>
<script src="/uzdub/js/stars.js"></script>
</body>
</html>
