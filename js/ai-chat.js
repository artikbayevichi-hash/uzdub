/* ============================================================
   js/ai-chat.js
   UZDUB AI — OVOZLI suhbat widgeti
   FAB tugma -> oynada: orb + mikrafon (STT) + ovozli javob (TTS)
   + jonli rejim + chat tarixi (Suhbatlar) + tavsiyalar
   ============================================================ */

window.ROOT_URL = window.ROOT_URL || '/uzdub';

document.addEventListener('DOMContentLoaded', function () {
  "use strict";

  function $(id) { return document.getElementById(id); }

  var fab = $('aic-fab'), panel = $('aic-panel');
  if (!fab || !panel) return;

  var closeBtn = $('aic-close'), backBtn = $('aic-back'), histBtn = $('aic-hist'),
      newChatBtn = $('aic-new-chat'), newChatBtnTop = $('aic-new-chat-btn'),
      chatList = $('aic-chat-list'), chatView = $('aic-chat-view'), listItems = $('aic-list-items'),
      log = $('aic-log'), input = $('aic-input'), sendBtn = $('aic-send');

  var orbWrap = $('orbWrap'), statusEl = $('vs-status'), interimEl = $('vs-interim'),
      micBtn = $('aic-mic'), voiceBtn = $('voiceBtn'), liveMode = $('liveMode'),
      voiceLangEl = $('voiceLang'), ttsVoiceEl = $('ttsVoice'), ttsEngineEl = $('ttsEngine');

  var csrfToken = window.aicCsrfToken || '';
  var isGuest = !window.aicIsLoggedIn;
  var lang = window.aicLang || 'uz';

  var SERVER_TTS = "http://127.0.0.1:11434/api/tts";

  /* ---------------- ko'p tilli matnlar ---------------- */
  var LANG_TEXTS = {
    uz: {
      greeting: "Assalomu alaykum! Men UZDUB AI yordamchisiman. Yonib turgan yumaloq orbni bosing va gapiring — kino, anime, multfilmlar va istalgan mavzuda erkin suhbatlashamiz.",
      prompts: [
        { label: "🔥 Eng ko'p ko'rilganlar", text: "Eng ko'p ko'rilgan filmlarni tavsiya qiling" },
        { label: '😂 Kulgili film', text: 'Kulgili film tavsiya qiling' },
        { label: '👻 Qo\u2018rqinchli anime', text: 'Qo\u2018rqinchli anime bormi?' },
        { label: '🧙 Sehrli / fantastik', text: 'Sehrli yoki fantastik anime tavsiya qiling' },
        { label: '💥 Jangari kino', text: 'Jangari kino tavsiya qiling' },
        { label: '🎭 Drama film', text: 'Drama film tavsiya qiling' }
      ]
    },
    ru: {
      greeting: 'Ассаламу алейкум! Я UZDUB AI помощник. Нажмите на светящийся круглый orb и говорите — фильмы, аниме, мультфильмы и любые темы. Голосовой помощник отвечает по-узбекски.',
      prompts: [
        { label: '🔥 Популярные', text: 'Посоветуйте популярные фильмы' },
        { label: '😂 Комедия', text: 'Посоветуйте комедию' },
        { label: '👻 Ужасы', text: 'Посоветуйте ужасы или хоррор аниме' },
        { label: '🧙 Фэнтези', text: 'Посоветуйте фэнтези аниме' },
        { label: '💥 Боевик', text: 'Посоветуйте боевик' },
        { label: '🎭 Драма', text: 'Посоветуйте драму' }
      ]
    },
    en: {
      greeting: 'Assalamu alaykum! I am UZDUB AI assistant. Tap the glowing orb and talk — movies, anime, cartoons or any topic. I reply in Uzbek voice.',
      prompts: [
        { label: '🔥 Trending', text: 'Recommend trending movies' },
        { label: '😂 Comedy', text: 'Recommend a comedy movie' },
        { label: '👻 Horror', text: 'Is there a horror anime?' },
        { label: '🧙 Fantasy', text: 'Recommend fantasy anime' },
        { label: '💥 Action', text: 'Recommend an action movie' },
        { label: '🎭 Drama', text: 'Recommend a drama' }
      ]
    }
  };
  var texts = LANG_TEXTS[lang] || LANG_TEXTS.uz;
  var GREETING = texts.greeting;
  var QUICK_PROMPTS = texts.prompts;

  /* ---------------- holatlar ---------------- */
  var greeted = false;
  var currentSessionId = null;
  var isSending = false;
  var pendingQueue = [];
  var sessionActive = false;
  var micDenied = false;
  var micWanted = false;
  var listening = false, thinking = false;
  var recog = null, silenceTimer = null, comfyTimer = null;

  var CONV_STATE = { LISTEN: "listening", THINK: "thinking", SPEAK: "speaking" };

  function setState(st) {
    if (!orbWrap) return;
    orbWrap.className = "orbWrap" + (st ? " state-" + st : "");
    var labels = {
      listening: "🎙️ Eshitmoqda...",
      thinking:  "✦ O'ylayapman...",
      speaking:  "🔊 Gapiraman..."
    };
    if (st && statusEl) statusEl.textContent = labels[st];
    else if (statusEl) statusEl.textContent = "Tayyor 😊";
    var oi = $('orbIcon');
    if (oi) oi.textContent = (st && { listening: "🎙️", thinking: "✦", speaking: "🔊" }[st]) || "🎤";
  }
  function setLabel(t) { if (statusEl) statusEl.textContent = t; }

  /* ---------------- ovoz sozlamalari (eslab qolinadi) ---------------- */
  var voiceOn = localStorage.getItem("ua_voice_on") !== "0";
  if (voiceBtn) voiceBtn.classList.toggle("on", voiceOn);
  try {
    if (localStorage.getItem("ua_voice_lang") && voiceLangEl) voiceLangEl.value = localStorage.getItem("ua_voice_lang");
    if (localStorage.getItem("ua_tts_voice") && ttsVoiceEl) ttsVoiceEl.value = localStorage.getItem("ua_tts_voice");
  } catch (e) {}
  if (voiceLangEl) voiceLangEl.addEventListener("change", function () { try { localStorage.setItem("ua_voice_lang", voiceLangEl.value); } catch (e) {} });
  if (ttsVoiceEl) ttsVoiceEl.addEventListener("change", function () { try { localStorage.setItem("ua_tts_voice", ttsVoiceEl.value); } catch (e) {} });
  if (voiceBtn) voiceBtn.addEventListener("click", function () {
    voiceOn = !voiceOn;
    try { localStorage.setItem("ua_voice_on", voiceOn ? "1" : "0"); } catch (e) {}
    voiceBtn.classList.toggle("on", voiceOn);
    if (!voiceOn) stopSpeaking();
  });

  /* ---------------- suhbat bubllari ---------------- */
  function addBubble(text, who) {
    if (!text) return null;
    var d = addMessage(text, who);
    return d;
  }

  var userScrolledUp = false;
  if (log) log.addEventListener('scroll', function () {
    var threshold = 60;
    userScrolledUp = log.scrollHeight - log.scrollTop - log.clientHeight > threshold;
  });
  function scrollToBottom(force) {
    if (!log) return;
    if (!force && userScrolledUp) return;
    log.scrollTop = log.scrollHeight;
    setTimeout(function () {
      var atBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 60;
      if (atBottom) userScrolledUp = false;
    }, 100);
  }

  function addMessage(text, from) {
    if (!log) return null;
    var wrap = document.createElement('div');
    wrap.className = 'aic-msg-wrap ' + (from === 'user' ? 'aic-msg-user' : 'aic-msg-bot');

    var avatar = document.createElement('div');
    avatar.className = 'aic-msg-avatar';
    if (from === 'user') {
      avatar.textContent = (window.aicUsername || 'U').charAt(0).toUpperCase();
    } else {
      avatar.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 1.9l2.3 6.9 6.9 2.3-6.9 2.3L12 20.3l-2.3-6.9-6.9-2.3 6.9-2.3L12 1.9z"/></svg>';
    }
    var div = document.createElement('div');
    div.className = 'aic-msg';
    if (from === 'bot') { div.innerHTML = formatBotMessage(text); }
    else { div.textContent = text; }
    wrap.appendChild(avatar);
    wrap.appendChild(div);
    log.appendChild(wrap);
    scrollToBottom(true);
    return div;
  }

  function formatBotMessage(text) {
    text = String(text == null ? '' : text);
    // 1) watch.php havolalarini butunlay olib tashlaymiz («Ko'rish» tugmasi yo'q)
    text = text.replace(/(?:https?:\/\/[^\s"'<>]+\/(?:uzdub\/)?|\/uzdub\/|\/)?watch\.php\?id=\d+/g, '');
    var html = escapeHtml(text);
    // 2) qolgan http/https havolalar — oddiy link (tugma emas)
    html = html.replace(/(https?:\/\/[^\s<]+)/g, function (match) {
      return '<a href="' + match + '" target="_blank" rel="noopener" class="aic-plain-link">' + match + '</a>';
    });
    // 3) **qalin** belgi — chiroyli formatlash (Gemini uslubi)
    html = html.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
    // 4) «# » bilan boshlangan sarlavhalar — qalin satr
    html = html.replace(/(^|\n)#{1,3}\s+([^\n]+)/g, '$1<strong>$2</strong>');
    return html;
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function formatDate(dateStr) {
    var date = new Date(dateStr);
    var now = new Date();
    var days = Math.floor((now - date) / (1000 * 60 * 60 * 24));
    if (days === 0) return 'Bugun';
    if (days === 1) return 'Kecha';
    if (days < 7) return days + ' kun oldin';
    return date.toLocaleDateString('uz-UZ');
  }

  /* ---------------- tezkor chiplar ---------------- */
  function renderQuickChips() {
    if (!log) return;
    var existing = document.getElementById('aic-quick-chips');
    if (existing) existing.remove();
    var wrap = document.createElement('div');
    wrap.className = 'aic-chips';
    wrap.id = 'aic-quick-chips';
    QUICK_PROMPTS.forEach(function (p) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'aic-chip';
      btn.textContent = p.label;
      btn.addEventListener('click', function () {
        removeQuickChips();
        sendText(p.text);
      });
      wrap.appendChild(btn);
    });
    log.appendChild(wrap);
    log.scrollTop = log.scrollHeight;
  }
  function removeQuickChips() {
    var el = document.getElementById('aic-quick-chips');
    if (el) el.remove();
  }

  /* ============================================================
     OYNA — OVOZLI KO'RINISH (default)
     ============================================================ */
  function showVoiceView() {
    chatList.style.display = 'none';
    chatView.style.display = 'flex';
    if (backBtn) backBtn.style.display = 'none';
    if (histBtn) histBtn.style.display = 'block';
    removeQuickChips();
    if (input) input.focus();
  }

  function openVoice() {
    showVoiceView();
    log.innerHTML = '';
    addMessage(GREETING, 'bot');
    if (isGuest) addGuestBanner();
    renderQuickChips();
    if (voiceOn) {
      setTimeout(function () {
        if (!isSending && !listening) speak(shortIntro(), function () {});
      }, 900);
    }
  }

  function shortIntro() {
    var s = GREETING.replace(/🎤/g, '').split('.')[0] + '.';
    return s;
  }

  function addGuestBanner() {
    var div = document.createElement('div');
    div.className = 'aic-guest-banner';
    div.innerHTML = 'Suhbat tarixini saqlash uchun <a href="' + ROOT_URL + '/auth/register.php">ro\u2018yxatdan o\u2018ting</a>';
    log.appendChild(div);
  }

  /* ---------------- sessiya boshqaruvi (tarix bilan) ---------------- */
  function startSession() {
    return new Promise(function (resolve) {
      if (isGuest) { currentSessionId = Date.now(); resolve(); return; }
      fetch(ROOT_URL + '/api/chat/list.php')
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.sessions && data.sessions.length) {
            // Eng so'nggi suhbat davom etadi (ochilishda tarix yuklanmaydi —
            // greeting + chiplar ko'rsatiladi; tarix «Suhbatlar»da saqlanadi)
            currentSessionId = data.sessions[0].id;
          } else {
            createNewSessionFromApi().then(function (id) { currentSessionId = id; });
          }
          resolve();
        })
        .catch(function () {
          currentSessionId = Date.now();
          resolve();
        });
    });
  }

  function createNewSessionFromApi() {
    return fetch(ROOT_URL + '/api/chat/create.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'title=&csrf_token=' + encodeURIComponent(csrfToken)
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (!data.error && data.session_id) return data.session_id;
      return Date.now();
    }).catch(function () { return Date.now(); });
  }

  function loadHistory(sessionId, silent) {
    fetch(ROOT_URL + '/api/chat/history.php?session_id=' + sessionId)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.error && data.messages && data.messages.length > 0) {
          log.innerHTML = '';
          data.messages.forEach(function (msg) {
            addMessage(msg.message, msg.role);
          });
        } else {
          if (!silent) { addMessage(GREETING, 'bot'); renderQuickChips(); }
        }
      })
      .catch(function () {});
  }

  /* ---------------- suhbatlar ro'yxati ---------------- */
  function showChatList() {
    stopListening(); stopSpeaking(); sessionActive = false; micWanted = false;
    chatView.style.display = 'none';
    chatList.style.display = 'flex';
    if (backBtn) backBtn.style.display = 'block';
    if (histBtn) histBtn.style.display = 'none';
    loadChatList();
  }

  function loadChatList() {
    listItems.innerHTML = '<div class="aic-loading">Yuklanmoqda...</div>';
    fetch(ROOT_URL + '/api/chat/list.php')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) {
          listItems.innerHTML = '<div class="aic-empty">Xatolik: ' + escapeHtml(data.error) + '</div>';
          return;
        }
        if (!data.sessions || data.sessions.length === 0) {
          listItems.innerHTML = '<div class="aic-empty">Hali chatlar yo\'q. Yangi chat yarating!</div>';
          return;
        }
        listItems.innerHTML = '';
        data.sessions.forEach(function (session) {
          var div = document.createElement('div');
          div.className = 'aic-list-item';
          div.dataset.id = session.id;
          div.innerHTML = '<div style="flex:1;min-width:0;">' +
            '<div class="aic-list-item-title">' + escapeHtml(session.title) + '</div>' +
            '<div class="aic-list-item-date">' + formatDate(session.updated_at) + '</div>' +
            '</div>' +
            '<button class="aic-list-item-delete" data-id="' + session.id + '" title="O\'chirish">&times;</button>';
          div.addEventListener('click', function (e) {
            if (e.target.classList.contains('aic-list-item-delete')) return;
            currentSessionId = session.id;
            showVoiceView();
            log.innerHTML = '';
            loadHistory(session.id, false);
          });
          div.querySelector('.aic-list-item-delete').addEventListener('click', function (e) {
            e.stopPropagation();
            deleteChat(session.id);
          });
          listItems.appendChild(div);
        });
      })
      .catch(function (err) {
        listItems.innerHTML = '<div class="aic-empty">Xatolik: ' + escapeHtml(err.message) + '</div>';
      });
  }

  function deleteChat(sessionId) {
    if (!confirm('Chatni o\'chirishni tasdiqlaysizmi?')) return;
    fetch(ROOT_URL + '/api/chat/delete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'session_id=' + sessionId + '&csrf_token=' + encodeURIComponent(csrfToken)
    }).then(function (r) { return r.json(); }).then(function () {
      if (currentSessionId == sessionId) currentSessionId = null;
      loadChatList();
    }).catch(function () {});
  }

  function createNewChat() {
    stopListening(); stopSpeaking(); sessionActive = false;
    createNewSessionFromApi().then(function (id) {
      currentSessionId = id;
      showVoiceView();
      log.innerHTML = '';
      addMessage(GREETING, 'bot');
      renderQuickChips();
      if (voiceOn) setTimeout(function () { speak(shortIntro(), function () {}); }, 800);
    });
  }

  function updateSessionTitle(firstMessage) {
    if (isGuest || !currentSessionId || String(currentSessionId).indexOf('g-') === 0) return;
    var title = firstMessage.substring(0, 30) + (firstMessage.length > 30 ? '...' : '');
    fetch(ROOT_URL + '/api/chat/update_title.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'session_id=' + encodeURIComponent(currentSessionId) + '&title=' + encodeURIComponent(title) + '&csrf_token=' + encodeURIComponent(csrfToken)
    }).catch(function () {});
  }

  /* ============================================================
     OVOZLI KIRISH (STT) — jonli rejim
     ============================================================ */
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;

  var finalBuf = "", interimBuf = "";

  function fixTranscript(t) {
    if (!t) return t;
    var s = t.trim().replace(/\s+/g, " ").toLowerCase();
    var greets = {
      "saylov": "salom", "saylev": "salom", "sallom": "salom", "salim": "salom",
      "salam": "salom", "assalom": "assalomu alaykum",
      "saylov alaykum": "assalomu alaykum", "saylovu alaykum": "assalomu alaykum",
      "salom alaykum": "assalomu alaykum", "assalomu alekum": "assalomu alaykum",
      "assalomu alayko'm": "assalomu alaykum", "assalom aleykum": "assalomu alaykum",
      "assalomu aleykum": "assalomu alaykum"
    };
    if (greets[s]) return greets[s];
    if (s.slice(0, 5) === "salom" || s.slice(0, 8) === "assalomu") return greets[s] || s;
    return t;
  }

  var WORD_FIX = {
    "kina": "kino", "kinani": "kinoni", "filim": "film", "filimni": "filmni",
    "multfilim": "multfilm", "multfilimni": "multfilmni", "rafmat": "rahmat",
    "yordamla": "yordam", "tavsiya et": "tavsiya qil"
  };
  var FILLERS = /\s+(ee|aa|um|hm|hmm|eh|ah|oh|mm|mma)\s+/g;

  function cleanTranscript(t) {
    if (!t) return t;
    var s = " " + t.replace(/\s+/g, " ").trim().toLowerCase() + " ";
    s = s.replace(FILLERS, " ");
    s = s.replace(/\b(\w{3,})\s+\1\b/g, "$1");
    var words = s.trim().split(" ");
    for (var i = 0; i < words.length; i++) {
      if (WORD_FIX[words[i]]) words[i] = WORD_FIX[words[i]];
    }
    return fixTranscript(words.join(" "));
  }

  function ensureMicPermission(cb) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { cb(); return; }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
      micGranted = true;
      stream.getTracks().forEach(function (t) { t.stop(); });
      cb();
    }).catch(function (err) {
      micDenied = true;
      sessionActive = false;
      setLabel("Tayyor 😊");
      var name = (err && err.name) || "xato";
      if (navigator.permissions && navigator.permissions.query) {
        try {
          navigator.permissions.query({ name: 'microphone' }).then(function (status) {
            if (status.state === 'denied') renderMicHelpCard(name, true);
            else if (status.state === 'prompt') renderMicHelpCard(name, false);
            else addBubble("Mikrofon olinmadi (" + name + "). 🎤 tugmasiga yana bir bor bosing.", "sys");
          });
          return;
        } catch (e) {}
      }
      renderMicHelpCard(name, false);
    });
  }

  function renderMicHelpCard(name, blocked) {
    if (!log) return;
    var card = document.createElement('div');
    card.className = 'aic-mic-help';
    card.innerHTML =
      '<div class="aic-mic-help-title">🎤 Mikrofonga ruxsat kerak</div>' +
      '<div class="aic-mic-help-text">Adres satridagi <b>🔒</b> belgini bosing → <b>Sayt sozlamalari</b> → <b>Mikrofon</b> → <b>Ruxsat berish</b>' +
      (blocked ? '.<br>Brauzer hozircha mikrofonga ruxsatni <b>bloklagan</b> (' + escapeHtml(name) + ') — uni yuqoridagi kabi oching.' : '.') +
      '</div>' +
      '<div class="aic-mic-help-btns">' +
        '<button type="button" class="aic-mic-help-btn primary" data-act="retry">🔄 Qayta urinish</button>' +
        '<button type="button" class="aic-mic-help-btn" data-act="reload">🔁 Sahifani yangilash (F5)</button>' +
      '</div>';
    log.appendChild(card);
    log.scrollTop = log.scrollHeight;
    var retry = card.querySelector('[data-act="retry"]');
    if (retry) retry.addEventListener('click', function () {
      card.remove();
      sessionActive = true;
      micWanted = true;
      ensureMicPermission(startListening);
    });
    var reload = card.querySelector('[data-act="reload"]');
    if (reload) reload.addEventListener('click', function () {
      try { location.reload(); } catch (e) {}
    });
  }

  function watchMicPermission() {
    if (!navigator.permissions || !navigator.permissions.query) return;
    try {
      navigator.permissions.query({ name: 'microphone' }).then(function (status) {
        status.addEventListener('change', function () {
          if (status.state === 'granted') {
            micDenied = false;
            setLabel("Ruxsat berildi ✅");
            addBubble("Mikrofonga ruxsat berildi! 🎉 " +
              (micWanted ? "Eshitishni boshlayapman..." : "Endi 🎤 tugmasini bosing va gapiring."), "sys");
            if (micWanted && !listening) {
              sessionActive = true;
              ensureMicPermission(startListening);
            }
          } else if (status.state === 'denied') {
            micDenied = true;
          }
        });
      }).catch(function () {});
    } catch (e) {}
  }

  function startListening() {
    if (listening || !SR || !sessionActive) return;
    if (!window.isSecureContext) {
      addBubble("Mikrofon ishlamaydi: sahifani http://localhost orqali oching.", "sys");
      sessionActive = false; return;
    }
    listening = true;
    setState(CONV_STATE.LISTEN);
    if (micBtn) micBtn.classList.add("listening");

    var r = new SR();
    r.lang = voiceLangEl ? voiceLangEl.value || "uz-UZ" : "uz-UZ";
    r.continuous = true;
    r.interimResults = true;

    var SILENCE_MS = 2600, SILENCE_EXTRA = 1800;
    var finalSegments = 0;

    function rearmSilence() {
      clearTimeout(silenceTimer);
      clearTimeout(comfyTimer);
      silenceTimer = setTimeout(function () { decideStop(); }, SILENCE_MS);
    }
    function stopSilence() {
      clearTimeout(silenceTimer);
      clearTimeout(comfyTimer);
    }
    function decideStop() {
      var said = (finalBuf + interimBuf).replace(/\s+/g, " ").trim();
      var completed = /[.!?…؟]$/.test(said) || finalSegments >= 1;
      if (completed) { try { r.stop(); } catch (e) {} return; }
      clearTimeout(comfyTimer);
      comfyTimer = setTimeout(function () { try { r.stop(); } catch (e) {} }, SILENCE_EXTRA);
    }

    r.onstart = function () {
      finalBuf = ""; interimBuf = ""; finalSegments = 0;
      rearmSilence();
    };

    r.onresult = function (e) {
      interimBuf = "";
      for (var i = e.resultIndex; i < e.results.length; i++) {
        var res = e.results[i];
        var tr = fixTranscript(res[0].transcript);
        if (res.isFinal) { finalSegments++; finalBuf += tr; }
        else interimBuf += tr;
      }
      var shown = (finalBuf + interimBuf).replace(/\s+/g, " ").trim();
      if (interimEl) interimEl.textContent = shown;
      if (input) input.value = shown;
      if (shown) rearmSilence();
    };

    r.onerror = function (ev) {
      if (ev.error === "no-speech" || ev.error === "aborted") return;
      var msg = {
        "not-allowed": "Mikrofonga ruxsat berilmadi. 🔒/ℹ️ belgidan ruxsat bering va sahifani yangilang (F5).",
        "service-not-allowed": "Brauzer mikrofonga ruxsat bermayapti — sozlamalardan tekshiring.",
        "language-not-supported": "Tanlangan til qo'llab-quvvatlanmaydi — yuqorida boshqa til tanlang.",
        "network": "Ovozli tanishuv xizmatiga ulanish yo'q — internetni tekshiring.",
        "audio-capture": "Mikrofon topilmadi yoki ulanganini tekshiring."
      }[ev.error];
      if (msg) { addBubble(msg, "sys"); micDenied = true; sessionActive = false; setLabel("Tayyor 😊"); if (micBtn) micBtn.classList.remove("listening"); }
    };

    r.onend = function () {
      stopSilence();
      listening = false;
      if (micBtn) micBtn.classList.remove("listening");
      if (!sessionActive) { setState(""); setLabel("To'xtatildi"); return; }
      var said = cleanTranscript(finalBuf || (input ? input.value : "") || "");
      if (interimEl) interimEl.textContent = "";
      if (said) {
        if (input) input.value = "";
        addBubble(said, "user");
        sendToAI(said);
      } else if (sessionActive && !micDenied) {
        setState("");
        setLabel("...");
        setTimeout(startListening, 600);
      }
    };

    recog = r;
    try { r.start(); } catch (e) {
      listening = false;
      if (micBtn) micBtn.classList.remove("listening");
      setState("");
    }
  }

  function stopListening() {
    clearTimeout(silenceTimer);
    clearTimeout(comfyTimer);
    if (recog) { try { recog.stop(); } catch (e) {} }
    recog = null; listening = false;
    if (micBtn) micBtn.classList.remove("listening");
    if (interimEl) interimEl.textContent = "";
  }

  /* ============================================================
     YUBORISH (stream + tarix + tavsiyalar)
     ============================================================ */
  var QUICK_REPLIES = {
    uz: {
      greetings: { re: /^(assalomu?\s*alaykum|salom|selom|salomu|salam|hi|hey|hello|privet|salomlar)$/i, items: [
        "Assalomu alaykum! UZDUB AI yordamchisiman. Kino, anime yoki multfilm haqida nima bilmoqchisiz? 🎬",
        "Salom! Qanday yordam bera olaman? Saytimizda ko'plab kino va anime mavjud! 😊",
        "Alaykum assalom! Kino yoki anime tavsiya kerakmi? Menga yozing! 🎌"
      ]},
      how: { re: /^(qalaysan|qalay|yaxshimisan|yaxshilik|qilyapsan|nima\s*qilyapsan)$/i, items: [
        "Yaxshiman, rahmat! 😊 Siz nima qilyapsiz? Kino yoki anime kerakmi?",
        "Zo'r! UZDUB da yangi kontentlar qo'shildi, ko'rdingizmi? 🎬"
      ]},
      thanks: { re: /^(rahmat|thanks|thank\s*you|tashakkur|minnatdorman|katta\s*rahmat)$/i, items: [
        "Arzimaydi! Agar boshqa savol bo'lsa — bemalol so'rang 😊",
        "Ko'mak berishdan xursandmiz! Yana nima bilmoqchisiz? 🔥"
      ]},
      ok: { re: /^(ok|okay|tushundim|mayli|yaxshi|ha|yo'?q|yoq|haa)$/i, items: [
        "Tushundim! Yana nima kerak? 😊",
        "Mayli! Boshqa savol bo'lsa, yozing 👍"
      ]}
    },
    ru: {
      greetings: { re: /^(привет|здравствуй|салам|хай|hello)$/i, items: [
        "Привет! Я AI-помощник UZDUB. Чем могу помочь? 🎬",
        "Здравствуйте! Ищете фильм или аниме? Спрашивайте! 😊"
      ]},
      thanks: { re: /^(спасибо|благодарю|сенкс|thanks)$/i, items: [
        "Пожалуйста! Если есть ещё вопросы — спрашивайте 😊",
        "Рад помочь! Что ещё хотите узнать? 🎬"
      ]}
    },
    en: {
      greetings: { re: /^(hi|hey|hello|hola|sup|yo|howdy)$/i, items: [
        "Hey! I'm UZDUB AI assistant. How can I help? 🎬",
        "Hi there! Looking for a movie or anime? Ask away! 😊"
      ]},
      thanks: { re: /^(thanks|thank\s*you|thx|cheers|appreciate)$/i, items: [
        "You're welcome! Feel free to ask anything else 😊",
        "Happy to help! Need more movie recommendations? 🎬"
      ]}
    }
  };

  function getQuickReply(text) {
    var t = text.trim().toLowerCase();
    var langData = QUICK_REPLIES[lang] || QUICK_REPLIES.uz;
    if (langData.greetings.re.test(t)) return pick(langData.greetings.items);
    if (langData.how && langData.how.re.test(t)) return pick(langData.how.items);
    if (langData.thanks.re.test(t)) return pick(langData.thanks.items);
    if (langData.ok && langData.ok.re.test(t)) return pick(langData.ok.items);
    return null;
  }
  function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

  function sendText(text) {
    text = (text || '').trim();
    if (!text || isSending) return;
    if (input) input.value = '';
    removeQuickChips();
    if (sessionActive) { sessionActive = false; micDenied = false; micWanted = false; stopListening(); }
    addMessage(text, 'user');

    var quick = getQuickReply(text);
    if (quick) {
      setTimeout(function () {
        addMessage(quick, 'bot');
        if (voiceOn) speak(quick, function () { afterSpeech(); });
        else afterSpeech();
      }, 300 + Math.random() * 400);
      return;
    }
    sendToAI(text);
  }

  function sendToAI(userText) {
    if (thinking) return;
    if (!isGuest && !currentSessionId) {
      // Sessiya hali o'rnatilmagan (tez yuborilganda) — avval o'rnatamiz
      startSession().then(function () { sendToAI(userText); });
      return;
    }
    thinking = true;
    isSending = true;
    setState(CONV_STATE.THINK);
    voiceFeedAborted = false;   // yangi savol → ovoz oqimi yana faol
    spokenUpTo = 0;

    fetch(ROOT_URL + '/api/stream.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: userText, session_id: currentSessionId, csrf_token: csrfToken, lang: lang })
    })
      .then(function (response) {
        if (!response.body || !response.body.getReader) {
          return response.json().then(function (data) {
            thinking = false; isSending = false; setState("");
            addBubble(data.reply || data.error || "Javob olinmadi.", 'bot');
            if (data.recommendations) renderRecommendations(data.recommendations);
            finishTurn(userText);
          });
        }
        var reader = response.body.getReader();
        var decoder = new TextDecoder();
        var buffer = '';
        var accumulated = '';
        var gotFirstToken = false;

        function pump() {
          return reader.read().then(function (result) {
            if (result.done) {
              thinking = false; isSending = false; setState("");
              if (!gotFirstToken && !accumulated) { addBubble("Javob olinmadi.", 'bot'); }
              if (voiceOn && gotFirstToken && !voiceFeedAborted) {
                // Oxirgi tugallanmagan qismni ham aytib, keyin tinglaymiz
                speakAppend(accumulated.slice(spokenUpTo), function () { afterSpeech(); });
              }
              finishTurn(userText, true);
              return;
            }
            buffer += decoder.decode(result.value, { stream: true });
            var idx;
            while ((idx = buffer.indexOf('\n\n')) !== -1) {
              var rawEvent = buffer.slice(0, idx);
              buffer = buffer.slice(idx + 2);
              var line = rawEvent.replace(/^data:\s*/, '').trim();
              if (!line) continue;
              var obj;
              try { obj = JSON.parse(line); } catch (e) { continue; }
              if (obj.busy) {
                thinking = false; isSending = false; setState("");
                addBubble(obj.msg || 'AI hozir band, biroz kuting...', 'sys');
                finishTurn(userText);
                return;
              }
              if (obj.error) {
                thinking = false; isSending = false; setState("");
                addBubble(obj.error, 'sys');
                gotFirstToken = true;
                continue;
              }
              if (obj.delta) {
                if (!gotFirstToken) gotFirstToken = true;
                accumulated += obj.delta;
                addBubblePreview(accumulated);
                voiceFeed(accumulated);   // tayyor gap → darhol aytamiz
              }
              if (obj.recommendations) {
                renderRecommendations(obj.recommendations);
              }
            }
            return pump();
          });
        }
        return pump();
      })
      .catch(function (err) {
        thinking = false; isSending = false; setState("");
        try { console.error('AIC_FETCH_ERR', err && err.stack ? err.stack : String(err)); } catch (e2) {}
        addBubble("AI bilan bog'lanishda muammo yuz berdi. Qaytadan urinib ko'ring.", 'sys');
        finishTurn(userText);
      });
  }

  var lastBotDiv = null;
  function addBubblePreview(text) {
    if (text === null || text === undefined) text = '';
    text = String(text);
    if (!lastBotDiv) {
      lastBotDiv = addStreamingBotBubble();
    }
    if (lastBotDiv) {
      lastBotDiv.innerHTML = formatBotMessage(text);
      if (log) log.scrollTop = log.scrollHeight;
    }
  }

  function addStreamingBotBubble() {
    var wrap = document.createElement('div');
    wrap.className = 'aic-msg-wrap aic-msg-bot';
    var avatar = document.createElement('div');
    avatar.className = 'aic-msg-avatar';
    avatar.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 1.9l2.3 6.9 6.9 2.3-6.9 2.3L12 20.3l-2.3-6.9-6.9-2.3 6.9-2.3L12 1.9z"/></svg>';
    var div = document.createElement('div');
    div.className = 'aic-msg';
    wrap.appendChild(avatar);
    wrap.appendChild(div);
    log.appendChild(wrap);
    scrollToBottom(true);
    return div;
  }

  function finishTurn(userText, streamed) {
    var answer = lastBotDiv ? lastBotDiv.textContent : '';
    lastBotDiv = null;
    if (streamed) {
      // Streaming ovozi o'zi davom etadi — afterSpeech qolgan gap tugagach keladi
      if (!(voiceOn && answer && answer.trim() && !voiceFeedAborted)) afterSpeech();
    } else if (answer && answer.trim()) {
      if (voiceOn) speak(answer, function () { afterSpeech(); });
      else afterSpeech();
    } else {
      afterSpeech();
    }
    if (!isGuest && currentSessionId && String(currentSessionId).indexOf('g-') !== 0) {
      updateSessionTitle(userText);
    }
    if (pendingQueue.length) {
      var next = pendingQueue.shift();
      addMessage(next, 'user');
      sendToAI(next);
    } else {
      isSending = false;
    }
  }

  /* ============================================================
     OVOZLI JAVOB (TTS) — Edge neyron o'zbek ovozi
     ============================================================ */
  var ttsStop = false, ttsAudio = null;
  var voiceQueue = [], voiceBusy = false, voiceFinish = null;
  var voiceFeedAborted = false, spokenUpTo = 0;

  /* ============================================================
     BARG-IN: bot gapirganda foydalanuvchi gapira boshlasa —
     to'xtab, TINGLAY boshlaydi (Gemini jonli muloqoti kabi)
     ============================================================ */
  var micStream = null, micCtx = null, micAnalyser = null,
      interruptRAF = 0, speechSince = 0, micGranted = false;

  function startInterruptWatch() {
    if (ttsStop || interruptRAF || !micGranted) return;
    if (!liveMode || !liveMode.checked) return;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return;
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
      if (ttsStop) { try { stream.getTracks().forEach(function (t) { t.stop(); }); } catch (e) {} return; }
      micStream = stream;
      try {
        micCtx = new (window.AudioContext || window.webkitAudioContext)();
        micAnalyser = micCtx.createAnalyser();
        micAnalyser.fftSize = 1024;
        var src = micCtx.createMediaStreamSource(stream);
        src.connect(micAnalyser);
      } catch (e) { stopInterruptWatch(); return; }
      var data = new Uint8Array(micAnalyser.fftSize);
      var chunkSince = Date.now();
      function tick() {
        if (ttsStop || !micAnalyser) { stopInterruptWatch(); return; }
        micAnalyser.getByteTimeDomainData(data);
        var sum = 0;
        for (var i = 0; i < data.length; i++) { var v = (data[i] - 128) / 128; sum += v * v; }
        var rms = Math.sqrt(sum / data.length);
        if (rms > 0.07) {
          if (!speechSince) speechSince = Date.now();
          else if (Date.now() - speechSince > 420 && Date.now() - chunkSince > 400) {
            // Foydalanuvchi gapirdi → to'xtab, tinglashni boshlaymiz
            stopSpeaking();
            voiceFeedAborted = true;   // streaming ovoz endi qayta aytilmaydi
            stopInterruptWatch();
            sessionActive = true; micDenied = false; micWanted = false;
            setLabel("🎙️ Eshitmoqda...");
            setTimeout(function () { startListening(); }, 250);
            return;
          }
        } else { speechSince = 0; }
        interruptRAF = requestAnimationFrame(tick);
      }
      tick();
    }).catch(function () {});
  }

  function stopInterruptWatch() {
    if (interruptRAF) cancelAnimationFrame(interruptRAF);
    interruptRAF = 0; speechSince = 0;
    if (micCtx) { try { micCtx.close(); } catch (e) {} micCtx = null; }
    micAnalyser = null;
    if (micStream) {
      try { micStream.getTracks().forEach(function (t) { t.stop(); }); } catch (e) {}
      micStream = null;
    }
  }

  function setEngineLabel(txt) { if (ttsEngineEl) ttsEngineEl.textContent = txt ? "· " + txt : ""; }

  function splitChunks(text, max) {
    var out = [], cur = "";
    for (var i = 0; i < text.length; i++) {
      cur += text[i];
      if (cur.length >= max || isSentenceEnd(text, i)) {
        var p = cur.trim(); if (p) out.push(p); cur = "";
      }
    }
    if (cur.trim()) out.push(cur.trim());
    if (!out.length && text.trim()) out.push(text.trim());
    return out;
  }

  /* Gap oxiri belgisi (8.7 kabi kasr nuqtasida bo'lmaydi) */
  function isSentenceEnd(str, i) {
    var c = str[i];
    if (c === '…' || c === '\n') return true;
    if (c !== '.' && c !== '!' && c !== '?') return false;
    if (c === '.') {
      var prev = str[i - 1], next = str[i + 1];
      if (prev >= '0' && prev <= '9' && next >= '0' && next <= '9') return false; // 8.7
      if (prev === ' ' && next === ' ') return false;                            // "U. Z." kabi emas
    }
    return true;
  }

  function prepareSpeech(text) {
    var clean = String(text || "")
      .replace(/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE0F}]/gu, " ")
      .replace(/[•·]/g, " ")                                  // ro'yxat belgilari → tinimsiz o'qish
      .replace(/\[[^\]]*\](?=\s|$)/g, " ")                    // [Drama, Triller] → o'qilmaydi
      .replace(/\(([^)]{1,80})\)/g, function (m, g) {         // (2022, Kino ★6.6) → o'qilmaydi
        return /[0-9]{4}/.test(g) || (g.split(',').length >= 2 && /[a-zA-Zа-яА-Я]/.test(g)) ? " " : m;
      })
      .replace(/[#*_`>|]/g, " ")
      .replace(/\s+/g, " ")
      .trim();
    return clean;
  }

  function enqueueSpeech(text, onDone) {
    ttsStop = false;
    if (onDone) voiceFinish = onDone;
    var clean = prepareSpeech(text);
    if (!clean) { pumpVoice(); return; }
    var chunks = splitChunks(clean, 170);
    for (var i = 0; i < chunks.length; i++) {
      if (chunks[i].replace(/\s+/g, '').length >= 10) voiceQueue.push(chunks[i]);
    }
    pumpVoice();
  }

  function speakAppend(text, onDone) {
    enqueueSpeech(text, onDone);
  }

  function pumpVoice() {
    if (ttsStop) { voiceQueue = []; return; }
    if (voiceBusy) return;
    if (!voiceQueue.length) {
      var f = voiceFinish; voiceFinish = null;
      if (f) { f(); return; }
      setState("");
      return;
    }
    voiceBusy = true;
    var chunk = voiceQueue.shift();
    playChunk(chunk, function () {
      voiceBusy = false;
      if (ttsStop) return;
      pumpVoice();
    });
  }

  function playChunk(chunk, onDone) {
    setState(CONV_STATE.SPEAK);
    var voice = (ttsVoiceEl && ttsVoiceEl.value) || "uz-UZ-MadinaNeural";
    setEngineLabel(voice === "uz-UZ-SardorNeural" ? "Sardor (o'zbek)" : "Madina (o'zbek)");
    startInterruptWatch();
    prefetchVoice(voice, voiceQueue[0]);   // navbatdagi parchani oldindan yuklab qo'yamiz
    var a = new Audio();
    ttsAudio = a;
    a.src = SERVER_TTS + "?voice=" + encodeURIComponent(voice) + "&text=" + encodeURIComponent(chunk);
    a.onended = function () { onDone(); };
    a.onerror = function () { fallbackChunk(chunk, onDone); };
    a.play().catch(function () { fallbackChunk(chunk, onDone); });
  }

  function prefetchVoice(voice, chunk) {
    if (!chunk) return;
    try {
      fetch(SERVER_TTS + "?voice=" + encodeURIComponent(voice) + "&text=" + encodeURIComponent(chunk))
        .then(function () {}).catch(function () {});
    } catch (e) {}
  }

  function fallbackChunk(chunk, onDone) {
    if (ttsStop || !window.speechSynthesis) { onDone(); return; }
    var u;
    try { u = new SpeechSynthesisUtterance(chunk); } catch (e) { onDone(); return; }
    var v = pickVoice() || null;
    if (v) { u.voice = v; u.lang = v.lang; }
    u.rate = 1.0; u.pitch = 1.0; u.volume = 1.0;
    setEngineLabel("Tizim ovozi");
    u.onend = function () { onDone(); };
    u.onerror = function () { onDone(); };
    try { speechSynthesis.speak(u); } catch (e) { onDone(); }
  }

  function pickVoice() {
    var pref = ["uz", "tr", "ru", "en"];
    var voices = window.speechSynthesis ? speechSynthesis.getVoices() : [];
    for (var i = 0; i < pref.length; i++)
      for (var j = 0; j < voices.length; j++)
        if (voices[j].lang && voices[j].lang.toLowerCase().indexOf(pref[i] + "-") === 0) return voices[j];
    return voices[0] || null;
  }

  function speak(text, onDone) {
    // Yangi javob — eski tovushni to'xtatib, yangisini navbat bilan aytadi
    stopSpeaking(false);
    enqueueSpeech(text, onDone);
  }

  function stopSpeaking(silent) {
    stopInterruptWatch();
    ttsStop = true;
    voiceQueue = [];
    voiceBusy = false;
    if (ttsAudio) { try { ttsAudio.pause(); } catch (e) {} ttsAudio = null; }
    if (window.speechSynthesis) speechSynthesis.cancel();
    if (!silent) setState("");
  }

  /* Streaming paytida: tayyor bo'lgan gaplarni darhol ovozga beramiz */
  function voiceFeed(txt) {
    if (!txt) return;
    if (!voiceOn || voiceFeedAborted) { spokenUpTo = txt.length; return; }
    var i = spokenUpTo;
    while (i < txt.length) {
      var m = -1;
      for (var j = i; j < txt.length; j++) {
        if (isSentenceEnd(txt, j)) { m = j; break; }
      }
      if (m === -1) break;
      var end = m;
      var s = txt.slice(i, end + 1).trim();
      if (s && prepareSpeech(s).replace(/\s+/g, '').length >= 10) speakAppend(s);
      i = end + 1;
    }
    spokenUpTo = i;
  }

  function afterSpeech() {
    if (sessionActive && liveMode && liveMode.checked && !micDenied) {
      setLabel("...");
      setState("");
      setTimeout(startListening, 450);
    } else {
      setLabel("Tayyor 😊");
      setState("");
    }
  }

  function renderRecommendations(list) {
    if (!list || !list.length || !log) return;
    var wrap = document.createElement('div');
    wrap.className = 'aic-reco-list';
    list.forEach(function (item) {
      var a = document.createElement('a');
      a.className = 'aic-reco-card';
      a.href = item.url;
      var img = document.createElement('img');
      img.className = 'aic-reco-poster';
      img.loading = 'lazy';
      img.src = item.poster || 'https://via.placeholder.com/84x120/121a2b/2196f3?text=' + encodeURIComponent(item.title.slice(0, 1));
      img.alt = item.title;
      var info = document.createElement('div');
      info.className = 'aic-reco-info';
      var title = document.createElement('div');
      title.className = 'aic-reco-title';
      title.textContent = item.title;
      var meta = document.createElement('div');
      meta.className = 'aic-reco-meta';
      var parts = [];
      if (item.category) parts.push(item.category);
      if (item.year) parts.push(item.year);
      if (item.rating) parts.push('\u2605 ' + item.rating);
      if (item.status) parts.push(item.status);
      if (item.duration) parts.push(item.duration);
      meta.textContent = parts.join(' \u00b7 ');
      if (item.is_premium) {
        var premiumSpan = document.createElement('span');
        premiumSpan.className = 'aic-reco-premium';
        premiumSpan.textContent = ' \u2022 Premium';
        meta.appendChild(premiumSpan);
      }
      info.appendChild(title);
      info.appendChild(meta);
      if (item.genres) {
        var genres = document.createElement('div');
        genres.className = 'aic-reco-genres';
        genres.textContent = item.genres;
        info.appendChild(genres);
      }
      if (item.description) {
        var desc = document.createElement('div');
        desc.className = 'aic-reco-desc';
        desc.textContent = item.description;
        info.appendChild(desc);
      }
      a.appendChild(img);
      a.appendChild(info);
      wrap.appendChild(a);
    });
    log.appendChild(wrap);
    log.scrollTop = log.scrollHeight;
  }

  /* ============================================================
     TUGMALAR
     ============================================================ */
  fab.addEventListener('click', function () {
    panel.classList.toggle('aic-open');
    if (!panel.classList.contains('aic-open')) {
      stopListening(); stopSpeaking(); sessionActive = false; micWanted = false;
      return;
    }
    if (greeted) { showVoiceView(); if (input) input.focus(); return; }
    greeted = true;
    openVoice();
    startSession();
  });

  closeBtn.addEventListener('click', function () {
    stopListening(); stopSpeaking(); sessionActive = false; micWanted = false;
    panel.classList.add('aic-closing');
    setTimeout(function () {
      panel.classList.remove('aic-open', 'aic-closing');
    }, 250);
  });

  if (histBtn) histBtn.addEventListener('click', function () { showChatList(); });
  if (backBtn) backBtn.addEventListener('click', function () { showVoiceView(); });
  if (newChatBtn) newChatBtn.addEventListener('click', function () {
    if (isGuest) { currentSessionId = Date.now(); showVoiceView(); log.innerHTML = ''; addMessage(GREETING, 'bot'); renderQuickChips(); }
    else createNewChat();
  });
  if (newChatBtnTop) newChatBtnTop.addEventListener('click', function () {
    if (isGuest) { currentSessionId = Date.now(); showVoiceView(); log.innerHTML = ''; addMessage(GREETING, 'bot'); renderQuickChips(); }
    else createNewChat();
  });

  if (sendBtn) sendBtn.addEventListener('click', function () {
    var t = input ? input.value : '';
    if (!t.trim() || isSending) return;
    if (pendingQueue.length) { pendingQueue.push(t.trim()); if (input) input.value = ''; return; }
    sendText(t);
  });
  if (input) input.addEventListener('keydown', function (e) { if (e.key === 'Enter') sendBtn.click(); });

  if (micBtn) {
    micBtn.addEventListener('click', function () {
      if (sessionActive) {
        sessionActive = false;
        micDenied = false;
        micWanted = false;
        stopListening();
        stopSpeaking();
        setState("");
        setLabel("To'xtatildi · yana boshlash uchun bosing");
        return;
      }
      if (!SR) { addBubble("Bu brauzer ovozli kirishni qo'llamaydi — Chrome yoki Edge'dan oching.", "sys"); return; }
      sessionActive = true;
      micWanted = true;
      micDenied = false;
      ensureMicPermission(startListening);
    });
  }

  /* ---------------- orb = mikrafon (v6: yumaloq orb bosiladi) ---------------- */
  if (orbWrap) orbWrap.addEventListener('click', function () {
    if (micBtn) micBtn.click();
  });

  /* ---------------- sozlamalar popoveri (⚙️) ---------------- */
  var gearBtn = $('aic-gear'), gearPop = $('aic-gear-pop');
  if (gearBtn && gearPop) {
    gearBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var show = !gearPop.classList.contains('show');
      gearPop.classList.toggle('show', show);
      gearBtn.classList.toggle('act', show);
    });
    document.addEventListener('click', function (e) {
      if (gearPop.classList.contains('show') && !gearPop.contains(e.target) && !gearBtn.contains(e.target)) {
        gearPop.classList.remove('show');
        gearBtn.classList.remove('act');
      }
    });
  }

  /* ---------------- boshlang'ich holat ---------------- */
  if (!SR) {
    if (micBtn) micBtn.style.display = 'none';
  }
  watchMicPermission();
});