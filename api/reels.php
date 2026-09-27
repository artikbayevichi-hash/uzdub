<?php
/**
 * Reels lentasi — sahifalab JSON qaytaradi.
 * GET ?offset=<n>&limit=<n>
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/reels.php';

$offset = (int)($_GET['offset'] ?? 0);
$limit = (int)($_GET['limit'] ?? REELS_PAGE_SIZE);
$user_id = is_user() ? (int)$_SESSION['user_id'] : null;

$items = reels_fetch($pdo, $offset, $limit, $user_id);

echo json_encode([
    'ok' => true,
    'items' => $items,
    'offset' => $offset + count($items),
    'has_more' => count($items) >= max(1, min(20, $limit)),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
