<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $attempt_id = 'admin:' . client_ip() . ':' . mb_strtolower($username);

    if (login_is_locked($pdo, $attempt_id)) {
        $error = 'Juda ko\'p muvaffaqiyatsiz urinish. ' . LOGIN_LOCKOUT_MINUTES . ' daqiqadan so\'ng qayta urinib ko\'ring.';
    } else {
        if (!validate_csrf($_POST['csrf_token'] ?? '')) {
            $error = 'Xavfsizlik tokeni noto\'g\'ri. Sahifani yangilab qayta urinib ko\'ring.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
            login_clear_attempts($pdo, $attempt_id);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            session_regenerate_id(true);
            header('Location: dashboard.php');
            exit;
        } else {
            login_register_failed($pdo, $attempt_id);
            $error = 'Login yoki parol noto\'g\'ri.';
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
<title>Admin kirish - UZDUB PLATFORM</title>
<link rel="stylesheet" href="/uzdub/css/style.css">
<link rel="stylesheet" href="/uzdub/css/auth.css">
<style>
.auth-orb.o1 { background:#ff6f00; }
.auth-orb.o2 { background:#f9a825; }
.auth-box { border-color:rgba(249,168,37,0.3); width:380px; }
.auth-box:hover { box-shadow:0 28px 70px rgba(0,0,0,0.5), 0 0 30px rgba(249,168,37,0.15); }
.auth-box h1 { color:#f9a825; text-shadow:0 0 20px rgba(249,168,37,0.45); }
.auth-box input { border-color:rgba(249,168,37,0.25); }
.auth-box input:focus { border-color:#f9a825; box-shadow:0 0 0 3px rgba(249,168,37,0.15); }
.auth-box .btn { background:linear-gradient(135deg,#f9a825,#ff6f00); }
.auth-box .btn:hover { box-shadow:0 10px 24px rgba(249,168,37,0.35); }
.auth-box .alt-link a { color:#f9a825; }
</style>
</head>
<body>
<canvas id="stars-canvas"></canvas>
<div class="auth-orb o1"></div>
<div class="auth-orb o2"></div>
<div class="auth-wrap">
    <div class="auth-box">
        <h1>🛡️ UZDUB PLATFORM Admin</h1>
        <h2>Boshqaruv paneliga kirish</h2>
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <form method="post">
            <?php echo csrf_input(); ?>
            <label>Login</label>
            <input type="text" name="username" required autofocus>
            <label>Parol</label>
            <input type="password" name="password" required>
            <button type="submit" class="btn">Kirish</button>
        </form>
        <div class="alt-link"><a href="/uzdub/auth/login.php">← Foydalanuvchi sifatida kirish</a></div>
    </div>
</div>
<script src="/uzdub/js/stars.js"></script>
</body>
</html>
