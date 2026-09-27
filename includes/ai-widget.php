<?php
/* ============================================================
   includes/ai-widget.php
   UZDUB AI — OVOZLI suhbat widgeti (chat tarixi bilan, Premium)
   FAB tugma -> oynada ovozli AI (orb-mikrafon + jonli rejim)
   Dizayn: Gemini Dark — toza chat, markaziy salomlashuv, composer
   ============================================================ */
$is_premium_user = is_user() && !empty(current_user()['is_premium']);
?>
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>/css/ai-chat.css?v=<?php echo @filemtime(__DIR__ . '/../css/ai-chat.css') ?: 1; ?>">

<?php if ($is_premium_user): ?>
<button class="aic-fab" id="aic-fab" aria-label="AI yordamchi" title="AI yordamchi">
  <svg viewBox="0 0 24 24">
    <path d="M12 2a10 10 0 1 0 3.6 19.33L22 22l-1.03-4.24A10 10 0 0 0 12 2zm0 2a8 8 0 1 1-4.24 14.79l-.4-.25-2.85.68.7-2.76-.27-.42A8 8 0 0 1 12 4z"/>
  </svg>
</button>

<div class="aic-panel" id="aic-panel">
  <!-- ===================== HEADER (Gemini: markaziy brend) ===================== -->
  <div class="aic-header">
    <button class="aic-back" id="aic-back" style="display:none;" title="Orqaga">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </button>
    <button class="aic-ico" id="aic-hist" title="Suhbatlar tarixi">☰</button>
    <div class="aic-header-info">
      <span class="aic-title"><svg class="hspark" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1.9l2.3 6.9 6.9 2.3-6.9 2.3L12 20.3l-2.3-6.9-6.9-2.3 6.9-2.3L12 1.9z"/></svg>UZDUB AI<?php if ($is_premium_user): ?><span class="aic-prem">👑</span><?php endif; ?></span>
      <span class="aic-header-status"><span class="aic-dot"></span> Online</span>
    </div>
    <button class="aic-ico" id="aic-gear" title="Ovoz sozlamalari">⚙️</button>
    <button class="aic-ico aic-ico-new" id="aic-new-chat" title="Yangi chat">＋</button>
    <button class="aic-close" id="aic-close" aria-label="Yopish" title="Yopish">&times;</button>
  </div>

  <!-- ===================== Suhbatlar ro'yxati ===================== -->
  <div class="aic-chat-list" id="aic-chat-list" style="display:none;">
    <div class="aic-list-header">
      <h3>💬 Suhbatlar</h3>
      <button class="aic-new-chat-btn" id="aic-new-chat-btn">+ Yangi</button>
    </div>
    <div class="aic-list-items" id="aic-list-items">
      <div class="aic-loading">Yuklanmoqda...</div>
    </div>
  </div>

  <!-- ===================== CHAT (Gemini Dark) ===================== -->
  <div class="aic-chat-view" id="aic-chat-view" style="display:flex;">
    <!-- Chat — butun o'rta qism -->
    <div class="aic-log" id="aic-log"></div>

    <!-- Jonli matn (eshitganda, composer tepasida) -->
    <div class="vs-interim" id="vs-interim"></div>

    <!-- Holat yozuvi (kichkina, markazda) -->
    <div class="vs-gstatus" id="vs-status"></div>

    <!-- Composer: [orb-mikrafon] [matn] [➤] -->
    <div class="aic-composer">
      <button class="aic-hidden-mic" id="aic-mic" aria-label="Ovozli kiritish" title="Mikrofon">🎤</button>
      <button class="orbWrap" id="orbWrap" title="Bosing va gapiring" aria-label="Mikrofon">
        <div class="ring"></div>
        <div class="ring"></div>
        <div class="orb" id="orb"></div>
        <span class="orbIcon" id="orbIcon">🎤</span>
      </button>
      <input id="aic-input" class="aic-input" type="text" placeholder="UZDUB AI ga savol bering..." autocomplete="off">
      <button class="aic-send" id="aic-send" aria-label="Yuborish" title="Yuborish">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7"/><path d="M8 7h9v9"/></svg>
      </button>
    </div>
    <div class="vs-foot">AI xato qilishi mumkin. Muhim ma'lumotlarni tekshiring · <b>UZDUB AI</b></div>

    <!-- Sozlamalar popoveri (⚙️) -->
    <div class="gearpop" id="aic-gear-pop">
      <div class="gp-title">Ovoz sozlamalari</div>
      <div class="gp-row"><span>🎤 Kirish tili</span>
        <select id="voiceLang" title="Mikrofon tili">
          <option value="uz-UZ">🇺🇿 O'zbekcha</option>
          <option value="ru-RU">🇷🇺 Ruscha</option>
          <option value="en-US">🇬🇧 Inglizcha</option>
          <option value="tr-TR">🇹🇷 Turkcha</option>
        </select>
      </div>
      <div class="gp-row"><span>🗣 UZDUB ovozi</span>
        <select id="ttsVoice" title="Bot ovozi">
          <option value="uz-UZ-MadinaNeural">Madina (ayol)</option>
          <option value="uz-UZ-SardorNeural">Sardor (erkak)</option>
        </select>
      </div>
      <div class="gp-row"><span>⚙️ Dvigatel</span><span id="ttsEngine" class="gp-engine"></span></div>
      <div class="gp-row">
        <button type="button" class="gp-vbtn" id="voiceBtn" title="Ovozli javob yoqish/o'chirish">🔊 Ovozli javob</button>
      </div>
      <div class="gp-sep"></div>
      <div class="gp-row"><label class="chk"><input type="checkbox" id="liveMode" checked> ⚡ Jonli rejim (har gapga javob)</label></div>
    </div>
  </div>
</div>
<?php else: ?>
<button class="aic-fab aic-fab-premium-only" id="aic-fab" aria-label="AI yordamchi (Premium)" title="AI yordamchi — faqat Premium uchun" onclick="window.location.href='<?php echo ROOT_URL; ?>/premium.php'">
  <svg viewBox="0 0 24 24">
    <path d="M12 2a10 10 0 1 0 3.6 19.33L22 22l-1.03-4.24A10 10 0 0 0 12 2zm0 2a8 8 0 1 1-4.24 14.79l-.4-.25-2.85.68.7-2.76-.27-.42A8 8 0 0 1 12 4z"/>
  </svg>
  <span class="aic-premium-lock">👑</span>
</button>
<?php endif; ?>

<script>
  window.aicCsrfToken = <?php echo json_encode(csrf_token()); ?>;
  window.aicIsLoggedIn = <?php echo json_encode(function_exists('is_user') && is_user()); ?>;
  window.aicIsPremium = <?php echo json_encode($is_premium_user); ?>;
  window.aicLang = <?php echo json_encode(current_lang()); ?>;
  window.aicUsername = <?php echo json_encode(is_user() ? current_user()['username'] : ''); ?>;
</script>
<?php if ($is_premium_user): ?>
<script src="<?php echo ROOT_URL; ?>/js/ai-chat.js?v=<?php echo @filemtime(__DIR__ . '/../js/ai-chat.js') ?: 3; ?>" defer></script>
<?php endif; ?>