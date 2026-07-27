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
        $stmt = $pdo->prepare("SELECT id, session_token, user_agent, ip_address, last_activity FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC");
        $stmt->execute([$uid]);
        $sessions = $stmt->fetchAll();

        $current_session_id = $_SESSION['session_db_id'] ?? 0;

        $parsed = array_map(function($s) use ($current_session_id) {
            $ua = $s['user_agent'];
            $browser = 'Unknown';
            $os = 'Unknown';

            if (preg_match('/Windows/i', $ua)) $os = 'Windows';
            elseif (preg_match('/Macintosh|Mac OS X/i', $ua)) $os = 'macOS';
            elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';
            elseif (preg_match('/Android/i', $ua)) $os = 'Android';
            elseif (preg_match('/iPhone|iPad/i', $ua)) $os = 'iOS';

            if (preg_match('/Chrome\/(\d+)/i', $ua, $m)) $browser = 'Chrome ' . $m[1];
            elseif (preg_match('/Firefox\/(\d+)/i', $ua, $m)) $browser = 'Firefox ' . $m[1];
            elseif (preg_match('/Safari\/(\d+)/i', $ua, $m)) $browser = 'Safari ' . $m[1];
            elseif (preg_match('/Edge\/(\d+)/i', $ua, $m)) $browser = 'Edge ' . $m[1];
            elseif (preg_match('/Opera|OPR\/(\d+)/i', $ua, $m)) $browser = 'Opera ' . ($m[1] ?? '');

            if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) {
                $device_type = 'mobile';
            } elseif (preg_match('/Tablet|iPad/i', $ua)) {
                $device_type = 'tablet';
            } else {
                $device_type = 'desktop';
            }

            return [
                'id' => (int)$s['id'],
                'browser' => $browser,
                'os' => $os,
                'device' => $device_type,
                'ip' => $s['ip_address'],
                'last_active' => $s['last_activity'],
                'is_current' => (int)$s['id'] === $current_session_id,
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
