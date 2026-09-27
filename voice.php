<?php
/* ============================================================
   voice.php — UZDUB AI ovozli suhbat (saytdagi to'liq sahifa)
   ============================================================ */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'UZDUB AI — Ovozli suhbat 📞';
$page_desc  = 'UZDUB AI bilan jonli ovozli suhbat — gapiring, AI eshitadi va o\'zbekcha ovozda javob beradi.';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo e($page_desc); ?>">
<meta name="theme-color" content="#0a0f1e">
<title><?php echo e($page_title); ?> - UZDUB PLATFORM</title>
<script>window.ROOT_URL = <?php echo json_encode(ROOT_URL); ?>;</script>
<link rel="icon" type="image/svg+xml" href="<?php echo ROOT_URL; ?>/favicon.svg">
<link rel="shortcut icon" href="<?php echo ROOT_URL; ?>/favicon.svg">
<link rel="manifest" href="<?php echo ROOT_URL; ?>/manifest.php">
<style>
  :root{
    --bg:#0a0f1e; --card:#101828; --text:#e8eefc; --muted:#8fa3c8;
    --accent:#4f8cff; --ok:#34d399; --err:#f87171; --mic:#f43f5e;
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  body{
    font-family:'Segoe UI', Tahoma, Arial, sans-serif;
    background:radial-gradient(1200px 700px at 50% -10%, #1a2440 0%, var(--bg) 55%);
    color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:14px;
  }
  .phone{
    width:100%; max-width:420px; height:min(94vh, 780px); background:var(--card);
    border:1px solid #223052; border-radius:36px; overflow:hidden;
    display:flex; flex-direction:column; box-shadow:0 30px 80px rgba(0,0,0,.5);
  }
  .head{ padding:16px 20px 8px; text-align:center; position:relative; }
  .head h1{ font-size:17px; letter-spacing:.3px; }
  .head h1 b{ color:var(--accent); }
  .status{ font-size:12.5px; color:var(--muted); margin-top:3px; min-height:16px; }
  .clearBtn{
    position:absolute; top:16px; right:18px; background:#1b2642; color:var(--muted);
    border:1px solid #2a3852; border-radius:20px; padding:4px 12px; font-size:11.5px; cursor:pointer;
  }
  .clearBtn:hover{ color:var(--text); border-color:var(--accent); }

  .orbArea{ display:flex; align-items:center; justify-content:center; padding:26px 0 14px; }
  .orbWrap{ position:relative; width:132px; height:132px; display:flex; align-items:center; justify-content:center; }
  .orb{
    width:84px; height:84px; border-radius:50%;
    background:radial-gradient(circle at 32% 30%, #7fb2ff 0%, #3b6dff 40%, #14224a 100%);
    box-shadow:0 0 30px rgba(79,140,255,.55), inset 0 0 22px rgba(255,255,255,.18);
    animation:breathe 4s ease-in-out infinite;
    z-index:2;
  }
  .orb::after{ content:""; position:absolute; inset:0; border-radius:50%; }
  .ring{ position:absolute; inset:0; border-radius:50%; border:2px solid rgba(79,140,255,.35); opacity:0; }
  @keyframes breathe{ 0%,100%{transform:scale(1)} 50%{transform:scale(1.07)} }
  @keyframes pulseRing{ 0%{transform:scale(.9); opacity:.7} 100%{transform:scale(1.35); opacity:0} }
  @keyframes spin{ from{transform:rotate(0)} to{transform:rotate(360deg)} }

  .orbWrap.state-listening .ring{ border-color:var(--mic); animation:pulseRing 1.3s ease-out infinite; opacity:0; }
  .orbWrap.state-listening .orb{ background:radial-gradient(circle at 32% 30%, #ff9fb2 0%, #e11d48 45%, #4a0d22 100%); box-shadow:0 0 30px rgba(244,63,94,.6), inset 0 0 22px rgba(255,255,255,.15); }
  .orbWrap.state-listening .ring:nth-child(2){ animation-delay:.35s; }
  .orbWrap.state-listening .ring:nth-child(3){ animation-delay:.7s; }
  .orbWrap.state-thinking .ring{ border-color:var(--accent); border-right-color:transparent; animation:spin 1s linear infinite; opacity:.8; }
  .orbWrap.state-thinking .orb{ background:radial-gradient(circle at 32% 30%, #fff3c2 0%, #f59e0b 45%, #3a2a05 100%); box-shadow:0 0 30px rgba(245,158,11,.5), inset 0 0 22px rgba(255,255,255,.15); }
  .orbWrap.state-speaking .ring{ border-color:var(--ok); border-top-color:transparent; animation:spin .9s linear infinite; opacity:.85; }
  .orbWrap.state-speaking .orb{ background:radial-gradient(circle at 32% 30%, #a7f3d0 0%, #10b981 45%, #064e3b 100%); box-shadow:0 0 30px rgba(52,211,153,.55), inset 0 0 22px rgba(255,255,255,.16); }

  .liveBox{ min-height:52px; padding:0 22px 6px; }
  .liveBox .interim{
    font-size:15px; line-height:1.45; text-align:center; color:#cfe0ff; min-height:44px;
    display:flex; align-items:center; justify-content:center;
  }
  .liveBox .interim:empty::before{ content:"Gapiring..."; color:#5b6d92; font-size:13px; }

  #chatLog{ flex:1; overflow-y:auto; padding:10px 16px; display:flex; flex-direction:column; gap:8px; }
  #chatLog::-webkit-scrollbar{ width:5px; }
  #chatLog::-webkit-scrollbar-thumb{ background:#2a3852; border-radius:3px; }
  .msg{ max-width:85%; padding:9px 13px; border-radius:17px; font-size:14px; line-height:1.5; word-wrap:break-word; white-space:pre-wrap; animation:fadeUp .25s ease; }
  @keyframes fadeUp{ from{opacity:0; transform:translateY(6px)} to{opacity:1} }
  .user{ align-self:flex-end; background:linear-gradient(135deg,#2b59d9,#3b6dff); border-bottom-right-radius:5px; }
  .bot{ align-self:flex-start; background:#1b2642; border:1px solid #2a3852; border-bottom-left-radius:5px; }
  .sys{ align-self:center; background:#3a1d0e; color:#fcd9a8; border:1px solid #6b4423; font-size:12.5px; max-width:92%; }

  .settings{ display:flex; flex-wrap:wrap; gap:7px 12px; align-items:center; justify-content:center; padding:6px 14px 4px; font-size:11.5px; color:var(--muted); }
  .settings select{
    background:#131c30; color:var(--text); border:1px solid #2a3852; border-radius:20px;
    padding:4px 8px; font-size:11.5px; outline:none; cursor:pointer;
  }
  .settings select:focus{ border-color:var(--accent); }
  .settings label.chk{ display:flex; align-items:center; gap:5px; cursor:pointer; }
  #ttsEngine{ color:var(--ok); }

  .inputRow{ display:flex; gap:8px; align-items:center; padding:10px 16px 16px; }
  #textInput{
    flex:1; background:#0d1526; border:1px solid #2a3852; color:var(--text);
    border-radius:24px; padding:11px 16px; font-size:14px; outline:none;
  }
  #textInput:focus{ border-color:var(--accent); }
  .btn{
    width:46px; height:46px; border-radius:50%; border:none; cursor:pointer; flex:none;
    display:flex; align-items:center; justify-content:center; font-size:19px;
    transition:transform .12s, box-shadow .2s;
  }
  .btn:active{ transform:scale(.93); }
  #sendBtn{ background:linear-gradient(135deg,#2b59d9,#3b6dff); color:#fff; }
  #micBtn{ background:linear-gradient(135deg,#d41f45,#f43f5e); color:#fff; width:54px; height:54px; font-size:22px; }
  #micBtn.listening{ box-shadow:0 0 0 7px rgba(244,63,94,.22), 0 0 26px rgba(244,63,94,.6); animation:pulseRing 1.3s infinite; }
  #voiceBtn{ background:#1b2642; border:1px solid #2a3852; color:var(--muted); width:46px; height:46px; }
  #voiceBtn.on{ color:var(--ok); border-color:#14532d; }

  .foot{ text-align:center; padding:0 0 12px; font-size:11px; color:#4c5c7d; }
  @media (prefers-reduced-motion: reduce){
    .orb, .ring, #micBtn.listening{ animation:none !important; }
    .msg{ animation:none; }
  }
</style>
</head>
<body>
<div class="phone">
  <div class="head">
    <h1>UZDUB <b>AI</b> — ovozli suhbat</h1>
    <div class="status" id="statusEl">Tayyor 😊</div>
    <button class="clearBtn" id="clearBtn" title="Suhbat va xotirani tozalash">♻ Tozalash</button>
  </div>

  <div class="orbArea">
    <div class="orbWrap" id="orbWrap">
      <div class="ring"></div>
      <div class="ring"></div>
      <div class="ring"></div>
      <div class="orb" id="orb"></div>
    </div>
  </div>

  <div class="liveBox"><div class="interim" id="interimText"></div></div>

  <div id="chatLog"></div>

  <div class="settings">
    <select id="voiceLang" title="Mikrofon tili">
      <option value="uz-UZ">🎤 O'zbekcha</option>
      <option value="ru-RU">🎤 Ruscha</option>
      <option value="en-US">🎤 Inglizcha</option>
      <option value="tr-TR">🎤 Turkcha</option>
    </select>
    <select id="ttsVoice" title="Bot ovozi">
      <option value="uz-UZ-MadinaNeural">🗣 Madina (ayol)</option>
      <option value="uz-UZ-SardorNeural">🗣 Sardor (erkak)</option>
    </select>
    <span id="ttsEngine"></span>
    <label class="chk" title="Bot javob berganidan keyin avtomatik eshitish">
      <input type="checkbox" id="liveMode" checked> Jonli rejim
    </label>
  </div>

  <div class="inputRow">
    <button class="btn" id="voiceBtn" title="Ovozli javob yoqish/o'chirish">🔊</button>
    <input id="textInput" type="text" placeholder="Matn yozing yoki gapiring...">
    <button class="btn" id="sendBtn" title="Yuborish">➤</button>
    <button class="btn" id="micBtn" title="Mikrofon">🎤</button>
  </div>
  <div class="foot">Mikrofon tugmasini bosing — erkin gapiring. Sukut 1.8 soniya bo'lsa, savol yuboriladi.</div>
</div>

<script>
window.ROOT_URL = window.ROOT_URL || '/uzdub';
(function () {
  "use strict";

  var SERVER     = "http://127.0.0.1:11434/api/chat";
  var SERVER_TTS = "http://127.0.0.1:11434/api/tts";
  var MODEL      = "uzdub-ai";

  var SID = sessionStorage.getItem("ua_sid");
  if (!SID) { SID = "s-" + Date.now() + "-" + Math.floor(Math.random() * 9999); sessionStorage.setItem("ua_sid", SID); }

  function $(id) { return document.getElementById(id); }
  var chatLog = $("chatLog"), orbWrap = $("orbWrap"),
      statusEl = $("statusEl"), interimEl = $("interimText"),
      micBtn = $("micBtn"), voiceBtn = $("voiceBtn"), liveMode = $("liveMode"),
      sendBtn = $("sendBtn"), textInput = $("textInput"), clearBtn = $("clearBtn"),
      voiceLangEl = $("voiceLang"), ttsVoiceEl = $("ttsVoice"), ttsEngineEl = $("ttsEngine");

  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;

  var voiceOn = localStorage.getItem("ua_voice_on") !== "0";
  voiceBtn.classList.toggle("on", voiceOn);
  try {
    if (localStorage.getItem("ua_voice_lang")) voiceLangEl.value = localStorage.getItem("ua_voice_lang");
    if (localStorage.getItem("ua_tts_voice")) ttsVoiceEl.value = localStorage.getItem("ua_tts_voice");
  } catch (e) {}
  voiceLangEl.addEventListener("change", function () { try { localStorage.setItem("ua_voice_lang", voiceLangEl.value); } catch (e) {} });
  ttsVoiceEl.addEventListener("change", function () { try { localStorage.setItem("ua_tts_voice", ttsVoiceEl.value); } catch (e) {} });
  voiceBtn.addEventListener("click", function () {
    voiceOn = !voiceOn;
    localStorage.setItem("ua_voice_on", voiceOn ? "1" : "0");
    voiceBtn.classList.toggle("on", voiceOn);
    if (!voiceOn) stopSpeaking();
  });

  var CONV_STATE = { LISTEN: "listening", THINK: "thinking", SPEAK: "speaking" };
  var listening = false, thinking = false, sessionActive = false;
  var recog = null, silenceTimer = null, comfyTimer = null, micDenied = false;

  function setState(st) {
    orbWrap.className = "orbWrap" + (st ? " state-" + st : "");
    var labels = {
      listening: "🎤 Eshityapman...",
      thinking:  "🧠 O'ylayapman...",
      speaking:  "🔊 Gapiryapman..."
    };
    if (st) statusEl.textContent = labels[st];
  }
  function setLabel(t) { statusEl.textContent = t; }

  function addBubble(text, who) {
    if (!text) return;
    var d = document.createElement("div");
    d.className = "msg " + who;
    d.textContent = text;
    chatLog.appendChild(d);
    chatLog.scrollTop = chatLog.scrollHeight;
    return d;
  }

  function ensureMicPermission(cb) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { cb(); return; }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
      stream.getTracks().forEach(function (t) { t.stop(); });
      cb();
    }).catch(function (err) {
      micDenied = true;
      var name = (err && err.name) || "xato";
      sessionActive = false;
      setLabel("Tayyor 😊");
      var denied = false;
      if (navigator.permissions && navigator.permissions.query) {
        try {
          navigator.permissions.query({ name: 'microphone' }).then(function (status) {
            denied = status.state === 'denied';
            addBubble(denied
              ? "Mikrofon brauzerda bloklangan (" + name + "). Tuzatish: adres satridagi 🔒/ℹ️ belgini bosing → «Sayt sozlamalari» → Mikrofon → «Ruxsat berish» → sahifani yangilang (F5)."
              : "Mikrofonga ruxsat kerak (" + name + "). Paydo bo'lgan oynada «Ruxsat berish» tugmasini bosing va yana urinib ko'ring.", "sys");
          });
          return;
        } catch (e) {}
      }
      addBubble("Mikrofon ruxsati yo'q (" + name + "). Adres satridagi 🔒/ℹ️ belgidan ruxsat bering va sahifani yangilang (F5).", "sys");
    });
  }

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
    if (s.slice(0, 5) === "salom" || s.slice(0, 8) === "assalomu")
      return greets[s] || s;
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

  function startListening() {
    if (listening || !SR || !sessionActive) return;
    if (!window.isSecureContext) {
      addBubble("Mikrofon ishlamaydi: sahifani http://localhost orqali oching.", "sys");
      sessionActive = false; return;
    }
    listening = true;
    setState(CONV_STATE.LISTEN);
    micBtn.classList.add("listening");

    var r = new SR();
    r.lang = voiceLangEl.value || "uz-UZ";
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
      if (completed) {
        try { r.stop(); } catch (e) {}
        return;
      }
      clearTimeout(comfyTimer);
      comfyTimer = setTimeout(function () {
        try { r.stop(); } catch (e) {}
      }, SILENCE_EXTRA);
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
      interimEl.textContent = shown;
      textInput.value = shown;
      if (shown) rearmSilence();
    };

    r.onerror = function (ev) {
      if (ev.error === "no-speech" || ev.error === "aborted") return;
      var msg = {
        "not-allowed": "Mikrofonga ruxsat berilmadi. 🔒 belgidan ruxsat bering.",
        "service-not-allowed": "Brauzer mikrofonga ruxsat bermayapti — sozlamalardan tekshiring.",
        "language-not-supported": "Tanlangan til qo'llab-quvvatlanmaydi — yuqorida boshqa til tanlang.",
        "network": "Ovozli tanishuv xizmatiga ulanish yo'q — internetni tekshiring.",
        "audio-capture": "Mikrofon topilmadi yoki ulanganini tekshiring."
      }[ev.error];
      if (msg) { addBubble(msg, "sys"); micDenied = true; sessionActive = false; setLabel("Tayyor 😊"); }
    };

    r.onend = function () {
      stopSilence();
      listening = false;
      micBtn.classList.remove("listening");
      if (!sessionActive) { setState(""); setLabel("To'xtatildi"); return; }

      var said = cleanTranscript(finalBuf || textInput.value || "");
      interimEl.textContent = "";
      if (said) {
        textInput.value = "";
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
      listening = false; micBtn.classList.remove("listening"); setState("");
    }
  }

  function stopListening() {
    clearTimeout(silenceTimer);
    if (recog) { try { recog.stop(); } catch (e) {} }
    recog = null; listening = false;
    micBtn.classList.remove("listening");
    interimEl.textContent = "";
  }

  function sendToAI(userText) {
    if (thinking) return;
    thinking = true;
    setState(CONV_STATE.THINK);

    var body = JSON.stringify({
      model: MODEL,
      session_id: SID,
      stream: true,
      messages: [{ role: "user", content: userText }]
    });

    fetch(SERVER, { method: "POST", headers: { "Content-Type": "application/json" }, body: body })
      .then(function (res) {
        if (!res.ok) throw new Error("HTTP " + res.status);
        var reader = res.body.getReader();
        var dec = new TextDecoder("utf-8");
        var buf = "";
        var answer = "";

        function pump() {
          return reader.read().then(function (r) {
            if (r.done) {
              if (answer.trim()) onReply(answer);
              else throw new Error("bo'sh javob");
              return;
            }
            buf += dec.decode(r.value, { stream: true });
            var lines = buf.split("\n");
            buf = lines.pop();
            lines.forEach(function (line) {
              try {
                var obj = JSON.parse(line);
                var piece = obj.message && obj.message.content;
                if (piece) answer += piece;
              } catch (e) {}
            });
            return pump();
          });
        }
        return pump();
      })
      .catch(function (err) {
        thinking = false;
        setState("");
        addBubble("Serverga ulanishda xatolik (" + err.message + "). AI server ishlayotganini tekshiring: ai-server\\start_ai.bat", "sys");
        setLabel("Tayyor 😊");
      });
  }

  function onReply(answer) {
    thinking = false;
    setState("");
    var clean = answer.trim();
    addBubble(clean, "bot");
    if (voiceOn) {
      speak(clean, function () { afterSpeech(); });
    } else {
      afterSpeech();
    }
  }

  var ttsStop = false, ttsAudio = null;

  function setEngineLabel(txt) { if (ttsEngineEl) ttsEngineEl.textContent = txt ? "· " + txt : ""; }

  function splitChunks(text, max) {
    var out = [], cur = "";
    for (var i = 0; i < text.length; i++) {
      cur += text[i];
      if (cur.length >= max || /[.!?…]/.test(text[i])) {
        var p = cur.trim(); if (p) out.push(p); cur = "";
      }
    }
    if (cur.trim()) out.push(cur.trim());
    if (!out.length && text.trim()) out.push(text.trim());
    return out;
  }

  function speakServer(text, voice, onDone) {
    var chunks = splitChunks(text, 170);
    ttsStop = false;
    var idx = 0;
    setState(CONV_STATE.SPEAK);
    setEngineLabel(voice === "uz-UZ-SardorNeural" ? "Sardor (o'zbek)" : "Madina (o'zbek)");

    function playNext() {
      if (ttsStop) { setState(""); onDone && onDone(); return; }
      if (idx >= chunks.length) { setState(""); onDone && onDone(); return; }
      var a = new Audio();
      ttsAudio = a;
      a.src = SERVER_TTS + "?voice=" + encodeURIComponent(voice) + "&text=" + encodeURIComponent(chunks[idx++]);
      a.onended = function () { if (!ttsStop) playNext(); else { setState(""); onDone && onDone(); } };
      a.onerror = function () { serverTtsFailed(text, onDone); };
      a.play().catch(function () { serverTtsFailed(text, onDone); });
    }
    playNext();
  }

  function serverTtsFailed(fullText, onDone) {
    if (ttsStop) return;
    if (window.speechSynthesis) speakSystem(fullText, onDone);
    else { setState(""); onDone && onDone(); }
  }

  function speakSystem(text, onDone) {
    var u;
    try { u = new SpeechSynthesisUtterance(text); } catch (e) { onDone && onDone(); return; }
    var v = pickVoice() || null;
    if (v) { u.voice = v; u.lang = v.lang; }
    u.rate = 1.0; u.pitch = 1.0; u.volume = 1.0;
    setState(CONV_STATE.SPEAK);
    setEngineLabel("Tizim ovozi");
    u.onend = function () { setState(""); onDone && onDone(); };
    u.onerror = function () { setState(""); onDone && onDone(); };
    try { speechSynthesis.speak(u); } catch (e) { setState(""); onDone && onDone(); }
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
    stopSpeaking();
    var clean = String(text || "").replace(/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE0F}]/gu, " ")
      .replace(/[#*_`>|]/g, " ").replace(/\s+/g, " ").trim();
    if (!clean) { onDone && onDone(); return; }
    speakServer(clean, ttsVoiceEl.value || "uz-UZ-MadinaNeural", onDone);
  }

  function stopSpeaking() {
    ttsStop = true;
    if (ttsAudio) { try { ttsAudio.pause(); } catch (e) {} ttsAudio = null; }
    if (window.speechSynthesis) speechSynthesis.cancel();
    setState("");
  }

  function afterSpeech() {
    if (sessionActive && liveMode.checked && !micDenied) {
      setLabel("...");
      setTimeout(startListening, 450);
    } else {
      setLabel("Tayyor 😊");
      setState("");
    }
  }

  micBtn.addEventListener("click", function () {
    if (sessionActive) {
      sessionActive = false;
      micDenied = false;
      stopListening();
      stopSpeaking();
      setState("");
      setLabel("To'xtatildi · yana boshlash uchun bosing");
      return;
    }
    if (!SR) { addBubble("Bu brauzer ovozli kirishni qo'llamaydi — Chrome yoki Edge'dan oching.", "sys"); return; }
    sessionActive = true;
    micDenied = false;
    ensureMicPermission(startListening);
  });

  sendBtn.addEventListener("click", function () {
    var t = textInput.value.trim();
    if (!t || thinking) return;
    textInput.value = "";
    addBubble(t, "user");
    if (sessionActive) { sessionActive = false; micDenied = false; stopListening(); }
    sendToAI(t);
  });
  textInput.addEventListener("keydown", function (e) { if (e.key === "Enter") sendBtn.click(); });

  clearBtn.addEventListener("click", function () {
    chatLog.innerHTML = "";
    interimEl.textContent = "";
    stopListening(); stopSpeaking();
    sessionActive = false;
    setState(""); setLabel("Tayyor 😊");
    fetch(SERVER.replace("/api/chat", "/api/history/clear"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ session_id: SID })
    }).catch(function () {});
  });

  if (!window.isSecureContext) {
    addBubble("Mikrofon ishlamaydi: sahifani http://localhost orqali oching (file:// emas).", "sys");
  } else if (!SR) {
    addBubble("Bu brauzer ovozli kirishni qo'llamaydi — Chrome yoki Edge'dan oching.", "sys");
  }
  addBubble("Assalomu alaykum! Men UZDUB AI yordamchisiman. 🎤 tugmasini bosing va gapiring — kino, anime, multfilmlar va istalgan mavzuda erkin suhbatlashamiz.", "bot");
})();
</script>
</body>
</html>