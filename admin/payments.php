<?php
$page_title = "To'lovlar";
require_once __DIR__ . '/../config/payment.php';
include __DIR__ . '/includes/admin_header.php';

$message = '';

// To'lovni tasdiqlash -> Premiumni yoqish
if (isset($_GET['approve'])) {
    $pid = (int)$_GET['approve'];
    $stmt = $pdo->prepare("SELECT * FROM premium_payments WHERE id = ?");
    $stmt->execute([$pid]);
    $payment = $stmt->fetch();
    if ($payment && $payment['status'] === 'pending') {
        // Muddatni HOZIRDAN boshlab hisoblash (tasdiqlangan kundan)
        $plans = PREMIUM_PLANS;
        $days = $plans[$payment['plan']]['days'] ?? 30;
        $expires = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));

        $pdo->prepare("UPDATE users SET is_premium=1, premium_expires_at=? WHERE id=?")->execute([$expires, $payment['user_id']]);
        $pdo->prepare("UPDATE premium_payments SET status='approved', expires_at=? WHERE id=?")->execute([$expires, $pid]);
        $message = "To'lov tasdiqlandi va Premium yoqildi!";
    }
}

// To'lovni rad etish
if (isset($_GET['reject'])) {
    $pdo->prepare("UPDATE premium_payments SET status='rejected' WHERE id=?")->execute([(int)$_GET['reject']]);
    $message = "To'lov rad etildi.";
}

$payments = $pdo->query("SELECT p.*, u.username, u.user_id as uid FROM premium_payments p JOIN users u ON p.user_id = u.id ORDER BY (p.status='pending') DESC, p.created_at DESC")->fetchAll();

$plan_labels = ['1month' => '1 Oy', '3month' => '3 Oy', '1year' => '1 Yil'];
$pending_count = 0;
foreach ($payments as $p) if ($p['status'] === 'pending') $pending_count++;
?>

<h1>To'lovlar tarixi (<?php echo count($payments); ?>)<?php if ($pending_count): ?> — <span style="color:#ffb300;"><?php echo $pending_count; ?> ta kutilmoqda</span><?php endif; ?></h1>
<?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>

<div class="card-box">
<table>
    <tr><th>Sana</th><th>Foydalanuvchi</th><th>Tarif</th><th>Summa</th><th>Screenshot</th><th>Status</th><th>Amallar</th></tr>
    <?php foreach ($payments as $p): ?>
    <tr style="<?php echo $p['status']==='pending' ? 'background:rgba(255,179,0,0.06);' : ''; ?>">
        <td><?php echo date('d.m.Y H:i', strtotime($p['created_at'])); ?></td>
        <td><?php echo e($p['username']); ?> (<?php echo e($p['uid']); ?>)</td>
        <td><?php echo e($plan_labels[$p['plan']] ?? $p['plan']); ?></td>
        <td><?php echo number_format($p['amount'], 0, '.', ' '); ?> so'm</td>
        <td>
            <?php if ($p['screenshot']): ?>
            <a href="../uploads/screenshots/<?php echo e($p['screenshot']); ?>" target="_blank">
                <img src="../uploads/screenshots/<?php echo e($p['screenshot']); ?>" style="width:50px;height:50px;object-fit:cover;border-radius:6px;">
            </a>
            <?php else: ?>
            <span style="color:var(--text-muted);">Admin berdi</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($p['status'] === 'approved'): ?>
            <span style="color:#4caf50;">✅ Tasdiqlangan</span>
            <?php elseif ($p['status'] === 'rejected'): ?>
            <span style="color:#ef5350;">❌ Rad etilgan</span>
            <?php else: ?>
            <span style="color:#ffb300;">⏳ Kutilmoqda</span>
            <?php endif; ?>
        </td>
        <td class="action-links">
            <?php if ($p['status'] === 'pending'): ?>
            <a href="payments.php?approve=<?php echo $p['id']; ?>" onclick="return confirm('Premiumni tasdiqlaysizmi?');">✅ Tasdiqlash</a>
            <a href="payments.php?reject=<?php echo $p['id']; ?>" class="danger" onclick="return confirm('Rad etasizmi?');">❌ Rad etish</a>
            <?php else: ?>
            <span style="color:var(--text-muted);">—</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($payments)): ?><tr><td colspan="7">Hozircha to'lov yo'q.</td></tr><?php endif; ?>
</table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
