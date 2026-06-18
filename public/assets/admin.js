(function () {
    const tokenInput = document.getElementById('adminToken');
    const authForm = document.getElementById('authForm');
    const refreshButton = document.getElementById('refreshButton');
    const conversationList = document.getElementById('conversationList');
    const messages = document.getElementById('messages');
    const chatTitle = document.getElementById('chatTitle');
    const chatSubtitle = document.getElementById('chatSubtitle');
    const channelBadge = document.getElementById('channelBadge');
    const composer = document.getElementById('composer');
    const messageInput = document.getElementById('messageInput');
    const sendButton = document.getElementById('sendButton');

    let token = localStorage.getItem('support_admin_token') || '';
    let conversations = [];
    let selectedId = null;
    let messageSignature = '';
    let sending = false;

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

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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
            const time = item.last_message_at || item.updated_at || '';
            return `
                <button class="conversation${active}" data-id="${item.id}" type="button">
                    <span>
                        <strong>${escapeHtml(name)}</strong>
                        <small>${escapeHtml(item.visitor_handle || time || '')}</small>
                    </span>
                    <span class="badge ${item.channel}">${channelText(item.channel)}</span>
                    <span class="last">${escapeHtml(last.length > 80 ? last.slice(0, 77) + '...' : last)}</span>
                    ${unread > 0 ? `<span class="badge unread">${unread}</span>` : ''}
                </button>
            `;
        }).join('');
    }

    function renderMessages(items) {
        const signature = JSON.stringify(items.map((message) => [message.id, message.body]));
        if (signature === messageSignature) {
            return;
        }
        messageSignature = signature;

        if (items.length === 0) {
            messages.innerHTML = '<div class="empty">Сообщений пока нет</div>';
            return;
        }

        messages.innerHTML = items.map((message) => `
            <article class="message ${message.sender === 'support' ? 'support' : 'visitor'}">
                <div class="message-meta">${message.sender === 'support' ? 'Оператор' : 'Клиент'} · ${escapeHtml(message.created_at)}</div>
                <div class="message-body">${escapeHtml(message.body)}</div>
            </article>
        `).join('');
        messages.scrollTop = messages.scrollHeight;
    }

    function selectConversation(id) {
        selectedId = id;
        messageSignature = '';
        const item = conversations.find((conversation) => Number(conversation.id) === Number(id));
        if (item) {
            chatTitle.textContent = item.visitor_name || item.visitor_handle || 'Клиент';
            chatSubtitle.textContent = item.visitor_handle || 'Диалог #' + item.id;
            channelBadge.textContent = channelText(item.channel);
            channelBadge.className = 'badge ' + item.channel;
        }
        messageInput.disabled = false;
        sendButton.disabled = false;
        renderConversations();
        loadMessages();
    }

    function loadConversations() {
        if (!token) {
            renderConversations();
            return Promise.resolve();
        }

        return api('api/conversations.php')
            .then((data) => {
                conversations = data.conversations || [];
                if (!selectedId && conversations.length > 0) {
                    selectedId = Number(conversations[0].id);
                }
                renderConversations();
                if (selectedId) {
                    const stillExists = conversations.some((item) => Number(item.id) === Number(selectedId));
                    if (stillExists) {
                        selectConversation(selectedId);
                    }
                }
            })
            .catch((error) => {
                conversationList.innerHTML = '<div class="empty">' + escapeHtml(error.message) + '</div>';
            });
    }

    function loadMessages() {
        if (!selectedId || !token) {
            return Promise.resolve();
        }

        return api('api/messages.php?admin=1&conversation_id=' + encodeURIComponent(selectedId))
            .then((data) => renderMessages(data.messages || []))
            .catch((error) => {
                messages.innerHTML = '<div class="empty">' + escapeHtml(error.message) + '</div>';
            });
    }

    authForm.addEventListener('submit', (event) => {
        event.preventDefault();
        token = tokenInput.value.trim();
        localStorage.setItem('support_admin_token', token);
        loadConversations();
    });

    refreshButton.addEventListener('click', () => {
        loadConversations();
    });

    conversationList.addEventListener('click', (event) => {
        const button = event.target.closest('.conversation');
        if (button) {
            selectConversation(Number(button.dataset.id));
        }
    });

    composer.addEventListener('submit', (event) => {
        event.preventDefault();
        const body = messageInput.value.trim();
        if (!body || !selectedId) {
            return;
        }
        messageInput.value = '';
        api('api/messages.php?admin=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversation_id: selectedId, body }),
        }).then(() => {
            loadMessages();
            loadConversations();
        }).catch((error) => {
            alert(error.message);
        });
    });

    setInterval(loadConversations, 5000);
    setInterval(loadMessages, 2500);
    loadConversations();
}());
