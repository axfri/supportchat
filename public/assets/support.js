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
    const fileInput = document.getElementById('fileInput');
    const attachButton = document.getElementById('attachButton');
    const filePreview = document.getElementById('filePreview');
    const sendButton = composer.querySelector('.submit-button');
    const themeToggle = document.getElementById('themeToggle');
    const THEME_STORAGE_KEY = 'support_theme';
    const THEMES = {
        LIGHT: 'light',
        DARK: 'dark',
    };

    let messageSignature = '';
    let loadedMessages = [];
    let hasMoreBefore = false;
    let loadingOlder = false;

    function getSavedTheme() {
        return localStorage.getItem(THEME_STORAGE_KEY);
    }

    function getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? THEMES.DARK : THEMES.LIGHT;
    }

    function updateThemeToggleButton(theme) {
        if (!themeToggle) return;
        const isDark = theme === THEMES.DARK;
        themeToggle.innerHTML = `<span aria-hidden="true">${isDark ? '☼' : '◐'}</span><b>${isDark ? 'Светлая' : 'Тёмная'}</b>`;
        themeToggle.title = isDark ? 'Светлая тема' : 'Тёмная тема';
        themeToggle.setAttribute('aria-label', isDark ? 'Переключить на светлую тему' : 'Переключить на тёмную тему');
        themeToggle.setAttribute('aria-pressed', String(isDark));
    }

    function applyTheme(theme, persist = true) {
        if (![THEMES.LIGHT, THEMES.DARK].includes(theme)) {
            theme = THEMES.LIGHT;
        }

        document.documentElement.dataset.theme = theme;
        updateThemeToggleButton(theme);

        if (persist) {
            localStorage.setItem(THEME_STORAGE_KEY, theme);
        }
    }

    function initTheme() {
        const savedTheme = getSavedTheme();
        const theme = savedTheme || getSystemTheme();
        applyTheme(theme, Boolean(savedTheme));

        if (!savedTheme && window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            const handleSystemChange = (event) => {
                applyTheme(event.matches ? THEMES.DARK : THEMES.LIGHT, false);
            };

            if (typeof mediaQuery.addEventListener === 'function') {
                mediaQuery.addEventListener('change', handleSystemChange);
            } else if (typeof mediaQuery.addListener === 'function') {
                mediaQuery.addListener(handleSystemChange);
            }
        }
    }

    let sending = false;
    let selectedFiles = [];
    const emojiList = ['😀','🙂','👍','🙏','✅','🔥','❤️','😎','🤝','📎'];

    function supportUser() {
        const user = window.FArtSupportUser || {};
        return {
            visitor_name: String(user.visitor_name || user.name || user.display_name || '').trim(),
            visitor_user_id: String(user.visitor_user_id || user.user_id || user.id || '').trim(),
            visitor_email: String(user.visitor_email || user.email || '').trim(),
            visitor_balance: String(user.visitor_balance ?? user.balance ?? '').trim(),
        };
    }

    function appendSupportUser(formData) {
        const user = supportUser();
        Object.keys(user).forEach((key) => {
            if (user[key]) formData.append(key, user[key]);
        });
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
        if (!value) return '';
        const normalized = String(value).replace(' ', 'T') + 'Z';
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        return (bytes / (1024 * 1024 * 1024)).toFixed(1) + ' GB';
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

    function insertAtCursor(input, text) {
        const start = input.selectionStart || 0;
        const end = input.selectionEnd || 0;
        input.value = input.value.slice(0, start) + text + input.value.slice(end);
        input.selectionStart = input.selectionEnd = start + text.length;
        input.focus();
        autosize();
    }

    function initEmojiPicker() {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'emoji-button';
        button.title = 'Вставить эмодзи';
        button.setAttribute('aria-label', 'Вставить эмодзи');
        button.textContent = '☺';

        const picker = document.createElement('div');
        picker.className = 'emoji-picker';
        picker.hidden = true;
        picker.innerHTML = emojiList.map((emoji) => `<button type="button" data-emoji="${emoji}">${emoji}</button>`).join('');
        composer.insertBefore(button, messageInput);
        composer.appendChild(picker);

        button.addEventListener('click', () => {
            picker.hidden = !picker.hidden;
        });
        picker.addEventListener('click', (event) => {
            const option = event.target.closest('[data-emoji]');
            if (!option) return;
            insertAtCursor(messageInput, option.dataset.emoji);
            picker.hidden = true;
        });
        document.addEventListener('click', (event) => {
            if (!picker.hidden && !picker.contains(event.target) && event.target !== button) {
                picker.hidden = true;
            }
        });
    }

    function ensureFileModal() {
        let modal = document.getElementById('supportFileModal');
        if (modal) return modal;
        modal = document.createElement('div');
        modal.id = 'supportFileModal';
        modal.className = 'file-view-modal';
        modal.hidden = true;
        modal.innerHTML = `
            <div class="file-view-modal__backdrop" data-file-close></div>
            <section class="file-view-modal__panel" role="dialog" aria-modal="true">
                <button type="button" class="file-view-modal__close" data-file-close aria-label="Закрыть">x</button>
                <div class="file-view-modal__body"></div>
                <a class="file-view-modal__download" href="#" download>Скачать</a>
            </section>
        `;
        document.body.appendChild(modal);
        modal.querySelectorAll('[data-file-close]').forEach((item) => item.addEventListener('click', () => {
            modal.hidden = true;
            modal.querySelector('.file-view-modal__body').innerHTML = '';
        }));
        return modal;
    }

    function openFileModal(url, name, type) {
        const modal = ensureFileModal();
        const body = modal.querySelector('.file-view-modal__body');
        const download = modal.querySelector('.file-view-modal__download');
        download.href = url.replace('&inline=1', '').replace('?inline=1', '');
        download.download = name || 'file';
        if (type === 'video') {
            body.innerHTML = `<video controls autoplay src="${url}"></video>`;
        } else {
            body.innerHTML = `<img src="${url}" alt="${escapeHtml(name || 'file')}">`;
        }
        modal.hidden = false;
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

    function updateFilePreview() {
        if (selectedFiles.length === 0) {
            filePreview.style.display = 'none';
            filePreview.innerHTML = '';
            return;
        }

        filePreview.innerHTML = selectedFiles.map((file, i) => `
            <div class="file-item">
                <span class="file-name">${escapeHtml(file.name)}</span>
                <span class="file-size">${formatFileSize(file.size)}</span>
                <button type="button" class="remove-file" data-index="${i}" title="Удалить">✕</button>
            </div>
        `).join('');

        filePreview.style.display = 'block';

        filePreview.querySelectorAll('.remove-file').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const idx = parseInt(btn.dataset.index);
                selectedFiles.splice(idx, 1);
                updateFilePreview();
            });
        });
    }

    function renderAttachments(attachments) {
        if (!attachments || attachments.length === 0) return '';

        return '<div class="attachments">' + attachments.map((att) => {
            const ext = String(att.original_filename || '').split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext);
            const isVideo = ['mp4', 'webm', 'mov', 'mkv', 'avi'].includes(ext);

            if (isImage) {
                return `<button type="button" data-preview-url="api/download.php?id=${att.id}&inline=1" data-preview-type="image" data-preview-name="${escapeHtml(att.original_filename)}" class="attachment-image" title="${escapeHtml(att.original_filename)}">
                    <img src="api/download.php?id=${att.id}&inline=1" alt="${escapeHtml(att.original_filename)}" style="max-width: 200px; max-height: 200px; border-radius: 4px;">
                </button>`;
            } else if (isVideo) {
                return `<button type="button" data-preview-url="api/download.php?id=${att.id}&inline=1" data-preview-type="video" data-preview-name="${escapeHtml(att.original_filename)}" class="attachment-video-button" title="${escapeHtml(att.original_filename)}">▶ ${escapeHtml(att.original_filename)}</button>`;
            } else {
                return `<a href="api/download.php?id=${att.id}" class="attachment-file" download="${escapeHtml(att.original_filename)}" title="${escapeHtml(att.original_filename)}">
                    📎 ${escapeHtml(att.original_filename)} (${formatFileSize(att.file_size)})
                </a>`;
            }
        }).join('') + '</div>';
    }

    function render(items, keepScroll = false) {
        const previousHeight = messages.scrollHeight;
        const previousTop = messages.scrollTop;
        const signature = JSON.stringify(items.map((message) => [message.id, message.sender, message.body, message.delivery_error || '', (message.attachments || []).map(a => [a.id, a.original_filename, a.mime_type].join(':')).join(','), message.is_deleted_by_visitor, message.is_deleted_for_user]));
        if (signature === messageSignature) {
            return;
        }
        messageSignature = signature;

        if (items.length === 0) {
            messages.innerHTML = `
                <div class="welcome">
                    <strong>Здравствуйте!</strong>
                    <span>Опишите вопрос одним сообщением, оператор ответит в этом чате.</span>
                </div>
            `;
            return;
        }

        messages.innerHTML = items.map((message) => {
            const isDeleted = Boolean(message.is_deleted_for_user);
            const senderClass = message.sender === 'support' ? 'support' : message.sender === 'system' ? 'system' : 'visitor';
            const author = message.sender === 'support' ? 'Поддержка' : message.sender === 'system' ? 'Система' : 'Вы';
            return `
            <div class="message-row ${senderClass}${isDeleted ? ' message-deleted' : ''}">
                <div class="message-line">
                    <article class="message ${senderClass}${message.delivery_error ? ' message-delivery-error' : ''}">
                        <div class="message-body">${isDeleted ? '<em>Сообщение удалено</em>' : messageBodyText(message)}</div>
                        ${!isDeleted && message.delivery_error ? `<div class="message-error-note">Ошибка доставки: ${escapeHtml(message.delivery_error)}</div>` : ``}
                        ${!isDeleted ? renderAttachments(message.attachments || []) : ''}
                    </article>
                </div>
                <div class="message-meta">
                    <span>${author} · ${escapeHtml(formatDate(message.created_at))}</span>
                </div>
            </div>
        `;
        }).join('');
        messages.scrollTop = keepScroll ? messages.scrollHeight - previousHeight + previousTop : messages.scrollHeight;
    }

    function showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = '⚠ ' + message;
        messages.parentElement.insertBefore(errorDiv, messages);
        
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
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

    function loadMessages(beforeId = 0) {
        const url = beforeId > 0 ? 'api/messages.php?before_id=' + encodeURIComponent(beforeId) : 'api/messages.php';
        return fetch(url)
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    throw new Error(data.error || 'Ошибка загрузки');
                }
                applyConversationState(data.conversation);
                const unread = Number(data.conversation?.unread_visitor || 0);
                launcherCount.textContent = !panel.classList.contains('open') && unread > 0 ? String(unread) : '';
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
                subtitle.textContent = 'Проблема соединения, пробуем обновить чат';
            });
    }

    async function sendMessage(body) {
        if (sending) return;
        sending = true;
        sendButton.disabled = true;
        messageInput.disabled = true;
        attachButton.disabled = true;
        sendButton.textContent = 'Отправка';
        try {
            const formData = new FormData();
            formData.append('body', body);
            appendSupportUser(formData);
            for (const file of selectedFiles) {
                formData.append('files[]', file);
            }

            const res = await fetch('api/messages.php', {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();
            if (!data.ok) {
                throw new Error(data.error || 'Ошибка отправки');
            }
            messageInput.value = '';
            selectedFiles = [];
            updateFilePreview();
            autosize();
            await loadMessages();
        } catch (err) {
            alert(err.message || 'Ошибка сети');
        } finally {
            sending = false;
            sendButton.disabled = false;
            messageInput.disabled = false;
            attachButton.disabled = false;
            sendButton.textContent = 'Отправить';
            messageInput.focus();
        }
    }

    launcher.addEventListener('click', () => setOpen(true));
    if (closeChat) closeChat.addEventListener('click', () => setOpen(false));

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.dataset.theme || THEMES.LIGHT;
            applyTheme(currentTheme === THEMES.DARK ? THEMES.LIGHT : THEMES.DARK);
        });
    }

    attachButton.addEventListener('click', (e) => {
        e.preventDefault();
        fileInput.click();
    });

    fileInput.addEventListener('change', (e) => {
        selectedFiles = Array.from(e.target.files || []);
        updateFilePreview();
    });
    messages.addEventListener('scroll', () => {
        if (!hasMoreBefore || loadingOlder || messages.scrollTop > 60 || loadedMessages.length === 0) return;
        loadingOlder = true;
        loadMessages(Number(loadedMessages[0].id || 0)).finally(() => {
            loadingOlder = false;
        });
    });
    messages.addEventListener('click', (event) => {
        const preview = event.target.closest('[data-preview-url]');
        if (!preview) return;
        openFileModal(preview.dataset.previewUrl, preview.dataset.previewName, preview.dataset.previewType);
    });

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
        if (!body && selectedFiles.length === 0) {
            return;
        }
        sendMessage(body);
    });

    initTheme();
    initEmojiPicker();
    setInterval(loadMessages, 2500);
    loadMessages();
    autosize();
}());
