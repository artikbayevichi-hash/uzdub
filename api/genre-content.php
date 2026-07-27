<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$raw_genres = $_GET['genres'] ?? $_GET['genre'] ?? '';
if (is_string($raw_genres)) $raw_genres = explode(',', $raw_genres);
$raw_genres = array_map('trim', array_filter((array)$raw_genres));
$genre_slugs = array_values($raw_genres);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(6, (int)($_GET['limit'] ?? 18)));
$sort = $_GET['sort'] ?? 'newest';
$category = $_GET['category'] ?? '';
$offset = ($page - 1) * $limit;
$selected_count = count($genre_slugs);

if ($selected_count === 0) {
    echo json_encode(['ok' => false, 'error' => 'genre parameter required']);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, $selected_count, '?'));

    $genreStmt = $pdo->prepare("SELECT * FROM genres WHERE slug IN ($placeholders)");
    $genreStmt->execute($genre_slugs);
    $genres = $genreStmt->fetchAll();

    if (empty($genres)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'No matching genres found']);
        exit;
    }

    $genre_ids = array_column($genres, 'id');
    $id_placeholders = implode(',', array_fill(0, count($genre_ids), '?'));

    $where = "cg.genre_id IN ($id_placeholders)";
    $params = $genre_ids;

    $allowedCats = ['kino', 'anime', 'multfilm'];
    if (in_array($category, $allowedCats)) {
        $where .= " AND cat.slug = ?";
        $params[] = $category;
    }

    $orderBy = match($sort) {
        'rating' => 'c.rating DESC, c.title ASC',
        'year_desc' => 'c.release_year DESC, c.title ASC',
        'year_asc' => 'c.release_year ASC, c.title ASC',
        'popular' => 'c.views DESC, c.title ASC',
        'title' => 'c.title ASC',
        default => 'c.created_at DESC, c.title ASC',
    };

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM (
            SELECT c.id FROM content c
            JOIN content_genres cg ON c.id = cg.content_id
            JOIN categories cat ON c.category_id = cat.id
            WHERE $where
            GROUP BY c.id
            HAVING COUNT(DISTINCT cg.genre_id) = $selected_count
        ) sub
    ");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $limit));

    $dataStmt = $pdo->prepare("
        SELECT c.id, c.title, c.title_ru, c.title_en, c.poster, c.poster_thumb,
               c.release_year, c.rating, c.views, c.status, c.is_series, c.is_premium,
               cat.name AS cat_name, cat.slug AS cat_slug
        FROM content c
        JOIN content_genres cg ON c.id = cg.content_id
        JOIN categories cat ON c.category_id = cat.id
        WHERE $where
        GROUP BY c.id
        HAVING COUNT(DISTINCT cg.genre_id) = $selected_count
        ORDER BY $orderBy
        LIMIT $limit OFFSET $offset
    ");
    $dataStmt->execute($params);
    $items = $dataStmt->fetchAll();

    foreach ($items as &$item) {
        $item['display_title'] = t_title($item);
    }

    echo json_encode([
        'ok' => true,
        'genres' => $genres,
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
