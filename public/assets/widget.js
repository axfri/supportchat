(function () {
    if (window.FArtSupportWidgetLoaded) return;
    window.FArtSupportWidgetLoaded = true;

    const apiBase = window.FArtSupportApiBase || '/support-chat/api/messages.php';
    const root = document.createElement('div');
    root.innerHTML = `
        <button class="fscw-launcher" type="button" aria-label="Открыть поддержку">
            <span>Поддержка</span>
            <strong class="fscw-launcher-count"></strong>
        </button>
        <section class="fscw-panel" aria-live="polite">
            <header class="fscw-header">
                <div class="fscw-brand">
                    <span class="fscw-logo"><b>F</b><strong>-ART</strong><small>.bot</small></span>
                    <h2 class="fscw-title">Поддержка</h2>
                    <p class="fscw-subtitle">Обычно отвечаем в течение нескольких минут</p>
                </div>
                <div class="fscw-actions">
                    <span class="fscw-status">online</span>
                    <button class="fscw-close" type="button">Свернуть</button>
                </div>
            </header>
            <div class="fscw-messages"></div>
            <form class="fscw-composer">
                <textarea class="fscw-input" rows="2" placeholder="Введите сообщение"></textarea>
                <button class="fscw-send" type="submit">Отправить</button>
            </form>
        </section>
    `;
    document.body.appendChild(root);

    const launcher = root.querySelector('.fscw-launcher');
    const count = root.querySelector('.fscw-launcher-count');
    const panel = root.querySelector('.fscw-panel');
    const close = root.querySelector('.fscw-close');
    const messages = root.querySelector('.fscw-messages');
    const form = root.querySelector('.fscw-composer');
    const input = root.querySelector('.fscw-input');
    const send = root.querySelector('.fscw-send');
    const status = root.querySelector('.fscw-status');
    const subtitle = root.querySelector('.fscw-subtitle');

    let signature = '';
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

    function time(value) {
        if (!value) return '';
        const date = new Date(String(value).replace(' ', 'T') + 'Z');
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    }

    function autosize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 138) + 'px';
    }

    function setOpen(open) {
        panel.classList.toggle('is-open', open);
        launcher.classList.toggle('is-hidden', open);
        if (open) {
            count.textContent = '';
            input.focus();
            messages.scrollTop = messages.scrollHeight;
        }
    }

    function render(items) {
        const nextSignature = JSON.stringify(items.map((message) => [message.id, message.sender, message.body]));
        if (nextSignature === signature) return;
        signature = nextSignature;

        const supportCount = items.filter((message) => message.sender === 'support').length;
        if (!panel.classList.contains('is-open') && supportCount > lastSupportCount) {
            count.textContent = String(supportCount - lastSupportCount);
        }
        lastSupportCount = supportCount;

        if (items.length === 0) {
            messages.innerHTML = '<div class="fscw-welcome"><strong>Здравствуйте!</strong><span>Опишите вопрос одним сообщением, оператор ответит в этом чате.</span></div>';
            return;
        }

        messages.innerHTML = items.map((message) => {
            const cls = message.sender === 'support' ? ' is-support' : message.sender === 'system' ? ' is-system' : '';
            const author = message.sender === 'support' ? 'Поддержка' : message.sender === 'system' ? 'Система' : 'Вы';
            return '<article class="fscw-message' + cls + '"><div class="fscw-body">' + escapeHtml(message.body) + '</div><div class="fscw-meta">' + author + ' · ' + escapeHtml(time(message.created_at)) + '</div></article>';
        }).join('');
        messages.scrollTop = messages.scrollHeight;
    }

    function applyState(conversation) {
        if (!conversation) return;
        if (conversation.status === 'closed') {
            status.textContent = 'closed';
            subtitle.textContent = 'Новое сообщение откроет диалог снова';
            input.placeholder = 'Напишите новое сообщение';
        } else {
            status.textContent = 'online';
            subtitle.textContent = 'Обычно отвечаем в течение нескольких минут';
            input.placeholder = 'Введите сообщение';
        }
    }

    function load() {
        return fetch(apiBase, { credentials: 'same-origin' })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) throw new Error(data.error || 'Ошибка загрузки');
                applyState(data.conversation);
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
        send.disabled = true;
        input.disabled = true;
        send.textContent = 'Отправка';
        try {
            const response = await fetch(apiBase, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ body }),
            });
            const data = await response.json();
            if (!data.ok) throw new Error(data.error || 'Ошибка отправки');
            input.value = '';
            autosize();
            await load();
        } catch (error) {
            alert(error.message || 'Ошибка сети');
        } finally {
            sending = false;
            send.disabled = false;
            input.disabled = false;
            send.textContent = 'Отправить';
            input.focus();
        }
    }

    launcher.addEventListener('click', () => setOpen(true));
    close.addEventListener('click', () => setOpen(false));
    input.addEventListener('input', autosize);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const body = input.value.trim();
        if (body) sendMessage(body);
    });

    setInterval(load, 2500);
    load();
    autosize();
}());
