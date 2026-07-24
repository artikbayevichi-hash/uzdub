<?php
/* ============================================================
   includes/ai-widget.php
   Chat tarixi bilan AI yordamchi (faqat Premium)
   ============================================================ */
$is_premium_user = is_user() && !empty(current_user()['is_premium']);
?>
<link rel="stylesheet" href="/uzdub/css/ai-chat.css">

<?php if ($is_premium_user): ?>
<button class="aic-fab" id="aic-fab" aria-label="AI yordamchi" title="AI yordamchi">
  <svg viewBox="0 0 24 24">
    <path d="M12 2a10 10 0 1 0 3.6 19.33L22 22l-1.03-4.24A10 10 0 0 0 12 2zm0 2a8 8 0 1 1-4.24 14.79l-.4-.25-2.85.68.7-2.76-.27-.42A8 8 0 0 1 12 4z"/>
  </svg>
</button>

<div class="aic-panel" id="aic-panel">
  <div class="aic-header">
    <button class="aic-back" id="aic-back" style="display:none;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </button>
    <div class="aic-header-avatar">
      <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 3.6 19.33L22 22l-1.03-4.24A10 10 0 0 0 12 2zm0 2a8 8 0 1 1-4.24 14.79l-.4-.25-2.85.68.7-2.76-.27-.42A8 8 0 0 1 12 4z"/></svg>
    </div>
    <div class="aic-header-info">
      <span class="aic-title">UZDUB AI</span>
      <span class="aic-header-status"><span class="aic-dot"></span> Online</span>
    </div>
    <button class="aic-new-chat" id="aic-new-chat" title="Yangi chat">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
    </button>
    <button class="aic-close" id="aic-close" aria-label="Yopish">&times;</button>
  </div>
  
  <div class="aic-chat-list" id="aic-chat-list">
    <div class="aic-list-header">
      <h3>Suhbatlar</h3>
      <button class="aic-new-chat-btn" id="aic-new-chat-btn">+ Yangi</button>
    </div>
    <div class="aic-list-items" id="aic-list-items">
      <div class="aic-loading">Yuklanmoqda...</div>
    </div>
  </div>

  <div class="aic-chat-view" id="aic-chat-view" style="display:none;">
    <div class="aic-log" id="aic-log"></div>
    <div class="aic-typing" id="aic-typing" style="display:none;">
      <span class="aic-typing-dots"><span></span><span></span><span></span></span>
    </div>
    <div class="aic-inputbar">
      <input type="text" id="aic-input" class="aic-input" placeholder="Xabar yozing..." autocomplete="off">
      <button class="aic-send" id="aic-send" aria-label="Yuborish">
        <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
      </button>
    </div>
  </div>
</div>
<?php else: ?>
<button class="aic-fab aic-fab-premium-only" id="aic-fab" aria-label="AI yordamchi (Premium)" title="AI yordamchi — faqat Premium uchun" onclick="window.location.href='/uzdub/premium.php'">
  <svg viewBox="0 0 24 24">
    <path d="M12 2a10 10 0 1 0 3.6 19.33L22 22l-1.03-4.24A10 10 0 0 0 12 2zm0 2a8 8 0 1 1-4.24 14.79l-.4-.25-2.85.68.7-2.76-.27-.42A8 8 0 0 1 12 4z"/>
  </svg>
  <span class="aic-premium-lock">⭐</span>
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
<script src="/uzdub/js/ai-chat.js" defer></script>
<?php endif; ?>
