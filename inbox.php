<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_user();

$page_title = t('inbox_title');
$user = current_user();

// Foydalanuvchi bilan yozishgan barcha odamlar (oxirgi xabar bilan)
$stmt = $pdo->prepare("
    SELECT u.id, u.user_id, u.username, u.avatar, u.is_premium,
           pm.message as last_message, pm.created_at as last_time,
           COALESCE(unread.unread_count, 0) as unread
    FROM users u
    INNER JOIN (
        SELECT 
            CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_user_id,
            MAX(id) as last_id
        FROM private_messages
        WHERE sender_id = ? OR receiver_id = ?
        GROUP BY other_user_id
    ) conv ON conv.other_user_id = u.id
    LEFT JOIN private_messages pm ON pm.id = conv.last_id
    LEFT JOIN (
        SELECT sender_id, COUNT(*) as unread_count
        FROM private_messages
        WHERE receiver_id = ? AND is_read = 0
        GROUP BY sender_id
    ) unread ON unread.sender_id = u.id
    ORDER BY pm.id DESC
");
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$conversations = $stmt->fetchAll();

// Foydalanuvchi qidirish (yangi suhbat boshlash)
$search_result = null;
$search_q = trim($_GET['find'] ?? '');
if ($search_q !== '') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (user_id = ? OR username LIKE ?) AND id != ?");
    $stmt->execute([$search_q, '%'.$search_q.'%', $user['id']]);
    $search_result = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>
<style>
.inbox-page { max-width:700px; margin:100px auto 40px; padding:0 16px; position:relative;z-index:1; }
.inbox-page h1 { font-size:22px; margin-bottom:16px; border-left:4px solid var(--blue-primary); padding-left:10px; }
.find-box { display:flex; gap:10px; margin-bottom:20px; }
.find-box input { flex:1; padding:11px 15px; background:var(--card-bg); border:1px solid rgba(33,150,243,0.25); border-radius:8px; color:var(--text-light); font-size:14px; outline:none; }
.find-box button { padding:11px 20px; background:var(--blue-primary); border:none; border-radius:8px; color:#fff; cursor:pointer; font-weight:600; }
.conv-item { display:flex; align-items:center; gap:14px; background:var(--card-bg); border:1px solid rgba(33,150,243,0.15); border-radius:10px; padding:14px 18px; margin-bottom:10px; text-decoration:none; color:var(--text-light); transition:0.2s; }
.conv-item:hover { border-color:var(--blue-primary); }
.conv-item img { width:48px; height:48px; border-radius:50%; object-fit:cover; border:2px solid var(--blue-primary); flex-shrink:0; }
.conv-info { flex:1; min-width:0; }
.conv-info .name { font-weight:600; font-size:15px; margin-bottom:2px; }
.conv-info .last-msg { font-size:13px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.conv-meta { text-align:right; flex-shrink:0; }
.conv-meta .time { font-size:11px; color:var(--text-muted); }
.unread-badge { background:var(--blue-primary); color:#fff; font-size:11px; padding:2px 8px; border-radius:10px; display:inline-block; margin-top:4px; }
.empty-inbox { text-align:center; color:var(--text-muted); padding:40px; }
</style>

<div class="inbox-page">
    <h1>💌 <?php echo t('inbox_title'); ?></h1>

    <form class="find-box" method="get">
        <input type="text" name="find" placeholder="<?php echo t('search_user_placeholder'); ?>" value="<?php echo e($search_q); ?>">
        <button type="submit"><?php echo t('search_btn'); ?></button>
    </form>

    <?php if ($search_result !== null): ?>
        <?php foreach ($search_result as $su): ?>
        <a href="chat.php?with=<?php echo e($su['user_id']); ?>" class="conv-item">
            <img src="<?php echo avatar_url($su['avatar']); ?>" alt="">
            <div class="conv-info">
                <div class="name"><?php echo e($su['username']); ?> <?php if ($su['is_premium']): ?>⭐<?php endif; ?></div>
                <div class="last-msg">🆔 <?php echo e($su['user_id']); ?> — <?php echo t('start_chat'); ?></div>
            </div>
        </a>
        <?php endforeach; ?>
        <?php if (empty($search_result)): ?><p class="empty-inbox"><?php echo t('user_not_found'); ?></p><?php endif; ?>
        <hr style="border-color:rgba(33,150,243,0.15); margin:20px 0;">
    <?php endif; ?>

    <?php foreach ($conversations as $c): ?>
    <a href="chat.php?with=<?php echo e($c['user_id']); ?>" class="conv-item">
        <img src="<?php echo avatar_url($c['avatar']); ?>" alt="">
        <div class="conv-info">
            <div class="name"><?php echo e($c['username']); ?> <?php if ($c['is_premium']): ?>⭐<?php endif; ?></div>
            <div class="last-msg"><?php echo $c['last_message'] ? e($c['last_message']) : '📷 ' . t('image_gif_fallback'); ?></div>
        </div>
        <div class="conv-meta">
            <div class="time"><?php echo $c['last_time'] ? time_ago($c['last_time']) : ''; ?></div>
            <?php if ($c['unread'] > 0): ?><span class="unread-badge"><?php echo $c['unread']; ?></span><?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>

    <?php if (empty($conversations) && $search_result === null): ?>
    <div class="empty-inbox"><?php echo t('no_conversations'); ?></div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
