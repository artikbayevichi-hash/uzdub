<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/lang.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!is_user()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uid = $_SESSION['user_id'];

if ($method === 'GET') {
    try {
        $current_token = session_id();
        $current_session_id = (int)($_SESSION['session_db_id'] ?? 0);
        $live_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $stmt = $pdo->prepare("SELECT id, session_token, user_agent, ip_address, last_activity FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC");
        $stmt->execute([$uid]);
        $sessions = $stmt->fetchAll();

        // Joriy (real brauzer) sessiya ro'yxatda bo'lmasa — uni yozib qo'yamiz
        $found = false;
        foreach ($sessions as $s) {
            if ($s['session_token'] === $current_token) { $found = true; break; }
        }
        if (!$found) {
            $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?")->execute([$current_token]);
            dedupe_device_sessions($pdo, $uid, $current_token, $live_ua);
            $ins = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, user_agent, ip_address, last_activity) VALUES (?, ?, ?, ?, NOW())");
            $ins->execute([$uid, $current_token, $live_ua, client_ip()]);
            $current_session_id = (int)$pdo->lastInsertId();
            $_SESSION['session_db_id'] = $current_session_id;
            $stmt->execute([$uid]);
            $sessions = $stmt->fetchAll();
        }

        // Joriy seansda real brauzer UA'si bo'lmasa (eski curl/avtomatik yozilgan bo'lsa), jonli so'rov UA'sidan foydalanib DB'ni tuzatamiz
        $parsed = array_map(function($s) use ($pdo, $current_session_id, $current_token, $live_ua) {
            $is_current = ((int)$s['id'] === $current_session_id) || ($s['session_token'] === $current_token);

            $ua = $s['user_agent'];
            if ($is_current && (empty($ua) || $ua === 'Unknown' || stripos($ua, 'curl') !== false || stripos($ua, 'powershell') !== false || stripos($ua, 'python') !== false || stripos($ua, 'wget') !== false)) {
                $ua = $live_ua;
                try {
                    $pdo->prepare("UPDATE user_sessions SET user_agent = ? WHERE id = ?")->execute([$live_ua, (int)$s['id']]);
                } catch (PDOException $e) {}
            }

            $info = parse_user_agent($ua);

            return [
                'id' => (int)$s['id'],
                'browser' => $info['browser'],
                'browser_version' => $info['browser_version'],
                'browser_label' => $info['browser_label'],
                'os' => $info['os'],
                'os_version' => $info['os_version'],
                'os_label' => $info['os_label'],
                'device' => $info['device'],
                'device_label' => t('device_' . $info['device_label']),
                'ip' => $s['ip_address'],
                'last_active' => $s['last_activity'],
                'is_current' => $is_current,
            ];
        }, $sessions);

        echo json_encode(['ok' => true, 'sessions' => $parsed], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'DB error'], JSON_UNESCAPED_UNICODE);
    }
    exit;

} elseif ($method === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'error' => t('security_token_wrong')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'logout_all') {
        $current_session_id = $_SESSION['session_db_id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND id != ?");
            $stmt->execute([$uid, $current_session_id]);
            echo json_encode(['ok' => true, 'message' => 'Boshqa qurilmalardan chiqildi'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => 'DB error'], JSON_UNESCAPED_UNICODE);
        }
    } elseif ($action === 'logout_one') {
        $session_id = (int)($_POST['session_id'] ?? 0);
        if ($session_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE id = ? AND user_id = ?");
                $stmt->execute([$session_id, $uid]);
                echo json_encode(['ok' => true, 'message' => 'Seans o\'chirildi'], JSON_UNESCAPED_UNICODE);
            } catch (PDOException $e) {
                echo json_encode(['ok' => false, 'error' => 'DB error'], JSON_UNESCAPED_UNICODE);
            }
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
