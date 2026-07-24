<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_user();

$user = current_user();
check_premium_expiry($pdo, $user['id']);
refresh_user_session($pdo, $user['id']);
$user = current_user();

$with_uid = $_GET['with'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$with_uid]);
$other = $stmt->fetch();

if (!$other || $other['id'] == $user['id']) { header('Location: index.php'); exit; }

$page_title = t('chat_title_prefix') . $other['username'];

// AJAX - xabar yuborish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_send'])) {
    header('Content-Type: application/json');
    if (!validate_csrf($_POST['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'msg'=>t('security_token_wrong')]); exit; }
    $txt = trim($_POST['message'] ?? '');
    $attachment = null; $attachment_type = null;

    if (!empty($_FILES['attachment']['name'])) {
        if (!$user['is_premium']) { echo json_encode(['ok'=>false,'msg'=>t('image_gif_premium')]); exit; }
        $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed)) { echo json_encode(['ok'=>false,'msg'=>t('only_images_gifs')]); exit; }
        $attachment = upload_file('attachment', __DIR__ . '/uploads/chat/', $allowed);
        if (!$attachment) { echo json_encode(['ok'=>false,'msg'=>t('upload_error')]); exit; }
        $attachment_type = ($ext === 'gif') ? 'gif' : 'image';
    }

    if ($txt === '' && !$attachment) { echo json_encode(['ok'=>false,'msg'=>t('empty_message')]); exit; }

    $pdo->prepare("INSERT INTO private_messages (sender_id, receiver_id, message, attachment, attachment_type) VALUES (?,?,?,?,?)")
        ->execute([$user['id'], $other['id'], $txt ?: null, $attachment, $attachment_type]);
    echo json_encode(['ok'=>true]);
    exit;
}

