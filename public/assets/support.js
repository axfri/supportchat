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

    let lastSupportCount = 0;
    let sending = false;
    let selectedFiles = [];
    let pendingDeleteMessageId = null;
    let pendingDeleteButton = null;

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
                return `<a href="api/download.php?id=${att.id}&inline=1" class="attachment-image" title="${escapeHtml(att.original_filename)}" target="_blank">
                    <img src="api/download.php?id=${att.id}&inline=1" alt="${escapeHtml(att.original_filename)}" style="max-width: 200px; max-height: 200px; border-radius: 4px;">
                </a>`;
            } else if (isVideo) {
                return `<video controls style="max-width: 300px; max-height: 200px; border-radius: 4px;" title="${escapeHtml(att.original_filename)}">
                    <source src="api/download.php?id=${att.id}&inline=1" type="${escapeHtml(att.mime_type)}">
                    Видео не поддерживается
                </video>`;
            } else {
                return `<a href="api/download.php?id=${att.id}" class="attachment-file" download="${escapeHtml(att.original_filename)}" title="${escapeHtml(att.original_filename)}">
                    📎 ${escapeHtml(att.original_filename)} (${formatFileSize(att.file_size)})
                </a>`;
            }
        }).join('') + '</div>';
    }

    function trashIcon() {
        return `
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M9 3h6l1 2h4v2H4V5h4l1-2Z"></path>
                <path d="M6.5 9h11l-.7 10.2A2 2 0 0 1 14.8 21H9.2a2 2 0 0 1-2-1.8L6.5 9Z"></path>
                <path d="M10 11.5v6M14 11.5v6"></path>
            </svg>
        `;
    }

    function ensureDeleteConfirm() {
        let modal = document.getElementById('visitorDeleteConfirmModal');
        if (modal) {
            return modal;
        }

        modal = document.createElement('div');
        modal.id = 'visitorDeleteConfirmModal';
        modal.className = 'visitor-confirm-modal';
        modal.hidden = true;
        modal.innerHTML = `
            <div class="visitor-confirm-modal__backdrop" data-delete-cancel></div>
            <section class="visitor-confirm-modal__panel" role="dialog" aria-modal="true" aria-labelledby="visitorDeleteConfirmTitle">
                <div class="visitor-confirm-modal__icon">!</div>
                <div class="visitor-confirm-modal__content">
                    <h3 id="visitorDeleteConfirmTitle">Удалить сообщение?</h3>
                    <p>Сообщение исчезнет у вас, но останется в истории поддержки серым.</p>
                </div>
                <div class="visitor-confirm-modal__actions">
                    <button type="button" class="visitor-confirm-modal__cancel" data-delete-cancel>Отмена</button>
                    <button type="button" class="visitor-confirm-modal__delete" id="visitorDeleteConfirmAction">Удалить</button>
                </div>
            </section>
        `;
        document.body.appendChild(modal);

        modal.querySelectorAll('[data-delete-cancel]').forEach((button) => {
            button.addEventListener('click', closeDeleteConfirm);
        });
        modal.querySelector('#visitorDeleteConfirmAction').addEventListener('click', async () => {
            if (!pendingDeleteMessageId) {
                closeDeleteConfirm();
                return;
            }

            const action = modal.querySelector('#visitorDeleteConfirmAction');
            action.disabled = true;
            action.textContent = 'Удаляем...';
            try {
                const deleted = await deleteMessage(pendingDeleteMessageId, pendingDeleteButton);
                if (deleted) {
                    closeDeleteConfirm();
                }
            } finally {
                action.disabled = false;
                action.textContent = 'Удалить';
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                closeDeleteConfirm();
            }
        });

        return modal;
    }

    function openDeleteConfirm(messageId, button) {
        pendingDeleteMessageId = messageId;
        pendingDeleteButton = button;
        const modal = ensureDeleteConfirm();
        modal.hidden = false;
        document.body.classList.add('visitor-confirm-open');
        setTimeout(() => modal.querySelector('.visitor-confirm-modal__cancel')?.focus(), 0);
    }

    function closeDeleteConfirm() {
        const modal = document.getElementById('visitorDeleteConfirmModal');
        if (modal) {
            modal.hidden = true;
        }
        pendingDeleteMessageId = null;
        pendingDeleteButton = null;
        document.body.classList.remove('visitor-confirm-open');
    }

    function render(items) {
        const signature = JSON.stringify(items.map((message) => [message.id, message.sender, message.body, message.delivery_error || '', (message.attachments || []).map(a => [a.id, a.original_filename, a.mime_type].join(':')).join(','), message.is_deleted_by_visitor, message.is_deleted_for_user]));
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

        messages.innerHTML = items.map((message) => {
            const isDeleted = Boolean(message.is_deleted_by_visitor || message.is_deleted_for_user);
            const isSender = message.sender === 'visitor';
            const senderClass = message.sender === 'support' ? 'support' : message.sender === 'system' ? 'system' : 'visitor';
            const author = message.sender === 'support' ? 'Поддержка' : message.sender === 'system' ? 'Система' : 'Вы';
            const deleteButton = isSender && !isDeleted
                ? `<button class="delete-message-btn" data-message-id="${message.id}" title="Удалить сообщение" aria-label="Удалить сообщение">${trashIcon()}</button>`
                : '';
            return `
            <div class="message-row ${senderClass}${isDeleted ? ' message-deleted' : ''}">
                <div class="message-line">
                    ${deleteButton}
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
        messages.scrollTop = messages.scrollHeight;

        // Add delete event listeners
        messages.querySelectorAll('.delete-message-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const messageId = parseInt(btn.dataset.messageId);
                openDeleteConfirm(messageId, btn);
            });
        });
    }

    async function deleteMessage(messageId, btn) {
        try {
            btn.disabled = true;
            btn.classList.add('is-loading');
            
            const res = await fetch('api/delete_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: messageId }),
            });
            const data = await res.json();
            
            if (!data.ok) {
                showError(data.error || 'Ошибка удаления сообщения');
                btn.disabled = false;
                btn.classList.remove('is-loading');
                return false;
            }
            
            await loadMessages();
            return true;
        } catch (err) {
            showError(err.message || 'Ошибка сети при удалении');
            btn.disabled = false;
            btn.classList.remove('is-loading');
            return false;
        }
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
        attachButton.disabled = true;
        sendButton.textContent = 'Отправка';
        try {
            const formData = new FormData();
            formData.append('body', body);
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
    setInterval(loadMessages, 2500);
    loadMessages();
    autosize();
}());
