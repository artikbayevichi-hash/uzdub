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

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Login yoki parol noto\'g\'ri.';
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<title>Admin kirish - UZDUB</title>
<link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <h1>UZDUB Admin</h1>
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <form method="post">
            <label>Login</label>
            <input type="text" name="username" required autofocus>
            <label>Parol</label>
            <input type="password" name="password" required>
            <button type="submit" class="btn" style="width:100%;">Kirish</button>
        </form>
        <p style="text-align:center;margin-top:16px;font-size:13px;"><a href="/uzdub/auth/login.php" style="color:var(--blue-glow);text-decoration:none;">← Foydalanuvchi sifatida kirish</a></p>
    </div>
</div>
</body>
</html>
