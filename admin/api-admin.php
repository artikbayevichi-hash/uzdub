<?php
session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) { echo json_encode(['ok' => false, 'msg' => 'Ruxsat yo\'q']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';
$admin_id = $_SESSION['admin_id'] ?? 0;

// === GET actions ===
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'user_devices') {
        $uid = (int)($_GET['user_id'] ?? 0);
        if (!$uid) { echo json_encode(['ok' => false]); exit; }
        echo json_encode(['ok' => true, 'devices' => get_user_devices($pdo, $uid)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'user_activity') {
        $uid = (int)($_GET['user_id'] ?? 0);
        if (!$uid) { echo json_encode(['ok' => false]); exit; }
        $date_from = $_GET['date_from'] ?? null;
        $date_to = $_GET['date_to'] ?? null;
        $action_filter = $_GET['action_filter'] ?? null;
        $acts = get_user_activity($pdo, $uid, 500, 0, $date_from, $date_to, $action_filter);

        $content_cache = [];
        $comment_cache = [];

        foreach ($acts as &$a) {
            $a['time_ago'] = time_ago($a['created_at']);
            $a['created_at_fmt'] = date('d.m.Y H:i:s', strtotime($a['created_at']));
            $a['date_only'] = date('Y-m-d', strtotime($a['created_at']));

            if ($a['target_type'] === 'content' && $a['target_id']) {
                if (!isset($content_cache[$a['target_id']])) {
                    try {
                        $cstmt = $pdo->prepare("SELECT id, title, title_ru, title_en, poster, content_code FROM content WHERE id = ?");
                        $cstmt->execute([$a['target_id']]);
                        $content_cache[$a['target_id']] = $cstmt->fetch() ?: null;
                    } catch (Throwable $e) { $content_cache[$a['target_id']] = null; }
                }
                $a['target_data'] = $content_cache[$a['target_id']];
            } elseif (($a['target_type'] === 'comment') && $a['target_id']) {
                if (!isset($comment_cache[$a['target_id']])) {
                    try {
                        $cstmt = $pdo->prepare("SELECT cc.id, cc.comment, cc.content_id, c.title, c.poster, c.id as cid FROM comments cc JOIN content c ON c.id = cc.content_id WHERE cc.id = ?");
                        $cstmt->execute([$a['target_id']]);
                        $comment_cache[$a['target_id']] = $cstmt->fetch() ?: null;
                    } catch (Throwable $e) { $comment_cache[$a['target_id']] = null; }
                }
                $a['target_data'] = $comment_cache[$a['target_id']];
            } elseif ($a['target_type'] === 'chat' && $a['target_id']) {
                try {
                    $cstmt = $pdo->prepare("SELECT gm.id, gm.message, gm.is_deleted, gm.category FROM global_messages gm WHERE gm.id = ?");
                    $cstmt->execute([$a['target_id']]);
                    $a['target_data'] = $cstmt->fetch() ?: null;
                } catch (Throwable $e) { $a['target_data'] = null; }
            }
            if ($a['action'] === 'search' && $a['detail']) {
                $a['search_query'] = $a['detail'];
            }
        }
        echo json_encode(['ok' => true, 'activities' => $acts], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'user_info') {
        $uid = (int)($_GET['user_id'] ?? 0);
        if (!$uid) { echo json_encode(['ok' => false]); exit; }
        $stmt = $pdo->prepare("SELECT id, user_id, username, email, avatar, is_premium, premium_expires_at, created_at, last_login_at, last_activity FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if (!$user) { echo json_encode(['ok' => false]); exit; }
        $ban = is_user_banned($pdo, $uid);
        $devices = get_user_devices($pdo, $uid);
        foreach ($devices as &$d) {
            $d['parsed'] = parse_user_agent($d['user_agent'] ?? '');
        }
        echo json_encode(['ok' => true, 'user' => $user, 'ban' => $ban, 'devices' => $devices], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'search_users') {
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 1) { echo json_encode(['ok' => true, 'users' => []]); exit; }
        $like = '%' . $q . '%';
        $stmt = $pdo->prepare("SELECT id, user_id, username, email, avatar, is_premium, created_at, last_login_at FROM users WHERE username LIKE ? OR user_id LIKE ? OR email LIKE ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$like, $like, $like]);
        $users = $stmt->fetchAll();
        foreach ($users as &$u) {
            $u['avatar_url'] = avatar_url($u['avatar']);
            $u['is_banned'] = (bool)is_user_banned($pdo, $u['id']);
        }
        echo json_encode(['ok' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => false]); exit;
}

// === POST actions ===
if (!validate_csrf($input['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'msg' => 'CSRF xato']); exit;
}

if ($action === 'ban_user') {
    $uid = (int)($input['user_id'] ?? 0);
    $reason = trim($input['reason'] ?? '');
    $duration = $input['duration'] ?? '';
    if (!$uid || !$reason) { echo json_encode(['ok' => false, 'msg' => 'Ma\'lumot yetarli emas']); exit; }

    $hours = null;
    if ($duration === '1h') $hours = 1;
    elseif ($duration === '6h') $hours = 6;
    elseif ($duration === '24h') $hours = 24;
    elseif ($duration === '3d') $hours = 72;
    elseif ($duration === '7d') $hours = 168;
    elseif ($duration === '30d') $hours = 720;
    elseif ($duration === 'permanent') $hours = null;

    ban_user($pdo, $uid, $admin_id, $reason, $hours);

    notify_send($pdo, $uid, 'admin_warning', '⛔ Hisob bloklangan', $reason, ROOT_URL . '/auth/banned.php', $admin_id);
    log_user_activity($pdo, $uid, 'banned', 'user', $uid, $reason);

    echo json_encode(['ok' => true, 'msg' => 'Foydalanuvchi bloklandi']); exit;
}

if ($action === 'unban_user') {
    $uid = (int)($input['user_id'] ?? 0);
    if (!$uid) { echo json_encode(['ok' => false]); exit; }
    unban_user($pdo, $uid);
    notify_send($pdo, $uid, 'admin_warning', '✅ Hisob blokdan ochildi', '', ROOT_URL . '/index.php', $admin_id);
    echo json_encode(['ok' => true, 'msg' => 'Blok ochildi']); exit;
}

if ($action === 'send_warning') {
    $uid = (int)($input['user_id'] ?? 0);
    $title = trim($input['title'] ?? 'Ogohlantirish');
    $message = trim($input['message'] ?? '');
    if (!$uid || !$message) { echo json_encode(['ok' => false, 'msg' => 'Xabar bo\'sh']); exit; }

    notify_send($pdo, $uid, 'admin_warning', $title, $message, ROOT_URL . '/profile.php?uid=' . (($input['user_uid'] ?? '') ?: ''), $admin_id);
    log_user_activity($pdo, $uid, 'admin_warning', 'user', $uid, $title . ': ' . $message);

    echo json_encode(['ok' => true, 'msg' => 'Ogohlantirish yuborildi']); exit;
}

echo json_encode(['ok' => false, 'msg' => 'Noto\'g\'ri action']); exit;
