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
    $attempt_id = 'switch_verify:' . client_ip() . ':' . $user['id'];
    if (login_is_locked($pdo, $attempt_id)) {
        $error = 'Juda ko\'p noto\'g\'ri urinish. ' . LOGIN_LOCKOUT_MINUTES . ' daqiqadan so\'ng qayta urinib ko\'ring.';
    } elseif (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik tokeni noto\'g\'ri. Sahifani yangilab qayta urinib ko\'ring.';
    } else {
        $password = $_POST['password'] ?? '';
        if (password_verify($password, $user['password'])) {
            login_clear_attempts($pdo, $attempt_id);
            unset($_SESSION['verify_switch_uid'], $_SESSION['verify_switch_token'], $_SESSION['verify_switch_username']);
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
            check_premium_expiry($pdo, $user['id']);
            refresh_user_session($pdo, $user['id']);
            session_regenerate_id(true);
            header('Location: /uzdub/index.php');
            exit;
        }
        login_register_failed($pdo, $attempt_id);
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
<link rel="stylesheet" href="/uzdub/css/auth.css">
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
