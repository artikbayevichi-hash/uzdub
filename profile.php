<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// ===== AJAX tab handler =====
if (isset($_GET['ajax_tab']) && isset($_GET['uid'])) {
    header('Content-Type: text/html; charset=utf-8');
    $uid_param = $_GET['uid'];
    $tab = $_GET['ajax_tab'] ?? 'history';
    $cat = $_GET['cat'] ?? 'all';
    $allowed_tabs = ['history','watching','planned','completed','paused','dropped','favorites','settings','security'];
    if (!in_array($tab, $allowed_tabs, true)) $tab = 'history';
    $allowed_cats = ['all','kino','anime','multfilm'];
    if (!in_array($cat, $allowed_cats, true)) $cat = 'all';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$uid_param]);
    $profile_user = $stmt->fetch();
    if (!$profile_user) { exit; }
    $uid = $profile_user['id'];
    $is_own = is_user() && $_SESSION['user_id'] === $profile_user['id'];

    if ($tab === 'settings' && $is_own) {
    require_once __DIR__ . '/includes/lang.php';

    // Username 14-day cooldown
    $username_cooldown = 0;
    if (!empty($profile_user['username_changed_at'])) {
        $changed = new DateTime($profile_user['username_changed_at']);
        $now = new DateTime();
        $diff = $now->diff($changed);
        $days_passed = ($diff->days * 24 + $diff->h) * 60 + $diff->i;
        $days_passed_int = (int)floor($days_passed / (60 * 24));
        if ($days_passed_int < 14) {
            $username_cooldown = 14 - $days_passed_int;
        }
    }
    ?>
        <div class="settings-panel">

            <!-- Avatar Card -->
            <div class="rbx-settings-card">
                <div class="rbx-settings-card-header">
                    <h3>👤 <?php echo t('change_avatar_modal'); ?></h3>
                </div>
                <div class="rbx-avatar-card">
                    <img src="<?php echo avatar_url($profile_user['avatar']); ?>" class="rbx-avatar-card-img" id="rbxAvatarImg" alt="">
                    <div class="rbx-avatar-card-actions">
                        <label class="pf-btn pf-btn-blue">
                            📷 <?php echo t('change_avatar'); ?>
                            <input type="file" accept="image/*" id="rbxAvatarInput">
                        </label>
                    </div>
                </div>
            </div>

            <!-- Account Info Card -->
            <div class="rbx-settings-card">
                <div class="rbx-settings-card-header">
                    <h3>📋 <?php echo t('account_info'); ?></h3>
                </div>
                <div class="rbx-settings-card-body">
                    <!-- Username Row -->
                    <div class="rbx-setting-row" id="rbxUsernameRow">
                        <div class="rbx-setting-label"><?php echo t('username_label'); ?></div>
                        <div class="rbx-setting-value" id="rbxUsernameDisplay">
                            <span><?php echo e($profile_user['username']); ?></span>
                            <?php if ($username_cooldown > 0): ?>
                                <span class="cooldown-label"><?php echo sprintf(t('username_cooldown'), $username_cooldown); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($username_cooldown <= 0): ?>
                        <div class="rbx-setting-edit">
                            <button type="button" id="rbxUsernameEdit" title="Edit">✏️</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!-- Username Inline Edit -->
                    <div class="rbx-setting-row" id="rbxUsernameEditRow" style="display:none;">
                        <div class="rbx-setting-label"><?php echo t('username_label'); ?></div>
                        <div class="rbx-setting-value">
                            <div class="rbx-inline-edit">
                                <input type="text" id="rbxUsernameInput" value="<?php echo e($profile_user['username']); ?>" class="settings-input" maxlength="30">
                                <div class="rbx-inline-actions">
                                    <button type="button" class="rbx-inline-save" id="rbxUsernameSave"><?php echo t('save_btn'); ?></button>
                                    <button type="button" class="rbx-inline-cancel" id="rbxUsernameCancel">✕</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Row -->
                    <div class="rbx-setting-row" id="rbxEmailRow">
                        <div class="rbx-setting-label"><?php echo t('email_label'); ?></div>
                        <div class="rbx-setting-value" id="rbxEmailDisplay">
                            <span><?php echo e(mask_email($profile_user['email'] ?? '')); ?></span>
                            <?php if (!empty($profile_user['email'])): ?>
                                <span class="verified-label <?php echo $profile_user['is_email_verified'] ? 'ok' : 'warn'; ?>">
                                    <?php echo $profile_user['is_email_verified'] ? t('verified') : t('not_verified'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="rbx-setting-edit">
                            <button type="button" id="rbxEmailEdit" title="Edit">✏️</button>
                        </div>
                    </div>
                    <!-- Email Inline Edit -->
                    <div class="rbx-setting-row" id="rbxEmailEditRow" style="display:none;">
                        <div class="rbx-setting-label"><?php echo t('email_label'); ?></div>
                        <div class="rbx-setting-value">
                            <div class="rbx-inline-edit">
                                <input type="email" id="rbxEmailInput" value="<?php echo e($profile_user['email'] ?? ''); ?>" class="settings-input">
                                <div class="rbx-inline-actions">
                                    <button type="button" class="rbx-inline-save" id="rbxEmailSave"><?php echo t('save_btn'); ?></button>
                                    <button type="button" class="rbx-inline-cancel" id="rbxEmailCancel">✕</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Login Methods Card -->
            <div class="rbx-settings-card">
                <div class="rbx-settings-card-header">
                    <h3>🔑 <?php echo t('login_methods'); ?></h3>
                </div>
                <div class="rbx-settings-card-body">
                    <div class="rbx-pass-row">
                        <div class="rbx-pass-label"><?php echo t('change_password'); ?></div>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span class="rbx-pass-dots">••••••••</span>
                            <button type="button" class="pf-btn pf-btn-blue" id="rbxPassToggle" style="padding:6px 16px;font-size:12px;">✏️ <?php echo t('change_btn'); ?></button>
                        </div>
                    </div>
                    <div class="rbx-pass-expand" id="rbxPassExpand">
                        <label><?php echo t('current_password'); ?></label>
                        <input type="password" id="rbxCurrentPass" class="settings-input" autocomplete="current-password" placeholder="••••••••">
                        <label><?php echo t('new_password'); ?></label>
                        <input type="password" id="rbxNewPass" class="settings-input" autocomplete="new-password" placeholder="••••••••">
                        <label><?php echo t('confirm_password'); ?></label>
                        <input type="password" id="rbxConfPass" class="settings-input" autocomplete="new-password" placeholder="••••••••">
                        <div class="rbx-pass-actions">
                            <button type="button" class="pf-btn pf-btn-blue" id="rbxPassSave"><?php echo t('save_btn'); ?></button>
                            <button type="button" class="pf-btn pf-btn-ghost" id="rbxPassCancel"><?php echo t('cancel_btn'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- OTP Modal -->
        <div class="otp-modal" id="otpModal">
            <div class="otp-modal-box">
                <div class="otp-modal-icon">📧</div>
                <h3><?php echo t('otp_verify_title'); ?></h3>
                <p class="otp-modal-text" id="otpModalText"></p>
                <div class="otp-input-group">
                    <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" autofocus>
                    <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                </div>
                <div class="otp-actions">
                    <button type="button" class="pf-btn pf-btn-blue" id="otpVerifyBtn"><?php echo t('otp_verify_btn'); ?></button>
                    <button type="button" class="pf-btn pf-btn-ghost" id="otpResendBtn" disabled><?php echo t('otp_resend_btn'); ?></button>
                </div>
                <button type="button" class="otp-modal-close" id="otpModalClose">✕</button>
            </div>
        </div>

        <script>
        (function() {
            var csrf = '<?php echo csrf_token(); ?>';
            var profileUid = <?php echo json_encode($profile_user['user_id'], JSON_UNESCAPED_UNICODE); ?>;
            var saveSettingsUrl = '/uzdub/profile.php?uid=' + encodeURIComponent(profileUid);
            var origEmail = <?php echo json_encode($profile_user['email'] ?? '', JSON_UNESCAPED_UNICODE); ?>;
            var origUsername = <?php echo json_encode($profile_user['username'], JSON_UNESCAPED_UNICODE); ?>;
            var otpModal = document.getElementById('otpModal');
            var otpDigits = otpModal.querySelectorAll('.otp-digit');
            var otpVerifyBtn = document.getElementById('otpVerifyBtn');
            var otpResendBtn = document.getElementById('otpResendBtn');
            var otpModalClose = document.getElementById('otpModalClose');
            var otpText = document.getElementById('otpModalText');
            var pendingType = '';
            var resendTimer = null;

            function maskEmail(email) {
                if (!email || email.indexOf('@') === -1) return email;
                var parts = email.split('@');
                var name = parts[0], domain = parts[1];
                if (name.length <= 2) return name[0] + '*'.repeat(Math.max(1, name.length - 1)) + '@' + domain;
                return name[0] + '*'.repeat(Math.max(1, name.length - 2)) + name.slice(-1) + '@' + domain;
            }

            function getOTP() { var c=''; otpDigits.forEach(function(d){c+=d.value;}); return c; }
            function clearOTP() { otpDigits.forEach(function(d){d.value='';}); otpDigits[0].focus(); }
            function startResendCountdown(sec) {
                otpResendBtn.disabled = true; var left = sec;
                otpResendBtn.textContent = left + 's';
                resendTimer = setInterval(function(){
                    left--;
                    if(left<=0){clearInterval(resendTimer); otpResendBtn.disabled=false; otpResendBtn.textContent=<?php echo json_encode(t('otp_resend_btn'), JSON_UNESCAPED_UNICODE); ?>;}
                    else{ otpResendBtn.textContent = left+'s'; }
                },1000);
            }
            function openOTP(type, msg) {
                pendingType = type; otpText.innerHTML = msg; clearOTP();
                otpModal.classList.add('active'); startResendCountdown(60);
            }
            function closeOTP() {
                otpModal.classList.remove('active'); pendingType='';
                if(resendTimer) clearInterval(resendTimer);
            }

            otpModalClose.addEventListener('click', closeOTP);
            otpModal.addEventListener('click', function(e){ if(e.target===otpModal) closeOTP(); });

            otpDigits.forEach(function(d,i){
                d.addEventListener('input',function(){
                    this.value=this.value.replace(/[^0-9]/g,'');
                    if(this.value && i<otpDigits.length-1) otpDigits[i+1].focus();
                });
                d.addEventListener('keydown',function(e){
                    if(e.key==='Backspace' && !this.value && i>0) otpDigits[i-1].focus();
                    if(e.key==='Enter' && i===otpDigits.length-1) otpVerifyBtn.click();
                });
                d.addEventListener('paste',function(e){
                    e.preventDefault();
                    var p=(e.clipboardData||window.clipboardData).getData('text').replace(/[^0-9]/g,'').slice(0,6);
                    for(var j=0;j<p.length&&j<6;j++) otpDigits[j].value=p[j];
                    if(p.length>0) otpDigits[Math.min(p.length,5)].focus();
                });
            });

            /* ===== OTP Verify handler ===== */
            otpVerifyBtn.addEventListener('click',function(){
                var code=getOTP();
                if(code.length!==6){if(window.showToast)showToast('6 xonali kod kiriting','error');return;}
                otpVerifyBtn.disabled=true;
                fetch('/uzdub/api/otp-verify.php',{
                    method:'POST',
                    headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
                    body:JSON.stringify({code:code,type:pendingType,csrf_token:csrf})
                })
                .then(function(r){return r.json();})
                .then(function(d){
                    otpVerifyBtn.disabled=false;
                    if(d.ok){
                        closeOTP();
                        if(window.showToast)showToast(d.message||<?php echo json_encode(t('otp_success'), JSON_UNESCAPED_UNICODE); ?>,'success');

                        if(d.success_type==='pre-verify-email'){
                            document.getElementById('rbxEmailRow').style.display='none';
                            document.getElementById('rbxEmailEditRow').style.display='flex';
                            document.getElementById('rbxEmailInput').focus();
                        }
                        if(d.success_type==='pre-verify-password'){
                            document.getElementById('rbxPassExpand').classList.add('active');
                            document.getElementById('rbxCurrentPass').focus();
                        }
                        if(d.success_type==='email'){
                            origEmail=d.new_value;
                            document.getElementById('rbxEmailDisplay').innerHTML='<span>'+maskEmail(d.new_value)+'</span><span class="verified-label ok"><?php echo e(t('verified')); ?></span>';
                        }
                    } else {
                        if(window.showToast)showToast(d.error||'Xatolik','error');
                        clearOTP();
                    }
                })
                .catch(function(){otpVerifyBtn.disabled=false;if(window.showToast)showToast('Xatolik yuz berdi','error');});
            });

            /* ===== OTP Resend handler ===== */
            otpResendBtn.addEventListener('click',function(){
                var data={type:pendingType,csrf_token:csrf};
                fetch('/uzdub/api/otp-send.php',{
                    method:'POST',
                    headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
                    body:JSON.stringify(data)
                })
                .then(function(r){return r.json();})
                .then(function(d){
                    if(d.ok){ otpText.innerHTML=d.message; clearOTP(); startResendCountdown(60); if(window.showToast)showToast('Kod qayta yuborildi','success'); }
                    else { if(window.showToast)showToast(d.error||'Xatolik','error'); }
                })
                .catch(function(){if(window.showToast)showToast('Xatolik','error');});
            });

            /* ===== Avatar auto-upload ===== */
            function saveAvatar(){
                var fd=new FormData();
                fd.set('save_settings','1');
                fd.set('csrf_token',csrf);
                var av=document.getElementById('rbxAvatarInput');
                if(av.files.length>0) fd.set('avatar',av.files[0]);
                fetch(saveSettingsUrl,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
                .then(function(r){return r.json();})
                .then(function(d){
                    if(d.error){if(window.showToast)showToast(d.error,'error');return;}
                    if(d.avatar_url){document.getElementById('rbxAvatarImg').src=d.avatar_url;}
                    if(window.showToast)showToast(d.message||'Saqlandi','success');
                })
                .catch(function(){});
            }
            document.getElementById('rbxAvatarInput').addEventListener('change',function(){
                if(this.files.length>0) saveAvatar();
            });

            /* ===== Username inline edit (no OTP) ===== */
            var uEditBtn=document.getElementById('rbxUsernameEdit');
            var uDisplayRow=document.getElementById('rbxUsernameRow');
            var uEditRow=document.getElementById('rbxUsernameEditRow');
            var uInput=document.getElementById('rbxUsernameInput');
            var uSaveBtn=document.getElementById('rbxUsernameSave');
            var uCancelBtn=document.getElementById('rbxUsernameCancel');

            if(uEditBtn){
                uEditBtn.addEventListener('click',function(){
                    uDisplayRow.style.display='none';
                    uEditRow.style.display='flex';
                    uInput.focus();
                });
            }
            if(uCancelBtn){
                uCancelBtn.addEventListener('click',function(){
                    uEditRow.style.display='none';
                    uDisplayRow.style.display='flex';
                    uInput.value=origUsername;
                });
            }
            if(uSaveBtn){
                uSaveBtn.addEventListener('click',function(){
                    var val=uInput.value.trim();
                    if(!val){if(window.showToast)showToast('Nom bo\'sh bo\'lmasligi kerak','error');return;}
                    if(val===origUsername){
                        uEditRow.style.display='none';uDisplayRow.style.display='flex';return;
                    }
                    uSaveBtn.disabled=true;
                    var fd=new FormData();
                    fd.set('save_settings','1');
                    fd.set('new_username',val);
                    fd.set('csrf_token',csrf);
                    fetch(saveSettingsUrl,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
                    .then(function(r){return r.json();})
                    .then(function(d){
                        uSaveBtn.disabled=false;
                        if(d.error||!d.ok){if(window.showToast)showToast(d.error||d.msg||'Xatolik','error');return;}
                        origUsername=val;
                        var display=uDisplayRow.querySelector('.rbx-setting-value');
                        display.innerHTML='<span>'+val+'</span>';
                        uEditRow.style.display='none';
                        uDisplayRow.style.display='flex';
                        if(window.showToast)showToast(d.msg||d.message||'Saqlandi','success');
                    })
                    .catch(function(){uSaveBtn.disabled=false;});
                });
            }

            /* ===== Email edit ===== */
            var eEditBtn=document.getElementById('rbxEmailEdit');
            var eDisplayRow=document.getElementById('rbxEmailRow');
            var eEditRow=document.getElementById('rbxEmailEditRow');
            var eInput=document.getElementById('rbxEmailInput');
            var eSaveBtn=document.getElementById('rbxEmailSave');
            var eCancelBtn=document.getElementById('rbxEmailCancel');

            if(eEditBtn){
                eEditBtn.addEventListener('click',function(){
                    fetch('/uzdub/api/otp-send.php',{
                        method:'POST',
                        headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
                        body:JSON.stringify({type:'pre-verify-email',csrf_token:csrf})
                    })
                    .then(function(r){return r.json();})
                    .then(function(d){
                        if(d.ok){openOTP('pre-verify-email',d.message);}
                        else{if(window.showToast)showToast(d.error||'Xatolik','error');}
                    })
                    .catch(function(){if(window.showToast)showToast('Xatolik','error');});
                });
            }
            if(eCancelBtn){
                eCancelBtn.addEventListener('click',function(){
                    eEditRow.style.display='none';
                    eDisplayRow.style.display='flex';
                    eInput.value=origEmail;
                });
            }
            if(eSaveBtn){
                eSaveBtn.addEventListener('click',function(){
                    var val=eInput.value.trim();
                    if(!val){if(window.showToast)showToast('Email kiriting','error');return;}
                    if(val===origEmail){
                        eEditRow.style.display='none';eDisplayRow.style.display='flex';return;
                    }
                    eSaveBtn.disabled=true;
                    var fd=new FormData();
                    fd.set('save_settings','1');
                    fd.set('new_email',val);
                    fd.set('csrf_token',csrf);
                    fetch(saveSettingsUrl,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
                    .then(function(r){return r.json();})
                    .then(function(d){
                        eSaveBtn.disabled=false;
                        if(!d.ok){if(window.showToast)showToast(d.msg||d.error||'Xatolik','error');return;}
                        origEmail=val;
                        eDisplayRow.querySelector('.rbx-setting-value').innerHTML='<span>'+maskEmail(val)+'</span><span class="verified-label ok"><?php echo e(t('verified')); ?></span>';
                        eEditRow.style.display='none';
                        eDisplayRow.style.display='flex';
                        if(window.showToast)showToast(d.msg||d.message||'Saqlandi','success');
                    })
                    .catch(function(){eSaveBtn.disabled=false;if(window.showToast)showToast('Xatolik','error');});
                });
            }

            /* ===== Password edit ===== */
            var pToggle=document.getElementById('rbxPassToggle');
            var pExpand=document.getElementById('rbxPassExpand');
            var pSaveBtn=document.getElementById('rbxPassSave');
            var pCancelBtn=document.getElementById('rbxPassCancel');

            if(pToggle){
                pToggle.addEventListener('click',function(){
                    fetch('/uzdub/api/otp-send.php',{
                        method:'POST',
                        headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
                        body:JSON.stringify({type:'pre-verify-password',csrf_token:csrf})
                    })
                    .then(function(r){return r.json();})
                    .then(function(d){
                        if(d.ok){openOTP('pre-verify-password',d.message);}
                        else{if(window.showToast)showToast(d.error||'Xatolik','error');}
                    })
                    .catch(function(){if(window.showToast)showToast('Xatolik','error');});
                });
            }
            if(pCancelBtn){
                pCancelBtn.addEventListener('click',function(){
                    pExpand.classList.remove('active');
                    document.getElementById('rbxCurrentPass').value='';
                    document.getElementById('rbxNewPass').value='';
                    document.getElementById('rbxConfPass').value='';
                });
            }
            if(pSaveBtn){
                pSaveBtn.addEventListener('click',function(){
                    var cur=document.getElementById('rbxCurrentPass').value;
                    var nw=document.getElementById('rbxNewPass').value;
                    var cf=document.getElementById('rbxConfPass').value;
                    if(!cur){if(window.showToast)showToast('Joriy parolni kiriting','error');document.getElementById('rbxCurrentPass').focus();return;}
                    if(!nw){if(window.showToast)showToast('Yangi parolni kiriting','error');document.getElementById('rbxNewPass').focus();return;}
                    if(nw.length<6){if(window.showToast)showToast('Parol kamida 6 ta belgi','error');return;}
                    if(nw!==cf){if(window.showToast)showToast('Parollar mos kelmaydi','error');return;}
                    pSaveBtn.disabled=true;
                    var fd=new FormData();
                    fd.set('save_settings','1');
                    fd.set('new_password',nw);
                    fd.set('confirm_password',cf);
                    fd.set('csrf_token',csrf);
                    fetch(saveSettingsUrl,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
                    .then(function(r){return r.json();})
                    .then(function(d){
                        pSaveBtn.disabled=false;
                        if(!d.ok){if(window.showToast)showToast(d.msg||d.error||'Xatolik','error');return;}
                        pExpand.classList.remove('active');
                        document.getElementById('rbxCurrentPass').value='';
                        document.getElementById('rbxNewPass').value='';
                        document.getElementById('rbxConfPass').value='';
                        if(window.showToast)showToast(d.msg||d.message||'Saqlandi','success');
                    })
                    .catch(function(){pSaveBtn.disabled=false;if(window.showToast)showToast('Xatolik','error');});
                });
            }
        })();
        </script>
        <?php
        exit;
    }

    // ===== Security tab =====
    if ($tab === 'security' && $is_own) {
    require_once __DIR__ . '/includes/lang.php';
    $stmt = $pdo->prepare("SELECT two_factor_enabled, email FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $sec_user = $stmt->fetch();
    ?>
        <div class="settings-panel">
            <div class="security-section">
                <div class="security-header">
                    <div class="security-icon">🔐</div>
                    <div>
                        <h3><?php echo t('twofa_title'); ?></h3>
                        <p class="security-desc"><?php echo t('twofa_desc'); ?></p>
                    </div>
                    <div class="security-toggle-wrap">
                        <span class="security-status <?php echo $sec_user['two_factor_enabled'] ? 'status-on' : 'status-off'; ?>" id="twofaStatus">
                            <?php echo $sec_user['two_factor_enabled'] ? t('twofa_enabled') : t('twofa_disabled'); ?>
                        </span>
                        <?php if ($sec_user['two_factor_enabled']): ?>
                        <button type="button" class="pf-btn pf-btn-danger" id="twofaDisableBtn"><?php echo t('twofa_disable_btn'); ?></button>
                        <?php else: ?>
                        <button type="button" class="pf-btn pf-btn-blue" id="twofaEnableBtn"><?php echo t('twofa_enable_btn'); ?></button>
                        <?php endif; ?>
                    </div>
                </div>
                <div id="twofaSetupArea" class="twofa-setup-area" style="display:none;">
                    <div class="twofa-qr-wrap">
                        <img id="twofaQR" src="" alt="QR Code" class="twofa-qr-img">
                        <p class="twofa-secret-label"><?php echo t('twofa_scan_qr'); ?></p>
                        <code class="twofa-secret-code" id="twofaSecret"></code>
                    </div>
                    <div class="twofa-verify-form">
                        <label><?php echo t('twofa_enter_code'); ?></label>
                        <input type="text" id="twofaCodeInput" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="000000" class="settings-input" style="text-align:center;font-size:20px;letter-spacing:6px;max-width:200px;">
                        <label><?php echo t('twofa_current_pass'); ?></label>
                        <input type="password" id="twofaPassInput" class="settings-input" autocomplete="current-password">
                        <button type="button" class="pf-btn pf-btn-blue" id="twofaConfirmBtn"><?php echo t('otp_verify_btn'); ?></button>
                    </div>
                </div>
                <div id="twofaDisableArea" class="twofa-setup-area" style="display:none;">
                    <div class="twofa-verify-form">
                        <label><?php echo t('twofa_enter_code'); ?></label>
                        <input type="text" id="twofaDisableCodeInput" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="000000" class="settings-input" style="text-align:center;font-size:20px;letter-spacing:6px;max-width:200px;">
                        <label><?php echo t('twofa_current_pass'); ?></label>
                        <input type="password" id="twofaDisablePassInput" class="settings-input" autocomplete="current-password">
                        <button type="button" class="pf-btn pf-btn-danger" id="twofaDisableConfirmBtn"><?php echo t('twofa_disable_btn'); ?></button>
                    </div>
                </div>
            </div>

            <div class="security-divider"></div>

            <div class="security-section">
                <div class="security-header">
                    <div class="security-icon">📱</div>
                    <div>
                        <h3><?php echo t('sessions_title'); ?></h3>
                        <p class="security-desc"><?php echo t('sessions_desc'); ?></p>
                    </div>
                    <button type="button" class="pf-btn pf-btn-danger" id="sessionsLogoutAll"><?php echo t('sessions_logout_all'); ?></button>
                </div>
                <div id="sessionsList" class="sessions-list">
                    <div class="tab-loading"><div class="tab-spinner"></div></div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var csrf = document.querySelector('input[name="csrf_token"]');
            var csrfVal = csrf ? csrf.value : '';
            var twofaStatus = document.getElementById('twofaStatus');

            // ===== Load sessions =====
            function loadSessions() {
                fetch('/uzdub/api/sessions.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (!d.ok) return;
                    var list = document.getElementById('sessionsList');
                    if (!d.sessions || d.sessions.length === 0) {
                        list.innerHTML = '<div class="sessions-empty"><?php echo e(t('sessions_unknown')); ?></div>';
                        return;
                    }
                    var html = '';
                    d.sessions.forEach(function(s) {
                        var icon = s.device === 'mobile' ? '📱' : (s.device === 'tablet' ? '📟' : '💻');
                        var label = s.is_current ? '<span class="session-current"><?php echo e(t('sessions_current')); ?></span>' : '';
                        html += '<div class="session-item' + (s.is_current ? ' session-active' : '') + '">';
                        html += '<div class="session-info">';
                        html += '<div class="session-device">' + icon + ' ' + s.browser + ' — ' + s.os + ' ' + label + '</div>';
                        html += '<div class="session-meta">' + s.ip + ' · ' + s.last_active + '</div>';
                        html += '</div>';
                        if (!s.is_current) {
                            html += '<button class="pf-btn pf-btn-ghost session-logout-btn" data-id="' + s.id + '"><?php echo e(t('sessions_logout_one')); ?></button>';
                        }
                        html += '</div>';
                    });
                    list.innerHTML = html;

                    list.querySelectorAll('.session-logout-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            var fd = new FormData();
                            fd.append('action', 'logout_one');
                            fd.append('session_id', this.dataset.id);
                            fd.append('csrf_token', csrfVal);
                            fetch('/uzdub/api/sessions.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(function(r) { return r.json(); })
                            .then(function(d) { if (d.ok) { if (window.showToast) showToast(d.message, 'success'); loadSessions(); } else { if (window.showToast) showToast(d.error, 'error'); } });
                        });
                    });
                })
                .catch(function() {});
            }
            loadSessions();

            // ===== Logout all =====
            document.getElementById('sessionsLogoutAll').addEventListener('click', function() {
                if (!confirm('<?php echo e("Boshqa barcha qurilmalardan chiqilsinmi?"); ?>')) return;
                var fd = new FormData();
                fd.append('action', 'logout_all');
                fd.append('csrf_token', csrfVal);
                fetch('/uzdub/api/sessions.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(d) { if (d.ok) { if (window.showToast) showToast(d.message, 'success'); loadSessions(); } else { if (window.showToast) showToast(d.error, 'error'); } });
            });

            // ===== 2FA Enable =====
            var enableBtn = document.getElementById('twofaEnableBtn');
            var setupArea = document.getElementById('twofaSetupArea');
            var confirmBtn = document.getElementById('twofaConfirmBtn');

            if (enableBtn) {
                enableBtn.addEventListener('click', function() {
                    var fd = new FormData();
                    fd.append('action', 'generate');
                    fd.append('csrf_token', csrfVal);
                    fetch('/uzdub/api/2fa-setup.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.ok) {
                            document.getElementById('twofaQR').src = d.qr_url;
                            document.getElementById('twofaSecret').textContent = d.secret;
                            setupArea.style.display = 'block';
                        } else {
                            if (window.showToast) showToast(d.error, 'error');
                        }
                    });
                });
            }

            if (confirmBtn) {
                confirmBtn.addEventListener('click', function() {
                    var code = document.getElementById('twofaCodeInput').value;
                    var pass = document.getElementById('twofaPassInput').value;
                    if (code.length !== 6) { if (window.showToast) showToast('6 xonali kod kiriting', 'error'); return; }
                    if (!pass) { if (window.showToast) showToast('<?php echo e(t("twofa_current_pass")); ?>', 'error'); return; }
                    confirmBtn.disabled = true;
                    var fd = new FormData();
                    fd.append('action', 'enable');
                    fd.append('code', code);
                    fd.append('current_password', pass);
                    fd.append('csrf_token', csrfVal);
                    fetch('/uzdub/api/2fa-setup.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        confirmBtn.disabled = false;
                        if (d.ok) {
                            if (window.showToast) showToast(d.message, 'success');
                            twofaStatus.textContent = <?php echo json_encode(t('twofa_enabled'), JSON_UNESCAPED_UNICODE); ?>;
                            twofaStatus.className = 'security-status status-on';
                            enableBtn.replaceWith(Object.assign(document.createElement('button'), {
                                className: 'pf-btn pf-btn-danger', id: 'twofaDisableBtn', textContent: <?php echo json_encode(t('twofa_disable_btn'), JSON_UNESCAPED_UNICODE); ?>
                            }));
                            setupArea.style.display = 'none';
                        } else {
                            if (window.showToast) showToast(d.error, 'error');
                        }
                    });
                });
            }

            // ===== 2FA Disable =====
            var disableBtn = document.getElementById('twofaDisableBtn');
            var disableArea = document.getElementById('twofaDisableArea');
            var disableConfirmBtn = document.getElementById('twofaDisableConfirmBtn');

            if (disableBtn) {
                disableBtn.addEventListener('click', function() {
                    disableArea.style.display = disableArea.style.display === 'none' ? 'block' : 'none';
                });
            }

            if (disableConfirmBtn) {
                disableConfirmBtn.addEventListener('click', function() {
                    var code = document.getElementById('twofaDisableCodeInput').value;
                    var pass = document.getElementById('twofaDisablePassInput').value;
                    if (code.length !== 6) { if (window.showToast) showToast('6 xonali kod kiriting', 'error'); return; }
                    if (!pass) { if (window.showToast) showToast('<?php echo e(t("twofa_current_pass")); ?>', 'error'); return; }
                    disableConfirmBtn.disabled = true;
                    var fd = new FormData();
                    fd.append('action', 'disable');
                    fd.append('code', code);
                    fd.append('current_password', pass);
                    fd.append('csrf_token', csrfVal);
                    fetch('/uzdub/api/2fa-setup.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        disableConfirmBtn.disabled = false;
                        if (d.ok) {
                            if (window.showToast) showToast(d.message, 'success');
                            twofaStatus.textContent = <?php echo json_encode(t('twofa_disabled'), JSON_UNESCAPED_UNICODE); ?>;
                            twofaStatus.className = 'security-status status-off';
                            disableBtn.replaceWith(Object.assign(document.createElement('button'), {
                                className: 'pf-btn pf-btn-blue', id: 'twofaEnableBtn', textContent: <?php echo json_encode(t('twofa_enable_btn'), JSON_UNESCAPED_UNICODE); ?>
                            }));
                            disableArea.style.display = 'none';
                        } else {
                            if (window.showToast) showToast(d.error, 'error');
                        }
                    });
                });
            }
        })();
        </script>
    <?php
    exit;
    }

    // collection items
    $cat_filter = '';
    if ($cat !== 'all') {
        $cat_filter = " AND cat.slug = " . $pdo->quote($cat);
    }
    $collection_items = [];
    if ($tab === 'history') {
        $sql = "SELECT DISTINCT wh.content_id, c.title, c.poster, c.release_year, cat.slug as category, cat.name as category_name, MAX(wh.watched_at) as last_watched
                FROM watch_history wh JOIN content c ON c.id = wh.content_id JOIN categories cat ON cat.id = c.category_id
                WHERE wh.user_id = ? $cat_filter GROUP BY wh.content_id ORDER BY last_watched DESC LIMIT 50";
        $stmt = $pdo->prepare($sql); $stmt->execute([$uid]); $collection_items = $stmt->fetchAll();
    } elseif ($tab === 'favorites') {
        $fav_where = $cat !== 'all' ? "WHERE favs.category = " . $pdo->quote($cat) : 'WHERE 1=1';
        $sql = "SELECT content_id, title, poster, release_year, category, category_name FROM (
                SELECT ucs.content_id, c.title, c.poster, c.release_year, cat.slug as category, cat.name as category_name, ucs.created_at as sort_date
                FROM user_content_status ucs JOIN content c ON c.id = ucs.content_id JOIN categories cat ON cat.id = c.category_id WHERE ucs.user_id = ? AND ucs.status = 'favorite'
                UNION
                SELECT w.content_id, c.title, c.poster, c.release_year, cat.slug as category, cat.name as category_name, w.created_at as sort_date
                FROM watchlist w JOIN content c ON c.id = w.content_id JOIN categories cat ON cat.id = c.category_id WHERE w.user_id = ?
                ) AS favs $fav_where ORDER BY sort_date DESC LIMIT 50";
        $stmt = $pdo->prepare($sql); $stmt->execute([$uid, $uid]); $collection_items = $stmt->fetchAll();
    } else {
        $status_map = ['watching'=>'watching','planned'=>'planned','completed'=>'completed','paused'=>'paused','dropped'=>'dropped'];
        $status_val = $status_map[$tab] ?? $tab;
        $sql = "SELECT ucs.content_id, c.title, c.poster, c.release_year, cat.slug as category, cat.name as category_name
                FROM user_content_status ucs JOIN content c ON c.id = ucs.content_id JOIN categories cat ON cat.id = c.category_id
                WHERE ucs.user_id = ? AND ucs.status = ? $cat_filter ORDER BY ucs.updated_at DESC LIMIT 50";
        $stmt = $pdo->prepare($sql); $stmt->execute([$uid, $status_val]); $collection_items = $stmt->fetchAll();
    }

    require_once __DIR__ . '/includes/lang.php';
    if (empty($collection_items)):
    ?>
    <div class="empty-state">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".3"><rect width="18" height="18" x="3" y="3" rx="2"/><line x1="12" x2="12" y1="8" y2="16"/><line x1="8" x2="16" y1="12" y2="12"/></svg>
        <p><?php echo t('section_empty'); ?></p>
    </div>
    <?php else: ?>
    <div class="collection-grid">
        <?php foreach ($collection_items as $item): ?>
        <a href="watch.php?id=<?php echo $item['content_id']; ?>" class="collection-card">
            <div class="collection-poster">
                <?php if ($item['poster']): ?>
                <img src="/uzdub/uploads/posters/<?php echo e($item['poster']); ?>" alt="<?php echo e(t_title($item)); ?>" loading="lazy">
                <?php else: ?>
                <div class="no-poster">🎬</div>
                <?php endif; ?>
                <div class="collection-cat-badge"><?php echo e($item['category_name']); ?></div>
            </div>
            <div class="collection-info">
                <div class="collection-title"><?php echo e(t_title($item)); ?></div>
                <div class="collection-meta">
                    <?php if ($item['release_year']): ?><span><?php echo e($item['release_year']); ?></span><?php endif; ?>
                    <?php if ($tab === 'history' && isset($item['last_watched'])): ?><span>· <?php echo date('d.m', strtotime($item['last_watched'])); ?></span><?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif;
    exit;
}

