<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!is_user()) { echo json_encode(['error' => 'Unauthorized']); http_response_code(401); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$first_name = trim($input['first_name'] ?? '');
$last_name  = trim($input['last_name'] ?? '');
$dob        = trim($input['date_of_birth'] ?? '');

if ($first_name === '') {
    echo json_encode(['error' => 'Ism kiritilishi shart.']); exit;
}
if ($dob === '') {
    echo json_encode(['error' => 'Tug\'ulgan sana kiritilishi shart.']); exit;
}
if (mb_strlen($first_name) > 50) {
    echo json_encode(['error' => 'Ism 50 ta belgidan oshmasligi kerak.']); exit;
}
if (mb_strlen($last_name) > 50) {
    echo json_encode(['error' => 'Familiya 50 ta belgidan oshmasligi kerak.']); exit;
}
if (!preg_match('#^\d{4}-\d{2}-\d{2}$#', $dob)) {
    echo json_encode(['error' => 'Tug\'ulgan sana noto\'g\'ri formatda.']); exit;
}

$uid = $_SESSION['user_id'];
$stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, date_of_birth=? WHERE id=? AND first_name IS NULL");
$stmt->execute([$first_name, $last_name ?: null, $dob ?: null, $uid]);

echo json_encode(['ok' => true, 'first_name' => $first_name, 'last_name' => $last_name, 'date_of_birth' => $dob]);
