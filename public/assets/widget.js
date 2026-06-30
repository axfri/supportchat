(function () {
    if (window.FArtSupportWidgetLoaded) return;
    window.FArtSupportWidgetLoaded = true;

    const apiBase = window.FArtSupportApiBase || '/support-chat/api/messages.php';
    const downloadBase = apiBase.replace(/messages\.php(?:.*)?$/, 'download.php');
    const root = document.createElement('div');
    root.className = 'fscw-root';
    root.innerHTML = `
        <button class="fscw-launcher" type="button" aria-label="\u041e\u0442\u043a\u0440\u044b\u0442\u044c \u043f\u043e\u0434\u0434\u0435\u0440\u0436\u043a\u0443">
            <span>\u041f\u043e\u0434\u0434\u0435\u0440\u0436\u043a\u0430</span>
            <strong class="fscw-launcher-count"></strong>
        </button>
        <section class="fscw-panel" aria-live="polite">
            <header class="fscw-header">
                <div class="fscw-brand">
                    <span class="fscw-logo"><b>F</b><strong>-ART</strong><small>.bot</small></span>
                    <h2 class="fscw-title">\u041f\u043e\u0434\u0434\u0435\u0440\u0436\u043a\u0430</h2>
                    <p class="fscw-subtitle">\u041e\u0431\u044b\u0447\u043d\u043e \u043e\u0442\u0432\u0435\u0447\u0430\u0435\u043c \u0432 \u0442\u0435\u0447\u0435\u043d\u0438\u0435 \u043d\u0435\u0441\u043a\u043e\u043b\u044c\u043a\u0438\u0445 \u043c\u0438\u043d\u0443\u0442</p>
                </div>
                <div class="fscw-actions">
                    <span class="fscw-status">online</span>
                    <a class="fscw-full-link" href="/support-chat/support.php" target="_blank" rel="noopener">Диалог</a>
                    <button class="fscw-close" type="button">\u0421\u0432\u0435\u0440\u043d\u0443\u0442\u044c</button>
                </div>
            </header>
            <div class="fscw-messages"></div>
            <form class="fscw-composer">
                <textarea class="fscw-input" rows="2" placeholder="\u0412\u0432\u0435\u0434\u0438\u0442\u0435 \u0441\u043e\u043e\u0431\u0449\u0435\u043d\u0438\u0435"></textarea>
                <input class="fscw-file-input" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.mp4,.webm,.mov,.mkv,.avi,.zip,.rar,.7z,.tar,.gz,.tgz" hidden>
                <button class="fscw-attach" type="button" title="\u041f\u0440\u0438\u043a\u0440\u0435\u043f\u0438\u0442\u044c \u0444\u0430\u0439\u043b" aria-label="\u041f\u0440\u0438\u043a\u0440\u0435\u043f\u0438\u0442\u044c \u0444\u0430\u0439\u043b">\uD83D\uDCCE</button>
                <button class="fscw-send" type="submit">\u041e\u0442\u043f\u0440\u0430\u0432\u0438\u0442\u044c</button>
                <div class="fscw-file-preview"></div>
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
    const attach = root.querySelector('.fscw-attach');
    const fileInput = root.querySelector('.fscw-file-input');
    const filePreview = root.querySelector('.fscw-file-preview');
    const status = root.querySelector('.fscw-status');
    const subtitle = root.querySelector('.fscw-subtitle');

    let signature = '';
    let lastSupportCount = 0;
    let sending = false;
    let selectedFiles = [];
    let pollTimer = null;
    let loadedOnce = false;

    function escapeHtml(value) {
        return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function detectTheme() {
        const htmlTheme = document.documentElement.dataset.theme || document.body.dataset.theme;
        const className = (document.documentElement.className + ' ' + document.body.className).toLowerCase();
        const saved = localStorage.getItem('fart_theme') || localStorage.getItem('theme') || localStorage.getItem('support_theme');
        const value = (htmlTheme || saved || '').toLowerCase();
        if (value === 'light' || className.includes('light')) return 'light';
        if (value === 'dark' || className.includes('dark')) return 'dark';
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyWidgetTheme() {
        root.dataset.theme = detectTheme();
    }

    function time(value) {
        if (!value) return '';
        const date = new Date(String(value).replace(' ', 'T') + 'Z');
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    }

    function messageBodyText(message) {
        const attachments = message.attachments || [];
        const body = String(message.body || '').trim();
        if (body !== '' && body !== '[файл]' && body !== 'Файл') return escapeHtml(body);
        if (!attachments.length) return escapeHtml(body || '');
        if (attachments.length === 1) {
            const att = attachments[0];
            const mime = String(att.mime_type || '');
            if (mime.startsWith('image/')) return 'Фото: ' + escapeHtml(att.original_filename || 'изображение');
            if (mime.startsWith('video/')) return 'Видео: ' + escapeHtml(att.original_filename || 'видео');
            return 'Файл: ' + escapeHtml(att.original_filename || 'файл');
        }
        return 'Файлы: ' + attachments.length;
    }

    function autosize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 138) + 'px';
    }

    function startPolling() {
        if (!loadedOnce) {
            loadedOnce = true;
            load();
        }
        if (!pollTimer) pollTimer = setInterval(load, 2500);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function setOpen(open) {
        panel.classList.toggle('is-open', open);
        launcher.classList.toggle('is-hidden', open);
        if (open) {
            count.textContent = '';
            input.focus();
            messages.scrollTop = messages.scrollHeight;
            startPolling();
        } else {
            stopPolling();
        }
    }

    function renderAttachments(attachments) {
        if (!attachments || attachments.length === 0) return '';
        return '<div class="fscw-attachments">' + attachments.map((att) => {
            const name = escapeHtml(att.original_filename || 'file');
            const url = downloadBase + '?id=' + encodeURIComponent(att.id);
            const inlineUrl = url + '&inline=1';
            const type = String(att.mime_type || '');
            if (type.startsWith('image/')) {
                return '<a class="fscw-attachment-image" href="' + inlineUrl + '" target="_blank" title="' + name + '"><img src="' + inlineUrl + '" alt="' + name + '"></a>';
            }
            if (type.startsWith('video/')) {
                return '<video class="fscw-attachment-video" controls preload="metadata"><source src="' + inlineUrl + '" type="' + escapeHtml(type) + '"></video>';
            }
            return '<a class="fscw-attachment-file" href="' + url + '" download="' + name + '" title="' + name + '">\uD83D\uDCCE ' + name + '</a>';
        }).join('') + '</div>';
    }

    function updateFilePreview() {
        if (selectedFiles.length === 0) {
            filePreview.innerHTML = '';
            filePreview.style.display = 'none';
            return;
        }
        filePreview.style.display = 'flex';
        filePreview.innerHTML = selectedFiles.map((file, index) => '<span class="fscw-file-chip">' + escapeHtml(file.name) + '<button type="button" data-file-index="' + index + '">x</button></span>').join('');
    }

    function render(items) {
        const nextSignature = JSON.stringify(items.map((message) => [message.id, message.sender, message.body, message.delivery_error || '', (message.attachments || []).map(a => [a.id, a.original_filename, a.mime_type].join(':')).join(','), message.is_deleted_by_visitor, message.is_deleted_for_user]));
        if (nextSignature === signature) return;
        signature = nextSignature;

        const supportCount = items.filter((message) => message.sender === 'support').length;
        if (!panel.classList.contains('is-open') && supportCount > lastSupportCount) {
            count.textContent = String(supportCount - lastSupportCount);
        }
        lastSupportCount = supportCount;

        if (items.length === 0) {
            messages.innerHTML = '<div class="fscw-welcome"><strong>\u0417\u0434\u0440\u0430\u0432\u0441\u0442\u0432\u0443\u0439\u0442\u0435!</strong><span>\u041e\u043f\u0438\u0448\u0438\u0442\u0435 \u0432\u043e\u043f\u0440\u043e\u0441 \u043e\u0434\u043d\u0438\u043c \u0441\u043e\u043e\u0431\u0449\u0435\u043d\u0438\u0435\u043c, \u043e\u043f\u0435\u0440\u0430\u0442\u043e\u0440 \u043e\u0442\u0432\u0435\u0442\u0438\u0442 \u0432 \u044d\u0442\u043e\u043c \u0447\u0430\u0442\u0435.</span></div>';
            return;
        }

        messages.innerHTML = items.map((message) => {
            const cls = message.sender === 'support' ? ' is-support' : message.sender === 'system' ? ' is-system' : ' is-visitor';
            const author = message.sender === 'support' ? '\u041f\u043e\u0434\u0434\u0435\u0440\u0436\u043a\u0430' : message.sender === 'system' ? '\u0421\u0438\u0441\u0442\u0435\u043c\u0430' : '\u0412\u044b';
            const deleted = message.is_deleted_by_visitor ? '<em>Сообщение удалено</em>' : messageBodyText(message);
            const errorClass = message.delivery_error ? ' has-delivery-error' : '';
            const errorNote = message.delivery_error && !message.is_deleted_by_visitor ? '<div class="fscw-message-error">Ошибка доставки: ' + escapeHtml(message.delivery_error) + '</div>' : '';
            return '<article class="fscw-message' + cls + errorClass + '"><div class="fscw-body">' + deleted + '</div>' + errorNote + (!message.is_deleted_by_visitor ? renderAttachments(message.attachments || []) : '') + '<div class="fscw-meta">' + author + ' · ' + escapeHtml(time(message.created_at)) + '</div></article>';
        }).join('');
        messages.scrollTop = messages.scrollHeight;
    }

    function applyState(conversation) {
        if (!conversation) return;
        if (conversation.status === 'closed') {
            status.textContent = 'closed';
            subtitle.textContent = '\u041d\u043e\u0432\u043e\u0435 \u0441\u043e\u043e\u0431\u0449\u0435\u043d\u0438\u0435 \u043e\u0442\u043a\u0440\u043e\u0435\u0442 \u0434\u0438\u0430\u043b\u043e\u0433 \u0441\u043d\u043e\u0432\u0430';
            input.placeholder = '\u041d\u0430\u043f\u0438\u0448\u0438\u0442\u0435 \u043d\u043e\u0432\u043e\u0435 \u0441\u043e\u043e\u0431\u0449\u0435\u043d\u0438\u0435';
        } else {
            status.textContent = 'online';
            subtitle.textContent = '\u041e\u0431\u044b\u0447\u043d\u043e \u043e\u0442\u0432\u0435\u0447\u0430\u0435\u043c \u0432 \u0442\u0435\u0447\u0435\u043d\u0438\u0435 \u043d\u0435\u0441\u043a\u043e\u043b\u044c\u043a\u0438\u0445 \u043c\u0438\u043d\u0443\u0442';
            input.placeholder = '\u0412\u0432\u0435\u0434\u0438\u0442\u0435 \u0441\u043e\u043e\u0431\u0449\u0435\u043d\u0438\u0435';
        }
    }

    function load() {
        return fetch(apiBase, { credentials: 'same-origin' })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) throw new Error(data.error || '\u041e\u0448\u0438\u0431\u043a\u0430 \u0437\u0430\u0433\u0440\u0443\u0437\u043a\u0438');
                applyState(data.conversation);
                render(data.messages || []);
            })
            .catch(() => {
                status.textContent = 'offline';
                subtitle.textContent = '\u041f\u0440\u043e\u0431\u043b\u0435\u043c\u0430 \u0441\u043e\u0435\u0434\u0438\u043d\u0435\u043d\u0438\u044f, \u043f\u0440\u043e\u0431\u0443\u0435\u043c \u043e\u0431\u043d\u043e\u0432\u0438\u0442\u044c \u0447\u0430\u0442';
            });
    }

    async function sendMessage(body) {
        if (sending) return;
        sending = true;
        send.disabled = true;
        attach.disabled = true;
        input.disabled = true;
        send.textContent = '\u041e\u0442\u043f\u0440\u0430\u0432\u043a\u0430';
        try {
            const formData = new FormData();
            formData.append('body', body);
            selectedFiles.forEach((file) => formData.append('files[]', file));
            const response = await fetch(apiBase, { method: 'POST', credentials: 'same-origin', body: formData });
            const data = await response.json();
            if (!data.ok) throw new Error(data.error || '\u041e\u0448\u0438\u0431\u043a\u0430 \u043e\u0442\u043f\u0440\u0430\u0432\u043a\u0438');
            input.value = '';
            selectedFiles = [];
            fileInput.value = '';
            updateFilePreview();
            autosize();
            await load();
        } catch (error) {
            alert(error.message || '\u041e\u0448\u0438\u0431\u043a\u0430 \u0441\u0435\u0442\u0438');
        } finally {
            sending = false;
            send.disabled = false;
            attach.disabled = false;
            input.disabled = false;
            send.textContent = '\u041e\u0442\u043f\u0440\u0430\u0432\u0438\u0442\u044c';
            input.focus();
        }
    }

    launcher.addEventListener('click', () => setOpen(true));
    close.addEventListener('click', () => setOpen(false));
    attach.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', (event) => {
        selectedFiles = Array.from(event.target.files || []);
        updateFilePreview();
    });
    filePreview.addEventListener('click', (event) => {
        const button = event.target.closest('[data-file-index]');
        if (!button) return;
        selectedFiles.splice(Number(button.dataset.fileIndex), 1);
        updateFilePreview();
    });
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
        if (body || selectedFiles.length > 0) sendMessage(body);
    });

    applyWidgetTheme();
    const observer = new MutationObserver(applyWidgetTheme);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] });
    observer.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme'] });
    window.addEventListener('storage', applyWidgetTheme);
    autosize();
}());