// ===== Normal page load =====
$uid_param = $_GET['uid'] ?? '';
if (!$uid_param) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$uid_param]);
$profile_user = $stmt->fetch();

if (!$profile_user) { header('Location: index.php'); exit; }

check_premium_expiry($pdo, $profile_user['id']);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$profile_user['id']]);
$profile_user = $stmt->fetch();

$page_title = $profile_user['username'] . t('profile_title_suffix');
$is_own = is_user() && $_SESSION['user_id'] === $profile_user['id'];

// ===== Settings form handler (AJAX — only username/avatar, email/password via OTP) =====
if ($is_own && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'msg' => t('security_token_wrong')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $resp = ['ok' => true, 'msg' => ''];
    if (!empty($_FILES['avatar']['tmp_name'])) {
        $av = upload_file('avatar', __DIR__ . '/uploads/avatars/', ['jpg','jpeg','png','webp','gif'], ['image/jpeg','image/png','image/webp','image/gif']);
        if ($av) {
            $pdo->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$av, $profile_user['id']]);
            $profile_user['avatar'] = $av;
            $resp['avatar_url'] = '/uzdub/uploads/avatars/' . $av;
            $resp['msg'] .= t('avatar_updated') . ' ';
        }
    }
    $new_username = trim($_POST['new_username'] ?? '');
    if ($new_username && $new_username !== $profile_user['username']) {
        if (!empty($profile_user['username_changed_at'])) {
            $changed = new DateTime($profile_user['username_changed_at']);
            $now = new DateTime();
            $diff = $now->diff($changed);
            $days_passed = (int)floor((($diff->days * 24 + $diff->h) * 60 + $diff->i) / (60 * 24));
            if ($days_passed < 14) {
                $resp['ok'] = false;
                $resp['msg'] .= sprintf(t('username_cooldown'), 14 - $days_passed) . ' ';
            }
        }
        if ($resp['ok']) {
            if (mb_strlen($new_username) < 3) {
                $resp['ok'] = false;
                $resp['msg'] .= 'Username too short. ';
            } else {
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $check->execute([$new_username, $profile_user['id']]);
                if ($check->fetch()) {
                    $resp['ok'] = false;
                    $resp['msg'] .= 'Username already taken. ';
                } else {
                    $pdo->prepare("UPDATE users SET username=?, username_changed_at=NOW() WHERE id=?")->execute([$new_username, $profile_user['id']]);
                    $profile_user['username'] = $new_username;
                }
            }
        }
    }

    /* ===== Email change (requires pre-verify) ===== */
    $new_email = trim($_POST['new_email'] ?? '');
    if ($new_email && $new_email !== ($profile_user['email'] ?? '')) {
        $verified_at = $_SESSION['settings_verified_at'] ?? 0;
        if (($_SESSION['settings_verified_for'] ?? '') !== 'email' || (time() - $verified_at) > 600) {
            $resp['ok'] = false;
            $resp['msg'] .= 'Email o\'zgartirish uchun tasdiqlash kerak. ';
        } else {
            if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $resp['ok'] = false;
                $resp['msg'] .= 'Noto\'g\'ri email. ';
            } else {
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check->execute([$new_email, $profile_user['id']]);
                if ($check->fetch()) {
                    $resp['ok'] = false;
                    $resp['msg'] .= 'Email allaqachon band. ';
                } else {
                    $pdo->prepare("UPDATE users SET email=? WHERE id=?")->execute([$new_email, $profile_user['id']]);
                    $profile_user['email'] = $new_email;
                    unset($_SESSION['settings_verified_for'], $_SESSION['settings_verified_at']);
                    $resp['msg'] .= t('otp_email_changed') . ' ';
                }
            }
        }
    }

    /* ===== Password change (requires pre-verify) ===== */
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    if ($new_password) {
        $verified_at = $_SESSION['settings_verified_at'] ?? 0;
        if (($_SESSION['settings_verified_for'] ?? '') !== 'password' || (time() - $verified_at) > 600) {
            $resp['ok'] = false;
            $resp['msg'] .= 'Parol o\'zgartirish uchun tasdiqlash kerak. ';
        } else {
            if (mb_strlen($new_password) < 6) {
                $resp['ok'] = false;
                $resp['msg'] .= 'Parol kamida 6 ta belgi. ';
            } elseif ($new_password !== $confirm_password) {
                $resp['ok'] = false;
                $resp['msg'] .= 'Parollar mos kelmaydi. ';
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $profile_user['id']]);
                unset($_SESSION['settings_verified_for'], $_SESSION['settings_verified_at']);
                $resp['msg'] .= t('otp_password_changed') . ' ';
            }
        }
    }
    if (!$resp['msg']) $resp['msg'] = t('profile_updated');
    refresh_user_session($pdo, $profile_user['id']);
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;
}

