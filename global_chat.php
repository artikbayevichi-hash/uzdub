<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Global Chat';

// AJAX - yangi xabar (matn yoki rasm/gif)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_send'])) {
    header('Content-Type: application/json');
    if (!is_user()) { echo json_encode(['ok'=>false,'msg'=>'Kirish kerak']); exit; }
    if (!validate_csrf($_POST['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'msg'=>'Xavfsizlik tokeni noto\'g\'ri']); exit; }
    $user = current_user();
    check_premium_expiry($pdo, $user['id']);
    refresh_user_session($pdo, $user['id']);
    $user = current_user();

    $txt = trim($_POST['message'] ?? '');
    $attachment = null;
    $attachment_type = null;

    // Faqat premium foydalanuvchi rasm/gif yubora oladi
    if (!empty($_FILES['attachment']['name'])) {
        if (!$user['is_premium']) {
            echo json_encode(['ok'=>false,'msg'=>'Rasm/GIF yuborish faqat Premium foydalanuvchilar uchun! ⭐']);
            exit;
        }
        $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['ok'=>false,'msg'=>'Faqat rasm yoki GIF fayl yuborish mumkin.']);
            exit;
        }
        $attachment = upload_file('attachment', __DIR__ . '/uploads/chat/', $allowed);
        if (!$attachment) { echo json_encode(['ok'=>false,'msg'=>'Fayl yuklashda xatolik.']); exit; }
        $attachment_type = ($ext === 'gif') ? 'gif' : 'image';
    }

    if ($txt === '' && !$attachment) { echo json_encode(['ok'=>false,'msg'=>'Xabar bo\'sh bo\'lishi mumkin emas']); exit; }
    if (mb_strlen($txt) > 500) { echo json_encode(['ok'=>false,'msg'=>'Xabar juda uzun']); exit; }

    $pdo->prepare("INSERT INTO global_messages (user_id, message, attachment, attachment_type) VALUES (?,?,?,?)")
        ->execute([$user['id'], $txt ?: null, $attachment, $attachment_type]);
    echo json_encode(['ok'=>true]);
    exit;
}

