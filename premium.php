<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/payment.php';
require_user();

$user = current_user();
check_premium_expiry($pdo, $user['id']);
refresh_user_session($pdo, $user['id']);
$user = current_user();

$page_title = t('premium_page_title');
$selected_plan = $_GET['plan'] ?? '';
$plans = PREMIUM_PLANS;

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = t('token_error');
    } else {
    $plan = $_POST['plan'] ?? '';
    if (!isset($plans[$plan])) {
        $error = t('invalid_plan');
    } else {
        $screenshot = upload_file('screenshot', __DIR__ . '/uploads/screenshots/', ['jpg','jpeg','png','webp'], ['image/jpeg','image/png','image/webp']);
        if (!$screenshot) {
            $error = t('upload_screenshot');
        } else {
            $plan_info = $plans[$plan];
            $expires   = date('Y-m-d H:i:s', strtotime('+' . $plan_info['days'] . ' days'));

            $pdo->prepare("INSERT INTO premium_payments (user_id, plan, amount, screenshot, status, expires_at) VALUES (?,?,?,?,?,?)")
                ->execute([$user['id'], $plan, $plan_info['price'], $screenshot, 'pending', $expires]);

            $caption = "💰 <b>" . t('new_premium_request') . "</b>\n"
                . "👤 " . t('user_label') . " <b>" . $user['username'] . "</b> (ID: " . $user['user_id'] . ")\n"
                . "📦 " . t('plan_label') . " <b>" . $plan_info['label'] . "</b>\n"
                . "💵 " . t('amount_label') . " <b>" . number_format($plan_info['price'], 0, '.', ' ') . " " . t('currency') . "</b>\n"
                . "⏳ " . t('status_label') . " <b>" . t('pending_approval') . "</b>\n"
                . "👉 " . t('admin_approve');

            $photo_path = __DIR__ . '/uploads/screenshots/' . $screenshot;
            tg_send_photo($photo_path, $caption);
            $msg = t('request_received');
        }
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
    <h1>⭐ <?php echo t('premium_subscription'); ?></h1>
    <p class="subtitle"><?php echo t('premium_desc'); ?></p>

    <?php if ($msg): ?><div class="alert alert-success">⭐ <?php echo e($msg); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

    <?php if ($user['is_premium'] && $user['premium_expires_at']): ?>
    <div class="current-premium">
        ✅ <?php echo t('active_premium'); ?> <b><?php echo date('d.m.Y', strtotime($user['premium_expires_at'])); ?></b>. <?php echo t('extend_hint'); ?>
    </div>
    <?php endif; ?>

    <?php
    $pending_stmt = $pdo->prepare("SELECT * FROM premium_payments WHERE user_id=? AND status='pending' ORDER BY created_at DESC LIMIT 1");
    $pending_stmt->execute([$user['id']]);
    $pending = $pending_stmt->fetch();
    if ($pending):
    ?>
    <div class="current-premium" style="border-color:#2196f3; color:#90caf9;">
        ⏳ <?php echo t('pending_review'); ?> (<?php echo date('d.m.Y H:i', strtotime($pending['created_at'])); ?> <?php echo t('submitted'); ?>). <?php echo t('wait_admin'); ?>
    </div>
    <?php endif; ?>

    <div class="plans-grid">
        <?php foreach ($plans as $key => $plan): ?>
        <div class="plan-card <?php echo $key === '3month' ? 'popular' : ''; ?> <?php echo $selected_plan === $key ? 'selected' : ''; ?>"
             onclick="selectPlan('<?php echo $key; ?>', '<?php echo e($plan['label']); ?>', <?php echo $plan['price']; ?>)">
            <?php if ($key === '3month'): ?><div class="popular-badge">🔥 <?php echo t('popular'); ?></div><?php endif; ?>
            <div class="plan-name"><?php echo e($plan['label']); ?></div>
            <div class="plan-price"><?php echo number_format($plan['price'], 0, '.', ' '); ?> <span><?php echo t('currency'); ?></span></div>
            <div class="plan-desc"><?php echo $plan['days']; ?> <?php echo t('days_access'); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="payment-box" id="payment-box">
        <h2>💳 <?php echo t('choose_payment'); ?></h2>

        <div class="payment-tabs" style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;">
            <button type="button" class="pay-tab active" onclick="switchPaymentTab('card', this)" style="padding:10px 20px;border-radius:10px;border:2px solid rgba(33,150,243,0.3);background:var(--card-bg);color:var(--text-light);font-size:14px;font-weight:600;cursor:pointer;transition:0.2s;">
                💳 <?php echo t('card_transfer'); ?>
            </button>
            <?php if (defined('CLICK_MERCHANT_ID') && CLICK_MERCHANT_ID): ?>
            <button type="button" class="pay-tab" onclick="switchPaymentTab('click', this)" style="padding:10px 20px;border-radius:10px;border:2px solid rgba(33,150,243,0.3);background:var(--card-bg);color:var(--text-light);font-size:14px;font-weight:600;cursor:pointer;transition:0.2s;">
                🟢 Click
            </button>
            <?php endif; ?>
            <?php if (defined('UZUM_MERCHANT_ID') && UZUM_MERCHANT_ID): ?>
            <button type="button" class="pay-tab" onclick="switchPaymentTab('uzum', this)" style="padding:10px 20px;border-radius:10px;border:2px solid rgba(33,150,243,0.3);background:var(--card-bg);color:var(--text-light);font-size:14px;font-weight:600;cursor:pointer;transition:0.2s;">
                ⚡ Uzum
            </button>
            <?php endif; ?>
        </div>

        <!-- Karta o'tkazma -->
        <div class="pay-section" id="pay-card" style="display:block;">
            <p style="color:var(--text-muted);margin-bottom:20px;font-size:14px;"><?php echo t('transfer_to_card'); ?> <b id="pay-amount" style="color:#f9a825"></b> <?php echo t('transfer_sum'); ?></p>
            <div class="card-display">
                <div style="font-size:13px;opacity:0.7;margin-bottom:12px;">💳 <?php echo t('transfer_card'); ?></div>
                <div class="card-num"><?php echo e(CARD_NUMBER); ?></div>
                <div class="card-meta">
                    <span><?php echo e(CARD_OWNER); ?></span>
                    <span>UZDUB PLATFORM Premium</span>
                </div>
            </div>
            <ol class="steps">
                <li><b><?php echo t('transfer_exact_sum'); ?></b> <?php echo t('transfer_exact'); ?></li>
                <li><?php echo t('after_transfer'); ?> <b><?php echo t('upload_receipt'); ?></b> <?php echo t('upload_receipt2'); ?></li>
                <li><?php echo t('upload_to_field'); ?></li>
                <li><?php echo t('admin_will_activate'); ?> <b><?php echo t('auto_activate'); ?></b>.</li>
            </ol>
            <form method="post" enctype="multipart/form-data">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="plan" id="plan-input-card" value="">
                <input type="hidden" name="submit_payment" value="1">
                <div class="upload-area" onclick="document.getElementById('ssFile').click()">
                    <div>📸 <?php echo t('click_upload_screenshot'); ?></div>
                    <div style="font-size:12px;margin-top:6px;"><?php echo t('screenshot_format'); ?></div>
                    <input type="file" id="ssFile" name="screenshot" accept="image/*" onchange="previewShot(this)">
                    <img id="ssPreview" class="upload-preview" alt="Preview">
                </div>
                <button type="submit" class="submit-btn">✅ <?php echo t('confirm_payment'); ?></button>
            </form>
        </div>

        <!-- Click to'lov -->
        <div class="pay-section" id="pay-click" style="display:none;">
            <div style="text-align:center;padding:30px 20px;">
                <div style="font-size:48px;margin-bottom:16px;">🟢</div>
                <h3 style="margin-bottom:12px;color:var(--text-light);font-size:20px;"><?php echo t('pay_with_click'); ?></h3>
                <p style="color:var(--text-muted);margin-bottom:24px;font-size:14px;">
                    <?php echo t('click_desc'); ?>
                    <?php echo t('click_auto_activate'); ?>
                </p>
                <a href="#" id="clickPayBtn" class="btn" style="background:#00aa13;color:#fff;padding:14px 32px;font-size:16px;border-radius:10px;text-decoration:none;display:inline-flex;align-items:center;gap:10px;">
                    🟢 <?php echo t('click_pay_btn'); ?>
                </a>
                <p style="color:var(--text-muted);font-size:12px;margin-top:12px;"><?php echo t('click_security'); ?></p>
            </div>
        </div>

        <!-- Uzum to'lov -->
        <div class="pay-section" id="pay-uzum" style="display:none;">
            <div style="text-align:center;padding:30px 20px;">
                <div style="font-size:48px;margin-bottom:16px;">⚡</div>
                <h3 style="margin-bottom:12px;color:var(--text-light);font-size:20px;"><?php echo t('pay_with_uzum'); ?></h3>
                <p style="color:var(--text-muted);margin-bottom:24px;font-size:14px;">
                    <?php echo t('uzum_desc'); ?>
                    <?php echo t('uzum_auto_activate'); ?>
                </p>
                <a href="#" id="uzumPayBtn" class="btn" style="background:#7c3aed;color:#fff;padding:14px 32px;font-size:16px;border-radius:10px;text-decoration:none;display:inline-flex;align-items:center;gap:10px;">
                    ⚡ <?php echo t('uzum_pay_btn'); ?>
                </a>
                <p style="color:var(--text-muted);font-size:12px;margin-top:12px;"><?php echo t('uzum_security'); ?></p>
            </div>
        </div>
    </div>
</div>

<script>
var selectedPlan = null;
var selectedPrice = 0;

function selectPlan(key, label, price) {
    selectedPlan = key;
    selectedPrice = price;
    document.querySelectorAll('.plan-card').forEach(function(c) { c.classList.remove('selected'); });
    event.currentTarget.classList.add('selected');
    document.getElementById('pay-amount').textContent = price.toLocaleString('uz-UZ') + ' (' + label + ')';
    document.getElementById('plan-input-card').value = key;
    document.getElementById('payment-box').classList.add('active');
    updatePaymentUrls(key, price);
    document.getElementById('payment-box').scrollIntoView({behavior:'smooth'});
}

function switchPaymentTab(tab, btn) {
    document.querySelectorAll('.pay-tab').forEach(function(t) {
        t.style.borderColor = 'rgba(33,150,243,0.3)';
        t.style.background = 'var(--card-bg)';
    });
    document.querySelectorAll('.pay-section').forEach(function(s) { s.style.display = 'none'; });
    if (btn) { btn.style.borderColor = 'var(--blue-primary)'; btn.style.background = 'rgba(33,150,243,0.15)'; }
    var section = document.getElementById('pay-' + tab);
    if (section) section.style.display = 'block';
}

function updatePaymentUrls(planKey, price) {
    if (planKey) {
        var clickBtn = document.getElementById('clickPayBtn');
        var uzumBtn = document.getElementById('uzumPayBtn');
        <?php if (defined('CLICK_MERCHANT_ID') && CLICK_MERCHANT_ID): ?>
        clickBtn.href = '/uzdub/api/click-redirect.php?plan=' + planKey;
        <?php endif; ?>
        <?php if (defined('UZUM_MERCHANT_ID') && UZUM_MERCHANT_ID): ?>
        uzumBtn.href = '/uzdub/api/uzum-redirect.php?plan=' + planKey;
        <?php endif; ?>
    }
}

function previewShot(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('ssPreview');
            img.src = e.target.result;
            img.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

<?php if ($selected_plan && isset($plans[$selected_plan])): ?>
document.addEventListener('DOMContentLoaded', function() {
    selectPlan(<?php echo json_encode($selected_plan); ?>, <?php echo json_encode(e($plans[$selected_plan]['label'])); ?>, <?php echo (int)$plans[$selected_plan]['price']; ?>);
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
