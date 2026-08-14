<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in() && !is_ajax_request()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$login_ok = false;
$redirect_url = 'dashboard.php';

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
                $login_ok = true;
            } else {
                login_register_failed($pdo, $attempt_id);
                $error = 'Login yoki parol noto\'g\'ri.';
            }
        }
    }
}

// AJAX so'rovlar uchun (login sahifasidagi modal orqali kirish)
if (is_ajax_request()) {
    header('Content-Type: application/json; charset=utf-8');
    if ($login_ok) {
        echo json_encode(['ok' => true, 'redirect' => ROOT_URL . '/admin/' . $redirect_url], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin kirish - UZDUB PLATFORM</title>
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/style.css">
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/auth.css">
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/emoji-blue.css">
<script src="<?php echo ROOT_URL; ?>/js/emoji-blue.js" defer></script>
<style>
.auth-box { width: 400px; }
.auth-box h2 { margin-bottom: 24px; }
.admin-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: fit-content;
    margin: 0 auto 14px;
    padding: 5px 14px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: #90caf9;
    background: rgba(33,150,243,.12);
    border: 1px solid rgba(79,195,247,.35);
    border-radius: 20px;
    box-shadow: 0 0 18px rgba(33,150,243,.15);
}
.admin-shield { font-size: 17px; margin-right: 6px; }
@media (max-width: 600px) {
    .auth-box { width: 100%; max-width: 420px; padding: 26px 20px 26px; border-radius: 16px; }
}
@media (max-width: 380px) {
    .auth-box { padding: 22px 16px 22px; }
}
</style>
</head>
<body>
<div class="auth-grid"></div>
<div class="auth-wrap">
    <div class="auth-box">
        <div class="auth-logo">
            <span class="al-badge">🛡️</span>
            <span class="al-title">UZDUB</span>
            <span class="al-sub">PLATFORM · ADMIN</span>
        </div>
        <div class="admin-badge"><span class="admin-shield">🛡️</span>Boshqaruv paneli</div>
        <h2>Boshqaruv paneliga kirish</h2>
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <form method="post">
            <?php echo csrf_input(); ?>
            <label>Login</label>
            <div class="field">
                <span class="field-icon">👤</span>
                <input type="text" name="username" placeholder="Admin logini" required autofocus>
            </div>
            <label>Parol</label>
            <div class="field">
                <span class="field-icon">🔑</span>
                <input type="password" name="password" id="adminPass" class="has-toggle" placeholder="•••••••••" required>
                <button type="button" class="pass-toggle" data-target="adminPass" aria-label="Parolni ko'rsatish">👁</button>
            </div>
            <button type="submit" class="btn">Kirish</button>
        </form>
        <div class="alt-link"><a href="<?php echo ROOT_URL; ?>/auth/login.php">← Foydalanuvchi sifatida kirish</a></div>
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
