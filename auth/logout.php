<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Faol sessiya qatorini o'chirish (ro'yxatda qoldiq qolmasligi uchun)
if (is_user() && !empty($_SESSION['user_id'])) {
    try {
        $token = session_id();
        if ($token) {
            $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_token = ?")
                ->execute([(int)$_SESSION['user_id'], $token]);
        }
    } catch (PDOException $e) {}
}

session_regenerate_id(true);
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
header('Location: /uzdub/auth/login.php');
exit;
