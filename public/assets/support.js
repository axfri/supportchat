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
        themeToggle.textContent = isDark ? '☀️' : '🌙';
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
            const ext = att.original_filename.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext);
            const isVideo = ['mp4', 'webm', 'mov', 'mkv', 'avi'].includes(ext);

            if (isImage) {
                return `<a href="api/download.php?id=${att.id}" class="attachment-image" title="${escapeHtml(att.original_filename)}" target="_blank">
                    <img src="api/download.php?id=${att.id}" alt="${escapeHtml(att.original_filename)}" style="max-width: 200px; max-height: 200px; border-radius: 4px;">
                </a>`;
            } else if (isVideo) {
                return `<video controls style="max-width: 300px; max-height: 200px; border-radius: 4px;" title="${escapeHtml(att.original_filename)}">
                    <source src="api/download.php?id=${att.id}" type="${att.mime_type}">
                    Видео не поддерживается
                </video>`;
            } else {
                return `<a href="api/download.php?id=${att.id}" class="attachment-file" download="${escapeHtml(att.original_filename)}" title="${escapeHtml(att.original_filename)}">
                    📎 ${escapeHtml(att.original_filename)} (${formatFileSize(att.file_size)})
                </a>`;
            }
        }).join('') + '</div>';
    }

    function render(items) {
        const signature = JSON.stringify(items.map((message) => [message.id, message.sender, message.body, (message.attachments || []).map(a => a.id).join(',')]));
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
            const isDeleted = message.is_deleted_by_visitor;
            const isSender = message.sender === 'visitor';
            return `
            <article class="message ${message.sender}${isDeleted ? ' message-deleted' : ''}">
                <div class="message-body">${isDeleted ? '<em>Сообщение удалено</em>' : escapeHtml(message.body)}</div>
                ${!isDeleted ? renderAttachments(message.attachments || []) : ''}
                <div class="message-meta">
                    ${message.sender === 'support' ? 'Поддержка' : message.sender === 'system' ? 'Система' : 'Вы'} · ${escapeHtml(formatDate(message.created_at))}
                    ${isSender && !isDeleted ? `<button class="delete-message-btn" data-message-id="${message.id}" title="Удалить сообщение">✕</button>` : ''}
                </div>
            </article>
        `;
        }).join('');
        messages.scrollTop = messages.scrollHeight;

        // Add delete event listeners
        messages.querySelectorAll('.delete-message-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const messageId = parseInt(btn.dataset.messageId);
                await deleteMessage(messageId, btn);
            });
        });
    }

    async function deleteMessage(messageId, btn) {
        try {
            btn.disabled = true;
            btn.textContent = '...';
            
            const res = await fetch('api/delete_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: messageId }),
            });
            const data = await res.json();
            
            if (!data.ok) {
                showError(data.error || 'Ошибка удаления сообщения');
                btn.disabled = false;
                btn.textContent = '✕';
                return;
            }
            
            await loadMessages();
        } catch (err) {
            showError(err.message || 'Ошибка сети при удалении');
            btn.disabled = false;
            btn.textContent = '✕';
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
    closeChat.addEventListener('click', () => setOpen(false));

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
