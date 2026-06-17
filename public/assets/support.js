(function () {
    const messages = document.getElementById('messages');
    const composer = document.getElementById('composer');
    const messageInput = document.getElementById('messageInput');
    const status = document.getElementById('status');

    let messageSignature = '';

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function render(items) {
        const signature = JSON.stringify(items.map((message) => [message.id, message.body]));
        if (signature === messageSignature) {
            return;
        }
        messageSignature = signature;

        if (items.length === 0) {
            messages.innerHTML = '<div class="empty">Напишите первое сообщение</div>';
            return;
        }

        messages.innerHTML = items.map((message) => `
            <article class="message ${message.sender === 'support' ? 'support' : 'visitor'}">
                <div class="message-meta">${message.sender === 'support' ? 'Поддержка' : 'Вы'} · ${escapeHtml(message.created_at)}</div>
                <div class="message-body">${escapeHtml(message.body)}</div>
            </article>
        `).join('');
        messages.scrollTop = messages.scrollHeight;
    }

    function loadMessages() {
        return fetch('api/messages.php')
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    throw new Error(data.error || 'Ошибка загрузки');
                }
                status.textContent = 'online';
                render(data.messages || []);
            })
            .catch(() => {
                status.textContent = 'offline';
            });
    }

    composer.addEventListener('submit', (event) => {
        event.preventDefault();
        const body = messageInput.value.trim();
        if (!body) {
            return;
        }
        messageInput.value = '';
        fetch('api/messages.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ body }),
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    throw new Error(data.error || 'Ошибка отправки');
                }
                return loadMessages();
            })
            .catch((error) => {
                alert(error.message);
            });
    });

    setInterval(loadMessages, 2500);
    loadMessages();
}());