// AJAX - so'nggi xabarlarni olish
if (isset($_GET['fetch_msgs'])) {
    header('Content-Type: application/json');
    $last_id = (int)($_GET['last_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT gm.*, u.username, u.avatar, u.user_id as uid, u.is_premium FROM global_messages gm JOIN users u ON gm.user_id=u.id WHERE gm.id > ? ORDER BY gm.id ASC LIMIT 50");
    $stmt->execute([$last_id]);
    $msgs = $stmt->fetchAll();
    echo json_encode($msgs);
    exit;
}

$is_premium_user = false;
if (is_user()) {
    $u = current_user();
    check_premium_expiry($pdo, $u['id']);
    refresh_user_session($pdo, $u['id']);
    $is_premium_user = (bool)current_user()['is_premium'];
}

include __DIR__ . '/includes/header.php';
?>
<style>
.chat-page { max-width:860px; margin:100px auto 40px; padding:0 16px; position:relative;z-index:1; }
.chat-page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
.chat-page-header h1 { font-size:22px; border-left:4px solid var(--blue-primary); padding-left:10px; margin:0; }
.inbox-link-btn { display:flex; align-items:center; gap:8px; padding:9px 18px; background:var(--card-bg); border:1px solid var(--blue-primary); border-radius:20px; color:var(--blue-glow); text-decoration:none; font-size:13px; font-weight:600; position:relative; }
.inbox-link-btn:hover { background:rgba(33,150,243,0.15); }
.inbox-unread-dot { position:absolute; top:-4px; right:-4px; width:12px; height:12px; background:#e53935; border-radius:50%; border:2px solid var(--bg-dark); }
.chat-box { background:var(--card-bg); border:1px solid rgba(33,150,243,0.2); border-radius:12px; overflow:hidden; }
.messages-area { height:480px; overflow-y:auto; padding:18px; display:flex; flex-direction:column; gap:10px; }
.messages-area::-webkit-scrollbar { width:5px; }
.messages-area::-webkit-scrollbar-thumb { background:var(--blue-deep); border-radius:10px; }
.msg-item { display:flex; gap:10px; align-items:flex-start; }
.msg-item.own { flex-direction:row-reverse; }
.msg-avatar { width:38px; height:38px; border-radius:50%; object-fit:cover; border:2px solid var(--blue-primary); flex-shrink:0; }
.msg-body { max-width:72%; }
.msg-header { display:flex; align-items:center; gap:7px; margin-bottom:4px; }
.msg-username { font-size:13px; font-weight:600; color:var(--blue-glow); text-decoration:none; }
.msg-username:hover { text-decoration:underline; }
.msg-prem { background:linear-gradient(135deg,#f9a825,#ff6f00); color:#fff; font-size:10px; padding:1px 7px; border-radius:10px; }
.msg-time { font-size:11px; color:var(--text-muted); }
.msg-text { background:#0d1424; border-radius:0 10px 10px 10px; padding:10px 14px; font-size:14px; line-height:1.5; word-break:break-word; }
.msg-item.own .msg-text { border-radius:10px 0 10px 10px; background:var(--blue-deep); }
.msg-item.own .msg-header { flex-direction:row-reverse; }
.msg-image { max-width:240px; border-radius:10px; margin-top:4px; display:block; cursor:pointer; }
.chat-input-bar { padding:14px 16px; border-top:1px solid rgba(33,150,243,0.15); display:flex; gap:10px; align-items:center; }
.chat-input-bar input[type=text] { flex:1; padding:11px 15px; background:#0d1424; border:1px solid rgba(33,150,243,0.25); border-radius:8px; color:var(--text-light); font-size:14px; outline:none; }
.chat-input-bar input:focus { border-color:var(--blue-primary); }
.chat-send-btn { padding:11px 22px; background:var(--blue-primary); border:none; border-radius:8px; color:#fff; font-weight:600; cursor:pointer; font-size:14px; }
.chat-send-btn:hover { background:var(--blue-glow); }
.chat-attach-btn { width:42px; height:42px; border-radius:8px; background:rgba(255,255,255,0.08); border:1px solid rgba(33,150,243,0.25); color:var(--text-light); font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; position:relative; }
.chat-attach-btn.locked::after { content:'⭐'; position:absolute; top:-6px; right:-6px; font-size:11px; }
.chat-attach-btn:hover { border-color:var(--blue-primary); }
.need-login { text-align:center; padding:18px; color:var(--text-muted); font-size:14px; }
.need-login a { color:var(--blue-glow); }
.attach-preview-bar { padding:0 16px; }
.attach-preview-bar img { max-height:80px; border-radius:8px; margin:8px 0; }
.attach-preview-bar button { margin-left:10px; background:none; border:none; color:#ef5350; cursor:pointer; font-size:13px; }
.emoji-picker { position:absolute; bottom:60px; left:16px; background:var(--card-bg); border:1px solid rgba(33,150,243,0.3); border-radius:10px; padding:10px; display:none; flex-wrap:wrap; gap:6px; width:260px; z-index:20; }
.emoji-picker.active { display:flex; }
.emoji-picker span { font-size:20px; cursor:pointer; padding:4px; }
.emoji-picker span:hover { background:rgba(33,150,243,0.15); border-radius:6px; }
</style>

<div class="chat-page">
    <div class="chat-page-header">
        <h1>💬 Global Chat</h1>
        <?php if (is_user()):
            $unread_count = $pdo->prepare("SELECT COUNT(*) c FROM private_messages WHERE receiver_id=? AND is_read=0");
            $unread_count->execute([$_SESSION['user_id']]);
            $unread_count = $unread_count->fetch()['c'];
        ?>
        <a href="inbox.php" class="inbox-link-btn">
            💌 Shaxsiy xabarlar
            <?php if ($unread_count > 0): ?><span class="inbox-unread-dot"></span><?php endif; ?>
        </a>
        <?php endif; ?>
    </div>
    <div class="chat-box" style="position:relative;">
        <div class="messages-area" id="msgArea">
            <div style="text-align:center;color:var(--text-muted);font-size:13px;">⏳ Xabarlar yuklanmoqda...</div>
        </div>
        <?php if (is_user()): ?>
        <div class="attach-preview-bar" id="attachPreviewBar" style="display:none;">
            <img id="attachPreviewImg" src="">
            <button onclick="clearAttachment()">✕ Bekor qilish</button>
        </div>
        <div class="emoji-picker" id="emojiPicker">
            <?php foreach (['😀','😂','😍','😎','🥰','😢','😡','👍','👎','❤️','🔥','🎉','🍿','🎬','⭐','🤔','😴','🙌','👀','💯'] as $emo): ?>
            <span onclick="insertEmoji('<?php echo $emo; ?>')"><?php echo $emo; ?></span>
            <?php endforeach; ?>
        </div>
        <div class="chat-input-bar">
            <button type="button" class="chat-attach-btn" onclick="toggleEmoji()" title="Emoji">😊</button>
            <button type="button" class="chat-attach-btn <?php echo $is_premium_user ? '' : 'locked'; ?>" onclick="attachClick()" title="<?php echo $is_premium_user ? 'Rasm/GIF yuborish' : 'Faqat Premium uchun'; ?>">📎</button>
            <input type="file" id="attachInput" accept="image/*,.gif" style="display:none;" onchange="onAttachSelect(this)">
            <input type="text" id="msgInput" placeholder="Xabar yozing..." maxlength="500">
            <button class="chat-send-btn" onclick="sendMsg()">Yuborish</button>
        </div>
        <?php else: ?>
        <div class="need-login">Xabar yuborish uchun <a href="auth/login.php">kiring</a> yoki <a href="auth/register.php">ro'yxatdan o'ting</a>.</div>
        <?php endif; ?>
    </div>
</div>

<script>
var lastId = 0;
var currentUserId = <?php echo is_user() ? (int)$_SESSION['user_id'] : 'null'; ?>;
var isPremium = <?php echo $is_premium_user ? 'true' : 'false'; ?>;
var defaultAvatar = '/uzdub/assets/default-avatar.png';
var selectedFile = null;

function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str || ''));
    return div.innerHTML;
}

function renderMsg(msg) {
    var isOwn = currentUserId && msg.user_id == currentUserId;
    var avatar = msg.avatar ? '/uzdub/uploads/avatars/' + msg.avatar : defaultAvatar;
    var prem = msg.is_premium == 1 ? '<span class="msg-prem">⭐</span>' : '';
    var body = '';
    if (msg.message) body += '<div class="msg-text">' + escHtml(msg.message) + '</div>';
    if (msg.attachment) body += '<img class="msg-image" src="/uzdub/uploads/chat/' + msg.attachment + '" onclick="window.open(this.src)">';
    return '<div class="msg-item' + (isOwn ? ' own' : '') + '" data-id="' + msg.id + '">' +
        '<img class="msg-avatar" src="' + avatar + '" onerror="this.src=\'' + defaultAvatar + '\'">' +
        '<div class="msg-body">' +
            '<div class="msg-header">' +
                '<a href="/uzdub/profile.php?uid=' + escHtml(msg.uid) + '" class="msg-username">' + escHtml(msg.username) + '</a>' + prem +
                '<span class="msg-time">' + escHtml(msg.created_at.substring(11,16)) + '</span>' +
            '</div>' + body +
        '</div></div>';
}

function fetchMessages() {
    fetch('/uzdub/global_chat.php?fetch_msgs=1&last_id=' + lastId)
        .then(r => r.json())
        .then(msgs => {
            if (msgs.length > 0) {
                var area = document.getElementById('msgArea');
                var atBottom = area.scrollHeight - area.scrollTop <= area.clientHeight + 80;
                if (lastId === 0) area.innerHTML = '';
                msgs.forEach(m => {
                    area.insertAdjacentHTML('beforeend', renderMsg(m));
                    lastId = Math.max(lastId, parseInt(m.id));
                });
                if (atBottom) area.scrollTop = area.scrollHeight;
            } else if (lastId === 0) {
                document.getElementById('msgArea').innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:13px;padding:30px;">Hozircha xabar yo\'q. Birinchi bo\'ling! 🎉</div>';
            }
        });
}

function toggleEmoji() {
    document.getElementById('emojiPicker').classList.toggle('active');
}
function insertEmoji(emo) {
    var input = document.getElementById('msgInput');
    input.value += emo;
    input.focus();
}
function attachClick() {
    if (!isPremium) { alert('Rasm/GIF yuborish faqat Premium foydalanuvchilar uchun! Premium sahifasidan sotib oling ⭐'); return; }
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

function sendMsg() {
    var input = document.getElementById('msgInput');
    var txt = input.value.trim();
    if (!txt && !selectedFile) return;
    var fd = new FormData();
    fd.append('ajax_send', '1');
    fd.append('message', txt);
    fd.append('csrf_token', '<?php echo e(csrf_token()); ?>');
    if (selectedFile) fd.append('attachment', selectedFile);
    fetch('/uzdub/global_chat.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => {
            if (r.ok) {
                input.value = '';
                clearAttachment();
                fetchMessages();
            } else {
                alert(r.msg || 'Xatolik yuz berdi');
            }
        });
}

document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('msgInput');
    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
        });
    }
    document.addEventListener('click', function(e) {
        var picker = document.getElementById('emojiPicker');
        if (picker && !picker.contains(e.target) && e.target.textContent !== '😊') {
            picker.classList.remove('active');
        }
    });
    fetchMessages();
    setInterval(fetchMessages, 3000);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
