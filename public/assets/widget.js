(function () {
    if (window.FArtSupportWidgetLoaded) return;
    window.FArtSupportWidgetLoaded = true;

    const apiBase = window.FArtSupportApiBase || '/support-chat/api/messages.php';
    const downloadBase = apiBase.replace(/messages\.php(?:.*)?$/, 'download.php');
    const unreadBase = apiBase.replace(/messages\.php(?:.*)?$/, 'unread.php');
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
                    <a class="fscw-full-link" href="/checkbot/cabinet/#support">Диалог</a>
                    <button class="fscw-close" type="button">\u0421\u0432\u0435\u0440\u043d\u0443\u0442\u044c</button>
                </div>
            </header>
            <div class="fscw-messages"></div>
            <form class="fscw-composer">
                <input class="fscw-file-input" type="file" multiple hidden>
                <button class="fscw-attach" type="button" title="Добавить файл" aria-label="Добавить файл">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M7.2 13.1 13.8 6.5a3.3 3.3 0 0 1 4.7 4.7l-8.2 8.2a5.1 5.1 0 0 1-7.2-7.2l8.5-8.5"></path>
                    </svg>
                </button>
                <button class="fscw-emoji" type="button" title="Вставить эмодзи" aria-label="Вставить эмодзи">☺</button>
                <textarea class="fscw-input" rows="2" placeholder="\u0412\u0432\u0435\u0434\u0438\u0442\u0435 \u0441\u043e\u043e\u0431\u0449\u0435\u043d\u0438\u0435"></textarea>
                <button class="fscw-send" type="submit">\u041e\u0442\u043f\u0440\u0430\u0432\u0438\u0442\u044c</button>
                <div class="fscw-emoji-picker" hidden></div>
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
    const emojiButton = root.querySelector('.fscw-emoji');
    const emojiPicker = root.querySelector('.fscw-emoji-picker');
    const fileInput = root.querySelector('.fscw-file-input');
    const filePreview = root.querySelector('.fscw-file-preview');
    const status = root.querySelector('.fscw-status');
    const subtitle = root.querySelector('.fscw-subtitle');

    let signature = '';
    let sending = false;
    let selectedFiles = [];
    let pollTimer = null;
    let loadedOnce = false;
    let loadedMessages = [];
    let hasMoreBefore = false;
    let loadingOlder = false;
    const emojiList = ['😀','🙂','👍','🙏','✅','🔥','❤️','😎','🤝','📎'];

    function supportUser() {
        const user = window.FArtSupportUser || {};
        const browserLanguage = String((navigator.languages && navigator.languages[0]) || navigator.language || '').trim();
        const visitorLanguage = String(user.visitor_language || user.language || browserLanguage).trim();
        return {
            visitor_name: String(user.visitor_name || user.name || user.display_name || '').trim(),
            visitor_user_id: String(user.visitor_user_id || user.user_id || user.id || '').trim(),
            visitor_email: String(user.visitor_email || user.email || '').trim(),
            visitor_balance: String(user.visitor_balance ?? user.balance ?? '').trim(),
            visitor_language: visitorLanguage,
            browser_language: browserLanguage,
        };
    }

    function appendSupportUser(formData) {
        const user = supportUser();
        Object.keys(user).forEach((key) => {
            if (user[key]) formData.append(key, user[key]);
        });
    }

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
        const translated = String(message.translated_body || '').trim();
        if (message.sender === 'support' && translated !== '') return escapeHtml(translated);
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

    function insertAtCursor(text) {
        const start = input.selectionStart || 0;
        const end = input.selectionEnd || 0;
        input.value = input.value.slice(0, start) + text + input.value.slice(end);
        input.selectionStart = input.selectionEnd = start + text.length;
        input.focus();
        autosize();
    }

    function openFileModal(url, name, type) {
        let modal = document.querySelector('.fscw-file-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.className = 'fscw-file-modal';
            modal.hidden = true;
            modal.innerHTML = '<div class="fscw-file-modal__backdrop" data-close></div><section class="fscw-file-modal__panel"><button type="button" data-close class="fscw-file-modal__close">x</button><div class="fscw-file-modal__body"></div><a class="fscw-file-modal__download" href="#" download>Скачать</a></section>';
            root.appendChild(modal);
            modal.querySelectorAll('[data-close]').forEach((item) => item.addEventListener('click', () => {
                modal.hidden = true;
                modal.querySelector('.fscw-file-modal__body').innerHTML = '';
            }));
        }
        modal.querySelector('.fscw-file-modal__download').href = url.replace('&inline=1', '');
        modal.querySelector('.fscw-file-modal__download').download = name || 'file';
        modal.querySelector('.fscw-file-modal__body').innerHTML = type === 'video'
            ? '<video controls autoplay src="' + url + '"></video>'
            : type === 'audio'
                ? '<audio controls autoplay src="' + url + '"></audio>'
                : '<img src="' + url + '" alt="' + escapeHtml(name || 'file') + '">';
        modal.hidden = false;
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
        loadUnread();
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
                return '<button type="button" class="fscw-attachment-image" data-preview-url="' + inlineUrl + '" data-preview-type="image" data-preview-name="' + name + '" title="' + name + '"><img src="' + inlineUrl + '" alt="' + name + '"></button>';
            }
            if (type.startsWith('video/')) {
                return '<button type="button" class="fscw-attachment-video-button" data-preview-url="' + inlineUrl + '" data-preview-type="video" data-preview-name="' + name + '">▶ ' + name + '</button>';
            }
            if (type.startsWith('audio/')) {
                return '<button type="button" class="fscw-attachment-video-button" data-preview-url="' + inlineUrl + '" data-preview-type="audio" data-preview-name="' + name + '">♪ ' + name + '</button>';
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

    function render(items, keepScroll = false) {
        const previousHeight = messages.scrollHeight;
        const previousTop = messages.scrollTop;
        const nextSignature = JSON.stringify(items.map((message) => [message.id, message.sender, message.body, message.translated_body || '', message.delivery_error || '', (message.attachments || []).map(a => [a.id, a.original_filename, a.mime_type].join(':')).join(','), message.is_deleted_by_visitor, message.is_deleted_for_user]));
        if (nextSignature === signature) return;
        signature = nextSignature;

        if (items.length === 0) {
            messages.innerHTML = '<div class="fscw-welcome"><strong>\u0417\u0434\u0440\u0430\u0432\u0441\u0442\u0432\u0443\u0439\u0442\u0435!</strong><span>\u041e\u043f\u0438\u0448\u0438\u0442\u0435 \u0432\u043e\u043f\u0440\u043e\u0441 \u043e\u0434\u043d\u0438\u043c \u0441\u043e\u043e\u0431\u0449\u0435\u043d\u0438\u0435\u043c, \u043e\u043f\u0435\u0440\u0430\u0442\u043e\u0440 \u043e\u0442\u0432\u0435\u0442\u0438\u0442 \u0432 \u044d\u0442\u043e\u043c \u0447\u0430\u0442\u0435.</span></div>';
            return;
        }

        messages.innerHTML = items.map((message) => {
            const cls = message.sender === 'support' ? ' is-support' : message.sender === 'system' ? ' is-system' : ' is-visitor';
            const author = message.sender === 'support' ? '\u041f\u043e\u0434\u0434\u0435\u0440\u0436\u043a\u0430' : message.sender === 'system' ? '\u0421\u0438\u0441\u0442\u0435\u043c\u0430' : '\u0412\u044b';
            const deleted = message.is_deleted_for_user ? '<em>Сообщение удалено</em>' : messageBodyText(message);
            const errorClass = message.delivery_error ? ' has-delivery-error' : '';
            const errorNote = message.delivery_error && !message.is_deleted_for_user ? '<div class="fscw-message-error">Ошибка доставки: ' + escapeHtml(message.delivery_error) + '</div>' : '';
            return '<article class="fscw-message' + cls + errorClass + '"><div class="fscw-body">' + deleted + '</div>' + errorNote + (!message.is_deleted_for_user ? renderAttachments(message.attachments || []) : '') + '<div class="fscw-meta">' + author + ' · ' + escapeHtml(time(message.created_at)) + '</div></article>';
        }).join('');
        messages.scrollTop = keepScroll ? messages.scrollHeight - previousHeight + previousTop : messages.scrollHeight;
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

    function load(beforeId = 0) {
        const url = beforeId > 0 ? apiBase + (apiBase.includes('?') ? '&' : '?') + 'before_id=' + encodeURIComponent(beforeId) : apiBase;
        return fetch(url, { credentials: 'include' })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) throw new Error(data.error || '\u041e\u0448\u0438\u0431\u043a\u0430 \u0437\u0430\u0433\u0440\u0443\u0437\u043a\u0438');
                applyState(data.conversation);
                const unread = Number(data.conversation?.unread_visitor || 0);
                count.textContent = !panel.classList.contains('is-open') && unread > 0 ? String(unread) : '';
                hasMoreBefore = Boolean(data.has_more_before);
                if (beforeId > 0) {
                    loadedMessages = (data.messages || []).concat(loadedMessages);
                    render(loadedMessages, true);
                } else {
                    loadedMessages = data.messages || [];
                    render(loadedMessages);
                }
            })
            .catch(() => {
                status.textContent = 'offline';
                subtitle.textContent = '\u041f\u0440\u043e\u0431\u043b\u0435\u043c\u0430 \u0441\u043e\u0435\u0434\u0438\u043d\u0435\u043d\u0438\u044f, \u043f\u0440\u043e\u0431\u0443\u0435\u043c \u043e\u0431\u043d\u043e\u0432\u0438\u0442\u044c \u0447\u0430\u0442';
            });
    }

    function loadUnread() {
        return fetch(unreadBase, { credentials: 'include' })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) return;
                const unread = Number(data.unread || 0);
                count.textContent = !panel.classList.contains('is-open') && unread > 0 ? String(unread) : '';
            })
            .catch(() => {});
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
            appendSupportUser(formData);
            selectedFiles.forEach((file) => formData.append('files[]', file));
            const response = await fetch(apiBase, { method: 'POST', credentials: 'include', body: formData });
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
    emojiPicker.innerHTML = emojiList.map((emoji) => '<button type="button" data-emoji="' + emoji + '">' + emoji + '</button>').join('');
    emojiButton.addEventListener('click', () => {
        emojiPicker.hidden = !emojiPicker.hidden;
    });
    emojiPicker.addEventListener('click', (event) => {
        const button = event.target.closest('[data-emoji]');
        if (!button) return;
        insertAtCursor(button.dataset.emoji);
        emojiPicker.hidden = true;
    });
    messages.addEventListener('click', (event) => {
        const preview = event.target.closest('[data-preview-url]');
        if (!preview) return;
        openFileModal(preview.dataset.previewUrl, preview.dataset.previewName, preview.dataset.previewType);
    });
    messages.addEventListener('scroll', () => {
        if (!hasMoreBefore || loadingOlder || messages.scrollTop > 60 || loadedMessages.length === 0) return;
        loadingOlder = true;
        load(Number(loadedMessages[0].id || 0)).finally(() => {
            loadingOlder = false;
        });
    });
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
    setInterval(loadUnread, 5000);
    loadUnread();
    autosize();
}());
