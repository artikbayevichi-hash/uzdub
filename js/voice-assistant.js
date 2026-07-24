/* ============================================================
   js/voice-assistant.js
   "UZDUB PLATFORM AI" — nomi bilan chaqiriladigan, buyruq bajaradigan va
   ovozda javob beradigan yordamchi (brauzerning Web Speech API'si
   asosida — Yandex Alisa uslubiga o'xshab)
   ============================================================ */

(function () {
    const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;
    const widget = document.getElementById('va-widget');
    const toggleBtn = document.getElementById('va-toggle');
    const statusEl = document.getElementById('va-status');
    const transcriptEl = document.getElementById('va-transcript');
    const csrfToken = window.vaCsrfToken || '';
    const WAKE_WORD = (window.vaWakeWord || 'uzdub platform ai').toLowerCase();
    const STORAGE_KEY = 'uzdub_voice_enabled';

    if (!widget || !toggleBtn) return;

    if (!SpeechRecognitionCtor) {
        statusEl.textContent = "Brauzeringiz ovozni tanib olishni qo'llamaydi";
        toggleBtn.style.opacity = '0.4';
        toggleBtn.style.cursor = 'not-allowed';
        return;
    }

    let armed = false;          // wake-word kutish rejimi yoqilganmi
    let listeningCmd = false;   // hozir buyruq eshitilayaptimi
    let recognizer = null;
    let restartTimer = null;

    function setStatus(text, transcript) {
        statusEl.textContent = text;
        transcriptEl.textContent = transcript || '';
        widget.classList.add('va-show-panel');
        clearTimeout(setStatus._t);
        setStatus._t = setTimeout(function () {
            if (!listeningCmd) widget.classList.remove('va-show-panel');
        }, 4000);
    }

    function normalize(str) {
        return (str || '').toLowerCase().replace(/[.,!?;:]/g, '').trim();
    }

    // Wake so'zni biroz moslashuvchan (fuzzy) tanish — talaffuz farqlariga tolerant
    function containsWakeWord(str) {
        const variants = [WAKE_WORD, WAKE_WORD.replace(' ', ''), 'уздаб аи', 'уздуб аи', WAKE_WORD.replace('ai', 'ay')];
        return variants.some(v => str.includes(v));
    }

    function speak(text) {
        if (!text || !window.speechSynthesis) return;
        window.speechSynthesis.cancel();
        const utter = new SpeechSynthesisUtterance(text);
        utter.lang = 'uz-UZ';
        utter.rate = 1.02;
        utter.onstart = function () { widget.classList.add('va-speaking'); };
        utter.onend = function () { widget.classList.remove('va-speaking'); };

        // Ba'zi brauzerlarda uz-UZ ovozi bo'lmasa, ru yoki mavjud ovozdan foydalanamiz
        const voices = window.speechSynthesis.getVoices();
        const match = voices.find(v => v.lang && v.lang.toLowerCase().startsWith('uz')) ||
                      voices.find(v => v.lang && v.lang.toLowerCase().startsWith('ru'));
        if (match) utter.voice = match;

        window.speechSynthesis.speak(utter);
    }

    function executeAction(res) {
        if (!res) return;
        setStatus(res.speak || '...', '');
        speak(res.speak || '');

        switch (res.action) {
            case 'navigate':
                setTimeout(function () { window.location.href = res.url; }, 550);
                break;
            case 'back':
                setTimeout(function () { window.history.back(); }, 300);
                break;
            case 'video':
                controlVideo(res.control);
                break;
            case 'speak':
            default:
                break;
        }
    }

    function controlVideo(control) {
        const video = document.querySelector('.player-wrap video');
        if (!video) return;
        if (control === 'pause') video.pause();
        else if (control === 'play') video.play().catch(function () {});
        else if (control === 'mute') video.muted = true;
        else if (control === 'unmute') video.muted = false;
        else if (control === 'fullscreen' && video.requestFullscreen) video.requestFullscreen();
    }

    function sendCommand(text) {
        fetch('/uzdub/api/voice-command.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text: text, csrf_token: csrfToken })
        })
        .then(function (r) { return r.json(); })
        .then(executeAction)
        .catch(function () {
            setStatus("Server bilan aloqa yo'q", '');
        });
    }

    /* ------------------------------------------------------------
       Doimiy (passiv) tinglash — faqat wake-so'zni tanib oladi
       ------------------------------------------------------------ */
    function startPassiveListening() {
        if (!armed || listeningCmd) return;
        try {
            recognizer = new SpeechRecognitionCtor();
            recognizer.lang = 'uz-UZ';
            recognizer.continuous = true;
            recognizer.interimResults = true;

            recognizer.onresult = function (e) {
                const last = e.results[e.results.length - 1];
                const said = normalize(last[0].transcript);
                if (containsWakeWord(said)) {
                    stopRecognizer();
                    beginCommandListening();
                }
            };
            recognizer.onerror = function () { /* jimgina qayta urinamiz */ };
            recognizer.onend = function () {
                if (armed && !listeningCmd) {
                    restartTimer = setTimeout(startPassiveListening, 400);
                }
            };
            recognizer.start();
        } catch (err) { /* mikrofon band yoki ruxsat yo'q */ }
    }

    function stopRecognizer() {
        clearTimeout(restartTimer);
        if (recognizer) {
            recognizer.onend = null;
            try { recognizer.stop(); } catch (err) {}
            recognizer = null;
        }
    }

    /* ------------------------------------------------------------
       Faol tinglash — wake-so'zdan keyin bitta buyruqni yozib oladi
       ------------------------------------------------------------ */
    function beginCommandListening() {
        listeningCmd = true;
        widget.classList.add('va-listening');
        setStatus('Tinglayapman...', '');

        const cmdRecognizer = new SpeechRecognitionCtor();
        cmdRecognizer.lang = 'uz-UZ';
        cmdRecognizer.continuous = false;
        cmdRecognizer.interimResults = true;

        let finalText = '';
        let silenceTimer = setTimeout(function () { cmdRecognizer.stop(); }, 6000);

        cmdRecognizer.onresult = function (e) {
            let interim = '';
            for (let i = 0; i < e.results.length; i++) {
                if (e.results[i].isFinal) finalText += e.results[i][0].transcript;
                else interim += e.results[i][0].transcript;
            }
            setStatus('Tinglayapman...', finalText + interim);
            clearTimeout(silenceTimer);
            silenceTimer = setTimeout(function () { cmdRecognizer.stop(); }, 1500);
        };

        cmdRecognizer.onerror = function () { finalText = finalText || ''; };

        cmdRecognizer.onend = function () {
            clearTimeout(silenceTimer);
            listeningCmd = false;
            widget.classList.remove('va-listening');
            const said = finalText.trim();
            if (said) {
                setStatus('Bajarilmoqda...', said);
                sendCommand(said);
            } else {
                setStatus('"' + (window.vaWakeWord || 'uzdub platform ai') + '" deb chaqiring', '');
            }
            startPassiveListening();
        };

        try { cmdRecognizer.start(); } catch (err) { listeningCmd = false; startPassiveListening(); }
    }

    /* ------------------------------------------------------------
       Yoqish / o'chirish
       ------------------------------------------------------------ */
    function enable() {
        armed = true;
        widget.classList.add('va-armed');
        localStorage.setItem(STORAGE_KEY, '1');
        setStatus('"' + (window.vaWakeWord || 'uzdub platform ai') + '" deb chaqiring', '');
        startPassiveListening();
    }

    function disable() {
        armed = false;
        widget.classList.remove('va-armed', 'va-listening');
        localStorage.setItem(STORAGE_KEY, '0');
        stopRecognizer();
        if (window.speechSynthesis) window.speechSynthesis.cancel();
        setStatus('Ovozli yordamchi o\u02bbchirilgan', '');
    }

    toggleBtn.addEventListener('click', function () {
        if (armed) disable(); else enable();
    });

    // Foydalanuvchi avval yoqqan bo'lsa, sahifa yuklanganda avtomatik tiklanadi
    // (mikrofon ruxsati brauzer tomonidan allaqachon berilgan bo'ladi)
    if (localStorage.getItem(STORAGE_KEY) === '1') {
        enable();
    }

    // Ovoz ro'yxati asinxron yuklanadi — oldindan tayyorlab qo'yamiz
    if (window.speechSynthesis) {
        window.speechSynthesis.onvoiceschanged = function () {};
    }
})();
