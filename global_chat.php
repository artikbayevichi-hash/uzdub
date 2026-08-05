<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$cat = $_GET['cat'] ?? $_POST['category'] ?? 'kino';
if (!in_array($cat, ['kino', 'anime', 'multfilm'])) $cat = 'kino';
$cat_labels = ['kino' => t('movies'), 'anime' => t('anime'), 'multfilm' => t('cartoons')];
$cat_icons = ['kino' => '🎬', 'anime' => '🎌', 'multfilm' => '🎞️'];
$page_title = $cat_icons[$cat] . ' ' . $cat_labels[$cat] . ' — ' . t('chat');

$is_admin = isset($_SESSION['admin_id']);
$is_premium_user = false;
if (is_user()) {
    $u = current_user();
    check_premium_expiry($pdo, $u['id']);
    refresh_user_session($pdo, $u['id']);
    $is_premium_user = (bool)current_user()['is_premium'];
}

// AJAX - xabar yuborish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_send'])) {
    header('Content-Type: application/json');
    if (!is_user()) { echo json_encode(['ok' => false, 'msg' => t('login_required')]); exit; }
    if (!validate_csrf($_POST['csrf_token'] ?? '')) { echo json_encode(['ok' => false, 'msg' => t('security_token_wrong')]); exit; }
    $user = current_user();
    $txt = trim($_POST['message'] ?? '');
    $reply_to = (int)($_POST['reply_to'] ?? 0) ?: null;
    $msg_cat = $_POST['category'] ?? $cat;
    if (!in_array($msg_cat, ['kino', 'anime', 'multfilm'])) $msg_cat = $cat;

    $attachment = null;
    $attachment_type = null;
    if (!empty($_FILES['attachment']['name'])) {
        if (!$user['is_premium']) { echo json_encode(['ok' => false, 'msg' => t('image_gif_premium_global')]); exit; }
        $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $allowed)) { echo json_encode(['ok' => false, 'msg' => t('only_image_gif')]); exit; }
        $attachment = upload_file('attachment', __DIR__ . '/uploads/chat/', $allowed);
        if (!$attachment) { echo json_encode(['ok' => false, 'msg' => t('file_upload_error')]); exit; }
        $attachment_type = ($ext === 'gif') ? 'gif' : 'image';
    }
    if ($txt === '' && !$attachment) { echo json_encode(['ok' => false, 'msg' => t('message_empty')]); exit; }
    if (mb_strlen($txt) > 500) { echo json_encode(['ok' => false, 'msg' => t('message_too_long')]); exit; }

    if ($reply_to) {
        $chk = $pdo->prepare("SELECT id FROM global_messages WHERE id=? AND category=?");
        $chk->execute([$reply_to, $msg_cat]);
        if (!$chk->fetch()) $reply_to = null;
    }

    $pdo->prepare("INSERT INTO global_messages (user_id, category, message, attachment, attachment_type, reply_to) VALUES (?,?,?,?,?,?)")
        ->execute([$user['id'], $msg_cat, $txt ?: null, $attachment, $attachment_type, $reply_to]);
    echo json_encode(['ok' => true]);
    exit;
}

// AJAX - xabarni tahrirlash
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_edit'])) {
    header('Content-Type: application/json');
    if (!is_user()) { echo json_encode(['ok' => false]); exit; }
    if (!validate_csrf($_POST['csrf_token'] ?? '')) { echo json_encode(['ok' => false]); exit; }
    $mid = (int)($_POST['msg_id'] ?? 0);
    $txt = trim($_POST['message'] ?? '');
    if ($txt === '' || mb_strlen($txt) > 500) { echo json_encode(['ok' => false]); exit; }
    $stmt = $pdo->prepare("UPDATE global_messages SET message=?, is_edited=1 WHERE id=? AND user_id=? AND category=?");
    $stmt->execute([$txt, $mid, $_SESSION['user_id'], $cat]);
    echo json_encode(['ok' => $stmt->rowCount() > 0]);
    exit;
}

// AJAX - xabarni o'chirish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    if (!is_user()) { echo json_encode(['ok' => false]); exit; }
    if (!validate_csrf($_POST['csrf_token'] ?? '')) { echo json_encode(['ok' => false]); exit; }
    $mid = (int)($_POST['msg_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE global_messages SET is_deleted=1, message=NULL, attachment=NULL, attachment_type=NULL WHERE id=? AND user_id=? AND category=?");
    $stmt->execute([$mid, $_SESSION['user_id'], $cat]);
    echo json_encode(['ok' => $stmt->rowCount() > 0]);
    exit;
}

// AJAX - pin/unpin (admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_pin'])) {
    header('Content-Type: application/json');
    if (!$is_admin) { echo json_encode(['ok' => false]); exit; }
    if (!validate_csrf($_POST['csrf_token'] ?? '')) { echo json_encode(['ok' => false]); exit; }
    $mid = (int)($_POST['msg_id'] ?? 0);
    $pdo->prepare("UPDATE global_messages SET is_pinned = IF(is_pinned=1,0,1) WHERE id=? AND category=?")->execute([$mid, $cat]);
    echo json_encode(['ok' => true]);
    exit;
}

