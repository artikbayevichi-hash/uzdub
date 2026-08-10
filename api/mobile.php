<?php
session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('site_lang', $_GET['lang'], time() + 86400 * 365, '/', '', isset($_SERVER['HTTPS']), true);
}
$GLOBALS['current_lang'] = $_SESSION['lang'] ?? $_COOKIE['site_lang'] ?? 'uz';
$_GET['lang'] = $GLOBALS['current_lang'];

$action = $_GET['action'] ?? '';

function json_ok($data = []) {
    echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error($msg) {
    echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function require_auth() {
    if (!is_user()) {
        json_error('login_required');
    }
}

function get_input() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($ct, 'application/json') !== false) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }
        return $_POST;
    }
    return $_GET;
}

function build_content_query($pdo, $filters, $userId = null) {
    $page = max(1, (int)($filters['page'] ?? 1));
    $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];

    if (!empty($filters['category'])) {
        $where[] = 'cat.slug = ?';
        $params[] = $filters['category'];
    }

    if (!empty($filters['genre_id'])) {
        $where[] = 'cg.genre_id = ?';
        $params[] = (int)$filters['genre_id'];
    }

    if (!empty($filters['status'])) {
        $where[] = 'c.status = ?';
        $params[] = $filters['status'];
    }

    if (!empty($filters['search'])) {
        $s = '%' . $filters['search'] . '%';
        $where[] = '(c.title LIKE ? OR c.title_ru LIKE ? OR c.title_en LIKE ?)';
        $params[] = $s;
        $params[] = $s;
        $params[] = $s;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $favJoin = '';
    $watchJoin = '';
    if ($userId) {
        $favJoin = "LEFT JOIN user_content_status ucs ON ucs.content_id = c.id AND ucs.user_id = $userId AND ucs.status = 'favorite'";
        $watchJoin = "LEFT JOIN watchlist wl ON wl.content_id = c.id AND wl.user_id = $userId";
    }

    $genreJoin = !empty($filters['genre_id']) ? 'JOIN content_genres cg ON cg.content_id = c.id' : '';

    $countSql = "SELECT COUNT(DISTINCT c.id) FROM content c JOIN categories cat ON c.category_id = cat.id $genreJoin $whereClause";
    $cntStmt = $pdo->prepare($countSql);
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    $selectFav = $userId ? ', CASE WHEN ucs.id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite' : ', 0 AS is_favorite';
    $selectWatch = $userId ? ', CASE WHEN wl.id IS NOT NULL THEN 1 ELSE 0 END AS in_watchlist' : ', 0 AS in_watchlist';

    $sql = "SELECT c.id, c.title, c.title_ru, c.title_en, c.poster, c.release_year, c.rating,
                   c.description, c.content_code,
                   cat.name AS category_name, cat.slug AS category_slug,
                   c.duration, c.status, c.is_premium
                   $selectFav $selectWatch
            FROM content c
            JOIN categories cat ON c.category_id = cat.id
            $genreJoin
            $favJoin
            $watchJoin
            $whereClause
            ORDER BY c.id DESC
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        $item['is_favorite'] = (bool)$item['is_favorite'];
        $item['in_watchlist'] = (bool)$item['in_watchlist'];
        $item['is_premium'] = (bool)$item['is_premium'];
        $item['rating'] = $item['rating'] !== null ? (float)$item['rating'] : null;
    }

    return [
        'items' => $items,
        'total' => (int)$total,
        'page' => $page,
        'has_more' => ($offset + $limit) < $total,
    ];
}

