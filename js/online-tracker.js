// ================================================================
// UZDUB PLATFORM — Online Tracker
// Onlayn vaqtni localStorage + server sinxronizatsiyasi,
// Heartbeat (30s), Online count (12s), Timer display
// ================================================================
(function() {
    'use strict';

    var IS_LOGGED_IN = window.UZDUB_IS_LOGGED_IN === true;
    if (!IS_LOGGED_IN) return;

    var HEARTBEAT_INTERVAL = 30000;   // 30 sekundda heartbeat
    var COUNT_INTERVAL = 12000;       // 12 sekundda online count yangilash
    var SYNC_INTERVAL = 60000;        // 60 sekundda online_time serverga yuborish
    var STORAGE_KEY = 'uzdub_online_start';
    var STORAGE累积 = 'uzdub_online_accumulated';

    var accumulated = 0;    // oldin yig'ilgan vaqt (sekund)
    var sessionStart = Math.floor(Date.now() / 1000);   // joriy sahifa/session boshlanish vaqti
    var lastHeartbeat = 0;  // oxirgi heartbeatda yuborilgan elapsed

    function saveAccumulated(val) {
        try { localStorage.setItem(STORAGE累积, String(val)); } catch (e) {}
    }

    function saveSessionStart(val) {
        try { localStorage.setItem(STORAGE_KEY, String(val)); } catch (e) {}
    }

    // ===== Session timer =====
    var timerEl = document.getElementById('sessionTimer');
    var sessionSeconds = 0;
    function updateTimerDisplay() {
        var h = Math.floor(sessionSeconds / 3600);
        var m = Math.floor((sessionSeconds % 3600) / 60);
        var s = sessionSeconds % 60;
        if (timerEl) {
            timerEl.textContent = (h < 10 ? '0' : '') + h + 'h ' + (m < 10 ? '0' : '') + m + 'm ' + (s < 10 ? '0' : '') + s + 's';
        }
        sessionSeconds++;
    }
    updateTimerDisplay();
    setInterval(updateTimerDisplay, 1000);

    // ===== Heartbeat: har 30 sekundda serverga elapsed vaqt yuborish =====
    function sendHeartbeat() {
        var now = Math.floor(Date.now() / 1000);
        var elapsed = now - sessionStart - lastHeartbeat;
        if (elapsed <= 0) elapsed = 1;
        if (elapsed > 120) elapsed = 120;
        lastHeartbeat = now - sessionStart;

        // Vaqtni qo'shib qo'yish
        accumulated += elapsed;
        saveAccumulated(accumulated);

        // Yangi session boshlash
        sessionStart = now;
        saveSessionStart(sessionStart);

        // Serverga yuborish
        fetch('/uzdub/api/heartbeat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ elapsed: elapsed })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                updateOnlineCount(data.online_count || 0);
                if (data.online_time !== undefined) {
                    // Serverdagi real vaqtni localStorage ga sinxronlash
                    accumulated = data.online_time;
                    saveAccumulated(accumulated);
                    sessionStart = Math.floor(Date.now() / 1000);
                    saveSessionStart(sessionStart);
                    lastHeartbeat = 0;
                }
            }
        })
        .catch(function() {});
    }

    // Birinchi heartbeat 5 sekunddan keyin
    setTimeout(sendHeartbeat, 5000);
    setInterval(sendHeartbeat, HEARTBEAT_INTERVAL);

    // ===== Online count: har 12 sekundda yangilash =====
    var currentOnlineCount = 0;

    function updateOnlineCount(count) {
        currentOnlineCount = count;
        // Header'dagi online indikator
        var headerBadge = document.getElementById('onlineCountHeader');
        if (headerBadge) {
            headerBadge.textContent = '\uD83D\uDFE2 ' + count;
            headerBadge.title = count + ' onlayn';
        }
        // Chat sahifasidagi online indikator
        var chatBadge = document.getElementById('onlineCountChat');
        if (chatBadge) {
            chatBadge.textContent = '\uD83D\uDFE2 Onlayn: ' + count + ' ta';
        }
    }

    function fetchOnlineCount() {
        fetch('/uzdub/api/heartbeat.php', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok && data.online_count !== undefined) {
                updateOnlineCount(data.online_count);
            }
        })
        .catch(function() {});
    }

    // Birinchi fetch 3 sekunddan keyin
    setTimeout(fetchOnlineCount, 3000);
    setInterval(fetchOnlineCount, COUNT_INTERVAL);

    // ===== Sahifa yopilganda (beforeunload) — oxirgi vaqtni saqlash =====
    window.addEventListener('beforeunload', function() {
        var now = Math.floor(Date.now() / 1000);
        var elapsed = now - sessionStart;
        if (elapsed > 0 && elapsed < 3600) {
            accumulated += elapsed;
            saveAccumulated(accumulated);
        }
    });

    // ===== Visibility change — tabga qaytganida timer to'g'rilash =====
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            var now = Math.floor(Date.now() / 1000);
            sessionStart = now;
            saveSessionStart(sessionStart);
            lastHeartbeat = 0;
            fetchOnlineCount();
        }
    });

    // Global exposure
    window.UZDUBOnlineTracker = {
        getOnlineCount: function() { return currentOnlineCount; },
        getTotalTime: function() { return sessionSeconds; },
        refresh: function() { fetchOnlineCount(); sendHeartbeat(); }
    };
})();