// AJAX - xabarlarni olish
if (isset($_GET['fetch_msgs'])) {
    header('Content-Type: application/json');
    $last_id = (int)($_GET['last_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM private_messages WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) AND id > ? ORDER BY id ASC LIMIT 50");
    $stmt->execute([$user['id'], $other['id'], $other['id'], $user['id'], $last_id]);
    $msgs = $stmt->fetchAll();
    // O'qilgan deb belgilash
    $pdo->prepare("UPDATE private_messages SET is_read=1 WHERE sender_id=? AND receiver_id=?")->execute([$other['id'], $user['id']]);
    echo json_encode($msgs);
    exit;
}

include __DIR__ . '/includes/header.php';
?>
<style>
.chat-page { max-width:760px; margin:100px auto 40px; padding:0 16px; position:relative;z-index:1; }
.dm-header { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
.dm-header img { width:44px; height:44px; border-radius:50%; object-fit:cover; border:2px solid var(--blue-primary); }
.dm-header h1 { font-size:19px; }
.dm-header .uid { font-size:12px; color:var(--text-muted); }
.chat-box { background:var(--card-bg); border:1px solid rgba(33,150,243,0.2); border-radius:12px; overflow:hidden; position:relative; }
.messages-area { height:500px; overflow-y:auto; padding:18px; display:flex; flex-direction:column; gap:10px; }
.messages-area::-webkit-scrollbar { width:5px; }
.messages-area::-webkit-scrollbar-thumb { background:var(--blue-deep); border-radius:10px; }
.msg-item { display:flex; max-width:70%; }
.msg-item.own { align-self:flex-end; }
.msg-item.other { align-self:flex-start; }
.msg-text { background:#0d1424; border-radius:12px 12px 12px 2px; padding:10px 14px; font-size:14px; line-height:1.5; word-break:break-word; }
.msg-item.own .msg-text { background:var(--blue-deep); border-radius:12px 12px 2px 12px; }
.msg-image { max-width:220px; border-radius:10px; margin-top:4px; display:block; cursor:pointer; }
.msg-time-small { font-size:10px; color:var(--text-muted); margin-top:3px; display:block; text-align:right; }
.chat-input-bar { padding:14px 16px; border-top:1px solid rgba(33,150,243,0.15); display:flex; gap:10px; align-items:center; }
.chat-input-bar input[type=text] { flex:1; padding:11px 15px; background:#0d1424; border:1px solid rgba(33,150,243,0.25); border-radius:8px; color:var(--text-light); font-size:14px; outline:none; }
.chat-send-btn { padding:11px 22px; background:var(--blue-primary); border:none; border-radius:8px; color:#fff; font-weight:600; cursor:pointer; font-size:14px; }
.chat-send-btn:hover { background:var(--blue-glow); }
.chat-attach-btn { width:42px; height:42px; border-radius:8px; background:rgba(255,255,255,0.08); border:1px solid rgba(33,150,243,0.25); color:var(--text-light); font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; position:relative; }
.chat-attach-btn.locked::after { content:'⭐'; position:absolute; top:-6px; right:-6px; font-size:11px; }
.attach-preview-bar { padding:0 16px; }
.attach-preview-bar img { max-height:80px; border-radius:8px; margin:8px 0; }
.attach-preview-bar button { margin-left:10px; background:none; border:none; color:#ef5350; cursor:pointer; font-size:13px; }
.emoji-picker { position:absolute; bottom:60px; left:16px; background:var(--card-bg); border:1px solid rgba(33,150,243,0.3); border-radius:10px; padding:10px; display:none; flex-wrap:wrap; gap:6px; width:260px; z-index:20; }
.emoji-picker.active { display:flex; }
.emoji-picker span { font-size:20px; cursor:pointer; padding:4px; }
.emoji-picker span:hover { background:rgba(33,150,243,0.15); border-radius:6px; }
</style>

<div class="chat-page">
    <div class="dm-header">
        <img src="<?php echo avatar_url($other['avatar']); ?>" alt="">
        <div>
            <h1><?php echo e($other['username']); ?> <?php if ($other['is_premium']): ?>⭐<?php endif; ?></h1>
            <div class="uid">🆔 <?php echo e($other['user_id']); ?></div>
        </div>
    </div>
    <div class="chat-box">
        <div class="messages-area" id="msgArea">
            <div style="text-align:center;color:var(--text-muted);font-size:13px;">⏳ <?php echo t('loading'); ?></div>
        </div>
        <div class="attach-preview-bar" id="attachPreviewBar" style="display:none;">
            <img id="attachPreviewImg" src="">
            <button onclick="clearAttachment()">✕ <?php echo t('cancel'); ?></button>
        </div>
        <div class="emoji-picker" id="emojiPicker">
            <?php foreach (['😀','😂','😍','😎','🥰','😢','😡','👍','👎','❤️','🔥','🎉','🍿','🎬','⭐','🤔','😴','🙌','👀','💯'] as $emo): ?>
            <span onclick="insertEmoji('<?php echo $emo; ?>')"><?php echo $emo; ?></span>
            <?php endforeach; ?>
        </div>
        <div class="chat-input-bar">
            <button type="button" class="chat-attach-btn" onclick="toggleEmoji()">😊</button>
            <button type="button" class="chat-attach-btn <?php echo $user['is_premium'] ? '' : 'locked'; ?>" onclick="attachClick()">📎</button>
            <input type="file" id="attachInput" accept="image/*,.gif" style="display:none;" onchange="onAttachSelect(this)">
            <input type="text" id="msgInput" placeholder="<?php echo t('write_message'); ?>" maxlength="500">
            <button class="chat-send-btn" onclick="sendMsg()"><?php echo t('send_btn'); ?></button>
        </div>
    </div>
</div>

<script>
var lastId = 0;
var currentUserId = <?php echo (int)$user['id']; ?>;
var isPremium = <?php echo $user['is_premium'] ? 'true' : 'false'; ?>;
var selectedFile = null;

function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str || ''));
    return div.innerHTML;
}
function renderMsg(msg) {
    var isOwn = msg.sender_id == currentUserId;
    var body = '';
    if (msg.message) body += '<div class="msg-text">' + escHtml(msg.message) + '</div>';
    if (msg.attachment) body += '<img class="msg-image" src="/uzdub/uploads/chat/' + escHtml(msg.attachment) + '" onclick="window.open(this.src)">';
    body += '<span class="msg-time-small">' + escHtml(msg.created_at.substring(11,16)) + '</span>';
    return '<div class="msg-item ' + (isOwn ? 'own' : 'other') + '" data-id="' + msg.id + '">' + body + '</div>';
}
function fetchMessages() {
    fetch('/uzdub/chat.php?with=<?php echo e($other['user_id']); ?>&fetch_msgs=1&last_id=' + lastId)
        .then(r => r.json())
        .then(msgs => {
            if (msgs.length > 0) {
                var area = document.getElementById('msgArea');
                if (lastId === 0) area.innerHTML = '';
                msgs.forEach(m => {
                    area.insertAdjacentHTML('beforeend', renderMsg(m));
                    lastId = Math.max(lastId, parseInt(m.id));
                });
                area.scrollTop = area.scrollHeight;
            } else if (lastId === 0) {
                document.getElementById('msgArea').innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:13px;padding:30px;"><?php echo t('no_messages_say_hi'); ?> 👋</div>';
            }
        });
}
function toggleEmoji() { document.getElementById('emojiPicker').classList.toggle('active'); }
function insertEmoji(emo) { var i=document.getElementById('msgInput'); i.value += emo; i.focus(); }
function attachClick() {
    if (!isPremium) { alert('<?php echo t('image_gif_premium_js'); ?>'); return; }
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
    fetch('/uzdub/chat.php?with=<?php echo e($other['user_id']); ?>', {method:'POST', body:fd})
        .then(r => r.json())
        .then(r => {
            if (r.ok) { input.value = ''; clearAttachment(); fetchMessages(); }
            else alert(r.msg || '<?php echo t('error'); ?>');
        });
}
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('msgInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
    });
    document.addEventListener('click', function(e) {
        var picker = document.getElementById('emojiPicker');
        if (picker && !picker.contains(e.target) && e.target.textContent !== '😊') picker.classList.remove('active');
    });
    fetchMessages();
    setInterval(fetchMessages, 3000);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