try {
    switch ($action) {

        case 'ping':
            json_ok(['version' => '1.0']);

        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $input = get_input();
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';

            if (!$username || !$password) json_error('login_required');

            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                json_error('Invalid credentials');
            }

            $_SESSION['user_id'] = $user['id'];
            refresh_user_session($pdo, $user['id']);

            json_ok([
                'user' => [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'avatar' => $user['avatar'],
                    'is_premium' => (bool)$user['is_premium'],
                ]
            ]);

        case 'me':
            if (!is_user()) json_error('login_required');

            $stmt = $pdo->prepare("SELECT id, user_id, username, email, avatar, is_premium, created_at FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user) json_error('User not found');

            $user['is_premium'] = (bool)$user['is_premium'];
            json_ok(['user' => $user]);

        case 'logout':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $_SESSION = [];
            session_destroy();
            json_ok();

        case 'content_list':
            $userId = is_user() ? (int)$_SESSION['user_id'] : null;
            $result = build_content_query($pdo, [
                'category' => $_GET['category'] ?? null,
                'genre_id' => $_GET['genre_id'] ?? null,
                'status' => $_GET['status'] ?? null,
                'search' => $_GET['search'] ?? null,
                'page' => $_GET['page'] ?? 1,
                'limit' => $_GET['limit'] ?? 20,
            ], $userId);
            json_ok($result);

        case 'content_detail':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) json_error('Content ID required');

            $stmt = $pdo->prepare("SELECT c.*, cat.name AS category_name, cat.slug AS category_slug
                                   FROM content c
                                   JOIN categories cat ON c.category_id = cat.id
                                   WHERE c.id = ?");
            $stmt->execute([$id]);
            $content = $stmt->fetch();

            if (!$content) json_error('Content not found');

            $content['is_premium'] = (bool)$content['is_premium'];

            $genres = $pdo->prepare("SELECT g.id, g.name, g.slug, g.color FROM genres g
                                     JOIN content_genres cg ON cg.genre_id = g.id
                                     WHERE cg.content_id = ? ORDER BY g.name");
            $genres->execute([$id]);
            $content['genres'] = $genres->fetchAll();

            $actors = $pdo->prepare("SELECT id, name, role, image, sort_order FROM content_actors WHERE content_id = ? ORDER BY sort_order ASC");
            $actors->execute([$id]);
            $content['actors'] = $actors->fetchAll();

            $related = $pdo->prepare("SELECT c.id, c.title, c.title_ru, c.title_en, c.poster, c.release_year, c.rating,
                                             cat.name AS category_name, cat.slug AS category_slug, c.duration, c.status
                                      FROM related_content rc
                                      JOIN content c ON rc.related_id = c.id
                                      LEFT JOIN categories cat ON c.category_id = cat.id
                                      WHERE rc.content_id = ? LIMIT 12");
            $related->execute([$id]);
            $content['related'] = $related->fetchAll();

            $avgStmt = $pdo->prepare("SELECT ROUND(AVG(rating), 1) FROM ratings WHERE content_id = ?");
            $avgStmt->execute([$id]);
            $content['avg_rating'] = $avgStmt->fetchColumn();

            $userId = is_user() ? (int)$_SESSION['user_id'] : null;

            $content['user_rating'] = null;
            $content['is_favorite'] = false;
            $content['in_watchlist'] = false;
            $content['watch_progress'] = null;

            if ($userId) {
                $ur = $pdo->prepare("SELECT rating FROM ratings WHERE content_id = ? AND user_id = ?");
                $ur->execute([$id, $userId]);
                $r = $ur->fetchColumn();
                if ($r !== false) $content['user_rating'] = (int)$r;

                $fav = $pdo->prepare("SELECT id FROM user_content_status WHERE content_id = ? AND user_id = ? AND status = 'favorite'");
                $fav->execute([$id, $userId]);
                $content['is_favorite'] = (bool)$fav->fetch();

                $wl = $pdo->prepare("SELECT id FROM watchlist WHERE content_id = ? AND user_id = ?");
                $wl->execute([$id, $userId]);
                $content['in_watchlist'] = (bool)$wl->fetch();

                $wp = $pdo->prepare("SELECT position_seconds, duration_seconds FROM watch_progress WHERE content_id = ? AND user_id = ?");
                $wp->execute([$id, $userId]);
                $progress = $wp->fetch();
                if ($progress) {
                    $progress['position_seconds'] = (int)$progress['position_seconds'];
                    $progress['duration_seconds'] = (int)$progress['duration_seconds'];
                    $content['watch_progress'] = $progress;
                }
            }

            json_ok(['content' => $content]);

        case 'autocomplete':
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 2) json_ok(['items' => []]);

            $s = '%' . $q . '%';
            $stmt = $pdo->prepare("SELECT id, title, title_ru, title_en, poster, release_year, rating
                                   FROM content WHERE title LIKE ? OR title_ru LIKE ? OR title_en LIKE ?
                                   ORDER BY views DESC, rating DESC LIMIT 6");
            $stmt->execute([$s, $s, $s]);
            json_ok(['items' => $stmt->fetchAll()]);

        case 'search':
            $q = trim($_GET['q'] ?? '');
            if (!$q) json_error('Search query required');

            // Content code lookup (e.g. KN0001, AN0002, MF0003)
            $userId = is_user() ? (int)$_SESSION['user_id'] : null;
            if (preg_match('/^(KN|AN|MF|K|A|M)\d{2,6}$/i', $q)) {
                $stmt = $pdo->prepare("SELECT id FROM content WHERE content_code = ? LIMIT 1");
                $stmt->execute([$q]);
                $codeMatch = $stmt->fetchColumn();
                if ($codeMatch) {
                    json_ok(['content_id' => (int)$codeMatch]);
                }
            }

            $result = build_content_query($pdo, [
                'category' => $_GET['category'] ?? null,
                'search' => $q,
                'page' => $_GET['page'] ?? 1,
                'limit' => $_GET['limit'] ?? 20,
            ], $userId);
            json_ok($result);

        case 'categories':
            $stmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY id");
            json_ok(['categories' => $stmt->fetchAll()]);

        case 'genres':
            $stmt = $pdo->query("SELECT id, name, slug, color FROM genres ORDER BY name");
            json_ok(['genres' => $stmt->fetchAll()]);

        case 'toggle_favorite':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');

            $input = get_input();
            $contentId = (int)($input['content_id'] ?? 0);
            if (!$contentId) json_error('Content ID required');

            $userId = (int)$_SESSION['user_id'];

            $chk = $pdo->prepare("SELECT id, status FROM user_content_status WHERE user_id = ? AND content_id = ?");
            $chk->execute([$userId, $contentId]);
            $existing = $chk->fetch();

            if ($existing && $existing['status'] === 'favorite') {
                $pdo->prepare("DELETE FROM user_content_status WHERE id = ?")->execute([$existing['id']]);
                $pdo->prepare("DELETE FROM watchlist WHERE user_id = ? AND content_id = ?")->execute([$userId, $contentId]);
                json_ok(['is_favorite' => false]);
            } else {
                $pdo->prepare("INSERT INTO user_content_status (user_id, content_id, status) VALUES (?, ?, 'favorite') ON DUPLICATE KEY UPDATE status = 'favorite'")
                    ->execute([$userId, $contentId]);
                $pdo->prepare("INSERT IGNORE INTO watchlist (user_id, content_id) VALUES (?, ?)")->execute([$userId, $contentId]);
                json_ok(['is_favorite' => true]);
            }

        case 'toggle_watchlist':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');

            $input = get_input();
            $contentId = (int)($input['content_id'] ?? 0);
            if (!$contentId) json_error('Content ID required');

            $userId = (int)$_SESSION['user_id'];

            $chk = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND content_id = ?");
            $chk->execute([$userId, $contentId]);
            if ($chk->fetch()) {
                $pdo->prepare("DELETE FROM watchlist WHERE user_id = ? AND content_id = ?")->execute([$userId, $contentId]);
                json_ok(['in_watchlist' => false]);
            } else {
                $pdo->prepare("INSERT IGNORE INTO watchlist (user_id, content_id) VALUES (?, ?)")->execute([$userId, $contentId]);
                json_ok(['in_watchlist' => true]);
            }

        case 'register':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $input = get_input();
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';
            $email = trim($input['email'] ?? '');

            if (!$username || !$password) json_error('Username and password required');
            if (strlen($username) < 3) json_error('Username too short');
            if (strlen($password) < 4) json_error('Password too short');

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) json_error('Username already exists');

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hash, $email ?: null]);
            $userId = $pdo->lastInsertId();

            $_SESSION['user_id'] = $userId;
            json_ok([
                'user' => [
                    'id' => (int)$userId,
                    'username' => $username,
                    'avatar' => null,
                    'is_premium' => false,
                ]
            ]);

        case 'favorites':
            require_auth();
            $userId = (int)$_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT c.id, c.title, c.title_ru, c.title_en, c.poster, c.release_year, c.rating,
                                          cat.name AS category_name, cat.slug AS category_slug, c.duration, c.status, c.is_premium
                                   FROM user_content_status ucs
                                   JOIN content c ON c.id = ucs.content_id
                                   JOIN categories cat ON c.category_id = cat.id
                                   WHERE ucs.user_id = ? AND ucs.status = 'favorite'
                                   ORDER BY ucs.updated_at DESC LIMIT 50");
            $stmt->execute([$userId]);
            $items = $stmt->fetchAll();
            foreach ($items as &$item) {
                $item['is_premium'] = (bool)$item['is_premium'];
                $item['rating'] = $item['rating'] !== null ? (float)$item['rating'] : null;
            }
            json_ok(['items' => $items]);

        case 'watchlist_items':
            require_auth();
            $userId = (int)$_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT c.id, c.title, c.title_ru, c.title_en, c.poster, c.release_year, c.rating,
                                          cat.name AS category_name, cat.slug AS category_slug, c.duration, c.status, c.is_premium
                                   FROM watchlist wl
                                   JOIN content c ON c.id = wl.content_id
                                   JOIN categories cat ON c.category_id = cat.id
                                   WHERE wl.user_id = ?
                                   ORDER BY wl.created_at DESC LIMIT 50");
            $stmt->execute([$userId]);
            $items = $stmt->fetchAll();
            foreach ($items as &$item) {
                $item['is_premium'] = (bool)$item['is_premium'];
                $item['rating'] = $item['rating'] !== null ? (float)$item['rating'] : null;
            }
            json_ok(['items' => $items]);

        case 'save_progress':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');

            $input = get_input();
            $contentId = (int)($input['content_id'] ?? 0);
            $episodeId = (int)($input['episode_id'] ?? 0);
            $position = max(0, (int)($input['position_seconds'] ?? 0));
            $duration = max(0, (int)($input['duration_seconds'] ?? 0));

            if (!$contentId) json_error('Content ID required');

            $userId = (int)$_SESSION['user_id'];

            $is_completed = $duration > 0 && $position >= $duration - 600;

            $pdo->prepare("INSERT INTO watch_progress (user_id, content_id, episode_id, position_seconds, duration_seconds, is_completed)
                           VALUES (?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE position_seconds = VALUES(position_seconds), duration_seconds = VALUES(duration_seconds), is_completed = GREATEST(is_completed, VALUES(is_completed))")
                ->execute([$userId, $contentId, $episodeId, $position, $duration, $is_completed ? 1 : 0]);

            $pdo->prepare("INSERT INTO watch_history (user_id, content_id, episode_id, progress_seconds) VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE watched_at = CURRENT_TIMESTAMP, progress_seconds = VALUES(progress_seconds)")
                ->execute([$userId, $contentId, $episodeId, $position]);

            if ($is_completed) {
                mark_content_watched($pdo, $userId, $contentId, $episodeId);
            }

            json_ok();

        case 'watch_history':
            require_auth();

            $userId = (int)$_SESSION['user_id'];

            $stmt = $pdo->prepare("SELECT c.id, c.title, c.title_ru, c.title_en, c.poster, c.release_year,
                                          c.rating, cat.name AS category_name, cat.slug AS category_slug,
                                          c.duration, c.status, c.is_premium,
                                          wp.position_seconds, wp.duration_seconds, MAX(wh.watched_at) AS watched_at
                                   FROM watch_history wh
                                   JOIN content c ON c.id = wh.content_id
                                   JOIN categories cat ON c.category_id = cat.id
                                   LEFT JOIN watch_progress wp ON wp.id = (SELECT w2.id FROM watch_progress w2 WHERE w2.user_id = wh.user_id AND w2.content_id = wh.content_id ORDER BY w2.updated_at DESC, w2.id DESC LIMIT 1)
                                   WHERE wh.user_id = ?
                                   GROUP BY c.id
                                   ORDER BY watched_at DESC
                                   LIMIT 50");
            $stmt->execute([$userId]);
            $items = $stmt->fetchAll();

            foreach ($items as &$item) {
                $item['is_premium'] = (bool)$item['is_premium'];
                $item['rating'] = $item['rating'] !== null ? (float)$item['rating'] : null;
                $item['position_seconds'] = isset($item['position_seconds']) ? (int)$item['position_seconds'] : null;
                $item['duration_seconds'] = isset($item['duration_seconds']) ? (int)$item['duration_seconds'] : null;
            }

            json_ok(['items' => $items]);

        case 'notifications':
            require_auth();
            $uid = (int)$_SESSION['user_id'];
            $nAction = $_GET['subaction'] ?? ($input['subaction'] ?? 'list');
            if ($nAction === 'unread_count') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                $stmt->execute([$uid]);
                json_ok(['count' => (int)$stmt->fetchColumn()]);
            }
            if ($nAction === 'mark_read') {
                $nid = (int)($input['notification_id'] ?? 0);
                if ($nid) $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$nid, $uid]);
                json_ok();
            }
            if ($nAction === 'mark_all_read') {
                $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$uid]);
                json_ok();
            }
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = 30;
            $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
            $stmt->execute([$uid]);
            $total = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT n.*, u.username AS sender_name FROM notifications n LEFT JOIN users u ON n.sender_id = u.id WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute([$uid]);
            $rows = $stmt->fetchAll();
            json_ok(['notifications' => $rows, 'total' => $total]);

        case 'toggle_comment_like':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $input = get_input();
            $commentId = (int)($input['comment_id'] ?? 0);
            $type = $input['type'] ?? 'like';
            if (!$commentId || !in_array($type, ['like', 'dislike'])) json_error('Invalid params');
            $uid = (int)$_SESSION['user_id'];
            $check = $pdo->prepare("SELECT id, type FROM likes WHERE user_id = ? AND comment_id = ?");
            $check->execute([$uid, $commentId]);
            $existing = $check->fetch();
            if ($existing) {
                if ($existing['type'] === $type) {
                    $pdo->prepare("DELETE FROM likes WHERE id = ?")->execute([$existing['id']]);
                } else {
                    $pdo->prepare("UPDATE likes SET type = ? WHERE id = ?")->execute([$type, $existing['id']]);
                }
            } else {
                $pdo->prepare("INSERT INTO likes (user_id, comment_id, type) VALUES (?, ?, ?)")->execute([$uid, $commentId, $type]);
            }
            $likeCount = (int)$pdo->query("SELECT COUNT(*) FROM likes WHERE comment_id = $commentId AND type = 'like'")->fetchColumn();
            $dislikeCount = (int)$pdo->query("SELECT COUNT(*) FROM likes WHERE comment_id = $commentId AND type = 'dislike'")->fetchColumn();
            $userLikeStmt = $pdo->prepare("SELECT type FROM likes WHERE user_id = ? AND comment_id = ?");
            $userLikeStmt->execute([$uid, $commentId]);
            $userLike = $userLikeStmt->fetchColumn();
            json_ok(['likes' => $likeCount, 'dislikes' => $dislikeCount, 'user_like' => $userLike]);

        case 'heartbeat':
            $onl = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE last_activity >= NOW() - INTERVAL 5 MINUTE")->fetchColumn();
            $result = ['online_count' => $onl];
            if (is_user()) {
                $uid = (int)$_SESSION['user_id'];
                $elapsed = max(0, min(120, (int)($_GET['elapsed'] ?? 0)));
                if ($elapsed > 0) {
                    $pdo->prepare("UPDATE users SET last_activity = NOW(), online_time = online_time + ? WHERE id = ?")->execute([$elapsed, $uid]);
                } else {
                    $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?")->execute([$uid]);
                }
                $stmt = $pdo->prepare("SELECT online_time FROM users WHERE id = ?");
                $stmt->execute([$uid]);
                $result['online_time'] = (int)$stmt->fetchColumn();
            }
            json_ok($result);

        case 'forgot_password':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $input = get_input();
            $fpAction = $input['action'] ?? '';
            if ($fpAction === 'send_code') {
                $email = trim($input['email'] ?? '');
                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Email noto\'g\'ri');
                $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if (!$user) json_ok(['message' => 'Agar bu email ro\'yxatdan o\'tgan bo\'lsa, tasdiqlash kodi yuborildi.']);
                $code = rand(100000, 999999);
                $_SESSION['fp_code'] = $code;
                $_SESSION['fp_user_id'] = $user['id'];
                $_SESSION['fp_expires'] = time() + 300;
                $subject = "UZDUB — Parolni tiklash kodi";
                $msg = "Tasdiqlash kodi: $code\n\nBu kod 5 daqiqa amal qiladi.\n\nUZDUB Platform";
                $htmlMsg = email_layout('Parolni tiklash', email_paragraph('Parolingizni tiklash uchun quyidagi <b>tasdiqlash kodini</b> kiriting.') . email_code_card('Tasdiqlash kodi', $code, 'Bu kod 5 daqiqa amal qiladi.'));
                send_email($email, $subject, $msg, $htmlMsg);
                $masked = substr($email, 0, 2) . str_repeat('*', max(0, strlen($email) - 6)) . substr($email, -4);
                json_ok(['masked_email' => $masked, 'message' => "Kod $masked ga yuborildi"]);
            }
            if ($fpAction === 'verify_code') {
                $code = trim($input['code'] ?? '');
                if (empty($_SESSION['fp_code']) || empty($_SESSION['fp_user_id'])) json_error('Kod muddati tugagan');
                if (time() > ($_SESSION['fp_expires'] ?? 0)) {
                    unset($_SESSION['fp_code'], $_SESSION['fp_user_id'], $_SESSION['fp_expires']);
                    json_error('Kod muddati tugagan');
                }
                if ((string)$code !== (string)$_SESSION['fp_code']) json_error('Noto\'g\'ri kod');
                $_SESSION['fp_verified'] = true;
                unset($_SESSION['fp_code']);
                json_ok(['message' => 'Kod tasdiqlandi']);
            }
            if ($fpAction === 'reset_password') {
                $newPass = $input['new_password'] ?? '';
                $confPass = $input['confirm_password'] ?? '';
                if (empty($_SESSION['fp_verified']) || empty($_SESSION['fp_user_id'])) json_error('Avval tasdiqlang');
                if (!$newPass || $newPass !== $confPass) json_error('Parollar mos kelmaydi');
                if (mb_strlen($newPass) < 6) json_error('Parol kamida 6 belgi');
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $_SESSION['fp_user_id']]);
                unset($_SESSION['fp_code'], $_SESSION['fp_user_id'], $_SESSION['fp_expires'], $_SESSION['fp_verified']);
                json_ok(['message' => 'Parol yangilandi!']);
            }
            json_error('Unknown forgot_password action');

        case 'get_sessions':
            require_auth();
            $uid = (int)$_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT id, user_agent, ip_address, last_activity FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC");
            $stmt->execute([$uid]);
            $sessions = $stmt->fetchAll();
            $parsed = array_map(function($s) {
                $ua = $s['user_agent'];
                $os = 'Unknown'; $browser = 'Unknown'; $device = 'desktop';
                if (preg_match('/Windows/i', $ua)) $os = 'Windows';
                elseif (preg_match('/Mac|Mac OS/i', $ua)) $os = 'macOS';
                elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';
                elseif (preg_match('/Android/i', $ua)) $os = 'Android';
                elseif (preg_match('/iPhone|iPad/i', $ua)) $os = 'iOS';
                if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) $device = 'mobile';
                if (preg_match('/Chrome\/(\d+)/i', $ua, $m)) $browser = 'Chrome';
                elseif (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
                elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';
                elseif (preg_match('/Edge/i', $ua)) $browser = 'Edge';
                return ['id' => (int)$s['id'], 'browser' => $browser, 'os' => $os, 'device' => $device, 'ip' => $s['ip_address'], 'last_active' => $s['last_activity']];
            }, $sessions);
            json_ok(['sessions' => $parsed]);

        case 'logout_session':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $input = get_input();
            $sid = (int)($input['session_id'] ?? 0);
            if ($sid) $pdo->prepare("DELETE FROM user_sessions WHERE id = ? AND user_id = ?")->execute([$sid, (int)$_SESSION['user_id']]);
            json_ok();

        case 'logout_all_sessions':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([(int)$_SESSION['user_id']]);
            json_ok();

        case 'update_profile':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $input = get_input();
            $uid = (int)$_SESSION['user_id'];
            if (!empty($input['username'])) {
                $uname = trim($input['username']);
                $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $chk->execute([$uname, $uid]);
                if ($chk->fetch()) json_error('Username already taken');
                $pdo->prepare("UPDATE users SET username = ? WHERE id = ?")->execute([$uname, $uid]);
            }
            if (!empty($input['avatar_url'])) {
                $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$input['avatar_url'], $uid]);
            }
            $stmt = $pdo->prepare("SELECT id, username, email, avatar, is_premium FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $user = $stmt->fetch();
            $user['is_premium'] = (bool)$user['is_premium'];
            json_ok(['user' => $user]);

        case 'upload_avatar':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) json_error('Rasm yuklashda xatolik');
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($_FILES['avatar']['type'], $allowed)) json_error('Faqat JPG, PNG, WebP');
            if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) json_error('Rasm 2MB dan kichik bo\'lishi kerak');
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) json_error('Noto\'g\'ri fayl kengaytmasi');
            $img = @getimagesize($_FILES['avatar']['tmp_name']);
            if ($img === false) json_error('Fayl haqiqiy rasm emas');
            $fname = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $dest = __DIR__ . '/../uploads/avatars/' . $fname;
            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) json_error('Faylni saqlashda xatolik');
            $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$fname, $_SESSION['user_id']]);
            json_ok(['avatar' => $fname]);

        case 'send_otp':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $input = get_input();
            $uid = (int)$_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT email, password, two_factor_enabled, telegram_chat_id FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $user = $stmt->fetch();
            $type = $input['type'] ?? '';
            $validTypes = ['email', 'password', 'pre-verify-email', 'pre-verify-password'];
            if (!in_array($type, $validTypes)) json_error('Invalid type');
            // Email bilan bog'liq o'zgarishlar 2 bosqichli himoya + Telegram bot kodini talab qiladi
            if ($type === 'pre-verify-email' || $type === 'email') {
                if (empty($user['two_factor_enabled'])) json_error('Emailni o\'zgartirish uchun avval 2 bosqichli himoyani yoqing');
                if (empty($user['telegram_chat_id'])) json_error('Telegram botga ulanmagan. Avval 2 bosqichli himoyani yoqing va Telegram botni ulang.');
            }
            if (in_array($type, ['email', 'password'])) {
                $currPass = $input['current_password'] ?? '';
                if (!$currPass || !password_verify($currPass, $user['password'])) json_error('Joriy parol noto\'g\'ri');
            }
            $targetEmail = $user['email'];
            if ($type === 'email') {
                $newEmail = trim($input['new_email'] ?? '');
                if (!$newEmail || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) json_error('Noto\'g\'ri email');
                if (strtolower($newEmail) === strtolower($user['email'])) json_error('Bu sizning hozirgi emailingiz');
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $chk->execute([$newEmail, $uid]);
                if ($chk->fetch()) json_error('Bu email band');
                $targetEmail = $newEmail;
            }
            $code = rand(100000, 999999);
            $_SESSION['otp_code'] = $code;
            $_SESSION['otp_pending'] = $input;
            $_SESSION['otp_expires'] = time() + 300;
            if ($type === 'pre-verify-email' || $type === 'email') {
                require_once __DIR__ . '/../config/payment.php';
                $sent = tg_2fa_send_code($user['telegram_chat_id'], $code);
                $delivered = false;
                if (is_string($sent) && $sent !== '') { $tg = json_decode($sent); $delivered = !empty($tg->ok); }
                if (!$delivered) {
                    unset($_SESSION['otp_code'], $_SESSION['otp_pending'], $_SESSION['otp_expires']);
                    json_error('Kodni Telegram botga yuborib bo\'lmadi');
                }
                json_ok(['message' => 'Tasdiqlash kodi Telegram botingizga yuborildi']);
            }
            $subject = "UZDUB — Tasdiqlash kodi";
            $msg = "Tasdiqlash kodi: $code\n\n5 daqiqa amal qiladi.\n\nUZDUB Platform";
            $htmlMsg = email_layout('Tasdiqlash kodi', email_paragraph('Hisobingizda amal qiladigan amal uchun quyidagi <b>tasdiqlash kodini</b> kiriting.') . email_code_card('Tasdiqlash kodi', $code, 'Bu kod 5 daqiqa amal qiladi.'));
            send_email($targetEmail, $subject, $msg, $htmlMsg);
            $masked = substr($targetEmail, 0, 2) . str_repeat('*', max(0, strlen($targetEmail) - 6)) . substr($targetEmail, -4);
            json_ok(['masked_email' => $masked, 'message' => "Kod $masked ga yuborildi"]);

        case 'verify_otp':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $input = get_input();
            $code = trim($input['code'] ?? '');
            if (empty($_SESSION['otp_code']) || empty($_SESSION['otp_pending'])) json_error('Kod muddati tugagan');
            if (time() > ($_SESSION['otp_expires'] ?? 0)) {
                unset($_SESSION['otp_code'], $_SESSION['otp_pending'], $_SESSION['otp_expires']);
                json_error('Kod muddati tugagan');
            }
            if ((string)$code !== (string)$_SESSION['otp_code']) json_error('Noto\'g\'ri kod');
            $pending = $_SESSION['otp_pending'];
            $uid = (int)$_SESSION['user_id'];
            $type = $pending['type'] ?? '';
            if ($type === 'pre-verify-email' || $type === 'pre-verify-password') {
                $_SESSION['settings_verified_for'] = $type === 'pre-verify-email' ? 'email' : 'password';
                $_SESSION['settings_verified_at'] = time();
                unset($_SESSION['otp_code'], $_SESSION['otp_pending'], $_SESSION['otp_expires']);
                json_ok(['success_type' => $type]);
            }
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $user = $stmt->fetch();
            $currPass = $pending['current_password'] ?? '';
            if (!$currPass || !password_verify($currPass, $user['password'])) json_error('Parol noto\'g\'ri');
            if ($type === 'email') {
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $chk->execute([$pending['new_email'], $uid]);
                if ($chk->fetch()) json_error('Email band');
                $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$pending['new_email'], $uid]);
                unset($_SESSION['otp_code'], $_SESSION['otp_pending'], $_SESSION['otp_expires']);
                json_ok(['success_type' => 'email', 'message' => 'Email yangilandi', 'new_email' => $pending['new_email']]);
            }
            if ($type === 'password') {
                $newPass = $pending['new_password'] ?? '';
                if (!$newPass || mb_strlen($newPass) < 6) json_error('Parol juda qisqa');
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $uid]);
                unset($_SESSION['otp_code'], $_SESSION['otp_pending'], $_SESSION['otp_expires']);
                json_ok(['success_type' => 'password', 'message' => 'Parol yangilandi']);
            }
            json_error('Unknown type');

        case 'ai_recommendations':
            $userId = is_user() ? (int)$_SESSION['user_id'] : null;
            if (!$userId) {
                $stmt = $pdo->query("SELECT id, title, poster, release_year, rating FROM content ORDER BY views DESC LIMIT 6");
                json_ok(['recommendations' => $stmt->fetchAll()]);
            }
            $stmt = $pdo->prepare("SELECT c.category_id FROM watch_progress wp JOIN content c ON wp.content_id = c.id WHERE wp.user_id = ? ORDER BY wp.updated_at DESC LIMIT 3");
            $stmt->execute([$userId]);
            $history = $stmt->fetchAll();
            if (empty($history)) {
                $stmt = $pdo->query("SELECT id, title, poster, release_year, rating FROM content ORDER BY views DESC LIMIT 6");
                json_ok(['recommendations' => $stmt->fetchAll()]);
            }
            $catIds = array_unique(array_column($history, 'category_id'));
            $placeholders = implode(',', array_fill(0, count($catIds), '?'));
            $params = $catIds;
            $params[] = $userId;
            $sql = "SELECT id, title, poster, release_year, rating FROM content WHERE category_id IN ($placeholders) AND id NOT IN (SELECT content_id FROM watch_progress WHERE user_id = ?) ORDER BY rating DESC, views DESC LIMIT 6";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $recs = $stmt->fetchAll();
            if (count($recs) < 6) {
                $needed = 6 - count($recs);
                $excludeIds = array_column($recs, 'id');
                $excludeIds[] = 0;
                $exPh = implode(',', array_fill(0, count($excludeIds), '?'));
                $stmt = $pdo->prepare("SELECT id, title, poster, release_year, rating FROM content WHERE id NOT IN ($exPh) ORDER BY views DESC LIMIT $needed");
                $stmt->execute($excludeIds);
                $recs = array_merge($recs, $stmt->fetchAll());
            }
            json_ok(['recommendations' => $recs]);

        case 'genre_content':
            $rawGenres = explode(',', $_GET['genres'] ?? '');
            $rawGenres = array_map('trim', array_filter($rawGenres));
            if (empty($rawGenres)) json_error('Genres required');
            $gPage = max(1, (int)($_GET['page'] ?? 1));
            $gLimit = min(50, max(6, (int)($_GET['limit'] ?? 18)));
            $gSort = $_GET['sort'] ?? 'newest';
            $gCategory = $_GET['category'] ?? '';
            $gOffset = ($gPage - 1) * $gLimit;
            $gPlaceholders = implode(',', array_fill(0, count($rawGenres), '?'));
            $gStmt = $pdo->prepare("SELECT id, name, slug, color FROM genres WHERE slug IN ($gPlaceholders)");
            $gStmt->execute($rawGenres);
            $gGenres = $gStmt->fetchAll();
            if (empty($gGenres)) json_error('No matching genres');
            $genreIds = array_column($gGenres, 'id');
            $idPh = implode(',', array_fill(0, count($genreIds), '?'));
            $where = "cg.genre_id IN ($idPh)";
            $gParams = $genreIds;
            if (in_array($gCategory, ['kino', 'anime', 'multfilm'])) {
                $where .= " AND cat.slug = ?";
                $gParams[] = $gCategory;
            }
            $orderBy = match($gSort) { 'rating' => 'c.rating DESC', 'popular' => 'c.views DESC', 'title' => 'c.title ASC', default => 'c.created_at DESC' };
            $selectedCount = count($genreIds);
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM (SELECT c.id FROM content c JOIN content_genres cg ON c.id = cg.content_id JOIN categories cat ON c.category_id = cat.id WHERE $where GROUP BY c.id HAVING COUNT(DISTINCT cg.genre_id) = $selectedCount) sub");
            $countStmt->execute($gParams);
            $gTotal = (int)$countStmt->fetchColumn();
            $dataStmt = $pdo->prepare("SELECT c.id, c.title, c.title_ru, c.title_en, c.poster, c.release_year, c.rating, c.status, c.is_premium, cat.name AS cat_name, cat.slug AS cat_slug FROM content c JOIN content_genres cg ON c.id = cg.content_id JOIN categories cat ON c.category_id = cat.id WHERE $where GROUP BY c.id HAVING COUNT(DISTINCT cg.genre_id) = $selectedCount ORDER BY $orderBy LIMIT $gLimit OFFSET $gOffset");
            $dataStmt->execute($gParams);
            $gItems = $dataStmt->fetchAll();
            foreach ($gItems as &$item) { $item['is_premium'] = (bool)$item['is_premium']; }
            json_ok(['genres' => $gGenres, 'items' => $gItems, 'total' => $gTotal, 'has_more' => ($gOffset + $gLimit) < $gTotal]);

        case 'premium_plans':
            json_ok(['plans' => PREMIUM_PLANS]);

        case 'apply_promo':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $input = get_input();
            $code = strtoupper(trim($input['code'] ?? ''));
            if (!$code) json_error('Promo kod kiriting');
            if (!function_exists('redeem_promo_code')) {
                if (file_exists(__DIR__ . '/../config/payment.php')) require_once __DIR__ . '/../config/payment.php';
            }
            $result = redeem_promo_code($pdo, $code, (int)$_SESSION['user_id']);
            if ($result['ok']) {
                $stmt = $pdo->prepare("SELECT id, username, email, avatar, is_premium FROM users WHERE id = ?");
                $stmt->execute([(int)$_SESSION['user_id']]);
                $u = $stmt->fetch();
                $u['is_premium'] = (bool)$u['is_premium'];
                $result['user'] = $u;
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;

        case 'get_2fa_status':
            require_auth();
            $stmt = $pdo->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
            $stmt->execute([(int)$_SESSION['user_id']]);
            $enabled = (bool)$stmt->fetchColumn();
            json_ok(['enabled' => $enabled]);

        case 'post_comment':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $input = get_input();
            $cContentId = (int)($input['content_id'] ?? 0);
            $cText = trim($input['text'] ?? '');
            $cParentId = $input['parent_id'] ?? null;
            if (!$cContentId || !$cText) json_error('Izoh bo\'sh');
            if (mb_strlen($cText) > 1000) json_error('Izoh juda uzun');
            $pdo->prepare("INSERT INTO comments (user_id, content_id, parent_id, comment) VALUES (?,?,?,?)")
                ->execute([(int)$_SESSION['user_id'], $cContentId, $cParentId ? (int)$cParentId : null, $cText]);
            json_ok();

        case 'rate_content':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $input = get_input();
            $rContentId = (int)($input['content_id'] ?? 0);
            $rRating = (int)($input['rating'] ?? 0);
            if (!$rContentId || $rRating < 1 || $rRating > 5) json_error('Noto\'g\'ri reyting');
            $pdo->prepare("INSERT INTO ratings (user_id, content_id, rating) VALUES (?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating)")
                ->execute([(int)$_SESSION['user_id'], $rContentId, $rRating]);
            json_ok();

        case 'get_comments':
            $contentId = (int)($_GET['content_id'] ?? 0);
            if (!$contentId) json_error('Content ID required');
            $stmt = $pdo->prepare("SELECT c.*, u.username, u.avatar AS user_avatar FROM comments c JOIN users u ON c.user_id = u.id WHERE c.content_id = ? AND c.parent_id = 0 ORDER BY c.created_at DESC LIMIT 50");
            $stmt->execute([$contentId]);
            $comments = $stmt->fetchAll();
            foreach ($comments as &$c) {
                $c['time_ago'] = time_ago($c['created_at']);
                $rStmt = $pdo->prepare("SELECT r.*, u2.username FROM comments r JOIN users u2 ON r.user_id = u2.id WHERE r.parent_id = ? ORDER BY r.created_at ASC");
                $rStmt->execute([$c['id']]);
                $replies = $rStmt->fetchAll();
                foreach ($replies as &$r) { $r['time_ago'] = time_ago($r['created_at']); }
                $c['replies'] = $replies;
                $likeC = (int)$pdo->query("SELECT COUNT(*) FROM likes WHERE comment_id = {$c['id']} AND type = 'like'")->fetchColumn();
                $dislikeC = (int)$pdo->query("SELECT COUNT(*) FROM likes WHERE comment_id = {$c['id']} AND type = 'dislike'")->fetchColumn();
                $c['likes'] = $likeC;
                $c['dislikes'] = $dislikeC;
                $c['user_like'] = null;
                if (is_user()) {
                    $ul = $pdo->prepare("SELECT type FROM likes WHERE user_id = ? AND comment_id = ?");
                    $ul->execute([(int)$_SESSION['user_id'], $c['id']]);
                    $c['user_like'] = $ul->fetchColumn();
                }
            }
            json_ok(['comments' => $comments]);

        case 'delete_comment':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $input = get_input();
            $cid = (int)($input['comment_id'] ?? 0);
            $uid = (int)$_SESSION['user_id'];
            if ($cid) $pdo->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?")->execute([$cid, $uid]);
            json_ok();

        case 'ai_chat_status':
            require_auth();
            $stmt = $pdo->prepare("SELECT is_premium FROM users WHERE id = ?");
            $stmt->execute([(int)$_SESSION['user_id']]);
            json_ok(['premium' => (bool)$stmt->fetchColumn()]);

        case 'ai_chat_list':
            require_auth();
            $stmt = $pdo->prepare("SELECT cs.id, cs.title, cs.updated_at, (SELECT COUNT(*) FROM ai_chat_messages m WHERE m.session_id = cs.id) AS msg_count FROM ai_chat_sessions cs WHERE cs.user_id = ? ORDER BY cs.updated_at DESC");
            $stmt->execute([(int)$_SESSION['user_id']]);
            json_ok(['sessions' => $stmt->fetchAll()]);

        case 'ai_chat_create':
            require_auth();
            $pdo->prepare("INSERT INTO ai_chat_sessions (user_id, title) VALUES (?, 'Yangi chat')")->execute([(int)$_SESSION['user_id']]);
            json_ok(['session_id' => (int)$pdo->lastInsertId()]);

        case 'ai_chat_history':
            require_auth();
            $sid = (int)($_GET['session_id'] ?? 0);
            $uid = (int)$_SESSION['user_id'];
            if (!$sid) json_error('session_id talab qilinadi');
            $own = $pdo->prepare("SELECT id FROM ai_chat_sessions WHERE id = ? AND user_id = ?");
            $own->execute([$sid, $uid]);
            if (!$own->fetch()) json_error('Chat sessiyasi topilmadi');
            $stmt = $pdo->prepare("SELECT role, message, created_at FROM ai_chat_messages WHERE session_id = ? ORDER BY id ASC");
            $stmt->execute([$sid]);
            json_ok(['messages' => $stmt->fetchAll()]);

        case 'ai_chat_delete':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            $input = get_input();
            $sid = (int)($input['session_id'] ?? 0);
            $uid = (int)$_SESSION['user_id'];
            if (!$sid) json_error('session_id talab qilinadi');
            $pdo->prepare("DELETE FROM ai_chat_messages WHERE session_id = ? AND user_id = ?")->execute([$sid, $uid]);
            $pdo->prepare("DELETE FROM ai_chat_sessions WHERE id = ? AND user_id = ?")->execute([$sid, $uid]);
            json_ok();

        case 'ai_chat_send':
            require_auth();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed');
            if (!defined('AI_PROVIDERS')) require_once __DIR__ . '/../config/ai_secrets.php';
            $input = get_input();
            $msg = trim($input['message'] ?? '');
            $sid = (int)($input['session_id'] ?? 0);
            $lang = in_array($input['lang'] ?? '', ['uz', 'ru', 'en']) ? $input['lang'] : 'uz';
            if ($msg === '') json_error("Xabar bo'sh bo'lishi mumkin emas.");
            if (!$sid) json_error('session_id talab qilinadi');
            $uid = (int)$_SESSION['user_id'];

            $chk = $pdo->prepare("SELECT is_premium FROM users WHERE id = ?");
            $chk->execute([$uid]);
            if (!$chk->fetchColumn()) json_error('AI chat faqat Premium foydalanuvchilar uchun.');

            $own = $pdo->prepare("SELECT id FROM ai_chat_sessions WHERE id = ? AND user_id = ?");
            $own->execute([$sid, $uid]);
            if (!$own->fetch()) json_error('Chat sessiyasi topilmadi');

            if (!function_exists('ai_queue_try_acquire') || !ai_queue_try_acquire()) {
                json_error('AI hozir band, biroz kuting...');
            }
            register_shutdown_function('ai_queue_release');

            $match = findBestMatches($pdo, $msg, AI_MAX_RECOMMENDATIONS, $uid);
            $context = ai_build_context_text($match['rows']);
            $systemPrompt = ai_build_system_prompt($lang);
            $userContext = ai_build_user_context($pdo, $uid, $lang);

            $stmt = $pdo->prepare("SELECT role, message FROM ai_chat_messages WHERE user_id = :uid AND session_id = :sid ORDER BY id DESC LIMIT :lim");
            $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
            $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
            $stmt->bindValue(':lim', AI_HISTORY_MESSAGES, PDO::PARAM_INT);
            $stmt->execute();
            $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            foreach ($history as $h) $messages[] = ['role' => $h['role'], 'content' => $h['message']];
            $messages[] = ['role' => 'user', 'content' => $msg . $context . $userContext];

            $fullText = '';
            foreach (AI_PROVIDERS as $provider) {
                if (empty($provider['key'])) continue;
                $payload = [
                    'model' => $provider['model'],
                    'messages' => $messages,
                    'stream' => false,
                    'temperature' => 0.6,
                    'max_tokens' => OLLAMA_NUM_PREDICT,
                ];
                $ch = curl_init($provider['url']);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $provider['key']],
                    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => $provider['timeout'],
                ]);
                $resp = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($resp !== false && $httpCode === 200) {
                    $data = json_decode($resp, true);
                    $fullText = trim($data['choices'][0]['message']['content'] ?? '');
                    if ($fullText !== '') break;
                }
            }

            if ($fullText === '') {
                $payload = [
                    'model' => OLLAMA_MODEL,
                    'messages' => $messages,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.6,
                        'num_ctx' => OLLAMA_NUM_CTX,
                        'num_predict' => OLLAMA_NUM_PREDICT,
                        'num_thread' => OLLAMA_NUM_THREAD,
                    ],
                ];
                $ch = curl_init(OLLAMA_URL);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => OLLAMA_TIMEOUT,
                ]);
                $resp = curl_exec($ch);
                curl_close($ch);
                if ($resp !== false) {
                    $data = json_decode($resp, true);
                    $fullText = trim($data['message']['content'] ?? '');
                }
            }

            if ($fullText === '') {
                json_error("AI hozircha javob bera olmadi. Birozdan so'ng qaytadan urinib ko'ring.");
            }

            $cleanText = trim(preg_replace(['/\*{1,2}/', '/`+/'], '', $fullText));

            $cnt = $pdo->prepare("SELECT COUNT(*) FROM ai_chat_messages WHERE session_id = ?");
            $cnt->execute([$sid]);
            if ((int)$cnt->fetchColumn() === 0) {
                $t = mb_substr($msg, 0, 30) . (mb_strlen($msg) > 30 ? '...' : '');
                $pdo->prepare("UPDATE ai_chat_sessions SET title = ? WHERE id = ? AND user_id = ?")->execute([$t, $sid, $uid]);
            }

            $pdo->prepare("INSERT INTO ai_chat_messages (session_id, user_id, role, message) VALUES (?,?, 'user', ?)")->execute([$sid, $uid, $msg]);
            $pdo->prepare("INSERT INTO ai_chat_messages (session_id, user_id, role, message) VALUES (?,?, 'assistant', ?)")->execute([$sid, $uid, $cleanText]);

            json_ok([
                'reply' => $cleanText,
                'recommendations' => $match['matched'] ? ai_build_recommendations($match['rows']) : [],
            ]);

        default:
            json_error('Unknown action');
    }
} catch (PDOException $e) {
    error_log('mobile.php error: ' . $e->getMessage());
    json_error('An error occurred');
}
