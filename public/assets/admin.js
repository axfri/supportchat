(function () {
    const tokenInput = document.getElementById('adminToken');
    const authForm = document.getElementById('authForm');
    const conversationList = document.getElementById('conversationList');
    const messages = document.getElementById('messages');
    const chatTitle = document.getElementById('chatTitle');
    const chatSubtitle = document.getElementById('chatSubtitle');
    const channelBadge = document.getElementById('channelBadge');
    const statusBadge = document.getElementById('statusBadge');
    const composer = document.getElementById('composer');
    const messageInput = document.getElementById('messageInput');
    const sendButton = document.getElementById('sendButton');
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const channelFilter = document.getElementById('channelFilter');
    const quickReplies = document.getElementById('quickReplies');
    const clientAvatar = document.getElementById('clientAvatar');
    const clientName = document.getElementById('clientName');
    const clientHandle = document.getElementById('clientHandle');
    const detailChannel = document.getElementById('detailChannel');
    const detailStatus = document.getElementById('detailStatus');
    const detailCreated = document.getElementById('detailCreated');
    const detailUpdated = document.getElementById('detailUpdated');
    const operatorNote = document.getElementById('operatorNote');
    const soundToggle = document.getElementById('soundToggle');

    let token = localStorage.getItem('support_admin_token') || '';
    let soundEnabled = localStorage.getItem('support_sound_enabled') === '1';
    let conversations = [];
    let selectedId = null;
    let selectedConversation = null;
    let messageSignature = '';
    let lastUnreadTotal = 0;
    let sending = false;
    let loadingConversations = false;

    tokenInput.value = token;
    soundToggle.textContent = soundEnabled ? 'Звук: вкл' : 'Звук: выкл';

    function api(path, options) {
        const headers = Object.assign({
            Authorization: 'Bearer ' + token,
        }, options && options.headers ? options.headers : {});

        return fetch(path, Object.assign({}, options || {}, { headers }))
            .then((response) => response.json().then((data) => {
                if (!response.ok || !data.ok) {
                    throw new Error(data.error || 'Ошибка запроса');
                }
                return data;
            }));
    }

    function channelText(channel) {
        return channel === 'telegram' ? 'Telegram' : 'Сайт';
    }

    function statusText(status) {
        return ({ new: 'Новый', open: 'В работе', closed: 'Закрыт' })[status] || 'В работе';
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) return '-';
        const normalized = String(value).replace(' ', 'T') + 'Z';
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function initials(item) {
        const name = (item && (item.visitor_name || item.visitor_handle || item.external_id)) || '?';
        return name.trim().slice(0, 1).toUpperCase();
    }

    function currentQuery() {
        const params = new URLSearchParams();
        const search = searchInput.value.trim();
        if (search) params.set('search', search);
        if (statusFilter.value) params.set('status', statusFilter.value);
        if (channelFilter.value) params.set('channel', channelFilter.value);
        const query = params.toString();
        return query ? '?' + query : '';
    }

    function playNotification() {
        if (!soundEnabled) return;
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            const context = new AudioContext();
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = 820;
            gain.gain.setValueAtTime(0.0001, context.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.08, context.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.22);
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.start();
            oscillator.stop(context.currentTime + 0.24);
        } catch (error) {
            soundEnabled = false;
            localStorage.setItem('support_sound_enabled', '0');
            soundToggle.textContent = 'Звук: выкл';
        }
    }

    function updateTitle(unreadTotal) {
        document.title = unreadTotal > 0 ? '(' + unreadTotal + ') Поддержка - F-ART.bot' : 'Поддержка - F-ART.bot';
    }

    function renderConversations() {
        if (!token) {
            conversationList.innerHTML = '<div class="empty">Введите ключ доступа</div>';
            return;
        }

        if (conversations.length === 0) {
            conversationList.innerHTML = '<div class="empty">Диалогов пока нет</div>';
            return;
        }

        conversationList.innerHTML = conversations.map((item) => {
            const active = Number(item.id) === Number(selectedId) ? ' active' : '';
            const unread = Number(item.unread_support || 0);
            const name = item.visitor_name || item.visitor_handle || 'Клиент';
            const last = item.last_message || 'Нет сообщений';
            const preview = last.length > 94 ? last.slice(0, 91) + '...' : last;
            const time = formatDate(item.last_message_at || item.updated_at);
            return `
                <button class="conversation${active}" data-id="${item.id}" type="button">
                    <span class="avatar">${escapeHtml(initials(item))}</span>
                    <span class="conversation-main">
                        <span class="conversation-top">
                            <strong>${escapeHtml(name)}</strong>
                            <small>${escapeHtml(time)}</small>
                        </span>
                        <span class="last">${escapeHtml(preview)}</span>
                        <span class="conversation-meta">
                            <span class="badge ${item.channel}">${channelText(item.channel)}</span>
                            <span class="badge status-${item.status || 'open'}">${statusText(item.status)}</span>
                            ${unread > 0 ? `<span class="badge unread">${unread}</span>` : ''}
                        </span>
                    </span>
                </button>
            `;
        }).join('');
    }

    function setChatState(item) {
        selectedConversation = item || null;
        const disabled = !item || item.status === 'closed' || sending;

        if (!item) {
            chatTitle.textContent = 'Выберите диалог';
            chatSubtitle.textContent = 'Новые сообщения обновляются автоматически';
            channelBadge.textContent = '-';
            channelBadge.className = 'badge muted';
            statusBadge.textContent = '-';
            statusBadge.className = 'badge muted';
            messageInput.disabled = true;
            sendButton.disabled = true;
            clientAvatar.textContent = '?';
            clientName.textContent = 'Клиент не выбран';
            clientHandle.textContent = 'Выберите диалог слева';
            detailChannel.textContent = '-';
            detailStatus.textContent = '-';
            detailCreated.textContent = '-';
            detailUpdated.textContent = '-';
            operatorNote.value = '';
            operatorNote.disabled = true;
            return;
        }

        const title = item.visitor_name || item.visitor_handle || 'Клиент';
        chatTitle.textContent = title;
        chatSubtitle.textContent = item.visitor_handle || item.external_id || 'Диалог #' + item.id;
        channelBadge.textContent = channelText(item.channel);
        channelBadge.className = 'badge ' + item.channel;
        statusBadge.textContent = statusText(item.status);
        statusBadge.className = 'badge status-' + (item.status || 'open');
        messageInput.disabled = disabled;
        sendButton.disabled = disabled;
        sendButton.textContent = sending ? 'Отправка' : 'Отправить';
        messageInput.placeholder = item.status === 'closed' ? 'Диалог закрыт' : 'Ответить клиенту';

        clientAvatar.textContent = initials(item);
        clientName.textContent = title;
        clientHandle.textContent = item.visitor_handle || item.external_id || 'Без контакта';
        detailChannel.textContent = channelText(item.channel);
        detailStatus.textContent = statusText(item.status);
        detailCreated.textContent = formatDate(item.created_at);
        detailUpdated.textContent = formatDate(item.updated_at);
        operatorNote.disabled = false;
        operatorNote.value = localStorage.getItem('support_note_' + item.id) || '';
    }

    function renderMessages(items) {
        const signature = JSON.stringify(items.map((message) => [message.id, message.sender, message.body]));
        if (signature === messageSignature) {
            return;
        }
        messageSignature = signature;

        if (items.length === 0) {
            messages.innerHTML = '<div class="empty">Сообщений пока нет</div>';
            return;
        }

        let lastDay = '';
        messages.innerHTML = items.map((message) => {
            const day = String(message.created_at || '').slice(0, 10);
            const divider = day && day !== lastDay ? `<div class="day-divider">${escapeHtml(formatDate(message.created_at).split(',')[0] || day)}</div>` : '';
            lastDay = day || lastDay;
            const author = message.sender === 'support' ? 'Оператор' : message.sender === 'system' ? 'Система' : 'Клиент';
            return `
                ${divider}
                <article class="message ${message.sender}">
                    <div class="message-meta">${author} · ${escapeHtml(formatDate(message.created_at))}</div>
                    <div class="message-body">${escapeHtml(message.body)}</div>
                </article>
            `;
        }).join('');
        messages.scrollTop = messages.scrollHeight;
    }

    function selectConversation(id) {
        selectedId = id;
        messageSignature = '';
        const item = conversations.find((conversation) => Number(conversation.id) === Number(id));
        setChatState(item);
        renderConversations();
        loadMessages();
    }

    function loadConversations() {
        if (!token || loadingConversations) {
            renderConversations();
            return Promise.resolve();
        }

        loadingConversations = true;
        return api('api/conversations.php' + currentQuery())
            .then((data) => {
                conversations = data.conversations || [];
                const unreadTotal = conversations.reduce((sum, item) => sum + Number(item.unread_support || 0), 0);
                if (lastUnreadTotal && unreadTotal > lastUnreadTotal) {
                    playNotification();
                }
                lastUnreadTotal = unreadTotal;
                updateTitle(unreadTotal);

                if (selectedId && !conversations.some((item) => Number(item.id) === Number(selectedId))) {
                    selectedId = null;
                    selectedConversation = null;
                    messageSignature = '';
                    messages.innerHTML = '<div class="empty">Выберите диалог</div>';
                }
                if (!selectedId && conversations.length > 0) {
                    selectedId = Number(conversations[0].id);
                }
                renderConversations();
                if (selectedId) {
                    const item = conversations.find((conversation) => Number(conversation.id) === Number(selectedId));
                    setChatState(item);
                    return loadMessages();
                }
                setChatState(null);
                return null;
            })
            .catch((error) => {
                conversationList.innerHTML = '<div class="empty">' + escapeHtml(error.message) + '</div>';
            })
            .finally(() => {
                loadingConversations = false;
            });
    }

    function loadMessages() {
        if (!selectedId || !token) {
            return Promise.resolve();
        }

        return api('api/messages.php?admin=1&conversation_id=' + encodeURIComponent(selectedId))
            .then((data) => {
                if (data.conversation) {
                    selectedConversation = data.conversation;
                    setChatState(data.conversation);
                }
                renderMessages(data.messages || []);
            })
            .catch((error) => {
                messages.innerHTML = '<div class="empty">' + escapeHtml(error.message) + '</div>';
            });
    }

    function autosize() {
        messageInput.style.height = 'auto';
        messageInput.style.height = Math.min(messageInput.scrollHeight, 160) + 'px';
    }

    function insertReply(text) {
        if (!selectedConversation || selectedConversation.status === 'closed') return;
        const current = messageInput.value.trim();
        messageInput.value = current ? current + '\n' + text : text;
        autosize();
        messageInput.focus();
    }

    async function changeStatus(status) {
        if (!selectedId || !token) return;
        try {
            await api('api/conversations.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ conversation_id: selectedId, status }),
            });
            messageSignature = '';
            await loadConversations();
        } catch (error) {
            alert(error.message);
        }
    }

    authForm.addEventListener('submit', (event) => {
        event.preventDefault();
        token = tokenInput.value.trim();
        localStorage.setItem('support_admin_token', token);
        selectedId = null;
        selectedConversation = null;
        messageSignature = '';
        loadConversations();
    });

    function resetSelectionAndLoad() {
        selectedId = null;
        selectedConversation = null;
        messageSignature = '';
        messages.innerHTML = '<div class="empty">Выберите диалог</div>';
        loadConversations();
    }

    searchInput.addEventListener('input', () => {
        window.clearTimeout(searchInput._timer);
        searchInput._timer = window.setTimeout(resetSelectionAndLoad, 250);
    });
    statusFilter.addEventListener('change', resetSelectionAndLoad);
    channelFilter.addEventListener('change', resetSelectionAndLoad);

    conversationList.addEventListener('click', (event) => {
        const button = event.target.closest('.conversation');
        if (button) {
            selectConversation(Number(button.dataset.id));
        }
    });

    quickReplies.addEventListener('click', (event) => {
        const button = event.target.closest('[data-reply]');
        if (button) insertReply(button.dataset.reply);
    });

    document.querySelectorAll('[data-status-action]').forEach((button) => {
        button.addEventListener('click', () => changeStatus(button.dataset.statusAction));
    });

    soundToggle.addEventListener('click', () => {
        soundEnabled = !soundEnabled;
        localStorage.setItem('support_sound_enabled', soundEnabled ? '1' : '0');
        soundToggle.textContent = soundEnabled ? 'Звук: вкл' : 'Звук: выкл';
        if (soundEnabled) playNotification();
    });

    operatorNote.addEventListener('input', () => {
        if (selectedId) {
            localStorage.setItem('support_note_' + selectedId, operatorNote.value);
        }
    });

    messageInput.addEventListener('input', autosize);
    messageInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            composer.requestSubmit();
        }
    });

    composer.addEventListener('submit', async (event) => {
        event.preventDefault();
        const body = messageInput.value.trim();
        if (!body || !selectedId || sending) {
            return;
        }

        sending = true;
        setChatState(selectedConversation);
        try {
            await api('api/messages.php?admin=1', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ conversation_id: selectedId, body }),
            });
            messageInput.value = '';
            autosize();
            messageSignature = '';
            await loadMessages();
            await loadConversations();
        } catch (error) {
            alert(error.message);
        } finally {
            sending = false;
            setChatState(selectedConversation);
        }
    });

    setInterval(loadConversations, 5000);
    setInterval(loadMessages, 2500);
    loadConversations();
}());
