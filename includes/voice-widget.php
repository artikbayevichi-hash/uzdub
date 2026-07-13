<?php
/* ============================================================
   includes/voice-widget.php
   "UZDUB AI" — nomi bilan chaqiriladigan ovozli yordamchi widget
   ============================================================ */
?>
<link rel="stylesheet" href="/uzdub/css/voice-assistant.css">

<div class="va-widget" id="va-widget">
    <button class="va-toggle" id="va-toggle" type="button" title="UZDUB AI ovozli yordamchi">
        <svg viewBox="0 0 24 24" class="va-mic-icon">
            <path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v5a3 3 0 0 0 3 3z"/>
            <path d="M19 11a1 1 0 1 0-2 0 5 5 0 0 1-10 0 1 1 0 1 0-2 0 7 7 0 0 0 6 6.92V20H9a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2h-2v-2.08A7 7 0 0 0 19 11z"/>
        </svg>
        <span class="va-pulse-ring"></span>
    </button>
    <div class="va-panel" id="va-panel">
        <div class="va-status" id="va-status">"UZDUB AI" deb chaqiring</div>
        <div class="va-transcript" id="va-transcript"></div>
    </div>
</div>

<script>
    window.vaCsrfToken = <?php echo json_encode(csrf_token()); ?>;
    window.vaWakeWord = <?php echo json_encode('uzdub ai'); ?>;
</script>
<script src="/uzdub/js/voice-assistant.js" defer></script>