$uid = $profile_user['id'];

$stat_ratings = $pdo->prepare("SELECT COUNT(*) FROM ratings WHERE user_id = ?");
$stat_ratings->execute([$uid]); $stat_ratings_count = (int)$stat_ratings->fetchColumn();

$stat_comments = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
$stat_comments->execute([$uid]); $stat_comments_count = (int)$stat_comments->fetchColumn();

$stat_watchlist = $pdo->prepare("SELECT COUNT(*) FROM watchlist WHERE user_id = ?");
$stat_watchlist->execute([$uid]); $stat_watchlist_count = (int)$stat_watchlist->fetchColumn();

$stat_watched = $pdo->prepare("SELECT COUNT(DISTINCT content_id) FROM watch_history WHERE user_id = ?");
$stat_watched->execute([$uid]); $stat_watched_count = (int)$stat_watched->fetchColumn();

$stat_watch_seconds = (int)$profile_user['online_time'];
$stat_watch_hours = floor($stat_watch_seconds / 3600);
$stat_watch_mins = floor(($stat_watch_seconds % 3600) / 60);

$stat_favorites = $pdo->prepare("SELECT COUNT(DISTINCT content_id) as cnt FROM (
    SELECT content_id FROM user_content_status WHERE user_id = ? AND status = 'favorite'
    UNION SELECT content_id FROM watchlist WHERE user_id = ?
) AS all_favs");
$stat_favorites->execute([$uid, $uid]); $stat_favorites_count = (int)$stat_favorites->fetchColumn();

