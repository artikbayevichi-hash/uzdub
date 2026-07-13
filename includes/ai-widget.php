<?php
/* ============================================================
   includes/ai-widget.php
   Chat tarixi bilan AI yordamchi
   ============================================================ */
?>
<link rel="stylesheet" href="/uzdub/css/ai-chat.css">

<button class="aic-fab" id="aic-fab" aria-label="AI yordamchi" title="AI yordamchi">
  <svg viewBox="0 0 24 24">
    <path d="M12 2a10 10 0 1 0 3.6 19.33L22 22l-1.03-4.24A10 10 0 0 0 12 2zm0 2a8 8 0 1 1-4.24 14.79l-.4-.25-2.85.68.7-2.76-.27-.42A8 8 0 0 1 12 4z"/>
  </svg>
</button>

<div class="aic-panel" id="aic-panel">
  <div class="aic-header">
    <button class="aic-back" id="aic-back" style="display:none;">←</button>
    <span class="aic-dot"></span>
    <span class="aic-title">UZDUB AI Yordamchi</span>
    <button class="aic-new-chat" id="aic-new-chat" title="Yangi chat">+</button>
    <button class="aic-close" id="aic-close" aria-label="Yopish">&times;</button>
  </div>
  
  <div class="aic-chat-list" id="aic-chat-list">
    <div class="aic-list-header">
      <h3>Chatlar</h3>
      <button class="aic-new-chat-btn" id="aic-new-chat-btn">+ Yangi chat</button>
    </div>
    <div class="aic-list-items" id="aic-list-items">
      <div class="aic-loading">Yuklanmoqda...</div>
    </div>
  </div>

  <div class="aic-chat-view" id="aic-chat-view" style="display:none;">
    <div class="aic-log" id="aic-log"></div>
    <div class="aic-typing" id="aic-typing" style="display:none;">yozmoqda...</div>
    <div class="aic-inputbar">
      <input type="text" id="aic-input" class="aic-input" placeholder="Filmlar haqida so'rang...">
      <button class="aic-send" id="aic-send" aria-label="Yuborish">
        <svg viewBox="0 0 24 24"><path d="M2 21l21-9L2 3v7l15 2-15 2z"/></svg>
      </button>
    </div>
  </div>
</div>

<script>
  window.aicCsrfToken = <?php echo json_encode(csrf_token()); ?>;
</script>
<script src="/uzdub/js/ai-chat.js" defer></script>
