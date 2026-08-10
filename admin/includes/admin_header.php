<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login();
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? e($page_title) . ' - Admin' : 'Admin panel'; ?></title>
<link rel="stylesheet" href="css/admin.css">
<link rel="stylesheet" href="../css/emoji-blue.css">
<script src="../js/emoji-blue.js" defer></script>
</head>
<body>
<div class="admin-wrap">
    <aside class="sidebar">
        <h2>UZDUB PLATFORM Admin</h2>
        <div class="side-group">Asosiy</div>
        <a href="dashboard.php" class="<?php echo $current=='dashboard.php'?'active':''; ?>">&#128202; Boshqaruv paneli</a>
        <div class="side-group">Kontent</div>
        <a href="list_content.php" class="<?php echo $current=='list_content.php'?'active':''; ?>">&#127916; Barcha kontent</a>
        <a href="add_content.php" class="<?php echo $current=='add_content.php'?'active':''; ?>">&#10133; Kino/Anime/Multfilm qo'shish</a>
        <a href="episodes.php" class="<?php echo $current=='episodes.php'?'active':''; ?>">&#127909; Qismlar boshqaruvi</a>
        <a href="video_upload.php" class="<?php echo $current=='video_upload.php'?'active':''; ?>">&#11014; Video URL (VK / Archive.org)</a>
        <a href="video_sources.php" class="<?php echo $current=='video_sources.php'?'active':''; ?>">&#128260; Video manbalari kuzatuvi</a>
        <div class="side-group">Foydalanuvchilar</div>
        <a href="users.php" class="<?php echo $current=='users.php'?'active':''; ?>">&#128101; Foydalanuvchilar</a>
        <a href="chat.php" class="<?php echo $current=='chat.php'?'active':''; ?>">&#128172; Chat boshqarish</a>
        <a href="payments.php" class="<?php echo $current=='payments.php'?'active':''; ?>">&#128176; To'lovlar
            <?php
            $pend = $pdo->query("SELECT COUNT(*) c FROM premium_payments WHERE status='pending'")->fetch()['c'] ?? 0;
            if ($pend > 0): ?>
            <span style="background:#e53935;color:#fff;font-size:11px;padding:1px 7px;border-radius:10px;margin-left:6px;"><?php echo $pend; ?></span>
            <?php endif; ?>
        </a>
        <div class="side-sep"></div>
        <a href="../index.php" target="_blank">&#127760; Saytni ko'rish</a>
        <a href="logout.php">&#128274; Chiqish</a>
    </aside>
    <main class="main-content">
