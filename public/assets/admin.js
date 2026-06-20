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

    let token = localStorage.getItem('support_admin_token') || '';
    let conversations = [];
    let selectedId = null;
    let selectedConversation = null;
    let messageSignature = '';
    let sending = false;
    let loadingConversations = false;

    tokenInput.value = token;

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
        return ({ new: 'Новый', open: 'Открыт', closed: 'Закрыт' })[status] || 'Открыт';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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
            const preview = last.length > 90 ? last.slice(0, 87) + '...' : last;
            const time = item.last_message_at || item.updated_at || '';
            return `
                <button class="conversation${active}" data-id="${item.id}" type="button">
                    <span>
                        <strong>${escapeHtml(name)}</strong>
                        <small>${escapeHtml(item.visitor_handle || time || '')}</small>
                    </span>
                    <span class="badge ${item.channel}">${channelText(item.channel)}</span>
                    <span class="last">${escapeHtml(preview)}</span>
                    <span class="badge status-${item.status || 'open'}">${statusText(item.status)}</span>
                    ${unread > 0 ? `<span class="badge unread">${unread}</span>` : ''}
                </button>
            `;
        }).join('');
    }

    function setChatState(item) {
        selectedConversation = item || null;
        if (!item) {
            chatTitle.textContent = 'Выберите диалог';
            chatSubtitle.textContent = 'Новые сообщения обновляются автоматически';
            channelBadge.textContent = '-';
            channelBadge.className = 'badge muted';
            statusBadge.textContent = '-';
            statusBadge.className = 'badge muted';
            messageInput.disabled = true;
            sendButton.disabled = true;
            return;
        }

        chatTitle.textContent = item.visitor_name || item.visitor_handle || 'Клиент';
        chatSubtitle.textContent = item.visitor_handle || 'Диалог #' + item.id;
        channelBadge.textContent = channelText(item.channel);
        channelBadge.className = 'badge ' + item.channel;
        statusBadge.textContent = statusText(item.status);
        statusBadge.className = 'badge status-' + (item.status || 'open');

        const closed = item.status === 'closed';
        messageInput.disabled = closed || sending;
        sendButton.disabled = closed || sending;
        messageInput.placeholder = closed ? 'Диалог закрыт' : 'Ответить клиенту';
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

        messages.innerHTML = items.map((message) => `
            <article class="message ${message.sender}">
                <div class="message-meta">${message.sender === 'support' ? 'Оператор' : message.sender === 'system' ? 'Система' : 'Клиент'} · ${escapeHtml(message.created_at)}</div>
                <div class="message-body">${escapeHtml(message.body)}</div>
            </article>
        `).join('');
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
