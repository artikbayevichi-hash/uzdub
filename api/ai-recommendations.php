<?php
/* ============================================================
   api/ai-recommendations.php
   Foydalanuvchiga uning qiziqishlari asosida kinolar tavsiya qilish
   ============================================================ */

session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    // Agar foydalanuvchi kirmagan bo'lsa, eng ko'p ko'rilganlarni qaytarish
    $stmt = $pdo->query("SELECT id, title, poster, release_year, rating FROM content ORDER BY views DESC LIMIT 6");
    echo json_encode(['recommendations' => $stmt->fetchAll()]);
    exit;
}

// 1) Foydalanuvchi oxirgi ko'rgan 3 ta kinosini olish
$stmt = $pdo->prepare("
    SELECT c.category_id, c.title 
    FROM watch_progress wp 
    JOIN content c ON wp.content_id = c.id 
    WHERE wp.user_id = ? 
    ORDER BY wp.updated_at DESC LIMIT 3
");
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

if (empty($history)) {
    $stmt = $pdo->query("SELECT id, title, poster, release_year, rating FROM content ORDER BY views DESC LIMIT 6");
    echo json_encode(['recommendations' => $stmt->fetchAll()]);
    exit;
}

$categoryIds = array_unique(array_column($history, 'category_id'));
$placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

// 2) O'xshash kategoriyadagi kinolarni topish (ko'rilganlarni chiqarib tashlash)
$sql = "
    SELECT id, title, poster, release_year, rating 
    FROM content 
    WHERE category_id IN ($placeholders) 
    AND id NOT IN (SELECT content_id FROM watch_progress WHERE user_id = ?)
    ORDER BY rating DESC, views DESC 
    LIMIT 6
";

$stmt = $pdo->prepare($sql);
$params = array_merge($categoryIds, [$userId]);
$stmt->execute($params);
$recommendations = $stmt->fetchAll();

// Agar tavsiyalar kam bo'lsa, trenddagilar bilan to'ldirish
if (count($recommendations) < 6) {
    $needed = 6 - count($recommendations);
    $excludeIds = array_column($recommendations, 'id');
    $excludeIds[] = 0; // Bo'sh bo'lsa xato bermasligi uchun
    $excludePlaceholders = implode(',', array_fill(0, count($excludeIds), '?'));
    
    $stmt = $pdo->prepare("SELECT id, title, poster, release_year, rating FROM content WHERE id NOT IN ($excludePlaceholders) ORDER BY views DESC LIMIT $needed");
    $stmt->execute($excludeIds);
    $recommendations = array_merge($recommendations, $stmt->fetchAll());
}

echo json_encode(['recommendations' => $recommendations], JSON_UNESCAPED_UNICODE);