// AJAX - emoji reaction (Telegram-style: 1 reaction per user per message)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_react'])) {
    header('Content-Type: application/json');
    if (!is_user()) { echo json_encode(['ok' => false]); exit; }
    if (!validate_csrf($_POST['csrf_token'] ?? '')) { echo json_encode(['ok' => false]); exit; }
    $mid = (int)($_POST['msg_id'] ?? 0);
    $emoji = $_POST['emoji'] ?? '';
    $allowed_emojis = ['👍', '❤️', '🔥', '😂', '😮', '😢'];
    if (!in_array($emoji, $allowed_emojis)) { echo json_encode(['ok' => false]); exit; }
    $uid = (int)$_SESSION['user_id'];

    $check = $pdo->prepare("SELECT id, reaction FROM chat_reactions WHERE message_id = ? AND user_id = ?");
    $check->execute([$mid, $uid]);
    $existing = $check->fetch();

    if ($existing) {
        if ($existing['reaction'] === $emoji) {
            $pdo->prepare("DELETE FROM chat_reactions WHERE id = ?")->execute([$existing['id']]);
            $action = 'removed';
        } else {
            $pdo->prepare("UPDATE chat_reactions SET reaction = ? WHERE id = ?")->execute([$emoji, $existing['id']]);
            $action = 'updated';
        }
    } else {
        $pdo->prepare("INSERT INTO chat_reactions (message_id, user_id, reaction) VALUES (?, ?, ?)")
            ->execute([$mid, $uid, $emoji]);
        $action = 'added';
    }

    $allStmt = $pdo->prepare("SELECT user_id, reaction FROM chat_reactions WHERE message_id = ?");
    $allStmt->execute([$mid]);
    $allRows = $allStmt->fetchAll();
    $reactions = [];
    foreach ($allRows as $r) {
        $rEmoji = $r['reaction'];
        if (!isset($reactions[$rEmoji])) $reactions[$rEmoji] = [];
        $reactions[$rEmoji][] = (int)$r['user_id'];
    }

    echo json_encode(['ok' => true, 'reactions' => $reactions, 'action' => $action], JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX - forward
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_forward'])) {
    header('Content-Type: application/json');
    if (!is_user()) { echo json_encode(['ok' => false]); exit; }
    if (!validate_csrf($_POST['csrf_token'] ?? '')) { echo json_encode(['ok' => false]); exit; }
    $mid = (int)($_POST['msg_id'] ?? 0);
    $to_cat = $_POST['to_category'] ?? '';
    if (!in_array($to_cat, ['kino', 'anime', 'multfilm'])) { echo json_encode(['ok' => false]); exit; }
    $stmt = $pdo->prepare("SELECT * FROM global_messages WHERE id=? AND category=?");
    $stmt->execute([$mid, $cat]);
    $orig = $stmt->fetch();
    if (!$orig) { echo json_encode(['ok' => false]); exit; }
    $pdo->prepare("INSERT INTO global_messages (user_id, category, message, attachment, attachment_type, forwarded_from) VALUES (?,?,?,?,?,?)")
        ->execute([$_SESSION['user_id'], $to_cat, $orig['message'], $orig['attachment'], $orig['attachment_type'], $mid]);
    echo json_encode(['ok' => true]);
    exit;
}

