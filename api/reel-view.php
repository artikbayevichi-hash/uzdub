<?php
/**
 * Reel ko'rishlar sonini oshiradi (bir sessiyada har bir reel bir marta).
 * POST {reel_id}
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$reel_id = (int)($input['reel_id'] ?? 0);
if ($reel_id <= 0) { echo json_encode(['ok' => false]); exit; }

$seen = $_SESSION['reels_viewed'] ?? [];
if (isset($seen[$reel_id])) { echo json_encode(['ok' => true, 'counted' => false]); exit; }

$seen[$reel_id] = time();
$_SESSION['reels_viewed'] = $seen;

try {
    $pdo->prepare("UPDATE reels SET views = views + 1 WHERE id = ?")->execute([$reel_id]);
    echo json_encode(['ok' => true, 'counted' => true]);
} catch (PDOException $e) {
    error_log('reel-view error: ' . $e->getMessage());
    echo json_encode(['ok' => false]);
}
