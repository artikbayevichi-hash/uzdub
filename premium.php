<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/payment.php';
require_user();

$user = current_user();
check_premium_expiry($pdo, $user['id']);
refresh_user_session($pdo, $user['id']);
$user = current_user();

$page_title = 'Premium obuna';
$selected_plan = $_GET['plan'] ?? '';
$plans = PREMIUM_PLANS;

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $plan = $_POST['plan'] ?? '';
    if (!isset($plans[$plan])) {
        $error = 'Noto\'g\'ri tarif.';
    } else {
        // Screenshot yuklash
        $screenshot = upload_file('screenshot', __DIR__ . '/uploads/screenshots/', ['jpg','jpeg','png','webp']);
        if (!$screenshot) {
            $error = 'To\'lov skreenshot rasm (jpg/png) yuklang.';
        } else {
            $plan_info = $plans[$plan];
            $expires   = date('Y-m-d H:i:s', strtotime('+' . $plan_info['days'] . ' days'));

            // To'lovni "kutilmoqda" holatida saqlash — admin tasdiqlagach yonadi
            $pdo->prepare("INSERT INTO premium_payments (user_id, plan, amount, screenshot, status, expires_at) VALUES (?,?,?,?,?,?)")
                ->execute([$user['id'], $plan, $plan_info['price'], $screenshot, 'pending', $expires]);

            // Telegram ga screenshot + ma'lumot yuborish
            $caption = "💰 <b>Yangi premium so'rov!</b>\n" .
                "👤 Foydalanuvchi: <b>" . $user['username'] . "</b> (ID: " . $user['user_id'] . ")\n" .
                "📦 Tarif: <b>" . $plan_info['label'] . "</b>\n" .
                "💵 Summa: <b>" . number_format($plan_info['price'], 0, '.', ' ') . " so'm</b>\n" .
                "⏳ Holat: <b>Tasdiqlash kutilmoqda</b>\n" .
                "👉 Admin panel: /admin/payments.php orqali tasdiqlang.";

            $photo_path = __DIR__ . '/uploads/screenshots/' . $screenshot;
            tg_send_photo($photo_path, $caption);

            $msg = "To'lov so'rovingiz qabul qilindi! Admin tekshirib, tez orada Premiumni faollashtiradi. Odatda bu bir necha soat ichida amalga oshadi.";
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.premium-page { max-width:900px; margin:110px auto 60px; padding:0 20px; position:relative;z-index:1; }
.premium-page h1 { font-size:30px; text-align:center; margin-bottom:8px; }
.premium-page .subtitle { text-align:center; color:var(--text-muted); margin-bottom:40px; }
.plans-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:20px; margin-bottom:40px; }
.plan-card { background:var(--card-bg); border:2px solid rgba(33,150,243,0.2); border-radius:14px; padding:28px; text-align:center; cursor:pointer; transition:0.2s; position:relative; }
.plan-card:hover, .plan-card.selected { border-color:var(--blue-primary); box-shadow:0 0 25px rgba(33,150,243,0.3); }
.plan-card.selected::after { content:'✓'; position:absolute; top:12px; right:16px; color:var(--blue-glow); font-size:20px; font-weight:900; }
.plan-card .plan-name { font-size:20px; font-weight:700; margin-bottom:8px; }
.plan-card .plan-price { font-size:32px; font-weight:900; color:var(--blue-glow); }
.plan-card .plan-price span { font-size:16px; color:var(--text-muted); }
.plan-card .plan-desc { color:var(--text-muted); font-size:13px; margin-top:8px; }
.plan-card.popular { border-color:#f9a825; }
.plan-card.popular .plan-name { color:#f9a825; }
.popular-badge { background:#f9a825; color:#111; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; display:inline-block; margin-bottom:8px; }

.payment-box { background:var(--card-bg); border:1px solid rgba(33,150,243,0.25); border-radius:14px; padding:30px; display:none; }
.payment-box.active { display:block; }
.payment-box h2 { margin-bottom:20px; color:var(--blue-glow); }
.card-display { background:linear-gradient(135deg,#0d47a1,#1565c0); border-radius:14px; padding:24px 28px; color:#fff; margin-bottom:24px; max-width:380px; }
.card-display .card-num { font-size:22px; font-weight:700; letter-spacing:3px; margin-bottom:10px; font-family:monospace; }
.card-display .card-meta { display:flex; justify-content:space-between; font-size:13px; opacity:0.85; }
.steps { margin-bottom:24px; }
.steps li { margin-bottom:8px; color:var(--text-muted); font-size:14px; line-height:1.5; }
.steps li b { color:var(--text-light); }
.upload-area { border:2px dashed rgba(33,150,243,0.4); border-radius:10px; padding:24px; text-align:center; color:var(--text-muted); cursor:pointer; }
.upload-area:hover { border-color:var(--blue-primary); }
.upload-area input { display:none; }
.upload-preview { max-width:300px; border-radius:8px; margin:12px auto; display:none; }
.submit-btn { display:block; width:100%; padding:14px; background:linear-gradient(135deg,#f9a825,#ff6f00); color:#fff; border:none; border-radius:8px; font-size:16px; font-weight:700; cursor:pointer; margin-top:18px; }
.submit-btn:hover { opacity:0.9; }
.alert { padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:14px; }
.alert-success { background:rgba(76,175,80,0.15); border:1px solid #4caf50; color:#a5d6a7; }
.alert-error { background:rgba(229,57,53,0.15); border:1px solid #e53935; color:#ef9a9a; }
.current-premium { background:rgba(249,168,37,0.1); border:1px solid #f9a825; border-radius:10px; padding:16px 20px; margin-bottom:24px; color:#f9a825; font-size:14px; }
</style>

<div class="premium-page">
    <h1>⭐ Premium Obuna</h1>
    <p class="subtitle">Premium bilan barcha kino va animelarga cheklovsiz kirish</p>

    <?php if ($msg): ?><div class="alert alert-success">⭐ <?php echo e($msg); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

    <?php if ($user['is_premium'] && $user['premium_expires_at']): ?>
    <div class="current-premium">
        ✅ Sizda faol premium bor — tugaydi: <b><?php echo date('d.m.Y', strtotime($user['premium_expires_at'])); ?></b>. Muddatni uzaytirish uchun yangi obuna sotib olishingiz mumkin.
    </div>
    <?php endif; ?>

    <?php
    $pending_stmt = $pdo->prepare("SELECT * FROM premium_payments WHERE user_id=? AND status='pending' ORDER BY created_at DESC LIMIT 1");
    $pending_stmt->execute([$user['id']]);
    $pending = $pending_stmt->fetch();
    if ($pending):
    ?>
    <div class="current-premium" style="border-color:#2196f3; color:#90caf9;">
        ⏳ So'rovingiz ko'rib chiqilmoqda (<?php echo date('d.m.Y H:i', strtotime($pending['created_at'])); ?> yuborilgan). Admin tasdiqlashini kuting.
    </div>
    <?php endif; ?>

    <div class="plans-grid">
        <?php foreach ($plans as $key => $plan): ?>
        <div class="plan-card <?php echo $key === '3month' ? 'popular' : ''; ?> <?php echo $selected_plan === $key ? 'selected' : ''; ?>"
             onclick="selectPlan('<?php echo $key; ?>', '<?php echo e($plan['label']); ?>', <?php echo $plan['price']; ?>)">
            <?php if ($key === '3month'): ?><div class="popular-badge">🔥 Mashhur</div><?php endif; ?>
            <div class="plan-name"><?php echo e($plan['label']); ?></div>
            <div class="plan-price"><?php echo number_format($plan['price'], 0, '.', ' '); ?> <span>so'm</span></div>
            <div class="plan-desc"><?php echo $plan['days']; ?> kun to'liq kirish</div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="payment-box" id="payment-box">
        <h2>💳 To'lov qilish</h2>
        <p style="color:var(--text-muted);margin-bottom:20px;font-size:14px;">Quyidagi karta raqamiga <b id="pay-amount" style="color:#f9a825"></b> so'm o'tkazing:</p>

        <div class="card-display">
            <div style="font-size:13px;opacity:0.7;margin-bottom:12px;">💳 O'tkazma kartasi</div>
            <div class="card-num"><?php echo e(CARD_NUMBER); ?></div>
            <div class="card-meta">
                <span><?php echo e(CARD_OWNER); ?></span>
                <span>UZDUB Premium</span>
            </div>
        </div>

        <ol class="steps">
            <li><b>Yuqoridagi karta raqamiga</b> aniq summani o'tkazing.</li>
            <li>O'tkazmadan so'ng <b>bankomat/mobil bank screenshotini</b> oling.</li>
            <li>Quyidagi maydonga screenshotni yuklang.</li>
            <li>Admin tekshirib tasdiqlagach, <b>Premium avtomatik yoqiladi</b>.</li>
        </ol>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="plan" id="plan-input" value="">
            <input type="hidden" name="submit_payment" value="1">
            <div class="upload-area" onclick="document.getElementById('ssFile').click()">
                <div>📸 Screenshot yuklash uchun bosing</div>
                <div style="font-size:12px;margin-top:6px;">(JPG yoki PNG, max 5MB)</div>
                <input type="file" id="ssFile" name="screenshot" accept="image/*" onchange="previewShot(this)">
                <img id="ssPreview" class="upload-preview" alt="Preview">
            </div>
            <button type="submit" class="submit-btn">✅ To'lov qildim — Tasdiqlash uchun yuborish</button>
        </form>
    </div>
</div>

<script>
function selectPlan(key, label, price) {
    document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    document.getElementById('plan-input').value = key;
    document.getElementById('pay-amount').textContent = price.toLocaleString('uz-UZ') + " (" + label + ")";
    document.getElementById('payment-box').classList.add('active');
    document.getElementById('payment-box').scrollIntoView({behavior:'smooth'});
}
function previewShot(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.getElementById('ssPreview');
            img.src = e.target.result;
            img.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
<?php if ($selected_plan && isset($plans[$selected_plan])): ?>
window.onload = function() {
    selectPlanDirect('<?php echo $selected_plan; ?>', '<?php echo e($plans[$selected_plan]['label']); ?>', <?php echo $plans[$selected_plan]['price']; ?>);
};
function selectPlanDirect(key, label, price) {
    document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
    document.querySelector('[onclick*="'+key+'"]')?.classList.add('selected');
    document.getElementById('plan-input').value = key;
    document.getElementById('pay-amount').textContent = price.toLocaleString() + " (" + label + ")";
    document.getElementById('payment-box').classList.add('active');
}
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
