<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$uid_param = $_GET['uid'] ?? '';
if (!$uid_param) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$uid_param]);
$profile_user = $stmt->fetch();

if (!$profile_user) { header('Location: index.php'); exit; }

// Premium muddatini tekshirish
check_premium_expiry($pdo, $profile_user['id']);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$profile_user['id']]);
$profile_user = $stmt->fetch();

$page_title = $profile_user['username'] . ' profili';
$is_own = is_user() && $_SESSION['user_id'] == $profile_user['id'];

// Avatar yuklash (o'z profili bo'lsa)
$msg = '';
if ($is_own && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_avatar'])) {
    $is_ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $msg = 'Xavfsizlik tokeni noto\'g\'ri. Sahifani yangilab qayta urinib ko\'ring.';
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }
    } else {
        $av = upload_file('avatar', __DIR__ . '/uploads/avatars/', ['jpg','jpeg','png','webp','gif'], ['image/jpeg','image/png','image/webp','image/gif']);
        if ($av) {
            $pdo->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$av, $profile_user['id']]);
            refresh_user_session($pdo, $profile_user['id']);
            $profile_user['avatar'] = $av;
            $msg = 'Profil rasmi yangilandi!';
            if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>$msg,'avatar_url'=>avatar_url($av)]); exit; }
        } else {
            $msg = 'Xatolik: rasm formati noto\'g\'ri.';
            if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.profile-page { max-width:800px; margin:110px auto 40px; padding:0 20px; position:relative;z-index:1; }
.profile-card { background:var(--card-bg); border:1px solid rgba(33,150,243,0.25); border-radius:16px; padding:36px; display:flex; gap:30px; align-items:flex-start; flex-wrap:wrap; }
.avatar-wrap { position:relative; flex-shrink:0; }
.avatar-wrap img { width:120px; height:120px; border-radius:50%; object-fit:cover; border:3px solid var(--blue-primary); }
.avatar-edit-btn { position:absolute; bottom:4px; right:4px; background:var(--blue-primary); border:none; border-radius:50%; width:30px; height:30px; color:#fff; cursor:pointer; font-size:14px; display:flex;align-items:center;justify-content:center; }
.profile-info { flex:1; min-width:200px; }
.profile-info h1 { font-size:26px; margin-bottom:6px; display:flex; align-items:center; gap:10px; }
.premium-badge { background:linear-gradient(135deg,#f9a825,#ff6f00); color:#fff; font-size:11px; padding:3px 10px; border-radius:20px; font-weight:700; letter-spacing:0.5px; }
.user-id-badge { font-size:13px; color:var(--text-muted); background:rgba(33,150,243,0.12); padding:4px 12px; border-radius:20px; display:inline-block; margin-bottom:12px; }
.profile-meta { color:var(--text-muted); font-size:13px; margin-bottom:16px; }
.profile-actions { display:flex; gap:10px; flex-wrap:wrap; }
.profile-actions a, .profile-actions button { padding:9px 20px; border-radius:7px; font-size:14px; text-decoration:none; cursor:pointer; border:none; font-weight:600; }
.btn-dm { background:var(--blue-primary); color:#fff; }
.btn-dm:hover { background:var(--blue-glow); }
.btn-premium { background:linear-gradient(135deg,#f9a825,#ff6f00); color:#fff; }
.btn-premium:hover { opacity:0.9; }
.btn-edit { background:rgba(255,255,255,0.1); color:var(--text-light); }
.btn-edit:hover { background:rgba(255,255,255,0.2); }
.avatar-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:999; align-items:center; justify-content:center; }
.avatar-modal.active { display:flex; }
.avatar-modal-box { background:var(--card-bg); border:1px solid rgba(33,150,243,0.3); border-radius:12px; padding:30px; min-width:320px; }
.avatar-modal-box h3 { margin-bottom:16px; color:var(--blue-glow); }
.avatar-modal-box input[type=file] { display:block; margin-bottom:14px; color:var(--text-light); }
.avatar-modal-box .btn { padding:9px 20px; background:var(--blue-primary); color:#fff; border:none; border-radius:7px; cursor:pointer; }
.avatar-modal-box .btn-cancel { background:rgba(255,255,255,0.1); margin-left:8px; }
.alert-msg { padding:10px 15px; border-radius:7px; margin-bottom:14px; font-size:13px; background:rgba(33,150,243,0.12); border:1px solid var(--blue-primary); color:#90caf9; }
</style>

<div class="profile-page">

<div class="profile-card">
    <div class="avatar-wrap">
        <img src="<?php echo avatar_url($profile_user['avatar']); ?>" alt="Avatar" id="avatar-img">
        <?php if ($is_own): ?>
        <button class="avatar-edit-btn" onclick="document.getElementById('avatarModal').classList.add('active')" title="Rasmni o'zgartirish">✏️</button>
        <?php endif; ?>
    </div>
    <div class="profile-info">
        <h1>
            <?php echo e($profile_user['username']); ?>
            <?php if ($profile_user['is_premium']): ?>
            <span class="premium-badge">⭐ PREMIUM</span>
            <?php endif; ?>
        </h1>
        <div class="user-id-badge">🆔 <?php echo e($profile_user['user_id']); ?></div>
        <div class="profile-meta">
            A'zo bo'lgan: <?php echo date('d.m.Y', strtotime($profile_user['created_at'])); ?>
            <?php if ($profile_user['is_premium'] && $profile_user['premium_expires_at']): ?>
            &nbsp;·&nbsp; Premium tugaydi: <b style="color:#f9a825"><?php echo date('d.m.Y', strtotime($profile_user['premium_expires_at'])); ?></b>
            <?php endif; ?>
        </div>
        <div class="profile-actions">
            <?php if (!$is_own && is_user()): ?>
            <a href="chat.php?with=<?php echo e($profile_user['user_id']); ?>" class="btn-dm">💬 Xabar yuborish</a>
            <?php endif; ?>
            <?php if ($is_own): ?>
            <a href="premium.php" class="btn-premium">⭐ Premium olish</a>
            <a href="auth/logout.php" class="btn-edit">Chiqish</a>
            <?php endif; ?>
            <?php if (!is_user()): ?>
            <a href="auth/login.php" class="btn-dm">Kirish kerak</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<?php if ($is_own): ?>
<div class="avatar-modal" id="avatarModal">
    <div class="avatar-modal-box">
        <h3>Profil rasmini yangilash</h3>
        <form method="post" enctype="multipart/form-data" id="avatarForm">
            <?php echo csrf_input(); ?>
            <input type="file" name="avatar" accept="image/*" required>
            <button type="submit" name="update_avatar" class="btn">Yuklash</button>
            <button type="button" class="btn btn-cancel" onclick="document.getElementById('avatarModal').classList.remove('active')">Bekor</button>
        </form>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('avatarForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(form);
        fetch(window.location.href, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (window.showToast) showToast(data.msg, data.ok ? 'success' : 'error');
            if (data.ok && data.avatar_url) {
                document.getElementById('avatar-img').src = data.avatar_url;
                document.getElementById('avatarModal').classList.remove('active');
            }
        })
        .catch(function () {
            if (window.showToast) showToast("Bog'lanishda xatolik yuz berdi.", 'error');
        });
    });
})();
</script>
<?php endif; ?>

<?php if ($msg): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.showToast) showToast(<?php echo json_encode($msg, JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode(strpos($msg, 'Xatolik') === 0 || strpos($msg, "noto'g'ri") !== false ? 'error' : 'success'); ?>);
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