$stat_streak = 0;
$stat_streak_record = 0;
$streak_rows = $pdo->prepare("SELECT DISTINCT DATE(watched_at) as d FROM watch_history WHERE user_id = ? ORDER BY d DESC");
$streak_rows->execute([$uid]); $streak_dates = $streak_rows->fetchAll(PDO::FETCH_COLUMN);
if ($streak_dates) {
    $today = new DateTime('today');
    $check = new DateTime($streak_dates[0]);
    if ($check->format('Y-m-d') === $today->format('Y-m-d') || $check->format('Y-m-d') === $today->modify('-1 day')->format('Y-m-d')) {
        $stat_streak = 1;
        $current = new DateTime($streak_dates[0]);
        for ($i = 1; $i < count($streak_dates); $i++) {
            $prev = new DateTime($streak_dates[$i]);
            $diff = $current->diff($prev)->days;
            if ($diff === 1) { $stat_streak++; $current = $prev; } else break;
        }
    }
    $stat_streak_record = 1;
    $current = new DateTime($streak_dates[count($streak_dates) - 1]);
    $run = 1;
    for ($i = count($streak_dates) - 2; $i >= 0; $i--) {
        $prev = new DateTime($streak_dates[$i]);
        $diff = $prev->diff($current)->days;
        if ($diff === 1) { $run++; $current = $prev; if ($run > $stat_streak_record) $stat_streak_record = $run; }
        else { $current = $prev; $run = 1; }
    }
}