// AJAX - xabarlarni olish
if (isset($_GET['fetch_msgs'])) {
    header('Content-Type: application/json');
    $last_id = (int)($_GET['last_id'] ?? 0);
    $fetch_cat = $_GET['cat'] ?? $cat;
    if (!in_array($fetch_cat, ['kino', 'anime', 'multfilm'])) $fetch_cat = 'kino';

    $stmt = $pdo->prepare("
        SELECT gm.id, gm.user_id, gm.category, gm.message, gm.attachment, gm.attachment_type,
               gm.reply_to, gm.forwarded_from, gm.is_pinned, gm.is_edited, gm.is_deleted,
               gm.created_at,
               u.username, u.avatar, u.user_id as uid, u.is_premium,
               ru.username AS reply_username, rm.message AS reply_message, rm.attachment AS reply_attachment
        FROM global_messages gm
        JOIN users u ON gm.user_id = u.id
        LEFT JOIN global_messages rm ON gm.reply_to = rm.id
        LEFT JOIN users ru ON rm.user_id = ru.id
        WHERE gm.id > ? AND gm.category = ? AND gm.is_deleted = 0
        ORDER BY gm.id ASC LIMIT 80
    ");
    $stmt->execute([$last_id, $fetch_cat]);
    $msgs = $stmt->fetchAll();

    if (!empty($msgs)) {
        $msgIds = array_map(function($m) { return (int)$m['id']; }, $msgs);
        $ph = implode(',', array_fill(0, count($msgIds), '?'));
        $rStmt = $pdo->prepare("SELECT message_id, user_id, reaction FROM chat_reactions WHERE message_id IN ($ph)");
        $rStmt->execute($msgIds);
        $rRows = $rStmt->fetchAll();
        $rMap = [];
        foreach ($rRows as $r) {
            $mid = (int)$r['message_id'];
            if (!isset($rMap[$mid])) $rMap[$mid] = [];
            if (!isset($rMap[$mid][$r['reaction']])) $rMap[$mid][$r['reaction']] = [];
            $rMap[$mid][$r['reaction']][] = (int)$r['user_id'];
        }
        foreach ($msgs as &$m) {
            $m['reactions'] = isset($rMap[(int)$m['id']]) ? $rMap[(int)$m['id']] : null;
        }
        unset($m);
    }

    $pin_stmt = $pdo->prepare("SELECT gm.*, u.username FROM global_messages gm JOIN users u ON gm.user_id=u.id WHERE gm.category=? AND gm.is_pinned=1 AND gm.is_deleted=0 ORDER BY gm.id DESC LIMIT 1");
    $pin_stmt->execute([$fetch_cat]);
    $pinned = $pin_stmt->fetch();

    echo json_encode(['messages' => $msgs, 'pinned' => $pinned], JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX - reactions polling for already-loaded messages
if (isset($_GET['fetch_reactions'])) {
    header('Content-Type: application/json');
    $ids = $_GET['ids'] ?? '';
    $fetch_cat = $_GET['cat'] ?? $cat;
    if (!in_array($fetch_cat, ['kino', 'anime', 'multfilm'])) $fetch_cat = 'kino';

    $idArr = array_filter(array_map('intval', explode(',', $ids)), function($v) { return $v > 0; });
    if (empty($idArr)) { echo json_encode(['updates' => []]); exit; }

    $placeholders = implode(',', array_fill(0, count($idArr), '?'));
    $stmt = $pdo->prepare("SELECT cr.message_id, cr.user_id, cr.reaction FROM chat_reactions cr JOIN global_messages gm ON cr.message_id = gm.id WHERE cr.message_id IN ($placeholders) AND gm.category = ?");
    $params = array_merge($idArr, [$fetch_cat]);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $r) {
        $mid = (int)$r['message_id'];
        if (!isset($map[$mid])) $map[$mid] = [];
        if (!isset($map[$mid][$r['reaction']])) $map[$mid][$r['reaction']] = [];
        $map[$mid][$r['reaction']][] = (int)$r['user_id'];
    }

    $updates = [];
    foreach ($idArr as $id) {
        $updates[] = ['msg_id' => $id, 'reactions' => isset($map[$id]) ? $map[$id] : (object)[]];
    }
    echo json_encode(['updates' => $updates], JSON_UNESCAPED_UNICODE);
    exit;
}

include __DIR__ . '/includes/header.php';
?>
<style>
.chat-page { max-width:860px; margin:90px auto 40px; padding:0 16px; position:relative; z-index:1; }
.chat-cat-tabs { display:flex; gap:8px; margin-bottom:14px; }
.chat-cat-tab { padding:10px 22px; border-radius:10px; background:var(--card-bg); border:1px solid rgba(33,150,243,0.2); color:var(--text-muted); font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; transition:all .2s; }
.chat-cat-tab.active { background:var(--blue-primary); color:#fff; border-color:var(--blue-primary); box-shadow:0 0 20px rgba(33,150,243,0.4); }
.chat-cat-tab:hover:not(.active) { border-color:var(--blue-primary); color:var(--blue-glow); }
.chat-box { background:var(--card-bg); border:1px solid rgba(33,150,243,0.2); border-radius:12px; overflow:hidden; position:relative; }
.pinned-banner { padding:10px 16px; background:rgba(33,150,243,0.08); border-bottom:1px solid rgba(33,150,243,0.15); display:none; cursor:pointer; }
.pinned-banner .pin-label { font-size:11px; color:var(--blue-glow); font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
.pinned-banner .pin-user { font-size:12px; color:var(--text-muted); }
.pinned-banner .pin-text { font-size:13px; color:var(--text-light); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
.messages-area { height:480px; overflow-y:auto; padding:18px; display:flex; flex-direction:column; gap:10px; scroll-behavior:smooth; }
.messages-area::-webkit-scrollbar { width:5px; }
.messages-area::-webkit-scrollbar-thumb { background:var(--blue-deep); border-radius:10px; }
.msg-item { display:flex; gap:10px; align-items:flex-start; position:relative; }
.msg-item.own { flex-direction:row-reverse; }
.msg-avatar { width:38px; height:38px; border-radius:50%; object-fit:cover; border:2px solid var(--blue-primary); flex-shrink:0; }
.msg-body { max-width:72%; min-width:80px; position:relative; }
.msg-item { user-select: none; -webkit-user-select: none; }
.msg-header { display:flex; align-items:center; gap:7px; margin-bottom:4px; }
.msg-username { font-size:13px; font-weight:600; color:var(--blue-glow); text-decoration:none; }
.msg-username:hover { text-decoration:underline; }
.msg-prem { background:linear-gradient(135deg,#f9a825,#ff6f00); color:#fff; font-size:10px; padding:1px 7px; border-radius:10px; }
.msg-time { font-size:11px; color:var(--text-muted); }
.msg-edited { font-size:10px; color:var(--text-muted); font-style:italic; }
.msg-text { background:#0d1424; border-radius:0 10px 10px 10px; padding:10px 14px; font-size:14px; line-height:1.5; word-break:break-word; position:relative; }
.msg-item.own .msg-text { border-radius:10px 0 10px 10px; background:var(--blue-deep); }
.msg-item.own .msg-header { flex-direction:row-reverse; }
.msg-hover-bar { position:absolute; top:2px; right:0; display:flex; gap:1px; background:rgba(13,20,36,0.95); border:1px solid rgba(33,150,243,0.25); border-radius:16px; padding:3px; opacity:0; transform:translateY(4px); transition:opacity .2s,transform .2s; z-index:5; pointer-events:none; }
.msg-item.hovered .msg-hover-bar { opacity:1; transform:translateY(0); pointer-events:all; }
.msg-item.own .msg-hover-bar { right:auto; left:0; }
.msg-hover-bar button { width:30px; height:28px; border-radius:14px; border:none; background:none; font-size:14px; cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--text-light); }
.msg-hover-bar button:hover { background:rgba(33,150,243,0.2); }
.msg-hover-bar button.hb-sep { width:1px; height:18px; background:rgba(33,150,243,0.2); margin:0 2px; cursor:default; }
.msg-image { max-width:240px; border-radius:10px; margin-top:4px; display:block; cursor:pointer; }
.msg-reply-ref { background:rgba(33,150,243,0.1); border-left:3px solid var(--blue-primary); border-radius:0 6px 6px 0; padding:6px 10px; margin-bottom:6px; font-size:12px; cursor:pointer; }
.msg-reply-ref .ref-user { color:var(--blue-glow); font-weight:600; }
.msg-reply-ref .ref-text { color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:250px; display:block; }
.msg-forward-badge { font-size:11px; color:var(--text-muted); margin-bottom:4px; font-style:italic; }
.msg-reactions { display:flex; gap:4px; margin-top:6px; flex-wrap:wrap; align-items:center; }
.msg-reaction { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; background:rgba(33,150,243,0.08); border:1px solid rgba(33,150,243,0.18); border-radius:14px; font-size:14px; cursor:pointer; transition:all .2s; line-height:1; }
.msg-reaction:hover { background:rgba(33,150,243,0.18); transform:scale(1.05); }
.msg-reaction.active { background:rgba(33,150,243,0.22); border-color:var(--blue-primary); box-shadow:0 0 6px rgba(33,150,243,0.25); }
.msg-reaction .r-count { font-size:11px; color:var(--text-muted); font-weight:600; }
.msg-reaction.active .r-count { color:var(--blue-glow); }
.reply-bar { padding:8px 16px; background:rgba(33,150,243,0.06); border-top:1px solid rgba(33,150,243,0.15); display:none; align-items:center; gap:10px; }
.reply-bar.active { display:flex; }
.reply-bar .rb-info { flex:1; font-size:12px; color:var(--text-muted); overflow:hidden; }
.reply-bar .rb-info .rb-user { color:var(--blue-glow); font-weight:600; }
.reply-bar .rb-info .rb-txt { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block; }
.reply-bar .rb-close { background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:18px; padding:4px; }
.chat-input-bar { padding:14px 16px; border-top:1px solid rgba(33,150,243,0.15); display:flex; gap:10px; align-items:center; }
.chat-input-bar input[type=text] { flex:1; padding:11px 15px; background:#0d1424; border:1px solid rgba(33,150,243,0.25); border-radius:8px; color:var(--text-light); font-size:14px; outline:none; }
.chat-input-bar input:focus { border-color:var(--blue-primary); }
.chat-send-btn { padding:11px 22px; background:var(--blue-primary); border:none; border-radius:8px; color:#fff; font-weight:600; cursor:pointer; font-size:14px; }
.chat-send-btn:hover { background:var(--blue-glow); }
.chat-attach-btn { width:42px; height:42px; border-radius:8px; background:rgba(255,255,255,0.08); border:1px solid rgba(33,150,243,0.25); color:var(--text-light); font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; position:relative; }
.chat-attach-btn.locked::after { content:'👑'; position:absolute; top:-6px; right:-6px; font-size:11px; }
.chat-attach-btn:hover { border-color:var(--blue-primary); }
.need-login { text-align:center; padding:18px; color:var(--text-muted); font-size:14px; }
.need-login a { color:var(--blue-glow); }
.attach-preview-bar { padding:0 16px; }
.attach-preview-bar img { max-height:80px; border-radius:8px; margin:8px 0; }
.attach-preview-bar button { margin-left:10px; background:none; border:none; color:#ef5350; cursor:pointer; font-size:13px; }
.emoji-picker { position:fixed; bottom:80px; left:16px; background:var(--card-bg); border:1px solid rgba(33,150,243,0.3); border-radius:10px; padding:10px; display:none; flex-wrap:wrap; gap:6px; width:260px; z-index:1002; }
.emoji-picker.active { display:flex; }
.emoji-picker span { font-size:20px; cursor:pointer; padding:4px; }
.emoji-picker span:hover { background:rgba(33,150,243,0.15); border-radius:6px; }
.ctx-menu { position:fixed; background:var(--card-bg); border:1px solid rgba(33,150,243,0.3); border-radius:10px; padding:6px; min-width:180px; box-shadow:0 10px 30px rgba(0,0,0,0.5); z-index:9999; display:none; }
.ctx-menu.active { display:block; }
.ctx-menu button { display:flex; align-items:center; gap:8px; width:100%; padding:10px 12px; background:none; border:none; color:var(--text-light); font-size:13px; cursor:pointer; border-radius:6px; text-align:left; }
.ctx-menu button:hover { background:rgba(33,150,243,0.12); }
.ctx-menu button.danger { color:#ef5350; }
.ctx-menu .ctx-sep { height:1px; background:rgba(33,150,243,0.15); margin:4px 0; }
.react-picker { position:fixed; background:var(--card-bg); border:1px solid rgba(33,150,243,0.3); border-radius:24px; padding:6px 10px; display:none; gap:4px; z-index:1001; box-shadow:0 6px 20px rgba(0,0,0,0.5); }
.react-picker.active { display:flex; }
.react-picker span { font-size:22px; cursor:pointer; padding:4px; border-radius:8px; transition:transform .15s; }
.react-picker span:hover { transform:scale(1.3); background:rgba(33,150,243,0.15); }
.forward-modal { position:fixed; inset:0; background:rgba(0,0,0,0.6); display:none; align-items:center; justify-content:center; z-index:1002; }
.forward-modal.active { display:flex; }
.forward-modal-box { background:var(--card-bg); border:1px solid rgba(33,150,243,0.3); border-radius:14px; padding:24px; max-width:360px; width:90%; }
.forward-modal-box h3 { margin:0 0 16px; font-size:18px; color:var(--text-light); }
.forward-modal-box .fm-cat { display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:10px; border:1px solid rgba(33,150,243,0.2); cursor:pointer; margin-bottom:8px; transition:all .2s; }
.forward-modal-box .fm-cat:hover { background:rgba(33,150,243,0.1); border-color:var(--blue-primary); }
.forward-modal-box .fm-cat .fm-icon { font-size:20px; }
.forward-modal-box .fm-cat .fm-label { font-size:14px; font-weight:600; color:var(--text-light); }
.forward-modal-box .fm-cancel { margin-top:8px; width:100%; padding:10px; background:none; border:1px solid rgba(255,255,255,0.15); border-radius:8px; color:var(--text-muted); cursor:pointer; font-size:13px; }
</style>

<div class="chat-page emoji-keep">
    <div class="chat-cat-tabs">
        <?php foreach (['kino', 'anime', 'multfilm'] as $c): ?>
        <a href="?cat=<?php echo $c; ?>" class="chat-cat-tab <?php echo $c === $cat ? 'active' : ''; ?>"><?php echo $cat_icons[$c] . ' ' . $cat_labels[$c]; ?></a>
        <?php endforeach; ?>
        <span id="onlineCountChat" class="online-badge-chat">🟢 Onlayn: 0 ta</span>
    </div>
    <div class="chat-box">
        <div class="pinned-banner" id="pinnedBanner" onclick="scrollToPinned()">
            <div class="pin-label">📌 <?php echo t('pinned_message'); ?></div>
            <div class="pin-user" id="pinUser"></div>
            <div class="pin-text" id="pinText"></div>
        </div>
        <div class="messages-area" id="msgArea">
            <div style="text-align:center;color:var(--text-muted);font-size:13px;">⏳ <?php echo t('loading_messages'); ?></div>
        </div>
        <?php if (is_user()): ?>
        <div class="reply-bar" id="replyBar">
            <div class="rb-info">
                <span class="rb-user" id="rbUser"></span>
                <span class="rb-txt" id="rbTxt"></span>
            </div>
            <button class="rb-close" onclick="cancelReply()">✕</button>
        </div>
        <div class="attach-preview-bar" id="attachPreviewBar" style="display:none;">
            <img id="attachPreviewImg" src="">
            <button onclick="clearAttachment()">✕ <?php echo t('cancel'); ?></button>
        </div>
        <div class="chat-input-bar">
            <button type="button" class="chat-attach-btn" id="emojiToggleBtn" title="Emoji">😊</button>
            <button type="button" class="chat-attach-btn <?php echo $is_premium_user ? '' : 'locked'; ?>" onclick="attachClick()" title="<?php echo $is_premium_user ? t('image_gif_upload') : t('only_premium'); ?>">📎</button>
            <input type="file" id="attachInput" accept="image/*,.gif" style="display:none;" onchange="onAttachSelect(this)">
            <input type="text" id="msgInput" placeholder="<?php echo t('write_message'); ?>" maxlength="500">
            <button class="chat-send-btn" onclick="sendMsg()"><?php echo t('send_btn'); ?></button>
        </div>
        <?php else: ?>
        <div class="need-login"><?php echo t('send_to_login'); ?> <a href="auth/login.php"><?php echo t('login'); ?></a> <?php echo t('or'); ?> <a href="auth/register.php"><?php echo t('register_text'); ?></a>.</div>
        <?php endif; ?>
    </div>
</div>

<div class="emoji-picker emoji-keep" id="emojiPicker">
    <?php foreach (['😀','😂','😍','😎','🥰','😢','😡','👍','👎','❤️','🔥','🎉','🍿','🎬','⭐','🤔','😴','🙌','👀','💯'] as $emo): ?>
    <span data-emoji="<?php echo $emo; ?>"><?php echo $emo; ?></span>
    <?php endforeach; ?>
</div>

<div class="ctx-menu emoji-keep" id="ctxMenu">
    <button data-action="reply">↩️ <?php echo t('reply'); ?></button>
    <button data-action="react">😀 <?php echo t('reaction'); ?></button>
    <button data-action="forward">↪️ <?php echo t('forward'); ?></button>
    <div class="ctx-sep" id="ctxOwnerSep" style="display:none;"></div>
    <button id="ctxEditBtn" data-action="edit" style="display:none;">✏️ <?php echo t('edit'); ?></button>
    <button id="ctxDeleteBtn" data-action="delete" style="display:none;" class="danger">🗑️ <?php echo t('delete'); ?></button>
    <div class="ctx-sep" id="ctxAdminSep" style="display:none;"></div>
    <button id="ctxPinBtn" data-action="pin" style="display:none;">📌 <?php echo t('pin_message'); ?></button>
</div>

<div class="react-picker emoji-keep" id="reactPicker">
    <span data-emoji="👍">👍</span>
    <span data-emoji="❤️">❤️</span>
    <span data-emoji="🔥">🔥</span>
    <span data-emoji="😂">😂</span>
    <span data-emoji="😮">😮</span>
    <span data-emoji="😢">😢</span>
</div>

<div class="forward-modal" id="forwardModal">
    <div class="forward-modal-box">
        <h3>↪️ <?php echo t('forward_to'); ?></h3>
        <?php foreach (['kino' => '🎬', 'anime' => '🎌', 'multfilm' => '🎞️'] as $fc => $fi): ?>
        <?php if ($fc !== $cat): ?>
        <div class="fm-cat" onclick="doForward('<?php echo $fc; ?>')">
            <span class="fm-icon"><?php echo $fi; ?></span>
            <span class="fm-label"><?php echo $cat_labels[$fc]; ?></span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
        <button class="fm-cancel" onclick="document.getElementById('forwardModal').classList.remove('active')"><?php echo t('cancel'); ?></button>
    </div>
</div>

<script>
var lastId = 0;
var currentCat = '<?php echo $cat; ?>';
var currentUserId = <?php echo is_user() ? (int)$_SESSION['user_id'] : 'null'; ?>;
var isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
var isPremium = <?php echo $is_premium_user ? 'true' : 'false'; ?>;
var defaultAvatar = '/uzdub/assets/default-avatar.svg';
var selectedFile = null;
var replyToId = null;
var ctxMsgId = null;
var editingMsgId = null;
var csrfToken = '<?php echo e(csrf_token()); ?>';
var writeMsgPh = '<?php echo t('write_message'); ?>';
var editMsgPh = '<?php echo t('edit_message'); ?>';
var T = <?php echo json_encode([
    'no_messages_yet' => t('no_messages_yet'),
    'load_error' => t('load_error'),
    'retry' => t('retry'),
    'error_occurred' => t('error_occurred'),
    'image_gif_premium_alert' => t('image_gif_premium_alert'),
    'confirm_delete' => t('confirm_delete'),
    'edited' => t('edited'),
    'forwarded' => t('forwarded'),
    'reply' => t('reply'),
    'forward' => t('forward'),
    'pinned_message' => t('pinned_message'),
    'deleted_message' => t('deleted_message'),
], JSON_UNESCAPED_UNICODE); ?>;

function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str || ''));
    return div.innerHTML;
}

function postAjax(data, cb) {
    fetch('/uzdub/global_chat.php', {method:'POST', body:data})
        .then(function(r) { return r.json(); })
        .then(function(r) { if (cb) cb(r); })
        .catch(function(err) { console.error('AJAX error:', err); alert(T.error_occurred); });
}

function renderReactions(msgId, reactions) {
    var html = '';
    if (!reactions) return '';
    try {
        var r = typeof reactions === 'string' ? JSON.parse(reactions) : reactions;
        for (var emoji in r) {
            if (!r[emoji] || r[emoji].length === 0) continue;
            var isActive = currentUserId && r[emoji].indexOf(currentUserId) !== -1;
            html += '<span class="msg-reaction' + (isActive ? ' active' : '') + '" data-react-emoji="' + escHtml(emoji) + '" data-react-msg="' + msgId + '">' + emoji + ' <span class="r-count">' + r[emoji].length + '</span></span>';
        }
    } catch(e) {}
    return html ? '<div class="msg-reactions">' + html + '</div>' : '';
}

function renderMsg(msg) {
    if (msg.is_deleted == 1) {
        return '<div class="msg-item" data-id="' + msg.id + '" style="opacity:0.5;"><div class="msg-body"><div class="msg-text" style="font-style:italic;color:var(--text-muted);">🚫 ' + escHtml(T.deleted_message) + '</div></div></div>';
    }
    var isOwn = currentUserId && msg.user_id == currentUserId;
    var avatar = msg.avatar ? '/uzdub/uploads/avatars/' + msg.avatar : defaultAvatar;
    var prem = msg.is_premium == 1 ? '<span class="msg-prem">👑</span>' : '';
    var body = '';

    if (msg.forwarded_from) body += '<div class="msg-forward-badge">↪️ ' + escHtml(T.forwarded) + '</div>';

    if (msg.reply_to && msg.reply_message !== null) {
        var refUser = msg.reply_username || '?';
        var refTxt = msg.reply_attachment && !msg.reply_message ? '[📷]' : escHtml((msg.reply_message || '').substring(0, 80));
        body += '<div class="msg-reply-ref"><span class="ref-user">' + escHtml(refUser) + '</span><span class="ref-text">' + refTxt + '</span></div>';
    }

    if (msg.message) body += '<div class="msg-text">' + escHtml(msg.message) + '</div>';
    if (msg.attachment) body += '<img class="msg-image" src="/uzdub/uploads/chat/' + escHtml(msg.attachment) + '" onclick="window.open(this.src)">';

    var edited = msg.is_edited == 1 ? ' <span class="msg-edited">' + escHtml(T.edited) + '</span>' : '';

    var hoverBar = currentUserId ? '<div class="msg-hover-bar"><button data-haction="react" data-hid="' + msg.id + '" data-huid="' + msg.user_id + '" title="😀">😀</button><button data-haction="reply" data-hid="' + msg.id + '" data-huid="' + msg.user_id + '" title="' + escHtml(T.reply) + '">↩️</button></div>' : '';

    return '<div class="msg-item' + (isOwn ? ' own' : '') + '" data-id="' + msg.id + '" data-uid="' + msg.user_id + '">' +
        '<img class="msg-avatar" src="' + avatar + '" onerror="this.src=\'' + defaultAvatar + '\'">' +
        '<div class="msg-body">' + hoverBar +
            '<div class="msg-header">' +
                '<a href="/uzdub/profile.php?uid=' + escHtml(msg.uid) + '" class="msg-username">' + escHtml(msg.username) + '</a>' + prem +
                '<span class="msg-time">' + escHtml(msg.created_at.substring(11,16)) + '</span>' + edited +
            '</div>' + body +
            renderReactions(msg.id, msg.reactions) +
        '</div></div>';
}

function updateReactionsDOM(msgId, reactions) {
    var msgEl = document.querySelector('.msg-item[data-id="' + msgId + '"]');
    if (!msgEl) return;
    var oldBar = msgEl.querySelector('.msg-reactions');
    var newHtml = renderReactions(msgId, reactions);
    if (oldBar) {
        if (newHtml) {
            oldBar.outerHTML = newHtml;
        } else {
            oldBar.remove();
        }
    } else if (newHtml) {
        var body = msgEl.querySelector('.msg-body');
        if (body) body.insertAdjacentHTML('beforeend', newHtml);
    }
}

function deleteMsgDOM(msgId) {
    var msgEl = document.querySelector('.msg-item[data-id="' + msgId + '"]');
    if (!msgEl) return;
    msgEl.style.transition = 'opacity .3s, max-height .3s';
    msgEl.style.opacity = '0.3';
    msgEl.style.maxHeight = '0';
    msgEl.style.overflow = 'hidden';
    setTimeout(function() { msgEl.remove(); }, 350);
}

function editMsgDOM(msgId, newText) {
    var msgEl = document.querySelector('.msg-item[data-id="' + msgId + '"]');
    if (!msgEl) return;
    var txtEl = msgEl.querySelector('.msg-text');
    if (txtEl) {
        txtEl.textContent = newText;
        var hdr = msgEl.querySelector('.msg-header');
        if (hdr && !hdr.querySelector('.msg-edited')) {
            hdr.insertAdjacentHTML('beforeend', ' <span class="msg-edited">' + escHtml(T.edited) + '</span>');
        }
    }
}

var fetchRetries = 0;
var maxRetries = 3;
function fetchMessages() {
    fetch('/uzdub/global_chat.php?fetch_msgs=1&last_id=' + lastId + '&cat=' + currentCat)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            fetchRetries = 0;
            var msgs = data.messages || [];
            if (data.pinned) {
                var pb = document.getElementById('pinnedBanner');
                document.getElementById('pinUser').textContent = data.pinned.username;
                document.getElementById('pinText').textContent = data.pinned.message || data.pinned.attachment ? (data.pinned.message || '[📷]') : '';
                pb.style.display = 'block';
            } else {
                document.getElementById('pinnedBanner').style.display = 'none';
            }
            if (msgs.length > 0) {
                var area = document.getElementById('msgArea');
                var atBottom = area.scrollHeight - area.scrollTop <= area.clientHeight + 80;
                if (lastId === 0) area.innerHTML = '';
                msgs.forEach(function(m) {
                    area.insertAdjacentHTML('beforeend', renderMsg(m));
                    lastId = Math.max(lastId, parseInt(m.id));
                });
                if (atBottom || lastId === 0) area.scrollTop = area.scrollHeight;
            } else if (lastId === 0) {
                document.getElementById('msgArea').innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:13px;padding:30px;">' + escHtml(T.no_messages_yet) + ' 🎉</div>';
            }
        })
        .catch(function() {
            fetchRetries++;
            if (lastId === 0) {
                if (fetchRetries >= maxRetries) {
                    document.getElementById('msgArea').innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:13px;padding:30px;">⚠️ ' + escHtml(T.load_error) + ' <button onclick="fetchRetries=0;fetchMessages();" style="background:var(--blue-primary);border:none;color:#fff;padding:6px 16px;border-radius:8px;cursor:pointer;margin-left:8px;">' + escHtml(T.retry) + '</button></div>';
                } else { setTimeout(fetchMessages, 2000); }
            }
        });
}

function scrollToPinned() { document.getElementById('msgArea').scrollTop = 0; }

function toggleEmoji() { document.getElementById('emojiPicker').classList.toggle('active'); }
function insertEmoji(emo) {
    var input = document.getElementById('msgInput');
    input.value += emo;
    input.focus();
}
function attachClick() {
    if (!isPremium) { alert(T.image_gif_premium_alert); return; }
    document.getElementById('attachInput').click();
}
function onAttachSelect(input) {
    if (input.files && input.files[0]) {
        selectedFile = input.files[0];
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('attachPreviewImg').src = e.target.result;
            document.getElementById('attachPreviewBar').style.display = 'block';
        };
        reader.readAsDataURL(selectedFile);
    }
}
function clearAttachment() {
    selectedFile = null;
    document.getElementById('attachInput').value = '';
    document.getElementById('attachPreviewBar').style.display = 'none';
}

function setReply(mid, username, text) {
    replyToId = mid;
    document.getElementById('rbUser').textContent = username;
    document.getElementById('rbTxt').textContent = text ? text.substring(0, 100) : '[📷]';
    document.getElementById('replyBar').classList.add('active');
    document.getElementById('msgInput').focus();
}
function cancelReply() { replyToId = null; document.getElementById('replyBar').classList.remove('active'); }

function sendMsg() {
    if (editingMsgId) { doEditMsg(); return; }
    var input = document.getElementById('msgInput');
    var txt = input.value.trim();
    if (!txt && !selectedFile) return;
    var fd = new FormData();
    fd.append('ajax_send', '1');
    fd.append('message', txt);
    fd.append('category', currentCat);
    fd.append('csrf_token', csrfToken);
    if (replyToId) fd.append('reply_to', replyToId);
    if (selectedFile) fd.append('attachment', selectedFile);
    postAjax(fd, function(r) {
        if (r.ok) { input.value = ''; clearAttachment(); cancelReply(); cancelEdit(); fetchMessages(); }
        else { alert(r.msg || T.error_occurred); }
    });
}

function doEditMsg() {
    var input = document.getElementById('msgInput');
    var txt = input.value.trim();
    if (!txt || !editingMsgId) return;
    var fd = new FormData();
    fd.append('ajax_edit', '1');
    fd.append('msg_id', editingMsgId);
    fd.append('message', txt);
    fd.append('category', currentCat);
    fd.append('csrf_token', csrfToken);
    var mid = editingMsgId;
    cancelEdit();
    postAjax(fd, function(r) {
        if (r.ok) { editMsgDOM(mid, txt); }
        else { alert(r.msg || T.error_occurred); fetchMessages(); }
    });
}
function cancelEdit() {
    editingMsgId = null;
    var input = document.getElementById('msgInput');
    if (input) { input.value = ''; input.placeholder = writeMsgPh; }
}

function doReact(emoji, mid) {
    var rid = mid || ctxMsgId;
    document.getElementById('reactPicker').classList.remove('active');
    var fd = new FormData();
    fd.append('ajax_react', '1');
    fd.append('msg_id', rid);
    fd.append('emoji', emoji);
    fd.append('category', currentCat);
    fd.append('csrf_token', csrfToken);
    postAjax(fd, function(r) {
        if (r.ok && r.reactions) updateReactionsDOM(rid, r.reactions);
    });
}

function doForward(toCat) {
    document.getElementById('forwardModal').classList.remove('active');
    var fd = new FormData();
    fd.append('ajax_forward', '1');
    fd.append('msg_id', ctxMsgId);
    fd.append('to_category', toCat);
    fd.append('category', currentCat);
    fd.append('csrf_token', csrfToken);
    postAjax(fd, function() { fetchMessages(); });
}

function openCtxMenuAt(x, y, msgId, msgUid) {
    ctxMsgId = msgId;
    var isOwn = currentUserId && msgUid == currentUserId;

    document.getElementById('ctxOwnerSep').style.display = isOwn ? 'block' : 'none';
    document.getElementById('ctxEditBtn').style.display = isOwn ? 'flex' : 'none';
    document.getElementById('ctxDeleteBtn').style.display = isOwn ? 'flex' : 'none';
    document.getElementById('ctxAdminSep').style.display = isAdmin ? 'block' : 'none';
    document.getElementById('ctxPinBtn').style.display = isAdmin ? 'flex' : 'none';

    var cm = document.getElementById('ctxMenu');
    cm.style.top = Math.min(y + 4, window.innerHeight - 250) + 'px';
    cm.style.left = Math.min(x, window.innerWidth - 200) + 'px';
    cm.classList.add('active');
}

function hideCtx() {
    document.getElementById('ctxMenu').classList.remove('active');
    document.getElementById('reactPicker').classList.remove('active');
}

function setupInput() {
    var input = document.getElementById('msgInput');
    if (!input) return;
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
        if (e.key === 'Escape') { cancelReply(); cancelEdit(); }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    setupInput();
    var area = document.getElementById('msgArea');
    var hoverTimer = null;
    var longPressTimer = null;
    var longPressFired = false;
    var activeHoverBar = null;

    function showHoverBar(msgEl) {
        if (activeHoverBar && activeHoverBar !== msgEl) activeHoverBar.classList.remove('hovered');
        msgEl.classList.add('hovered');
        activeHoverBar = msgEl;
    }
    function hideHoverBar(msgEl) {
        if (msgEl) msgEl.classList.remove('hovered');
        else if (activeHoverBar) activeHoverBar.classList.remove('hovered');
        if (activeHoverBar && (!msgEl || msgEl === activeHoverBar)) activeHoverBar = null;
    }
    function hideAll() { hideHoverBar(); hideCtx(); document.getElementById('reactPicker').classList.remove('active'); }

    /* --- HOVER (500ms) → show hover bar with React + Reply --- */
    area.addEventListener('mouseover', function(e) {
        var msgEl = e.target.closest('.msg-item');
        if (!msgEl || !currentUserId) return;
        clearTimeout(hoverTimer);
        hoverTimer = setTimeout(function() { showHoverBar(msgEl); }, 500);
    });
    area.addEventListener('mouseout', function(e) {
        var msgEl = e.target.closest('.msg-item');
        clearTimeout(hoverTimer);
        if (msgEl) {
            var related = e.relatedTarget;
            if (!related || !msgEl.contains(related)) hideHoverBar(msgEl);
        }
    });

    /* --- DBLCLICK (instant) → quick 👍 reaction --- */
    area.addEventListener('dblclick', function(e) {
        if (e.target.closest('.msg-hover-bar') || e.target.closest('.ctx-menu') || e.target.closest('.react-picker')) return;
        var msgEl = e.target.closest('.msg-item');
        if (!msgEl || !currentUserId) return;
        var mid = parseInt(msgEl.dataset.id);
        if (!mid) return;
        doReact('👍', mid);
    });

    /* --- LONG PRESS (1000ms) → context menu (Edit/Delete for owner, Pin for admin) --- */
    area.addEventListener('mousedown', function(e) {
        if (e.target.closest('.msg-hover-bar') || e.target.closest('.ctx-menu') || e.target.closest('.react-picker')) return;
        if (e.button !== 0) return;
        var msgEl = e.target.closest('.msg-item');
        if (!msgEl || !currentUserId) return;
        longPressFired = false;
        var mid = parseInt(msgEl.dataset.id);
        var uid = parseInt(msgEl.dataset.uid);
        longPressTimer = setTimeout(function() {
            longPressFired = true;
            hideHoverBar();
            openCtxMenuAt(e.clientX, e.clientY, mid, uid);
        }, 1000);
    });
    area.addEventListener('mouseup', function() { clearTimeout(longPressTimer); });
    area.addEventListener('mouseleave', function() { clearTimeout(longPressTimer); });

    area.addEventListener('touchstart', function(e) {
        var msgEl = e.target.closest('.msg-item');
        if (!msgEl || !currentUserId) return;
        longPressFired = false;
        var mid = parseInt(msgEl.dataset.id);
        var uid = parseInt(msgEl.dataset.uid);
        var touch = e.touches[0];
        longPressTimer = setTimeout(function() {
            longPressFired = true;
            hideHoverBar();
            openCtxMenuAt(touch.clientX, touch.clientY, mid, uid);
        }, 1000);
    }, {passive: true});
    area.addEventListener('touchend', function() { clearTimeout(longPressTimer); });
    area.addEventListener('touchmove', function() { clearTimeout(longPressTimer); });

    /* --- CLICK DELEGATION (hover bar buttons, reactions, menu btn) --- */
    area.addEventListener('click', function(e) {
        if (longPressFired) { longPressFired = false; return; }

        var hbBtn = e.target.closest('[data-haction]');
        if (hbBtn) {
            e.stopPropagation();
            var action = hbBtn.dataset.haction;
            var hid = parseInt(hbBtn.dataset.hid);
            var huid = parseInt(hbBtn.dataset.huid);
            if (action === 'react') {
                var pr = document.getElementById('reactPicker');
                var rect = hbBtn.getBoundingClientRect();
                pr.style.top = (rect.bottom + 4) + 'px';
                pr.style.left = Math.min(rect.left, window.innerWidth - 260) + 'px';
                pr.classList.add('active');
                ctxMsgId = hid;
            } else if (action === 'reply') {
                var el = document.querySelector('.msg-item[data-id="'+hid+'"]');
                if (!el) return;
                var user = el.querySelector('.msg-username');
                var txt = el.querySelector('.msg-text');
                setReply(hid, user ? user.textContent : '', txt ? txt.textContent : '');
            }
            return;
        }

        var reactEl = e.target.closest('.msg-reaction');
        if (reactEl && reactEl.dataset.reactEmoji) {
            e.stopPropagation();
            doReact(reactEl.dataset.reactEmoji, parseInt(reactEl.dataset.reactMsg));
            return;
        }
    });

    /* --- GLOBAL CLICK: close pickers --- */
    document.addEventListener('click', function(e) {
        var emojiPicker = document.getElementById('emojiPicker');
        var emojiToggle = document.getElementById('emojiToggleBtn');
        if (emojiPicker && !emojiPicker.contains(e.target) && e.target !== emojiToggle && !emojiToggle.contains(e.target)) {
            emojiPicker.classList.remove('active');
        }
        if (!e.target.closest('.ctx-menu') && !e.target.closest('.react-picker') && !e.target.closest('.msg-hover-bar') && !e.target.closest('.msg-item')) hideCtx();
        if (!e.target.closest('.react-picker') && !e.target.closest('.msg-reaction') && !e.target.closest('[data-haction="react"]')) {
            document.getElementById('reactPicker').classList.remove('active');
        }
    });

    /* --- CTX MENU ACTIONS --- */
    document.getElementById('ctxMenu').addEventListener('click', function(e) {
        var btn = e.target.closest('button');
        if (!btn) return;
        e.stopPropagation();
        var action = btn.dataset.action;
        if (action === 'reply') {
            hideCtx();
            var el = document.querySelector('.msg-item[data-id="'+ctxMsgId+'"]');
            if (!el) return;
            var user = el.querySelector('.msg-username');
            var txt = el.querySelector('.msg-text');
            setReply(ctxMsgId, user ? user.textContent : '', txt ? txt.textContent : '');
        } else if (action === 'react') {
            hideCtx();
            var pr = document.getElementById('reactPicker');
            var el = document.querySelector('.msg-item[data-id="'+ctxMsgId+'"]');
            if (!el) return;
            var rect = el.getBoundingClientRect();
            pr.style.top = rect.top + 'px';
            pr.style.left = Math.min(rect.left + 20, window.innerWidth - 260) + 'px';
            pr.classList.add('active');
        } else if (action === 'forward') {
            hideCtx();
            document.getElementById('forwardModal').classList.add('active');
        } else if (action === 'edit') {
            hideCtx();
            var el = document.querySelector('.msg-item[data-id="'+ctxMsgId+'"]');
            if (!el) return;
            var txt = el.querySelector('.msg-text');
            if (!txt) return;
            editingMsgId = ctxMsgId;
            var input = document.getElementById('msgInput');
            input.value = txt.textContent;
            input.placeholder = editMsgPh;
            input.focus();
        } else if (action === 'delete') {
            hideCtx();
            if (!confirm(T.confirm_delete)) return;
            var mid = ctxMsgId;
            var fd = new FormData();
            fd.append('ajax_delete', '1');
            fd.append('msg_id', mid);
            fd.append('category', currentCat);
            fd.append('csrf_token', csrfToken);
            postAjax(fd, function(r) {
                if (r.ok) deleteMsgDOM(mid);
                else { alert(r.msg || T.error_occurred); fetchMessages(); }
            });
        } else if (action === 'pin') {
            hideCtx();
            var fd = new FormData();
            fd.append('ajax_pin', '1');
            fd.append('msg_id', ctxMsgId);
            fd.append('category', currentCat);
            fd.append('csrf_token', csrfToken);
            postAjax(fd, function() { fetchMessages(); });
        }
    });

    document.getElementById('reactPicker').addEventListener('click', function(e) {
        var span = e.target.closest('span');
        if (!span) return;
        e.stopPropagation();
        doReact(span.dataset.emoji);
    });

    var emojiToggleBtn = document.getElementById('emojiToggleBtn');
    if (emojiToggleBtn) {
        emojiToggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleEmoji();
        });
    }

    document.getElementById('emojiPicker').addEventListener('click', function(e) {
        var span = e.target.closest('span');
        if (!span || !span.dataset.emoji) return;
        e.stopPropagation();
        insertEmoji(span.dataset.emoji);
        document.getElementById('emojiPicker').classList.remove('active');
    });

    fetchMessages();
    setInterval(fetchMessages, 3000);

    /* --- REACTION POLLING --- */
    function pollReactions() {
        var area = document.getElementById('msgArea');
        if (!area) return;
        var msgEls = area.querySelectorAll('.msg-item[data-id]');
        if (msgEls.length === 0) return;
        var ids = [];
        msgEls.forEach(function(el) { ids.push(el.dataset.id); });
        fetch('/uzdub/global_chat.php?fetch_reactions=1&cat=' + currentCat + '&ids=' + ids.join(','))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.updates) return;
                data.updates.forEach(function(u) {
                    var msgEl = area.querySelector('.msg-item[data-id="' + u.msg_id + '"]');
                    if (!msgEl) return;
                    var oldBar = msgEl.querySelector('.msg-reactions');
                    var newHtml = renderReactions(u.msg_id, u.reactions);
                    if (oldBar) {
                        if (newHtml) { oldBar.outerHTML = newHtml; }
                        else { oldBar.remove(); }
                    } else if (newHtml) {
                        var body = msgEl.querySelector('.msg-body');
                        if (body) body.insertAdjacentHTML('beforeend', newHtml);
                    }
                });
            })
            .catch(function() {});
    }
    setInterval(pollReactions, 3000);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
