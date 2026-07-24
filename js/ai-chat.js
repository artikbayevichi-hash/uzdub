/* ============================================================
   js/ai-chat.js
   UZDUB PLATFORM AI Yordamchi — chat tarixi, mehmon rejimi,
   tezkor takliflar va kontent tavsiya kartochkalari bilan
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {
  const fab = document.getElementById('aic-fab');
  const panel = document.getElementById('aic-panel');
  const closeBtn = document.getElementById('aic-close');
  const backBtn = document.getElementById('aic-back');
  const newChatBtn = document.getElementById('aic-new-chat');
  const newChatBtnTop = document.getElementById('aic-new-chat-btn');
  const chatList = document.getElementById('aic-chat-list');
  const chatView = document.getElementById('aic-chat-view');
  const listItems = document.getElementById('aic-list-items');
  const log = document.getElementById('aic-log');
  const input = document.getElementById('aic-input');
  const sendBtn = document.getElementById('aic-send');
  const typing = document.getElementById('aic-typing');
  const csrfToken = window.aicCsrfToken || '';
  const isGuest = !window.aicIsLoggedIn;
  const lang = window.aicLang || 'uz';

  if (!fab || !panel) return;

  // Ko'p tilli greeting va tezkor takliflar
  const LANG_TEXTS = {
    uz: {
      greeting: "Assalomu alaykum! Men UZDUB PLATFORM AI yordamchiman. Kino, anime yoki multfilm haqida so'rang.",
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
      greeting: 'Ассаламу алейкум! Я UZDUB PLATFORM AI помощник. Спрашивайте о фильмах, аниме или мультфильмах.',
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
      greeting: 'Assalamu alaykum! I am UZDUB PLATFORM AI assistant. Ask me about movies, anime or cartoons.',
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

  const texts = LANG_TEXTS[lang] || LANG_TEXTS.uz;
  const GREETING = texts.greeting;
  const QUICK_PROMPTS = texts.prompts;

  let greeted = false;
  let currentSessionId = null;
  let isSending = false;
  let pendingQueue = [];

  // Mehmon foydalanuvchida chatlar ro'yxati mavjud emas (tarix saqlanmaydi) —
  // shu sabab ro'yxat ekranini butunlay yashiramiz va to'g'ridan-to'g'ri suhbatga o'tamiz.
  if (isGuest) {
    backBtn.style.display = 'none';
  }

  fab.addEventListener('click', function () {
    panel.classList.toggle('aic-open');
    if (!panel.classList.contains('aic-open') || greeted) return;
    greeted = true;
    if (isGuest) {
      startGuestChat();
    } else {
      loadChatList();
    }
  });

  closeBtn.addEventListener('click', function () {
    panel.classList.add('aic-closing');
    setTimeout(function() {
      panel.classList.remove('aic-open', 'aic-closing');
    }, 250);
  });

  backBtn.addEventListener('click', function () {
    showChatList();
  });

  newChatBtn.addEventListener('click', function () {
    if (isGuest) { startGuestChat(); } else { createNewChat(); }
  });

  newChatBtnTop.addEventListener('click', function () {
    if (isGuest) { startGuestChat(); } else { createNewChat(); }
  });

  function showChatList() {
    chatList.style.display = 'flex';
    chatView.style.display = 'none';
    backBtn.style.display = 'none';
    currentSessionId = null;
    loadChatList();
  }

  function showChatView() {
    chatList.style.display = 'none';
    chatView.style.display = 'flex';
    log.classList.remove('aic-fade-in');
    void log.offsetWidth;
    log.classList.add('aic-fade-in');
    if (!isGuest) backBtn.style.display = 'block';
  }

  // ---- Mehmon rejimi: tizimga kirmasdan to'g'ridan-to'g'ri suhbat ----
  function startGuestChat() {
    // Yangi (client tomonida yaratilgan) sessiya raqami — backend buni ko'rib,
    // avvalgi mehmon suhbati tarixini avtomatik tozalaydi.
    currentSessionId = Date.now();
    showChatView();
    log.innerHTML = '';
    addMessage(GREETING, 'bot');
    addGuestBanner();
    renderQuickChips();
  }

  function addGuestBanner() {
    const div = document.createElement('div');
    div.className = 'aic-guest-banner';
    div.innerHTML = 'Suhbat tarixini saqlash uchun <a href="/uzdub/auth/register.php">ro\u2018yxatdan o\u2018ting</a>';
    log.appendChild(div);
  }

  function renderQuickChips() {
    const wrap = document.createElement('div');
    wrap.className = 'aic-chips';
    wrap.id = 'aic-quick-chips';
    QUICK_PROMPTS.forEach(function (p) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'aic-chip';
      btn.textContent = p.label;
      btn.addEventListener('click', function () {
        removeQuickChips();
        addMessage(p.text, 'user');
        sendToAI(p.text);
      });
      wrap.appendChild(btn);
    });
    log.appendChild(wrap);
    log.scrollTop = log.scrollHeight;
  }

  function removeQuickChips() {
    const el = document.getElementById('aic-quick-chips');
    if (el) el.remove();
  }

  function loadChatList() {
    listItems.innerHTML = '<div class="aic-loading">Yuklanmoqda...</div>';

    fetch('/uzdub/api/chat/list.php')
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
          const div = document.createElement('div');
          div.className = 'aic-list-item';
          div.dataset.id = session.id;
          div.innerHTML = '<div style="flex:1;min-width:0;">' +
                          '<div class="aic-list-item-title">' + escapeHtml(session.title) + '</div>' +
                          '<div class="aic-list-item-date">' + formatDate(session.updated_at) + '</div>' +
                          '</div>' +
                          '<button class="aic-list-item-delete" data-id="' + session.id + '" title="O\'chirish">&times;</button>';
          
          div.addEventListener('click', function (e) {
            if (e.target.classList.contains('aic-list-item-delete')) return;
            openChat(session.id);
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
        console.error('Chat list error:', err);
      });
  }

  function openChat(sessionId) {
    currentSessionId = sessionId;
    showChatView();
    log.innerHTML = '';
    
    fetch('/uzdub/api/chat/history.php?session_id=' + sessionId)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) {
          addMessage('Xatolik: ' + data.error, 'bot');
          return;
        }

        if (data.messages && data.messages.length > 0) {
          data.messages.forEach(function (msg) {
            addMessage(msg.message, msg.role);
          });
        } else {
          addMessage(GREETING, 'bot');
          renderQuickChips();
        }
      })
      .catch(function () {
        addMessage("Bog'lanishda xatolik yuz berdi.", 'bot');
      });
  }

  function createNewChat() {
    fetch('/uzdub/api/chat/create.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'title=&csrf_token=' + encodeURIComponent(csrfToken),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) {
          alert('Xatolik: ' + data.error);
          return;
        }
        currentSessionId = data.session_id;
        showChatView();
        log.innerHTML = '';
        addMessage(GREETING, 'bot');
        renderQuickChips();
      })
      .catch(function () {
        alert('Xatolik yuz berdi');
      });
  }

  function deleteChat(sessionId) {
    if (!confirm('Chatni o\'chirishni tasdiqlaysizmi?')) return;
    
    fetch('/uzdub/api/chat/delete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'session_id=' + sessionId + '&csrf_token=' + encodeURIComponent(csrfToken),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) {
          alert('Xatolik: ' + data.error);
          return;
        }
        if (currentSessionId == sessionId) {
          showChatList();
        }
        loadChatList();
      })
      .catch(function () {
        alert('Xatolik yuz berdi');
      });
  }

  let userScrolledUp = false;

  log.addEventListener('scroll', function () {
    // Agar foydalanuvchi yuqoriga scroll qilgan bo'lsa, avtoscrollni to'xtatamiz
    const threshold = 60;
    userScrolledUp = log.scrollHeight - log.scrollTop - log.clientHeight > threshold;
  });

  function scrollToBottom(force) {
    if (!force && userScrolledUp) return;
    log.scrollTop = log.scrollHeight;
    // Scroll qilingandan so'ng, agar foydalanuvchi eng pastda bo'lsa, avtoscrollni qayta yoqamiz
    setTimeout(function () {
      const atBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 60;
      if (atBottom) userScrolledUp = false;
    }, 100);
  }

  function addMessage(text, from) {
    const wrap = document.createElement('div');
    wrap.className = 'aic-msg-wrap ' + (from === 'user' ? 'aic-msg-user' : 'aic-msg-bot');

    const avatar = document.createElement('div');
    avatar.className = 'aic-msg-avatar';
    if (from === 'user') {
      const initials = (window.aicUsername || 'U').charAt(0).toUpperCase();
      avatar.textContent = initials;
    } else {
      avatar.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 3.6 19.33L22 22l-1.03-4.24A10 10 0 0 0 12 2zm0 2a8 8 0 1 1-4.24 14.79l-.4-.25-2.85.68.7-2.76-.27-.42A8 8 0 0 1 12 4z"/></svg>';
    }

    const div = document.createElement('div');
    div.className = 'aic-msg';

    if (from === 'bot' && text) {
      div.innerHTML = formatBotMessage(text);
    } else {
      div.textContent = text;
    }

    wrap.appendChild(avatar);
    wrap.appendChild(div);
    log.appendChild(wrap);
    scrollToBottom(true);
    return div;
  }

  // Bot xabaridagi URL'larni formatlash — "Ko'rish" tugmasi qo'shish
  function formatBotMessage(text) {
    let html = escapeHtml(text);
    // /uzdub/watch.php?id=1 kabi havolalarni topish
    html = html.replace(/(\/uzdub\/watch\.php\?id=\d+)/g, function(match) {
      return '<a class="aic-watch-link" href="' + match + '" target="_blank">'
           + '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" style="vertical-align:middle;margin-right:4px;"><path d="M8 5v14l11-7z"/></svg>'
           + "Ko'rish"
           + '</a>';
    });
    // Oddiy URL'larni ham formatlash
    html = html.replace(/(https?:\/\/[^\s<]+)/g, function(match) {
      if (match.indexOf('watch.php') !== -1) return match;
      return '<a href="' + match + '" target="_blank" style="color:var(--aic-accent);text-decoration:underline;">' + match + '</a>';
    });
    return html;
  }

  function renderRecommendations(list) {
    if (!list || !list.length) return;
    const wrap = document.createElement('div');
    wrap.className = 'aic-reco-list';
    list.forEach(function (item) {
      const a = document.createElement('a');
      a.className = 'aic-reco-card';
      a.href = item.url;

      const img = document.createElement('img');
      img.className = 'aic-reco-poster';
      img.loading = 'lazy';
      img.src = item.poster || 'https://via.placeholder.com/84x120/121a2b/2196f3?text=' + encodeURIComponent(item.title.slice(0, 1));
      img.alt = item.title;

      const info = document.createElement('div');
      info.className = 'aic-reco-info';

      const title = document.createElement('div');
      title.className = 'aic-reco-title';
      title.textContent = item.title;

      const meta = document.createElement('div');
      meta.className = 'aic-reco-meta';
      const parts = [];
      if (item.category) parts.push(item.category);
      if (item.year) parts.push(item.year);
      if (item.rating) parts.push('\u2605 ' + item.rating);
      if (item.status) {
        const statusMap = { ongoing: 'Davom etmoqda', completed: 'Tugagan', upcoming: 'Kelayotgan' };
        parts.push(statusMap[item.status] || item.status);
      }
      if (item.episodes) parts.push(item.episodes + ' ep.');
      if (item.duration) parts.push(item.duration);
      meta.textContent = parts.join(' \u00b7 ');
      if (item.is_premium) {
        const premiumSpan = document.createElement('span');
        premiumSpan.className = 'aic-reco-premium';
        premiumSpan.textContent = ' \u2022 Premium';
        meta.appendChild(premiumSpan);
      }

      info.appendChild(title);
      info.appendChild(meta);

      if (item.genres) {
        const genres = document.createElement('div');
        genres.className = 'aic-reco-genres';
        genres.textContent = item.genres;
        info.appendChild(genres);
      }

      if (item.description) {
        const desc = document.createElement('div');
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

  // Oddiy salomlashuv va tez javoblar — serverga yubormasdan darhol javob
  const QUICK_REPLIES = {
    uz: {
      greetings: { re: /^(assalomu?\s*alaykum|salom|selom|salomu|salam|hi|hey|hello|privet|salomlar)$/i, items: [
        "Assalomu alaykum! UZDUB AI yordamchisiman. Kino, anime yoki multfilm haqida nima bilmoqchisiz? 🎬",
        "Salom! Qanday yordam bera olaman? Saytimizda ko'plab kino va anime mavjud! 😊",
        "Alaykum assalom! Kino yoki anime tavsiya kerakmi? Menga yozing! 🎌",
        "Salom salom! UZDUB platformasiga xush kelibsiz! Nima izlayapsiz? 🍿",
      ]},
      how: { re: /^(qalaysan|qalay|yaxshimisan|yaxshilik|qilyapsan|nima\s*qilyapsan|vazir?qin)$/i, items: [
        "Yaxshiman, rahmat! 😊 Siz nima qilyapsiz? Kino yoki anime kerakmi?",
        "Zo'r! UZDUB da yangi kontentlar qo'shildi, ko'rdingizmi? 🎬",
      ]},
      thanks: { re: /^(rahmat|rаhмат|thanks|thank\s*you|tashakkur|minnatdorman|cheers|katta\s*rahmat)$/i, items: [
        "Arzimaydi! Agar boshqa savol bo'lsa — bemalol so'rang 😊",
        "Biz doimo yordamga tayyormiz! Yana nima bilmoqchisiz? 🎬",
        "Ko'mak berishdan xursandmiz! Boshqa kino/anime kerak bo'lsa, ayting 🔥",
      ]},
      ok: { re: /^(ok|okay|tushundim|rоzi|mayli|yaxshi|ha|yo'?q|yoq|haa|хорошо|нормально|отлично|супер|класс|молодец)$/i, items: [
        "Tushundim! Yana nima kerak? 😊",
        "Ok! Boshqa savol bo'lsa, yozing 👍",
        "Mayli! Qo'llab-quvvatlash uchun rahmat 🙌",
      ]},
      random: { re: null, items: [
        "Qiziq savol! Lekin men asosan kino va anime haqida yaxshi bilaman 🎬 Saytimizdagi kontentlarni ko'rib chiqasizmi?",
        "Men UZDUB AI yordamchiman — kino, anime va multfilmlar haqida javob bera olaman. Nima so'rashni istaysiz? 🤔",
        "Hmm, bu haqida aniq bilmayman 😅 Lekin kino yoki anime haqida so'rasangiz, albatta yordam beraman!",
      ]}
    },
    ru: {
      greetings: { re: /^(привет|здравствуй|салам|хай|hello|приветик|приветствую)$/i, items: [
        "Привет! Я AI-помощник UZDUB. Чем могу помочь? 🎬",
        "Здравствуйте! Ищете фильм или аниме? Спрашивайте! 😊",
        "Салам! На сайте много фильмов и аниме. Что ищете? 🍿",
      ]},
      how: { re: /^(как\s*дела|как\s*ты|как\s*поживаешь|что\s*как|норм|нормально|лол)$/i, items: [
        "Хорошо, спасибо! А вы фильм искали? 🎬",
        "Отлично! Помочь найти фильм или аниме? 😊",
      ]},
      thanks: { re: /^(спасибо|благодарю|сенкс|thanks|респект)$/i, items: [
        "Пожалуйста! Если есть ещё вопросы — спрашивайте 😊",
        "Рад помочь! Что ещё хотите узнать? 🎬",
      ]},
      ok: { re: /^(ок|окей|понял|хорошо|да|нет|ага|угу)$/i, items: [
        "Понял! Если что — обращайтесь 👍",
        "Хорошо! ещё вопросы есть? 😊",
      ]},
      random: { re: null, items: [
        "Интересный вопрос! Но я больше разбираюсь в фильмах и аниме 🎬 Хотите посмотреть что-нибудь на сайте?",
        "Я AI-помощник UZDUB — могу помочь с фильмами и аниме. Что спросить? 🤔",
      ]}
    },
    en: {
      greetings: { re: /^(hi|hey|hello|hola|sup|yo|howdy)$/i, items: [
        "Hey! I'm UZDUB AI assistant. How can I help? 🎬",
        "Hi there! Looking for a movie or anime? Ask away! 😊",
        "Hello! Welcome to UZDUB! What are you looking for? 🍿",
      ]},
      how: { re: /^(how\s*are\s*you|what's\s*up|how's\s*it\s*going|what's\s*good)$/i, items: [
        "I'm good, thanks! Looking for a movie? 🎬",
        "Great! Need help finding something to watch? 😊",
      ]},
      thanks: { re: /^(thanks|thank\s*you|thx|cheers|appreciate)$/i, items: [
        "You're welcome! Feel free to ask anything else 😊",
        "Happy to help! Need more movie recommendations? 🎬",
      ]},
      ok: { re: /^(ok|okay|sure|got\s*it|alright|yep|yeah|no|nah)$/i, items: [
        "Got it! Let me know if you need anything else 👍",
        "Sure! Any other questions? 😊",
      ]},
      random: { re: null, items: [
        "Interesting question! I mainly know about movies and anime though 🎬 Want to check out what's on the site?",
        "I'm UZDUB AI — I can help with movies, anime and cartoons. What would you like to know? 🤔",
      ]}
    }
  };

  function getQuickReply(text) {
    const t = text.trim().toLowerCase();
    const langData = QUICK_REPLIES[lang] || QUICK_REPLIES.uz;

    if (langData.greetings.re.test(t)) return pick(langData.greetings.items);
    if (langData.how.re.test(t)) return pick(langData.how.items);
    if (langData.thanks.re.test(t)) return pick(langData.thanks.items);
    if (langData.ok.re.test(t)) return pick(langData.ok.items);

    return null;
  }

  function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

  function send() {
    const text = input.value.trim();
    if (!text || !currentSessionId) return;
    input.value = '';
    removeQuickChips();

    if (isSending) {
      addMessage(text, 'user');
      pendingQueue.push(text);
      if (window.showToast) showToast("Xabaringiz navbatga qo'yildi, hozir yuboriladi...", 'info', 2500);
      return;
    }

    addMessage(text, 'user');

    // Oddiy javoblar — serverga yubormasdan darhol
    const quick = getQuickReply(text);
    if (quick) {
      setTimeout(function() {
        addMessage(quick, 'bot');
      }, 300 + Math.random() * 400);
      return;
    }

    sendToAI(text);
  }

  function sendToAI(text) {
    isSending = true;
    input.disabled = true;
    sendBtn.disabled = true;
    typing.style.display = 'block';

    const botDiv = addMessage('', 'bot');
    let accumulated = '';
    let gotFirstToken = false;
    let turnFinished = false;

    function finishTurn() {
      if (turnFinished) return;
      turnFinished = true;
      isSending = false;
      typing.style.display = 'none';
      input.disabled = false;
      sendBtn.disabled = false;
      input.focus();

      // Streaming tugagandan keyin URL'larni formatlash
      if (accumulated) {
        botDiv.innerHTML = formatBotMessage(accumulated);
      }

      if (currentSessionId && !isGuest) {
        updateSessionTitle(currentSessionId, text);
        setTimeout(loadChatList, 500);
      }

      if (pendingQueue.length) {
        const next = pendingQueue.shift();
        sendToAI(next);
      }
    }

    fetch('/uzdub/api/stream.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, session_id: currentSessionId, csrf_token: csrfToken, lang: lang }),
    })
      .then(function (response) {
        if (!response.body || !response.body.getReader) {
          // Eski brauzer / streaming qo'llab-quvvatlanmasa
          return response.json().then(function (data) {
            botDiv.textContent = data.reply || data.error || "Javob olinmadi.";
            if (data.recommendations) renderRecommendations(data.recommendations);
            finishTurn();
          });
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        function pump() {
          return reader.read().then(function (result) {
            if (result.done) { finishTurn(); return; }
            buffer += decoder.decode(result.value, { stream: true });

            let idx;
            while ((idx = buffer.indexOf('\n\n')) !== -1) {
              const rawEvent = buffer.slice(0, idx);
              buffer = buffer.slice(idx + 2);
              const line = rawEvent.replace(/^data:\s*/, '').trim();
              if (!line) continue;

              let obj;
              try { obj = JSON.parse(line); } catch (e) { continue; }

              if (obj.busy) {
                botDiv.textContent = obj.msg || 'AI hozir band, biroz kuting...';
                if (window.showToast) showToast(obj.msg || 'AI hozir band', 'warning');
                finishTurn();
                return;
              }
              if (obj.error) {
                botDiv.textContent = obj.error;
                continue;
              }
              if (obj.delta) {
                if (!gotFirstToken) { gotFirstToken = true; typing.style.display = 'none'; }
                accumulated += obj.delta;
                botDiv.textContent = accumulated;
                log.scrollTop = log.scrollHeight;
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
      .catch(function () {
        if (!gotFirstToken) botDiv.textContent = "Bog'lanishda xatolik yuz berdi. Birozdan keyin urinib ko'ring.";
        finishTurn();
      });
  }

  function updateSessionTitle(sessionId, firstMessage) {
    const title = firstMessage.substring(0, 30) + (firstMessage.length > 30 ? '...' : '');
    fetch('/uzdub/api/chat/update_title.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'session_id=' + encodeURIComponent(sessionId) + '&title=' + encodeURIComponent(title) + '&csrf_token=' + encodeURIComponent(csrfToken),
    }).catch(function () {});
  }

  sendBtn.addEventListener('click', send);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') send();
  });

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function formatDate(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = now - date;
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));

    if (days === 0) return 'Bugun';
    if (days === 1) return 'Kecha';
    if (days < 7) return days + ' kun oldin';
    return date.toLocaleDateString('uz-UZ');
  }
});
