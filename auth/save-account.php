<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$redirect = $_SESSION['login_redirect'] ?? ROOT_URL . '/index.php';
unset($_SESSION['login_redirect']);

if (!preg_match('#^' . preg_quote(ROOT_URL, '#') . '/#', $redirect)) {
    $redirect = ROOT_URL . '/index.php';
}

header('Location: ' . $redirect);
exit;
