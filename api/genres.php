<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->query("
        SELECT g.id, g.name, g.slug, g.color,
               COUNT(cg.content_id) AS count
        FROM genres g
        LEFT JOIN content_genres cg ON g.id = cg.genre_id
        GROUP BY g.id
        ORDER BY g.name ASC
    ");
    $genres = $stmt->fetchAll();

    echo json_encode(['ok' => true, 'genres' => $genres], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
