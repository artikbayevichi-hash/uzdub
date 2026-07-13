/* ============================================================
   js/ai-chat.js
   UZDUB AI Yordamchi — chat tarixi bilan
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

  if (!fab || !panel) return;

  let greeted = false;
  let currentSessionId = null;

  fab.addEventListener('click', function () {
    panel.classList.toggle('aic-open');
    if (panel.classList.contains('aic-open') && !greeted) {
      loadChatList();
      greeted = true;
    }
  });

  closeBtn.addEventListener('click', function () {
    panel.classList.remove('aic-open');
  });

  backBtn.addEventListener('click', function () {
    showChatList();
  });

  newChatBtn.addEventListener('click', function () {
    createNewChat();
  });

  newChatBtnTop.addEventListener('click', function () {
    createNewChat();
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
    backBtn.style.display = 'block';
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
          addMessage("Assalomu alaykum! Men UZDUB AI yordamchiman. Kino, anime yoki multfilm haqida so'rang.", 'bot');
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
        addMessage("Assalomu alaykum! Men UZDUB AI yordamchiman. Kino, anime yoki multfilm haqida so'rang.", 'bot');
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

  function addMessage(text, from) {
    const div = document.createElement('div');
    div.className = 'aic-msg ' + (from === 'user' ? 'aic-user' : 'aic-bot');
    div.textContent = text;
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
    return div;
  }

  function typeText(element, text, speed, callback) {
    let i = 0;
    element.textContent = '';
    const cursor = document.createElement('span');
    cursor.className = 'typing-cursor';
    cursor.textContent = '|';
    element.appendChild(cursor);

    const interval = setInterval(function () {
      if (i < text.length) {
        element.insertBefore(document.createTextNode(text.charAt(i)), cursor);
        i++;
        log.scrollTop = log.scrollHeight;
      } else {
        clearInterval(interval);
        if (cursor.parentNode) cursor.parentNode.removeChild(cursor);
        if (callback) callback();
      }
    }, speed);
    return interval;
  }

  function send() {
    const text = input.value.trim();
    if (!text || !currentSessionId) return;

    addMessage(text, 'user');
    input.value = '';
    input.disabled = true;
    sendBtn.disabled = true;
    typing.style.display = 'block';

    fetch('/uzdub/api/ai-chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, session_id: currentSessionId, csrf_token: csrfToken }),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        typing.style.display = 'none';
        input.disabled = false;
        sendBtn.disabled = false;
        input.focus();

        const botDiv = addMessage('', 'bot');

        if (data.error) {
          botDiv.textContent = data.error;
        } else {
          typeText(botDiv, data.reply, 30, function () {
            log.scrollTop = log.scrollHeight;
          });
        }

        // Chat sarlavhasini yangilash
        if (currentSessionId) {
          updateSessionTitle(currentSessionId, text);
          // Chat ro'yxatini yangilash
          setTimeout(loadChatList, 500);
        }
      })
      .catch(function () {
        typing.style.display = 'none';
        input.disabled = false;
        sendBtn.disabled = false;
        addMessage("Bog'lanishda xatolik yuz berdi. Birozdan keyin urinib ko'ring.", 'bot');
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
