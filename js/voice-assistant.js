/* ============================================================
   js/voice-assistant.js - To'liq ishchi versiya
   ============================================================ */

(function () {
    const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;
    const widget = document.getElementById('va-widget');
    const toggleBtn = document.getElementById('va-toggle');
    const statusEl = document.getElementById('va-status');
    const transcriptEl = document.getElementById('va-transcript');
    const csrfToken = window.vaCsrfToken || (document.querySelector('input[name="csrf_token"]') ? document.querySelector('input[name="csrf_token"]').value : '');
    const WAKE_WORD = "uzdub ai";
    const STORAGE_KEY = 'uzdub_voice_enabled';

    if (!widget || !toggleBtn) return;

    if (!SpeechRecognitionCtor) {
        statusEl.textContent = "Ovozli qidiruv brauzeringizda ishlamaydi";
        return;
    }

    let armed = false;
    let listeningCmd = false;
    let recognizer = null;

    function speak(text) {
        if (!text || !window.speechSynthesis) return;
        window.speechSynthesis.cancel();
        const utter = new SpeechSynthesisUtterance(text);
        utter.lang = 'uz-UZ';
        
        // O'zbek tili bo'lmasa rus tilini ishlatish (ko'p brauzerlarda o'zbek tili yo'q)
        const voices = window.speechSynthesis.getVoices();
        const uzVoice = voices.find(v => v.lang.includes('uz'));
        const ruVoice = voices.find(v => v.lang.includes('ru'));
        if (uzVoice) utter.voice = uzVoice;
        else if (ruVoice) utter.voice = ruVoice;

        window.speechSynthesis.speak(utter);
    }

    function executeAction(res) {
        if (res.speak) speak(res.speak);
        if (res.action === 'navigate') {
            setTimeout(() => window.location.href = res.url, 1500);
        } else if (res.action === 'video') {
            const video = document.querySelector('video');
            if (video) {
                if (res.control === 'pause') video.pause();
                else if (res.control === 'play') video.play();
            }
        }
        statusEl.textContent = res.speak || "Bajarildi";
    }

    function sendCommand(text) {
        fetch('/uzdub/api/voice-command.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text: text, csrf_token: csrfToken })
        })
        .then(r => r.json())
        .then(executeAction)
        .catch(() => statusEl.textContent = "Xatolik yuz berdi");
    }

    function startListening() {
        if (recognizer) recognizer.stop();
        recognizer = new SpeechRecognitionCtor();
        recognizer.lang = 'uz-UZ';
        recognizer.continuous = false;
        recognizer.interimResults = true;

        recognizer.onstart = () => {
            listeningCmd = true;
            widget.classList.add('va-listening');
            statusEl.textContent = "Tinglayapman...";
        };

        recognizer.onresult = (e) => {
            const transcript = e.results[0][0].transcript;
            transcriptEl.textContent = transcript;
            if (e.results[0].isFinal) {
                sendCommand(transcript);
            }
        };

        recognizer.onend = () => {
            listeningCmd = false;
            widget.classList.remove('va-listening');
            if (armed) setTimeout(startListening, 1000); // Doimiy tinglash
        };

        recognizer.onerror = () => {
            if (armed) setTimeout(startListening, 2000);
        };

        recognizer.start();
    }

    toggleBtn.addEventListener('click', () => {
        armed = !armed;
        if (armed) {
            widget.classList.add('va-armed');
            localStorage.setItem(STORAGE_KEY, '1');
            startListening();
        } else {
            widget.classList.remove('va-armed');
            localStorage.setItem(STORAGE_KEY, '0');
            if (recognizer) recognizer.stop();
        }
    });

    if (localStorage.getItem(STORAGE_KEY) === '1') {
        armed = true;
        widget.classList.add('va-armed');
        setTimeout(startListening, 1000);
    }
})();
