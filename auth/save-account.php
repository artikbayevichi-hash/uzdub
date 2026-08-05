<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$redirect = $_SESSION['login_redirect'] ?? '/uzdub/index.php';
unset($_SESSION['login_redirect']);

if (!preg_match('#^/uzdub/#', $redirect)) {
    $redirect = '/uzdub/index.php';
}

header('Location: ' . $redirect);
exit;
