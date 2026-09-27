<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/payment.php';

$new_account = isset($_GET['new']);
if (is_user() && !$new_account) { header('Location: ' . ROOT_URL . '/index.php'); exit; }

// "Boshqa hisob bilan kirish" — tozalanib qolgan 2FA holatini bekor qilish
if (isset($_GET['reset'])) {
    unset($_SESSION['2fa_pending_id'], $_SESSION['2fa_totp_secret'], $_SESSION['2fa_pending_email'], $_SESSION['2fa_show_totp'], $_SESSION['login_approval_token'], $_SESSION['2fa_redirect']);
}

$error = '';
if (isset($_GET['google'])) {
    if ($_GET['google'] === 'unverified') {
        $error = 'Google akkauntidagi email tasdiqlanmagan. Faqat Google tomonidan tasdiqlangan email bilan kirish mumkin.';
    } elseif ($_GET['google'] === 'invalid') {
        $error = 'Google orqali kirish amalga oshmadi. Iltimos, qayta urinib ko\'ring.';
    }
}
$redirect = $_GET['redirect'] ?? ROOT_URL . '/index.php';
$allowed = [
    ROOT_URL . '/index.php',
    ROOT_URL . '/watch.php',
    ROOT_URL . '/category.php',
    ROOT_URL . '/random.php',
    ROOT_URL . '/global_chat.php',
    ROOT_URL . '/profile.php',
    ROOT_URL . '/premium.php',
    ROOT_URL . '/inbox.php',
    ROOT_URL . '/search.php',
];
if (!in_array($redirect, $allowed, true) && !preg_match('#^' . preg_quote(ROOT_URL, '#') . '/(watch|category|profile|premium|inbox|search)\.php#', $redirect)) {
    $redirect = ROOT_URL . '/index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $two_fa_code = $_POST['two_fa_code'] ?? '';

    // TOTP fallback verification step
    if ($two_fa_code && isset($_SESSION['2fa_pending_id']) && !empty($_SESSION['2fa_totp_secret'])) {
        $pending_id = $_SESSION['2fa_pending_id'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$pending_id]);
        $user = $stmt->fetch();

        require_once __DIR__ . '/../includes/totp.php';
        $code_ok = TOTP::verifyCode($_SESSION['2fa_totp_secret'], $two_fa_code);

        if ($user && $code_ok) {
            unset($_SESSION['2fa_pending_id'], $_SESSION['2fa_totp_secret'], $_SESSION['2fa_pending_email'], $_SESSION['2fa_show_totp']);
            login_clear_attempts($pdo, 'user:' . client_ip() . ':' . mb_strtolower($user['username']));
            $pdo->prepare("UPDATE users SET new_since = last_login_at, last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
            check_premium_expiry($pdo, $user['id']);
            refresh_user_session($pdo, $user['id']);
            session_regenerate_id(true);
            record_user_session($pdo, $user['id']);
            $_SESSION['login_redirect'] = $redirect;
            header('Location: ' . ROOT_URL . '/auth/save-account.php');
            exit;
        } else {
            $error = 'Noto\'g\'ri tasdiqlash kodi.';
            $show_2fa = true;
            $pending_email_masked = $_SESSION['2fa_pending_email'] ?? '';
        }
    } else {
        // Normal login step
        $login    = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $attempt_id = 'user:' . client_ip() . ':' . mb_strtolower($login);

        if (login_is_locked($pdo, $attempt_id)) {
            $error = 'Juda ko\'p muvaffaqiyatsiz urinish. ' . LOGIN_LOCKOUT_MINUTES . ' daqiqadan so\'ng qayta urinib ko\'ring.';
        } else {
            if (!validate_csrf($_POST['csrf_token'] ?? '')) {
                $error = 'Xavfsizlik tokeni noto\'g\'ri. Sahifani yangilab qayta urinib ko\'ring.';
            } else {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? OR email=?");
                $stmt->execute([$login, $login]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    login_clear_attempts($pdo, $attempt_id);

                    if ($user['two_factor_enabled']) {
                        require_once __DIR__ . '/../config/payment.php';

                        $_SESSION['2fa_pending_id'] = $user['id'];
                        $email = $user['email'];
                        $masked = substr($email, 0, 2) . str_repeat('*', max(0, strlen($email) - 6)) . substr($email, -4);
                        $_SESSION['2fa_pending_email'] = $masked;

                        $tg_chat = $user['telegram_chat_id'] ?? '';
                        if ($tg_chat !== '') {
                            // ===== Telegram orqali kirishni tasdiqlash (Ha/Yo'q tugmalari) =====
                            $token = bin2hex(random_bytes(16));
                            $ip = client_ip();
                            $expires = date('Y-m-d H:i:s', time() + 180);
                            $pdo->prepare("UPDATE login_approvals SET status = 'expired' WHERE user_id = ? AND status = 'pending'")->execute([$user['id']]);
                            $ins = $pdo->prepare("INSERT INTO login_approvals (user_id, token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
                            $ins->execute([$user['id'], $token, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', $expires]);
                            $_SESSION['login_approval_token'] = $token;
                            $_SESSION['2fa_redirect'] = $redirect;
                            tg_2fa_send_approval($tg_chat, $token, $ip, date('H:i d.m.Y'));
                            $show_2fa_approval = true;
                            $pending_email_masked = $masked;
                        } else {
                            // Telegram ulanmagan, eski TOTP sekretiga fallback
                            require_once __DIR__ . '/../includes/totp.php';
                            if (!empty($user['two_factor_secret'])) {
                                $_SESSION['2fa_totp_secret'] = $user['two_factor_secret'];
                            } else {
                                unset($_SESSION['2fa_pending_id'], $_SESSION['2fa_pending_email'], $_SESSION['2fa_show_totp']);
                                $error = '2FA sozlanmagan. Admin bilan bog\'laning.';
                            }

                            if (isset($_SESSION['2fa_pending_id'])) {
                                $show_2fa = true;
                                $pending_email_masked = $masked;
                            }
                        }
                    } else {
                        $pdo->prepare("UPDATE users SET new_since = last_login_at, last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
                        check_premium_expiry($pdo, $user['id']);
                        refresh_user_session($pdo, $user['id']);
                        session_regenerate_id(true);
                        record_user_session($pdo, $user['id']);
                        $_SESSION['login_redirect'] = $redirect;
                        header('Location: ' . ROOT_URL . '/auth/save-account.php');
                        exit;
                    }
                } else {
                    $admin_stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
                    $admin_stmt->execute([$login]);
                    $admin = $admin_stmt->fetch();

                    if ($admin && password_verify($password, $admin['password'])) {
                        login_clear_attempts($pdo, $attempt_id);
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        header('Location: ' . ROOT_URL . '/admin/dashboard.php');
                        exit;
                    }

                    login_register_failed($pdo, $attempt_id);
                    $error = 'Login yoki parol noto\'g\'ri.';
                }
            }
        }
    }
}

$google_client_id = env('GOOGLE_CLIENT_ID', '');

// Approval ekranida ko'rsatish uchun so'rov ma'lumotlari
$appr_display = null;
if (!empty($_SESSION['login_approval_token']) && !empty($_SESSION['2fa_pending_id'])) {
    $appr_stmt = $pdo->prepare("SELECT ip_address, created_at FROM login_approvals WHERE token = ? AND user_id = ? LIMIT 1");
    $appr_stmt->execute([$_SESSION['login_approval_token'], $_SESSION['2fa_pending_id']]);
    $appr_display = $appr_stmt->fetch();
}

// Google orqali kirishda ham tasdiqlash ekranini sessiya holatiga qarab ko'rsatish
// (parol bilan kirishda $show_2fa_approval POST oqimida o'rnatiladi)
if (empty($show_2fa_approval) && !empty($_SESSION['login_approval_token']) && !empty($_SESSION['2fa_pending_id']) && $appr_display) {
    $show_2fa_approval = true;
    $pending_email_masked = $_SESSION['2fa_pending_email'] ?? '';
}

// Google orqali kirishda TOTP fallback — kod kiritish formasini ko'rsatish
if (empty($show_2fa) && !empty($_SESSION['2fa_show_totp']) && !empty($_SESSION['2fa_totp_secret']) && !empty($_SESSION['2fa_pending_id'])) {
    $show_2fa = true;
    $pending_email_masked = $_SESSION['2fa_pending_email'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kirish - UZDUB PLATFORM</title>
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../css/style.css') ?: 1; ?>">
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/auth.css?v=<?php echo @filemtime(__DIR__ . '/../css/auth.css') ?: 1; ?>">
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/emoji-blue.css?v=<?php echo @filemtime(__DIR__ . '/../css/emoji-blue.css') ?: 1; ?>">
<script>window.ROOT_URL = <?php echo json_encode(ROOT_URL); ?>;</script>
<script src="<?php echo ROOT_URL; ?>/js/emoji-blue.js" defer></script>
</head>
<body>
<div class="auth-grid"></div>
<div class="auth-wrap">
<?php if (!empty($show_2fa_approval)): ?>
        <div class="auth-box auth-approval">
            <div class="auth-logo" style="margin-bottom:10px;">
                <span class="al-badge">🎬</span>
                <span class="al-title">UZDUB</span>
                <span class="al-sub">PLATFORM</span>
            </div>
            <div class="appr-orb" id="apprOrb">
                <div class="appr-orb-ring"></div>
                <div class="appr-orb-ring appr-orb-ring2"></div>
                <svg class="appr-tg-logo" viewBox="0 0 24 24" fill="#fff"><path d="M9.04 15.51l-.38 3.7c.55 0 .79-.24 1.08-.52l2.58-2.4 5.33 3.83c.98.54 1.68.26 1.94-.89l3.5-16.06c.3-1.35-.49-1.9-1.4-1.57L1.1 9.95c-1.32.51-1.3 1.24-.22 1.56l5.37 1.65L17.4 6.16c.54-.35 1.03-.16.63.2L9.04 15.51z"/></svg>
            </div>

            <h2>Kirishni tasdiqlang</h2>
            <p class="appr-sub">
                <b>@<?php echo e(TG_2FA_BOT_USERNAME); ?></b>ga so'rov yuborildi. Tasdiqlash uchun botda
                <b>"Ha, bu menman"</b> tugmasini bosing.
            </p>

            <div class="appr-mock" id="apprMock">
                <div class="appr-mock-head">
                    <span class="appr-mock-avatar"><svg viewBox="0 0 24 24" fill="#fff"><path d="M9.04 15.51l-.38 3.7c.55 0 .79-.24 1.08-.52l2.58-2.4 5.33 3.83c.98.54 1.68.26 1.94-.89l3.5-16.06c.3-1.35-.49-1.9-1.4-1.57L1.1 9.95c-1.32.51-1.3 1.24-.22 1.56l5.37 1.65L17.4 6.16c.54-.35 1.03-.16.63.2L9.04 15.51z"/></svg></span>
                    <div class="appr-mock-meta">
                        <div class="appr-mock-name">UZDUB Xavfsizlik</div>
                        <div class="appr-mock-time">hozir</div>
                    </div>
                </div>
                <div class="appr-mock-body">
                    <div class="appr-mock-title">🔐 Tizimga kirish so'rovi</div>
                    <div class="appr-mock-rows">
                        <div class="appr-mock-row"><span>🕒 Vaqt</span><code><?php echo e($appr_display['created_at'] ? date('H:i', strtotime($appr_display['created_at'])) : date('H:i')); ?></code></div>
                        <div class="appr-mock-row"><span>🌐 IP</span><code><?php echo e($appr_display['ip_address'] ?: '—'); ?></code></div>
                    </div>
                    <div class="appr-mock-btns">
                        <span class="appr-mock-yes">✅ Ha, bu menman</span>
                        <span class="appr-mock-no">🚫 Yo'q, bu men emasman</span>
                    </div>
                </div>
            </div>

            <div class="appr-status" id="apprStatus">
                <span class="appr-typing" id="apprTyping"><i></i><i></i><i></i></span>
                <span id="apprStatusText">Tasdiqlanish kutilmoqda...</span>
            </div>

            <div id="apprLink" style="display:none;margin-top:6px;">
                <a href="login.php?reset=1" class="btn">Qayta urinish</a>
            </div>
        </div>
        <script>
        (function() {
            var timer = setInterval(function() {
                fetch(ROOT_URL + '/api/login-approval.php?action=status', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (!d.ok) { fail('appr-err', 'So\'rov mavjud emas. Qayta kirishga urinib ko\'ring.'); return; }
                    if (d.status === 'approved') {
                        clearInterval(timer);
                        document.getElementById('apprStatusText').textContent = 'Tasdiqlandi! Kirish yakunlanmoqda...';
                        window.location.href = ROOT_URL + '/api/login-approval.php?action=finalize';
                    } else if (d.status === 'denied') {
                        fail('appr-no', 'Kirish rad etildi. Barcha faol sessiyalar yakunlandi.');
                    } else if (d.status === 'expired') {
                        fail('appr-exp', 'So\'rov muddati tugadi. Qayta urinib ko\'ring.');
                    }
                })
                .catch(function() {});
            }, 2000);
            function fail(kind, msg) {
                clearInterval(timer);
                var orb = document.getElementById('apprOrb');
                var typing = document.getElementById('apprTyping');
                var st = document.getElementById('apprStatusText');
                var link = document.getElementById('apprLink');
                orb.className = 'appr-orb ' + kind;
                typing.style.display = 'none';
                st.textContent = msg;
                st.style.color = kind === 'appr-no' ? '#ef5350' : (kind === 'appr-exp' ? '#ffa726' : '#ef5350');
                link.style.display = 'block';
            }
        })();
        </script>
<?php elseif (!empty($show_2fa)): ?>
        <div class="auth-box">
            <div class="auth-logo">
                <span class="al-badge">🔐</span>
                <span class="al-title">UZDUB</span>
                <span class="al-sub">PLATFORM</span>
            </div>
            <h2>Ikki bosqichli tasdiqlash</h2>
            <p class="auth-sub-text">
                Tasdiqlash kodi Telegram botingizga yuborildi. Botdan kodni olib, quyiga kiriting.
            </p>
            <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
            <form method="post">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="login" value="">
                <input type="hidden" name="password" value="">
                <label>Tasdiqlash kodi (6 xonali)</label>
                <input type="text" name="two_fa_code" class="code-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus autocomplete="one-time-code">
                <button type="submit" class="btn">Tasdiqlash</button>
            </form>
            <div class="alt-link"><a href="login.php?reset=1">Boshqa hisob bilan kirish</a></div>
        </div>
<?php else: ?>
        <div class="auth-box">
            <div class="auth-logo">
                <span class="al-badge">🎬</span>
                <span class="al-title">UZDUB</span>
                <span class="al-sub">PLATFORM</span>
            </div>
            <h2>Tizimga kirish</h2>
            <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
            <?php if (!$google_client_id): ?>
            <div class="alert alert-warning">⚠ Google kirish hali sozlanmagan. Admin panel'dan .env faylini to'ldiring.</div>
            <?php endif; ?>
            <a class="google-btn <?php if (!$google_client_id) echo 'disabled'; ?>" href="<?php echo $google_client_id ? ROOT_URL . '/auth/google-login.php' : '#'; ?>" <?php if (!$google_client_id): ?>onclick="alert('Google OAuth sozlanmagan. Admin bilan bog\'laning.'); return false;"<?php endif; ?>>
                <svg viewBox="0 0 24 24" width="20" height="20"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                Google orqali kirish
            </a>
            <div class="auth-divider"><span>yoki</span></div>
            <form method="post">
                <?php echo csrf_input(); ?>
                <label>Login yoki Email</label>
                <div class="field">
                    <span class="field-icon">👤</span>
                    <input type="text" name="login" placeholder="ali123 yoki email@example.com" required autofocus>
                </div>
                <label>Parol</label>
                <div class="field">
                    <span class="field-icon">🔑</span>
                    <input type="password" name="password" id="loginPass" class="has-toggle" required>
                    <button type="button" class="pass-toggle" data-target="loginPass" aria-label="Parolni ko'rsatish">👁</button>
                </div>
                <div style="text-align:right;margin:10px 0 0;"><a href="forgot-password.php" style="font-size:12px;color:var(--blue-primary,#2196f3);text-decoration:none;">Parolni unutdingizmi?</a></div>
                <button type="submit" class="btn">Kirish</button>
            </form>
            <div class="alt-link">Hisobingiz yo'qmi? <a href="register.php<?php echo $new_account ? '?new=1' : ''; ?>">Ro'yxatdan o'tish</a></div>
            <div class="alt-link" style="margin-top:6px;"><button type="button" class="admin-open-btn" id="adminOpenBtn">🔐 Men adminman</button></div>
        </div>
<?php endif; ?>
</div>

<!-- ===================== Admin kirish modali ===================== -->
<div class="admin-modal-overlay" id="adminModalOverlay">
    <div class="admin-modal auth-box" role="dialog" aria-modal="true" aria-labelledby="adminModalTitle">
        <button type="button" class="admin-modal-close" id="adminModalClose" aria-label="Yopish">&times;</button>
        <div class="auth-logo">
            <span class="al-badge">🛡️</span>
            <span class="al-title">UZDUB</span>
            <span class="al-sub">PLATFORM · ADMIN</span>
        </div>
        <div class="admin-badge"><span class="admin-shield">🛡️</span>Boshqaruv paneli</div>
        <h2 id="adminModalTitle">Boshqaruv paneliga kirish</h2>
        <div class="alert alert-error" id="adminModalError" style="display:none;"></div>
        <form id="adminModalForm">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <label>Login</label>
            <div class="field">
                <span class="field-icon">👤</span>
                <input type="text" name="username" id="adminModalUsername" placeholder="Admin logini" required autocomplete="username">
            </div>
            <label>Parol</label>
            <div class="field">
                <span class="field-icon">🔑</span>
                <input type="password" name="password" id="adminModalPass" class="has-toggle" placeholder="•••••••••" required autocomplete="current-password">
                <button type="button" class="pass-toggle" data-target="adminModalPass" aria-label="Parolni ko'rsatish">👁</button>
            </div>
            <button type="submit" class="btn" id="adminModalSubmit">Kirish</button>
        </form>
        <div class="alt-link"><a href="<?php echo ROOT_URL; ?>/admin/login.php">To'liq sahifada ochish</a></div>
    </div>
</div>

<style>
.admin-open-btn {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    color: var(--text-muted);
    font-size: 13px;
    font-family: inherit;
    transition: color .2s, text-shadow .2s;
}
.admin-open-btn:hover { color: #80d8ff; text-shadow: 0 0 14px rgba(79,195,247,.5); }
.admin-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(5,9,18,.74);
    -webkit-backdrop-filter: blur(7px);
    backdrop-filter: blur(7px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 20px;
}
.admin-modal-overlay.open { display: flex; }
.admin-modal {
    width: 400px;
    max-width: 100%;
    position: relative;
    animation: adminModalIn .35s cubic-bezier(.2,.8,.2,1) both;
    padding-top: 34px;
    padding-bottom: 30px;
}
.admin-modal:hover { transform: none; }
.admin-modal .auth-logo { margin-bottom: 10px; }
.admin-modal h2 { text-align: center; font-size: 17px; margin: 0 0 20px; color: var(--text-muted); font-weight: 500; letter-spacing: .3px; }
.admin-modal-close {
    position: absolute;
    top: 12px;
    right: 14px;
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
    padding: 4px 10px;
    border-radius: 8px;
    transition: color .2s, background .2s, transform .2s;
    z-index: 3;
}
.admin-modal-close:hover { color: #80d8ff; background: rgba(79,195,247,.12); transform: rotate(90deg); }
.admin-badge {
    display: block;
    width: fit-content;
    margin: 0 auto 14px;
    padding: 5px 14px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: #90caf9;
    background: rgba(33,150,243,.12);
    border: 1px solid rgba(79,195,247,.35);
    border-radius: 20px;
    box-shadow: 0 0 18px rgba(33,150,243,.15);
}
.admin-shield { font-size: 17px; margin-right: 6px; vertical-align: -1px; }
@keyframes adminModalIn {
    from { opacity: 0; transform: translateY(24px) scale(.96); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
@media (max-width: 600px) {
    .admin-modal-overlay { padding: 12px; align-items: flex-end; }
    .admin-modal {
        width: 100%;
        max-width: 100%;
        padding: 26px 20px 24px;
        border-radius: 18px;
    }
    .admin-modal .al-badge { width: 54px; height: 54px; font-size: 26px; border-radius: 15px; }
    .admin-modal .al-title { font-size: 27px; letter-spacing: 1.5px; }
    .admin-modal .al-sub { font-size: 9px; letter-spacing: 5px; }
    .admin-modal-close { top: 10px; right: 12px; }
}
@media (max-width: 380px) {
    .admin-modal { padding: 22px 16px 22px; }
}
</style>

<script>
(function() {
    var overlay = document.getElementById('adminModalOverlay');
    var openBtn = document.getElementById('adminOpenBtn');
    var closeBtn = document.getElementById('adminModalClose');
    var form = document.getElementById('adminModalForm');
    var errorBox = document.getElementById('adminModalError');
    var submitBtn = document.getElementById('adminModalSubmit');
    if (!overlay || !openBtn) return;

    function openModal() {
        overlay.classList.add('open');
        setTimeout(function() { var u = document.getElementById('adminModalUsername'); if (u) u.focus(); }, 60);
    }
    function closeModal() {
        overlay.classList.remove('open');
        errorBox.style.display = 'none';
        form.reset();
    }

    openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', function(e) { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        errorBox.style.display = 'none';
        submitBtn.disabled = true;
        var orig = submitBtn.textContent;
        submitBtn.textContent = 'Tekshirilmoqda...';
        fetch(ROOT_URL + '/admin/login.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d && d.ok) {
                window.location.href = d.redirect || ROOT_URL + '/admin/dashboard.php';
            } else {
                errorBox.textContent = (d && d.error) ? d.error : 'Kirish amalga oshmadi.';
                errorBox.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = orig;
            }
        })
        .catch(function() {
            errorBox.textContent = 'Server bilan bog\'lanishda xatolik. Qayta urinib ko\'ring.';
            errorBox.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = orig;
        });
    });
})();
</script>
<script>
document.querySelectorAll('.pass-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var inp = document.getElementById(this.dataset.target);
        if (!inp) return;
        var show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        this.textContent = show ? '🙈' : '👁';
    });
});
</script>
<script src="<?php echo ROOT_URL; ?>/js/auth-particles.js?v=<?php echo @filemtime(__DIR__ . '/../js/auth-particles.js') ?: 1; ?>" defer></script>
</body>
</html>
