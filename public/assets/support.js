(function () {
    const panel = document.getElementById('supportPanel');
    const launcher = document.getElementById('launcher');
    const launcherCount = document.getElementById('launcherCount');
    const closeChat = document.getElementById('closeChat');
    const messages = document.getElementById('messages');
    const composer = document.getElementById('composer');
    const messageInput = document.getElementById('messageInput');
    const status = document.getElementById('status');
    const subtitle = document.getElementById('subtitle');
    const sendButton = composer.querySelector('button');

    let messageSignature = '';
    let lastSupportCount = 0;
    let sending = false;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) return '';
        const normalized = String(value).replace(' ', 'T') + 'Z';
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    }

    function autosize() {
        messageInput.style.height = 'auto';
        messageInput.style.height = Math.min(messageInput.scrollHeight, 138) + 'px';
    }

    function setOpen(open) {
        panel.classList.toggle('open', open);
        launcher.classList.toggle('hidden', open);
        if (open) {
            launcherCount.textContent = '';
            messageInput.focus();
            messages.scrollTop = messages.scrollHeight;
        }
    }

    function render(items) {
        const signature = JSON.stringify(items.map((message) => [message.id, message.sender, message.body]));
        if (signature === messageSignature) {
            return;
        }
        messageSignature = signature;

        const supportCount = items.filter((message) => message.sender === 'support').length;
        if (!panel.classList.contains('open') && supportCount > lastSupportCount) {
            launcherCount.textContent = String(supportCount - lastSupportCount);
        }
        lastSupportCount = supportCount;

        if (items.length === 0) {
            messages.innerHTML = `
                <div class="welcome">
                    <strong>Здравствуйте!</strong>
                    <span>Опишите вопрос одним сообщением, оператор ответит в этом чате.</span>
                </div>
            `;
            return;
        }

        messages.innerHTML = items.map((message) => `
            <article class="message ${message.sender}">
                <div class="message-body">${escapeHtml(message.body)}</div>
                <div class="message-meta">${message.sender === 'support' ? 'Поддержка' : message.sender === 'system' ? 'Система' : 'Вы'} · ${escapeHtml(formatDate(message.created_at))}</div>
            </article>
        `).join('');
        messages.scrollTop = messages.scrollHeight;
    }

    function applyConversationState(conversation) {
        if (!conversation) return;
        if (conversation.status === 'closed') {
            status.textContent = 'closed';
            subtitle.textContent = 'Диалог закрыт, новое сообщение откроет его снова';
            messageInput.placeholder = 'Напишите новое сообщение';
        } else {
            status.textContent = 'online';
            subtitle.textContent = 'Обычно отвечаем в течение нескольких минут';
            messageInput.placeholder = 'Введите сообщение';
        }
    }

    function loadMessages() {
        return fetch('api/messages.php')
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    throw new Error(data.error || 'Ошибка загрузки');
                }
                applyConversationState(data.conversation);
                render(data.messages || []);
            })
            .catch(() => {
                status.textContent = 'offline';
                subtitle.textContent = 'Проблема соединения, пробуем обновить чат';
            });
    }

    async function sendMessage(body) {
        if (sending) return;
        sending = true;
        sendButton.disabled = true;
        messageInput.disabled = true;
        sendButton.textContent = 'Отправка';
        try {
            const res = await fetch('api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ body }),
            });
            const data = await res.json();
            if (!data.ok) {
                throw new Error(data.error || 'Ошибка отправки');
            }
            messageInput.value = '';
            autosize();
            await loadMessages();
        } catch (err) {
            alert(err.message || 'Ошибка сети');
        } finally {
            sending = false;
            sendButton.disabled = false;
            messageInput.disabled = false;
            sendButton.textContent = 'Отправить';
            messageInput.focus();
        }
    }

    launcher.addEventListener('click', () => setOpen(true));
    closeChat.addEventListener('click', () => setOpen(false));

    messageInput.addEventListener('input', autosize);
    messageInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            composer.requestSubmit();
        }
    });

    composer.addEventListener('submit', (event) => {
        event.preventDefault();
        const body = messageInput.value.trim();
        if (!body) {
            return;
        }
        sendMessage(body);
    });

    setInterval(loadMessages, 2500);
    loadMessages();
    autosize();
}());
