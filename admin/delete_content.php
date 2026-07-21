<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

// Endi faqat POST + CSRF token orqali (oldin GET orqali tokensiz o'chirish mumkin edi)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf($_POST['csrf_token'] ?? '')) {
    header('Location: list_content.php?error=csrf');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $stmt = $pdo->prepare("DELETE FROM content WHERE id = ?");
    $stmt->execute([$id]);
}
header('Location: list_content.php');
exit;