include __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="/uzdub/css/profile.css">

<div class="profile-page">

<div class="profile-header">
    <div class="profile-banner">
        <div class="profile-banner-overlay"></div>
    </div>
    <div class="profile-header-inner">
        <div class="profile-avatar-section">
            <div class="profile-avatar-wrap">
                <img src="<?php echo avatar_url($profile_user['avatar']); ?>" alt="Avatar" id="avatar-img" class="profile-avatar-img">
                <?php if ($profile_user['is_premium']): ?>
                <div class="avatar-crown">⭐</div>
                <?php endif; ?>
                <div class="online-dot"></div>

            </div>
        </div>
        <div class="profile-info-section">
            <div class="profile-name-row">
                <h1 class="profile-username"><?php echo e($profile_user['username']); ?></h1>
                <?php if ($profile_user['is_premium']): ?>
                <span class="premium-badge-sm">⭐ <?php echo t('premium_badge'); ?></span>
                <?php endif; ?>
            </div>
            <div class="profile-meta-row">
                <span class="meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                    <?php echo date('d.m.Y', strtotime($profile_user['created_at'])); ?>
                </span>
                <span class="meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span id="sessionTimer">00m 00s</span>
                </span>
                <span class="meta-item meta-role"><?php echo $profile_user['is_premium'] ? t('premium_badge') : t('user_role'); ?></span>
                <span class="meta-item meta-id"><?php echo t('id_label'); ?><?php echo e($profile_user['user_id']); ?></span>
            </div>
            <div class="profile-actions-row">
                <?php if ($is_own): ?>
                <a href="premium.php" class="pf-btn pf-btn-gold">⭐ <?php echo t('get_premium_btn'); ?></a>
                <a href="auth/logout.php" class="pf-btn pf-btn-ghost">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                    <?php echo t('logout_btn'); ?>
                </a>
                <?php elseif (!is_user()): ?>
                <a href="auth/login.php" class="pf-btn pf-btn-blue"><?php echo t('login_btn'); ?></a>
                <?php else: ?>
                <a href="chat.php?with=<?php echo e($profile_user['user_id']); ?>" class="pf-btn pf-btn-blue">💬 <?php echo t('send_message'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="profile-stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-value"><?php echo number_format($stat_watched_count); ?></div>
        <div class="stat-label"><?php echo t('watched'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⭐</div>
        <div class="stat-value"><?php echo number_format($stat_ratings_count); ?></div>
        <div class="stat-label"><?php echo t('ratings'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⏱️</div>
        <div class="stat-value" id="liveTotalTime"><?php echo $stat_watch_hours > 0 ? $stat_watch_hours . t('hours_abbrev') . $stat_watch_mins . t('minutes_abbrev_short') : ($stat_watch_mins > 0 ? $stat_watch_mins . t('minutes_abbrev_short') : '0' . t('minutes_abbrev_short')); ?></div>
        <div class="stat-label"><?php echo t('total_time'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🔥</div>
        <div class="stat-value"><?php echo $stat_streak; ?></div>
        <div class="stat-label"><?php echo t('streak'); ?> (<?php echo $stat_streak_record; ?><?php echo t('records'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">❤️</div>
        <div class="stat-value"><?php echo number_format($stat_favorites_count); ?></div>
        <div class="stat-label"><?php echo t('favorites'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💬</div>
        <div class="stat-value"><?php echo number_format($stat_comments_count); ?></div>
        <div class="stat-label"><?php echo t('comments'); ?></div>
    </div>
</div>

<div class="profile-tabs-section">
    <div class="profile-tabs" id="profileTabs">
        <button class="pf-tab active" data-tab="history">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?php echo t('tab_history'); ?>
        </button>
        <button class="pf-tab" data-tab="watching">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            <?php echo t('tab_watching'); ?>
        </button>
        <button class="pf-tab" data-tab="planned">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            <?php echo t('tab_planned'); ?>
        </button>
        <button class="pf-tab" data-tab="completed">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <?php echo t('tab_completed'); ?>
        </button>
        <button class="pf-tab" data-tab="paused">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="4" height="16" x="6" y="4"/><rect width="4" height="16" x="14" y="4"/></svg>
            <?php echo t('tab_on_hold'); ?>
        </button>
        <button class="pf-tab" data-tab="dropped">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" x2="9" y1="9" y2="15"/><line x1="9" x2="15" y1="9" y2="15"/></svg>
            <?php echo t('tab_dropped'); ?>
        </button>
        <button class="pf-tab" data-tab="favorites">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <?php echo t('tab_favorite'); ?>
        </button>
        <?php if ($is_own): ?>
        <button class="pf-tab" data-tab="settings">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <?php echo t('tab_settings'); ?>
        </button>
        <button class="pf-tab" data-tab="security">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <?php echo t('tab_security'); ?>
        </button>
        <?php endif; ?>
    </div>

    <div id="profileTabContent" class="profile-collection">
    <?php
    // Pre-render initial "history" tab server-side
    $init_tab = 'history';
    $init_cat_filter = '';
    $init_sql = "SELECT DISTINCT wh.content_id, c.title, c.poster, c.release_year, cat.slug as category, cat.name as category_name, MAX(wh.watched_at) as last_watched
            FROM watch_history wh JOIN content c ON c.id = wh.content_id JOIN categories cat ON cat.id = c.category_id
            WHERE wh.user_id = ? $init_cat_filter GROUP BY wh.content_id ORDER BY last_watched DESC LIMIT 50";
    $init_stmt = $pdo->prepare($init_sql); $init_stmt->execute([$uid]); $init_items = $init_stmt->fetchAll();
    if (empty($init_items)):
    ?>
        <div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".3"><rect width="18" height="18" x="3" y="3" rx="2"/><line x1="12" x2="12" y1="8" y2="16"/><line x1="8" x2="16" y1="12" y2="12"/></svg>
            <p><?php echo t('section_empty'); ?></p>
        </div>
    <?php else: ?>
        <div class="collection-grid">
        <?php foreach ($init_items as $item): ?>
            <a href="watch.php?id=<?php echo $item['content_id']; ?>" class="collection-card">
                <div class="collection-poster">
                    <?php if ($item['poster']): ?>
                    <img src="/uzdub/uploads/posters/<?php echo e($item['poster']); ?>" alt="<?php echo e(t_title($item)); ?>" loading="lazy">
                    <?php else: ?>
                    <div class="no-poster">🎬</div>
                    <?php endif; ?>
                    <div class="collection-cat-badge"><?php echo e($item['category_name']); ?></div>
                </div>
                <div class="collection-info">
                    <div class="collection-title"><?php echo e(t_title($item)); ?></div>
                    <div class="collection-meta">
                        <?php if ($item['release_year']): ?><span><?php echo e($item['release_year']); ?></span><?php endif; ?>
                        <?php if (isset($item['last_watched'])): ?><span>· <?php echo date('d.m', strtotime($item['last_watched'])); ?></span><?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>
</div>

</div>



<?php if (!empty($msg)): ?>
<script>document.addEventListener('DOMContentLoaded',function(){if(window.showToast)showToast(<?php echo json_encode($msg,JSON_UNESCAPED_UNICODE); ?>,<?php echo json_encode(strpos($msg,'error')!==false||strpos($msg,'Xato')!==false||strpos($msg,'incorrect')!==false||strpos($msg,'not')!==false||strpos($msg,'too short')!==false||strpos($msg,'do not match')!==false||strpos($msg,'taken')!==false?'error':'success'); ?>);});</script>
<?php endif; ?>

<script>
var PT = <?php echo json_encode(['hours_unit' => t('hours_unit')], JSON_UNESCAPED_UNICODE); ?>;
var PF_UID = <?php echo json_encode(e($uid_param), JSON_UNESCAPED_UNICODE); ?>;
var PF_IS_OWN = <?php echo $is_own ? 'true' : 'false'; ?>;
var PF_CURRENT_CAT = 'all';

// Session timer
(function(){
    var sec = 0;
    var el = document.getElementById('sessionTimer');
    if(!el) return;
    setInterval(function(){
        sec++;
        var h = Math.floor(sec/3600);
        var m = Math.floor((sec%3600)/60);
        var s = sec%60;
        var parts = [];
        if(h>0) parts.push(h+PT.hours_unit);
        parts.push((m<10?'0':'')+m+'m');
        parts.push((s<10?'0':'')+s+'s');
        el.textContent = parts.join(' ');
    },1000);
})();

// AJAX Tab system
(function() {
    var tabs = document.querySelectorAll('#profileTabs .pf-tab');
    var content = document.getElementById('profileTabContent');
    var activeTab = 'history';
    var cache = {};
    var scriptCache = {};
    cache['history'] = content.innerHTML;

    function execScripts(container) {
        var scripts = container.querySelectorAll('script');
        scripts.forEach(function(old) {
            var s = document.createElement('script');
            if (old.src) {
                s.src = old.src;
            } else {
                s.textContent = old.textContent;
            }
            old.parentNode.replaceChild(s, old);
        });
    }

    function loadTab(tab) {
        if (cache[tab]) {
            content.innerHTML = cache[tab];
            if (scriptCache[tab]) {
                content.querySelectorAll('script').forEach(function(old) {
                    var s = document.createElement('script');
                    s.textContent = old.textContent;
                    old.parentNode.replaceChild(s, old);
                });
            }
            return;
        }
        content.innerHTML = '<div class="tab-loading"><div class="tab-spinner"></div></div>';
        var url = '/uzdub/profile.php?ajax_tab=' + encodeURIComponent(tab) + '&uid=' + encodeURIComponent(PF_UID) + '&cat=' + encodeURIComponent(PF_CURRENT_CAT);
        fetch(url)
            .then(function(r) { return r.text(); })
            .then(function(html) {
                cache[tab] = html;
                content.innerHTML = html;
                scriptCache[tab] = true;
                execScripts(content);
            })
            .catch(function() {
                content.innerHTML = '<div class="empty-state"><p>Xatolik yuz berdi.</p></div>';
            });
    }

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var tabName = this.dataset.tab;
            if (tabName === activeTab) return;
            activeTab = tabName;
            tabs.forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            loadTab(tabName);
        });
    });

    // Load initial tab
    loadTab(activeTab);
})();

// Live total time — heartbeat dan har 60s da yangilanadi
(function() {
    var el = document.getElementById('liveTotalTime');
    if (!el || window.UZDUB_IS_LOGGED_IN !== true) return;
    var H = <?php echo json_encode(t('hours_abbrev'), JSON_UNESCAPED_UNICODE); ?>;
    var M = <?php echo json_encode(t('minutes_abbrev_short'), JSON_UNESCAPED_UNICODE); ?>;
    function fmt(sec) {
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        return h > 0 ? h + H + m + M : (m > 0 ? m + M : '0' + M);
    }
    setInterval(function() {
        fetch('/uzdub/api/heartbeat.php', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok && d.online_time !== undefined) el.textContent = fmt(d.online_time);
        })
        .catch(function() {});
    }, 60000);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
