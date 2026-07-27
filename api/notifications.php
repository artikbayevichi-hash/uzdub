<?php
session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_user()) { echo json_encode(['error' => 'unauthorized']); exit; }

$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'unread_count') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$uid]);
        echo json_encode(['count' => (int)$stmt->fetchColumn()]);
        exit;
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 30;
    $offset = ($page - 1) * $limit;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
    $countStmt->execute([$uid]);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT n.*, u.username AS sender_name, u.avatar AS sender_avatar
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$uid, $limit, $offset]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['time_ago'] = time_ago($r['created_at']);
        if ($r['sender_avatar']) {
            $r['sender_avatar_url'] = avatar_url($r['sender_avatar']);
        }
    }

    echo json_encode([
        'notifications' => $rows,
        'total' => $total,
        'unread' => (int)array_reduce($rows, function ($carry, $r) { return $carry + ($r['is_read'] ? 0 : 1); }, 0),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $act = $input['action'] ?? '';

    if (!validate_csrf($input['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'msg' => 'CSRF xato']);
        exit;
    }

    if ($act === 'mark_read') {
        $nid = (int)($input['notification_id'] ?? 0);
        if ($nid) {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$nid, $uid]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($act === 'mark_all_read') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$uid]);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Noto\'g\'ri action']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
