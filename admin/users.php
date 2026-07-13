<?php
$page_title = 'Foydalanuvchilar';
require_once __DIR__ . '/../config/payment.php';
include __DIR__ . '/includes/admin_header.php';

$message = '';

// Premium berish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grant_premium'])) {
    $user_db_id = (int)$_POST['user_db_id'];
    $plan = $_POST['plan'] ?? '';
    $plans = PREMIUM_PLANS;
    if (isset($plans[$plan])) {
        $expires = date('Y-m-d H:i:s', strtotime('+' . $plans[$plan]['days'] . ' days'));
        $pdo->prepare("UPDATE users SET is_premium=1, premium_expires_at=? WHERE id=?")->execute([$expires, $user_db_id]);
        $pdo->prepare("INSERT INTO premium_payments (user_id, plan, amount, status, expires_at) VALUES (?,?,0,'approved',?)")->execute([$user_db_id, $plan, $expires]);
        $message = 'Premium muvaffaqiyatli berildi! Tugash sanasi: ' . date('d.m.Y', strtotime($expires));
    }
}

// Premiumni bekor qilish
if (isset($_GET['revoke'])) {
    $pdo->prepare("UPDATE users SET is_premium=0, premium_expires_at=NULL WHERE id=?")->execute([(int)$_GET['revoke']]);
    header('Location: users.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username LIKE ? OR user_id LIKE ? OR email LIKE ? ORDER BY created_at DESC");
    $stmt->execute(['%'.$search.'%', '%'.$search.'%', '%'.$search.'%']);
} else {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
}
$users = $stmt->fetchAll();
$plans = PREMIUM_PLANS;
?>

<h1>Foydalanuvchilar (<?php echo count($users); ?>)</h1>
<?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>

<div class="card-box">
    <form method="get" style="margin-bottom:10px;">
        <input type="text" name="q" placeholder="Username, ID yoki email bo'yicha qidirish..." value="<?php echo e($search); ?>" style="max-width:400px;">
    </form>
</div>

<div class="card-box">
<table>
    <tr><th>Avatar</th><th>ID</th><th>Username</th><th>Email</th><th>Premium</th><th>Amallar</th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
        <td><img src="<?php echo $u['avatar'] ? '../uploads/avatars/'.e($u['avatar']) : '../assets/default-avatar.png'; ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;"></td>
        <td><?php echo e($u['user_id']); ?></td>
        <td><?php echo e($u['username']); ?></td>
        <td><?php echo e($u['email']); ?></td>
        <td>
            <?php if ($u['is_premium']): ?>
                ⭐ <?php echo date('d.m.Y', strtotime($u['premium_expires_at'])); ?>gacha
            <?php else: ?>
                <span style="color:var(--text-muted);">Yo'q</span>
            <?php endif; ?>
        </td>
        <td class="action-links">
            <a href="#" onclick="document.getElementById('grantModal<?php echo $u['id']; ?>').style.display='flex';return false;">Premium berish</a>
            <?php if ($u['is_premium']): ?>
            <a href="users.php?revoke=<?php echo $u['id']; ?>" class="danger" onclick="return confirm('Premiumni bekor qilasizmi?');">Bekor qilish</a>
            <?php endif; ?>
        </td>
    </tr>

    <div id="grantModal<?php echo $u['id']; ?>" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:999;align-items:center;justify-content:center;">
        <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:26px;min-width:320px;">
            <h3 style="margin-bottom:14px;color:var(--blue-glow);">"<?php echo e($u['username']); ?>" ga Premium berish</h3>
            <form method="post">
                <input type="hidden" name="user_db_id" value="<?php echo $u['id']; ?>">
                <label>Tarif tanlang</label>
                <select name="plan" required>
                    <?php foreach ($plans as $key => $p): ?>
                    <option value="<?php echo $key; ?>"><?php echo e($p['label']); ?> (<?php echo number_format($p['price'],0,'.',' '); ?> so'm)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="grant_premium" class="btn">Berish</button>
                <button type="button" class="btn" style="background:rgba(255,255,255,0.1);" onclick="document.getElementById('grantModal<?php echo $u['id']; ?>').style.display='none';">Bekor</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($users)): ?><tr><td colspan="6">Foydalanuvchi topilmadi.</td></tr><?php endif; ?>
</table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
