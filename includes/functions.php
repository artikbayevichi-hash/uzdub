<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/lang.php';

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// ===== ADMIN =====
function is_logged_in() { return isset($_SESSION['admin_id']); }
function require_login() { if (!is_logged_in()) { header('Location: login.php'); exit; } }

// ===== USER =====
function is_user() { return isset($_SESSION['user_id']); }
function current_user() { return $_SESSION['user_data'] ?? null; }

function require_user() {
    if (!is_user()) { header('Location: /uzdub/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit; }
}

function check_premium_expiry($pdo, $user_db_id) {
    $stmt = $pdo->prepare("SELECT is_premium, premium_expires_at FROM users WHERE id = ?");
    $stmt->execute([$user_db_id]);
    $u = $stmt->fetch();
    if ($u && $u['is_premium'] && $u['premium_expires_at'] && strtotime($u['premium_expires_at']) < time()) {
        $pdo->prepare("UPDATE users SET is_premium=0, premium_expires_at=NULL WHERE id=?")->execute([$user_db_id]);
        if (isset($_SESSION['user_data'])) {
            $_SESSION['user_data']['is_premium'] = 0;
            $_SESSION['user_data']['premium_expires_at'] = null;
        }
    }
}

function refresh_user_session($pdo, $user_db_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_db_id]);
    $u = $stmt->fetch();
    if ($u) {
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['user_data'] = $u;
    }
}

// ===== 8 xonali unikal ID yaratish =====
function generate_user_id($pdo) {
    do {
        $uid = str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        $exists = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
        $exists->execute([$uid]);
    } while ($exists->fetch());
    return $uid;
}

// ===== Kontent uchun avtomatik ID (masalan: KN0001, AN0002, MF0003, SR0004) =====
function generate_content_code($pdo, $category_slug) {
    $prefix_map = ['kino' => 'KN', 'anime' => 'AN', 'multfilm' => 'MF', 'serial' => 'SR'];
    $prefix = $prefix_map[$category_slug] ?? 'CN';
    do {
        $num = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $code = $prefix . $num;
        $exists = $pdo->prepare("SELECT id FROM content WHERE content_code = ?");
        $exists->execute([$code]);
    } while ($exists->fetch());
    return $code;
}

// ===== YouTube ID =====
function get_youtube_id($url) {
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
    if (preg_match($pattern, $url, $matches)) return $matches[1];
    return null;
}

// ===== Fayl yuklash =====
function upload_file($file_input_name, $target_dir, $allowed_ext) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) return null;
    $file = $_FILES[$file_input_name];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) return false;
    $new_name = uniqid('f_', true) . '.' . $ext;
    $target_path = $target_dir . $new_name;
    if (move_uploaded_file($file['tmp_name'], $target_path)) return $new_name;
    return false;
}

// ===== Video player =====
function render_player($video_type, $video_url, $base_path = 'uploads/videos/') {
    if ($video_type === 'youtube') {
        $yt_id = get_youtube_id($video_url);
        if ($yt_id) return '<div class="player-wrap"><iframe src="https://www.youtube.com/embed/' . e($yt_id) . '" allowfullscreen allow="autoplay; encrypted-media"></iframe></div>';
        return '<p class="player-error">YouTube havolasi noto\'g\'ri.</p>';
    } elseif ($video_type === 'cloud') {
        return '<div class="player-wrap"><iframe src="' . e($video_url) . '" allowfullscreen></iframe></div>';
    } elseif ($video_type === 'file') {
        return '<div class="player-wrap"><video controls autoplay src="' . e($base_path) . e($video_url) . '"></video></div>';
    }
    return '';
}

// ===== Avatar URL =====
function avatar_url($avatar, $base = '/uzdub/') {
    if ($avatar) return $base . 'uploads/avatars/' . e($avatar);
    return $base . 'assets/default-avatar.svg';
}

// ===== Vaqtni chiroyli ko'rsatish =====
function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Hozirgina';
    if ($diff < 3600) return floor($diff/60) . ' daqiqa oldin';
    if ($diff < 86400) return floor($diff/3600) . ' soat oldin';
    return date('d.m.Y H:i', strtotime($datetime));
}
