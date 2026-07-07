(function () {
    const adminLogin = document.getElementById('adminLogin');
    const adminPassword = document.getElementById('adminPassword');
    const authError = document.getElementById('authError');
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
    const fileInput = document.getElementById('fileInput');
    const attachButton = document.getElementById('attachButton');
    const filePreview = document.getElementById('filePreview');
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const channelFilter = document.getElementById('channelFilter');
    const quickReplies = document.getElementById('quickReplies');
    const clientAvatar = document.getElementById('clientAvatar');
    const clientName = document.getElementById('clientName');
    const clientHandle = document.getElementById('clientHandle');
    const detailChannel = document.getElementById('detailChannel');
    const detailStatus = document.getElementById('detailStatus');
    const detailBalance = document.getElementById('detailBalance');
    const detailLanguage = document.getElementById('detailLanguage');
    const detailCreated = document.getElementById('detailCreated');
    const detailUpdated = document.getElementById('detailUpdated');
    const operatorNote = document.getElementById('operatorNote');
    const soundToggle = document.getElementById('soundToggle');
    const adminThemeToggle = document.getElementById('adminThemeToggle');
    const balanceForm = document.getElementById('balanceForm');
    const balanceInput = document.getElementById('balanceInput');
    const balanceComment = document.getElementById('balanceComment');
    const balanceSave = document.getElementById('balanceSave');
    const balanceHistory = document.getElementById('balanceHistory');
    const adminTools = document.getElementById('adminTools');
    const staffForm = document.getElementById('staffForm');
    const staffLogin = document.getElementById('staffLogin');
    const staffPassword = document.getElementById('staffPassword');
    const staffRole = document.getElementById('staffRole');
    const staffList = document.getElementById('staffList');
    const loadTelegramLogs = document.getElementById('loadTelegramLogs');
    const telegramLogList = document.getElementById('telegramLogList');
    const adminPageLink = document.getElementById('adminPageLink');

    let isAuthorized = false;
    let currentRole = '';
    let soundEnabled = localStorage.getItem('support_sound_enabled') === '1';
    let conversations = [];
    let selectedId = null;
    let selectedConversation = null;
    let messageSignature = '';
    let loadedMessages = [];
    let hasMoreMessagesBefore = false;
    let loadingOlderMessages = false;
    let lastUnreadTotal = 0;
    let sending = false;
    let loadingConversations = false;
    let conversationOffset = 0;
    let hasMoreConversations = true;
    let selectedFiles = [];
    let pendingDeleteMessageId = null;
    const emojiList = ['😀','🙂','👍','🙏','✅','🔥','❤️','😎','🤝','📎'];

    soundToggle.textContent = soundEnabled ? 'Звук: вкл' : 'Звук: выкл';

    function setAdminTheme(theme, persist) {
        const nextTheme = theme === 'light' ? 'light' : 'dark';
        document.documentElement.dataset.theme = nextTheme;
        if (adminThemeToggle) {
            adminThemeToggle.textContent = nextTheme === 'dark' ? '☀️' : '🌙';
            adminThemeToggle.title = nextTheme === 'dark' ? 'Светлая тема' : 'Тёмная тема';
        }
        if (persist !== false) {
            localStorage.setItem('support_admin_theme', nextTheme);
        }
    }

    setAdminTheme(document.documentElement.dataset.theme || 'dark', false);

    function api(path, options) {
        const headers = Object.assign({}, options && options.headers ? options.headers : {});

        return fetch(path, Object.assign({}, options || {}, { headers, credentials: 'same-origin' }))
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
        return ({ new: 'Новый', open: 'В работе', closed: 'Закрыт' })[status] || 'В работе';
    }

    const cp1251Reverse = {
        '\u0402': 0x80, '\u0403': 0x81, '\u201a': 0x82, '\u0453': 0x83, '\u201e': 0x84, '\u2026': 0x85, '\u2020': 0x86, '\u2021': 0x87,
        '\u20ac': 0x88, '\u2030': 0x89, '\u0409': 0x8a, '\u2039': 0x8b, '\u040a': 0x8c, '\u040c': 0x8d, '\u040b': 0x8e, '\u040f': 0x8f,
        '\u0452': 0x90, '\u2018': 0x91, '\u2019': 0x92, '\u201c': 0x93, '\u201d': 0x94, '\u2022': 0x95, '\u2013': 0x96, '\u2014': 0x97,
        '\u2122': 0x99, '\u0459': 0x9a, '\u203a': 0x9b, '\u045a': 0x9c, '\u045c': 0x9d, '\u045b': 0x9e, '\u045f': 0x9f,
        '\u00a0': 0xa0, '\u040e': 0xa1, '\u045e': 0xa2, '\u0408': 0xa3, '\u00a4': 0xa4, '\u0490': 0xa5, '\u00a6': 0xa6, '\u00a7': 0xa7,
        '\u0401': 0xa8, '\u00a9': 0xa9, '\u0404': 0xaa, '\u00ab': 0xab, '\u00ac': 0xac, '\u00ad': 0xad, '\u00ae': 0xae, '\u0407': 0xaf,
        '\u00b0': 0xb0, '\u00b1': 0xb1, '\u0406': 0xb2, '\u0456': 0xb3, '\u0491': 0xb4, '\u00b5': 0xb5, '\u00b6': 0xb6, '\u00b7': 0xb7,
        '\u0451': 0xb8, '\u2116': 0xb9, '\u0454': 0xba, '\u00bb': 0xbb, '\u0458': 0xbc, '\u0405': 0xbd, '\u0455': 0xbe, '\u0457': 0xbf,
    };

    function cp1251Byte(char) {
        const code = char.charCodeAt(0);
        if (code <= 0x7f) return code;
        if (code >= 0x0410 && code <= 0x044f) return code - 0x350;
        return cp1251Reverse[char] || null;
    }

    function repairText(value) {
        const text = String(value || '');
        if (!/[РС][\u0400-\u052f\u2018\u2019\u201c\u201d]/.test(text) && !/[ÐÑ]/.test(text)) return text;
        try {
            if (typeof TextDecoder === 'undefined') return text;
            const bytes = [];
            for (const char of text) {
                const byte = cp1251Byte(char);
                if (byte === null) return text;
                bytes.push(byte);
            }
            return new TextDecoder('utf-8', { fatal: true }).decode(new Uint8Array(bytes));
        } catch (error) {
            return text;
        }
    }

    function displayText(value) {
        return escapeHtml(repairText(value));
    }

    function dialogLabel(item) {
        return 'ID диалога: ' + (item && item.id ? item.id : '-');
    }

    function adminDownloadUrl(att, inline) {
        const params = new URLSearchParams();
        params.set('id', att.id);
        params.set('admin', '1');
        if (inline) params.set('inline', '1');
        return 'api/download.php?' + params.toString();
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
        if (!value) return '-';
        const normalized = String(value).replace(' ', 'T') + 'Z';
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        return (bytes / (1024 * 1024 * 1024)).toFixed(1) + ' GB';
    }

    function normalizeMoneyInput(input) {
        const value = String(input || '').trim().replace(/\s+/g, '').replace(',', '.');
        return /^-?\d+(\.\d{1,2})?$/.test(value) ? value : '';
    }

    function initials(item) {
        const name = (item && item.visitor_name) || '?';
        return name.trim().slice(0, 1).toUpperCase();
    }

    function avatarMarkup(item, className = 'avatar') {
        const url = item && item.visitor_avatar_url ? String(item.visitor_avatar_url) : '';
        if (url) {
            return `<span class="${className} has-image"><img src="${escapeHtml(url)}" alt=""></span>`;
        }
        return `<span class="${className}">${escapeHtml(initials(item))}</span>`;
    }

    function applyAvatar(element, item) {
        if (!element) return;
        const url = item && item.visitor_avatar_url ? String(item.visitor_avatar_url) : '';
        element.classList.toggle('has-image', Boolean(url));
        element.innerHTML = url ? `<img src="${escapeHtml(url)}" alt="">` : escapeHtml(initials(item));
    }

    function languageLabel(item) {
        return repairText((item && (item.language_label || item.visitor_language || item.browser_language)) || '');
    }

    function currentQuery() {
        const params = new URLSearchParams();
        params.set('limit', '30');
        params.set('offset', String(conversationOffset));
        const search = searchInput.value.trim();
        if (search) params.set('search', search);
        if (statusFilter.value) params.set('status', statusFilter.value);
        if (channelFilter.value) params.set('channel', channelFilter.value);
        const query = params.toString();
        return query ? '?' + query : '';
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
        if (composer.querySelector('.emoji-picker')) return;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'icon-button emoji-button';
        button.title = 'Вставить эмодзи';
        button.setAttribute('aria-label', 'Вставить эмодзи');
        button.textContent = '☺';
        const picker = document.createElement('div');
        picker.className = 'emoji-picker';
        picker.hidden = true;
        picker.innerHTML = emojiList.map((emoji) => `<button type="button" data-emoji="${emoji}">${emoji}</button>`).join('');
        attachButton.parentElement.insertBefore(button, attachButton.nextSibling);
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
    }

    function ensureFileModal() {
        let modal = document.getElementById('adminFileModal');
        if (modal) return modal;
        modal = document.createElement('div');
        modal.id = 'adminFileModal';
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
        download.href = url.replace('&inline=1', '');
        download.download = name || 'file';
        body.innerHTML = type === 'video'
            ? `<video controls autoplay src="${url}"></video>`
            : `<img src="${url}" alt="${escapeHtml(name || 'file')}">`;
        modal.hidden = false;
    }

    function playNotification() {
        if (!soundEnabled) return;
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            const context = new AudioContext();
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = 820;
            gain.gain.setValueAtTime(0.0001, context.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.08, context.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.22);
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.start();
            oscillator.stop(context.currentTime + 0.24);
        } catch (error) {
            soundEnabled = false;
            localStorage.setItem('support_sound_enabled', '0');
            soundToggle.textContent = 'Звук: выкл';
        }
    }

    function updateTitle(unreadTotal) {
        document.title = unreadTotal > 0 ? '(' + unreadTotal + ') Поддержка - F-ART.bot' : 'Поддержка - F-ART.bot';
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
                <button type="button" class="remove-file" data-index="${i}" title="Удалить">x</button>
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

    function messageBodyText(message) {
        const attachments = message.attachments || [];
        const body = repairText(message.body || '').trim();
        if (body !== '' && body !== '[файл]' && body !== 'Файл') return displayText(body);
        if (!attachments.length) return displayText(body || '');
        if (attachments.length === 1) {
            const att = attachments[0];
            const mime = String(att.mime_type || '');
            if (mime.startsWith('image/')) return 'Фото: ' + displayText(att.original_filename || 'изображение');
            if (mime.startsWith('video/')) return 'Видео: ' + displayText(att.original_filename || 'видео');
            return 'Файл: ' + displayText(att.original_filename || 'файл');
        }
        return 'Файлы: ' + attachments.length;
    }
    function renderAttachments(attachments, disableLinks = false) {
        if (!attachments || attachments.length === 0) return '';

        return '<div class="attachments">' + attachments.map((att) => {
            const ext = att.original_filename.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext);
            const isVideo = ['mp4', 'webm', 'mov', 'mkv', 'avi'].includes(ext);
            const inlineUrl = adminDownloadUrl(att, true);
            const downloadUrl = adminDownloadUrl(att, false);

            if (isImage) {
                if (disableLinks) {
                    return `<div class="attachment-image-disabled" title="${displayText(att.original_filename)}">
                        <img src="${inlineUrl}" alt="${displayText(att.original_filename)}" style="max-width: 200px; max-height: 200px; border-radius: 4px; opacity: .7; filter: grayscale(30%);">
                    </div>`;
                }
                return `<button type="button" data-preview-url="${inlineUrl}" data-preview-type="image" data-preview-name="${displayText(att.original_filename)}" class="attachment-image" title="${displayText(att.original_filename)}">
                    <img src="${inlineUrl}" alt="${displayText(att.original_filename)}" style="max-width: 200px; max-height: 200px; border-radius: 4px;">
                </button>`;
            } else if (isVideo) {
                if (disableLinks) {
                    return `<div class="attachment-video-disabled" title="${displayText(att.original_filename)}">
                        <video style="max-width: 300px; max-height: 200px; border-radius: 4px;" title="${displayText(att.original_filename)}">
                            <source src="${inlineUrl}" type="${att.mime_type}">
                        </video>
                    </div>`;
                }
                return `<button type="button" data-preview-url="${inlineUrl}" data-preview-type="video" data-preview-name="${displayText(att.original_filename)}" class="attachment-video-button" title="${displayText(att.original_filename)}">▶ ${displayText(att.original_filename)}</button>`;
            } else {
                if (disableLinks) {
                    return `<div class="attachment-file-disabled" title="${displayText(att.original_filename)}">📎 ${displayText(att.original_filename)} (${formatFileSize(att.file_size)})</div>`;
                }
                return `<a href="${downloadUrl}" class="attachment-file" download="${displayText(att.original_filename)}" title="${displayText(att.original_filename)}">
                    📎 ${displayText(att.original_filename)} (${formatFileSize(att.file_size)})
                </a>`;
            }
        }).join('') + '</div>';
    }

    function showError(errorMessage) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'admin-error-message';
        errorDiv.textContent = 'Ошибка: ' + repairText(errorMessage);
        messages.parentElement.insertBefore(errorDiv, messages);
        
        setTimeout(() => {
            if (errorDiv.parentElement) {
                errorDiv.remove();
            }
        }, 6000);
    }

    function ensureDeleteConfirm() {
        let modal = document.getElementById('deleteConfirmModal');
        if (modal) {
            return modal;
        }

        modal = document.createElement('div');
        modal.id = 'deleteConfirmModal';
        modal.className = 'confirm-modal';
        modal.hidden = true;
        modal.innerHTML = `
            <div class="confirm-modal__backdrop" data-confirm-close></div>
            <section class="confirm-modal__panel" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
                <div class="confirm-modal__icon">!</div>
                <div class="confirm-modal__content">
                    <h3 id="deleteConfirmTitle">Удалить сообщение?</h3>
                    <p>Сообщение исчезнет у пользователя, но останется в истории поддержки серым.</p>
                </div>
                <div class="confirm-modal__actions">
                    <button type="button" class="confirm-modal__cancel" data-confirm-close>Отмена</button>
                    <button type="button" class="confirm-modal__delete" id="deleteConfirmAction">Удалить</button>
                </div>
            </section>
        `;
        document.body.appendChild(modal);

        modal.querySelectorAll('[data-confirm-close]').forEach((button) => {
            button.addEventListener('click', closeDeleteConfirm);
        });

        modal.querySelector('#deleteConfirmAction').addEventListener('click', async () => {
            if (!pendingDeleteMessageId) {
                closeDeleteConfirm();
                return;
            }

            const action = modal.querySelector('#deleteConfirmAction');
            action.disabled = true;
            action.textContent = 'Удаляем...';

            try {
                const deleted = await deleteMessageForUser(pendingDeleteMessageId);
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

    function openDeleteConfirm(messageId) {
        pendingDeleteMessageId = messageId;
        const modal = ensureDeleteConfirm();
        modal.hidden = false;
        document.body.classList.add('confirm-open');
        setTimeout(() => modal.querySelector('.confirm-modal__cancel')?.focus(), 0);
    }

    function closeDeleteConfirm() {
        const modal = document.getElementById('deleteConfirmModal');
        if (modal) {
            modal.hidden = true;
        }
        pendingDeleteMessageId = null;
        document.body.classList.remove('confirm-open');
    }

    function renderConversations() {
        if (!isAuthorized) {
            conversationList.innerHTML = '<div class="empty">Войдите в панель оператора</div>';
            return;
        }

        if (conversations.length === 0) {
            conversationList.innerHTML = '<div class="empty">Диалогов пока нет</div>';
            return;
        }

        conversationList.innerHTML = conversations.map((item) => {
            const active = Number(item.id) === Number(selectedId) ? ' active' : '';
            const unread = Number(item.unread_support || 0);
            const name = repairText(item.visitor_name || item.visitor_handle || 'Клиент');
            const last = repairText(item.last_message || 'Нет сообщений');
            const preview = last.length > 94 ? last.slice(0, 91) + '...' : last;
            const time = formatDate(item.last_message_at || item.updated_at);
            const language = languageLabel(item);
            return `
                <button class="conversation${active}" data-id="${item.id}" type="button">
                    ${avatarMarkup(item)}
                    <span class="conversation-main">
                        <span class="conversation-top">
                            <strong>${displayText(name)}</strong>
                            <small>${escapeHtml(time)}</small>
                        </span>
                        <span class="last">${displayText(preview)}</span>
                        <span class="conversation-meta">
                            <span class="badge ${item.channel}">${channelText(item.channel)}</span>
                            <span class="badge status-${item.status || 'open'}">${statusText(item.status)}</span>
                            ${language ? `<span class="badge lang">${displayText(language)}</span>` : ''}
                            ${unread > 0 ? `<span class="badge unread">${unread}</span>` : ''}
                        </span>
                    </span>
                </button>
            `;
        }).join('');
    }

    function setChatState(item) {
        selectedConversation = item || null;
        const disabled = !item || item.status === 'closed' || sending;

        if (!item) {
            chatTitle.textContent = 'Выберите диалог';
            chatSubtitle.textContent = 'Новые сообщения обновляются автоматически';
            channelBadge.textContent = '-';
            channelBadge.className = 'badge muted';
            statusBadge.textContent = '-';
            statusBadge.className = 'badge muted';
            messageInput.disabled = true;
            sendButton.disabled = true;
            attachButton.disabled = true;
            clientAvatar.classList.remove('has-image');
            clientAvatar.textContent = '?';
            clientName.textContent = 'Клиент не выбран';
            clientHandle.textContent = 'Выберите диалог слева';
            detailChannel.textContent = '-';
            detailStatus.textContent = '-';
            detailBalance.textContent = '0.00';
            if (detailLanguage) detailLanguage.textContent = '-';
            detailCreated.textContent = '-';
            detailUpdated.textContent = '-';
            operatorNote.value = '';
            operatorNote.disabled = true;
            balanceInput.disabled = true;
            balanceComment.disabled = true;
            balanceSave.disabled = true;
            balanceHistory.innerHTML = '';
            return;
        }

        const title = repairText(item.visitor_name || 'Клиент');
        chatTitle.textContent = title;
        chatSubtitle.textContent = dialogLabel(item);
        channelBadge.textContent = channelText(item.channel);
        channelBadge.className = 'badge ' + item.channel;
        statusBadge.textContent = statusText(item.status);
        statusBadge.className = 'badge status-' + (item.status || 'open');
        messageInput.disabled = disabled;
        sendButton.disabled = disabled;
        attachButton.disabled = disabled;
        sendButton.textContent = sending ? 'Отправка' : 'Отправить';
        messageInput.placeholder = item.status === 'closed' ? 'Диалог закрыт' : 'Ответить клиенту';

        applyAvatar(clientAvatar, item);
        clientName.textContent = title;
        clientHandle.textContent = [dialogLabel(item), languageLabel(item) ? 'Язык: ' + languageLabel(item) : ''].filter(Boolean).join(' · ');
        detailChannel.textContent = channelText(item.channel);
        detailStatus.textContent = statusText(item.status);
        detailBalance.textContent = Number(item.balance || 0).toFixed(2);
        if (detailLanguage) detailLanguage.textContent = languageLabel(item) || '-';
        balanceInput.value = Number(item.balance || 0).toFixed(2);
        balanceInput.disabled = false;
        balanceComment.disabled = false;
        balanceSave.disabled = false;
        detailCreated.textContent = formatDate(item.created_at);
        detailUpdated.textContent = formatDate(item.updated_at);
        operatorNote.disabled = false;
        operatorNote.value = localStorage.getItem('support_note_' + item.id) || '';
        loadBalance();
    }

    function renderBalanceHistory(items) {
        balanceHistory.innerHTML = (items || []).map((item) => `
            <div class="admin-mini-item">
                <strong>${Number(item.old_balance).toFixed(2)} → ${Number(item.new_balance).toFixed(2)}</strong>
                <span>${displayText(item.staff_login || 'admin')} · ${escapeHtml(formatDate(item.created_at))}</span>
                ${item.comment ? `<small>${displayText(item.comment)}</small>` : ''}
            </div>
        `).join('');
    }

    async function loadBalance() {
        if (!selectedId) return;
        try {
            const data = await api('api/balance.php?conversation_id=' + encodeURIComponent(selectedId));
            detailBalance.textContent = Number(data.balance || 0).toFixed(2);
            balanceInput.value = Number(data.balance || 0).toFixed(2);
            renderBalanceHistory(data.history || []);
        } catch (error) {
            balanceHistory.innerHTML = '<div class="empty-inline">' + escapeHtml(error.message) + '</div>';
        }
    }

    function renderStaff(items) {
        staffList.innerHTML = (items || []).map((item) => `
            <div class="admin-mini-item">
                <strong>${displayText(item.login)} · ${escapeHtml(item.role)}</strong>
                <span>${item.is_blocked ? 'заблокирован' : 'активен'}</span>
                <button type="button" data-staff-action="password" data-staff-id="${item.id}">Пароль</button>
                <button type="button" data-staff-action="${item.is_blocked ? 'unblock' : 'block'}" data-staff-id="${item.id}">${item.is_blocked ? 'Разблокировать' : 'Блокировать'}</button>
            </div>
        `).join('');
    }

    async function loadStaff() {
        if (currentRole !== 'admin' || !staffList) return;
        const data = await api('api/staff.php');
        renderStaff(data.staff || []);
    }

    async function loadLogs() {
        if (currentRole !== 'admin' || !telegramLogList) return;
        const data = await api('api/telegram_logs.php?limit=80');
        telegramLogList.innerHTML = (data.logs || []).map((item) => `
            <div class="admin-mini-item">
                <strong>${escapeHtml(item.direction)} · ${escapeHtml(item.action)} · ${item.success ? 'ok' : 'error'}</strong>
                <span>${escapeHtml(formatDate(item.created_at))} · chat ${escapeHtml(item.telegram_chat_id || '-')}</span>
                ${item.error ? `<small>${displayText(item.error)}</small>` : ''}
            </div>
        `).join('');
    }

    async function deleteMessageForUser(messageId) {
        try {
            const result = await api('api/messages.php?admin=1', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_for_user', message_id: messageId }),
            });
            if (result.telegram_errors && result.telegram_errors.length) {
                showError('Сообщение удалено на сайте, но не удалено в Telegram: ' + result.telegram_errors.join('\n'));
            }
            await loadMessages();
            return true;
        } catch (error) {
            showError(error.message || 'Не удалось удалить сообщение');
            return false;
        }
    }

    function renderMessages(items, keepScroll = false) {
        const previousHeight = messages.scrollHeight;
        const previousTop = messages.scrollTop;
        const signature = JSON.stringify(items.map((message) => [message.id, message.sender, message.body, message.delivery_error || '', (message.attachments || []).map(a => [a.id, a.original_filename, a.mime_type].join(':')).join(','), message.is_deleted_by_visitor, message.is_deleted_for_user]));
        if (signature === messageSignature) {
            return;
        }
        messageSignature = signature;

        if (items.length === 0) {
        messages.innerHTML = '<div class="empty">Выберите диалог</div>';
            return;
        }

        let lastDay = '';
        messages.innerHTML = items.map((message) => {
            const day = String(message.created_at || '').slice(0, 10);
            const divider = day && day !== lastDay ? `<div class="day-divider">${escapeHtml(formatDate(message.created_at).split(',')[0] || day)}</div>` : '';
            lastDay = day || lastDay;
            const author = message.sender === 'support' ? 'Оператор' : message.sender === 'system' ? 'Система' : 'Клиент';
            const isDeletedForUser = Boolean(message.is_deleted_for_user);
            const isDeleted = isDeletedForUser;
            const deleteLabel = isDeletedForUser ? 'Удалено оператором' : '';
            return `
                ${divider}
                <article class="message ${message.sender}${isDeleted ? ' message-deleted' : ''}${message.delivery_error ? ' message-delivery-error' : ''}">
                    <div class="message-head">
                        <div class="message-meta">
                            ${author} · ${escapeHtml(formatDate(message.created_at))}
                            ${isDeleted ? `<span class="message-delete-state">• ${escapeHtml(deleteLabel)}</span>` : ''}
                        </div>
                        ${message.sender !== 'system' && !isDeleted ? `<button class="delete-message-btn" data-message-id="${message.id}" type="button" aria-label="Удалить сообщение"><span></span></button>` : ''}
                    </div>
                    <div class="message-body">${messageBodyText(message)}</div>
                    ${message.delivery_error ? `<div class="message-error-note">Не доставлено в Telegram: ${displayText(message.delivery_error)}</div>` : ``}
                    ${renderAttachments(message.attachments || [], isDeleted)}
                </article>
            `;
        }).join('');
        messages.scrollTop = keepScroll ? messages.scrollHeight - previousHeight + previousTop : messages.scrollHeight;

        messages.querySelectorAll('.delete-message-btn').forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                const messageId = Number(button.dataset.messageId);
                if (Number.isFinite(messageId)) {
                    openDeleteConfirm(messageId);
                }
            });
        });
    }

    function selectConversation(id) {
        selectedId = id;
        messageSignature = '';
        loadedMessages = [];
        hasMoreMessagesBefore = false;
        const item = conversations.find((conversation) => Number(conversation.id) === Number(id));
        setChatState(item);
        renderConversations();
        loadMessages();
    }

    function loadConversations(appendPage = false) {
        if (!isAuthorized || loadingConversations) {
            renderConversations();
            return Promise.resolve();
        }

        if (!appendPage) {
            conversationOffset = 0;
            hasMoreConversations = true;
        }
        loadingConversations = true;
        const append = appendPage && conversationOffset > 0;
        return api('api/conversations.php' + currentQuery())
            .then((data) => {
                conversations = append ? conversations.concat(data.conversations || []) : (data.conversations || []);
                hasMoreConversations = Boolean(data.has_more);
                conversationOffset = Number(data.next_offset || conversations.length);
                const unreadTotal = conversations.reduce((sum, item) => sum + Number(item.unread_support || 0), 0);
                if (lastUnreadTotal && unreadTotal > lastUnreadTotal) {
                    playNotification();
                }
                lastUnreadTotal = unreadTotal;
                updateTitle(unreadTotal);

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
                if (/Unauthorized/i.test(error.message)) {
                    setAuthorizedState(false);
                    renderConversations();
                    showAuthError('Сессия оператора истекла. Войдите снова.');
                    return;
                }
                conversationList.innerHTML = '<div class="empty">' + escapeHtml(error.message) + '</div>';
            })
            .finally(() => {
                loadingConversations = false;
            });
    }

    function loadMessages(beforeId = 0) {
        if (!selectedId || !isAuthorized) {
            return Promise.resolve();
        }

        let url = 'api/messages.php?admin=1&conversation_id=' + encodeURIComponent(selectedId);
        if (beforeId > 0) {
            url += '&before_id=' + encodeURIComponent(beforeId);
        }
        return api(url)
            .then((data) => {
                conversations = conversations.map((item) => {
                    if (Number(item.id) === Number(selectedId)) {
                        return { ...item, unread_support: 0 };
                    }
                    return item;
                });
                lastUnreadTotal = conversations.reduce((sum, item) => sum + Number(item.unread_support || 0), 0);
                updateTitle(lastUnreadTotal);
                renderConversations();
                if (data.conversation) {
                    selectedConversation = data.conversation;
                    setChatState(data.conversation);
                }
                hasMoreMessagesBefore = Boolean(data.has_more_before);
                if (beforeId > 0) {
                    loadedMessages = (data.messages || []).concat(loadedMessages);
                    renderMessages(loadedMessages, true);
                } else {
                    loadedMessages = data.messages || [];
                    renderMessages(loadedMessages);
                }
            })
            .catch((error) => {
                messages.innerHTML = '<div class="empty">' + escapeHtml(error.message) + '</div>';
            });
    }

    function autosize() {
        messageInput.style.height = 'auto';
        messageInput.style.height = Math.min(messageInput.scrollHeight, 160) + 'px';
    }

    function insertReply(text) {
        if (!selectedConversation || selectedConversation.status === 'closed') return;
        const current = messageInput.value.trim();
        messageInput.value = current ? current + '\n' + text : text;
        autosize();
        messageInput.focus();
    }

    async function changeStatus(status) {
        if (!selectedId || !isAuthorized) return;
        try {
            await api('api/conversations.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ conversation_id: selectedId, status }),
            });
            messageSignature = '';
            await loadConversations();
        } catch (error) {
            alert(error.message);
        }
    }

    function showAuthError(message) {
        if (!authError) return;
        authError.textContent = message || '';
        authError.hidden = !message;
    }

    function setAuthorizedState(value) {
        isAuthorized = Boolean(value);
        if (!value) {
            window.location.href = 'login.php';
            return;
        }
        if (authForm) {
            authForm.classList.toggle('auth-ok', isAuthorized);
            authForm.hidden = isAuthorized;
            authForm.style.display = isAuthorized ? 'none' : '';
        }
    }

    if (authForm) authForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        showAuthError('');
        const login = adminLogin ? adminLogin.value.trim() : '';
        const password = adminPassword ? adminPassword.value : '';
        try {
            const loginData = await api('api/admin_login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ login, password }),
            });
            currentRole = loginData.role || '';
            if (adminTools) adminTools.hidden = currentRole !== 'admin';
            if (adminPageLink) adminPageLink.hidden = currentRole !== 'admin';
            if (adminPassword) adminPassword.value = '';
            setAuthorizedState(true);
            initEmojiPicker();
            selectedId = null;
            selectedConversation = null;
            messageSignature = '';
            await loadConversations();
            await loadStaff();
        } catch (error) {
            setAuthorizedState(false);
            showAuthError(error.message || 'Не удалось войти');
        }
    });

    function resetSelectionAndLoad() {
        selectedId = null;
        selectedConversation = null;
        messageSignature = '';
        conversationOffset = 0;
        hasMoreConversations = true;
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
    conversationList.addEventListener('scroll', () => {
        if (!hasMoreConversations || loadingConversations) return;
        if (conversationList.scrollTop + conversationList.clientHeight >= conversationList.scrollHeight - 80) {
            loadConversations(true);
        }
    });

    quickReplies.addEventListener('click', (event) => {
        const button = event.target.closest('[data-reply]');
        if (button) insertReply(button.dataset.reply);
    });

    document.querySelectorAll('[data-status-action]').forEach((button) => {
        button.addEventListener('click', () => changeStatus(button.dataset.statusAction));
    });

    soundToggle.addEventListener('click', () => {
        soundEnabled = !soundEnabled;
        localStorage.setItem('support_sound_enabled', soundEnabled ? '1' : '0');
        soundToggle.textContent = soundEnabled ? 'Звук: вкл' : 'Звук: выкл';
        if (soundEnabled) playNotification();
    });

    balanceForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!selectedId) return;
        const normalizedBalance = normalizeMoneyInput(balanceInput.value);
        if (!normalizedBalance) {
            balanceHistory.innerHTML = '<div class="empty-inline">Введите корректный баланс, например 902.00</div>';
            return;
        }
        const saveText = balanceSave.textContent;
        balanceSave.disabled = true;
        balanceSave.textContent = 'Сохранение...';
        try {
            await api('api/balance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    conversation_id: selectedId,
                    balance: normalizedBalance,
                    comment: balanceComment.value.trim(),
                }),
            });
            balanceComment.value = '';
            await loadBalance();
            await loadConversations();
        } catch (error) {
            showError(error.message || 'Не удалось изменить баланс');
        }
        balanceSave.disabled = false;
        balanceSave.textContent = saveText;
    });

    if (staffForm) staffForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            await api('api/staff.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create',
                    login: staffLogin.value.trim(),
                    password: staffPassword.value,
                    role: staffRole.value,
                }),
            });
            staffLogin.value = '';
            staffPassword.value = '';
            await loadStaff();
        } catch (error) {
            showError(error.message || 'Не удалось добавить менеджера');
        }
    });

    if (staffList) staffList.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-staff-action]');
        if (!button) return;
        try {
            let payload = { action: button.dataset.staffAction, id: Number(button.dataset.staffId) };
            if (button.dataset.staffAction === 'password') {
                const password = window.prompt('Новый пароль менеджера');
                if (!password) return;
                payload.password = password;
            }
            await api('api/staff.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            await loadStaff();
        } catch (error) {
            showError(error.message || 'Не удалось изменить менеджера');
        }
    });

    if (loadTelegramLogs) loadTelegramLogs.addEventListener('click', loadLogs);

    if (adminThemeToggle) {
        adminThemeToggle.addEventListener('click', () => {
            const current = document.documentElement.dataset.theme === 'light' ? 'light' : 'dark';
            setAdminTheme(current === 'light' ? 'dark' : 'light', true);
        });
    }

    operatorNote.addEventListener('input', () => {
        if (selectedId) {
            localStorage.setItem('support_note_' + selectedId, operatorNote.value);
        }
    });

    attachButton.addEventListener('click', (e) => {
        e.preventDefault();
        fileInput.click();
    });

    fileInput.addEventListener('change', (e) => {
        selectedFiles = Array.from(e.target.files || []);
        updateFilePreview();
    });
    messages.addEventListener('click', (event) => {
        const preview = event.target.closest('[data-preview-url]');
        if (!preview) return;
        openFileModal(preview.dataset.previewUrl, preview.dataset.previewName, preview.dataset.previewType);
    });
    messages.addEventListener('scroll', () => {
        if (!hasMoreMessagesBefore || loadingOlderMessages || messages.scrollTop > 60 || loadedMessages.length === 0) return;
        loadingOlderMessages = true;
        loadMessages(Number(loadedMessages[0].id || 0)).finally(() => {
            loadingOlderMessages = false;
        });
    });

    messageInput.addEventListener('input', autosize);
    messageInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            composer.requestSubmit();
        }
    });

    composer.addEventListener('submit', async (event) => {
        event.preventDefault();
        const body = messageInput.value.trim();
        if ((!body && selectedFiles.length === 0) || !selectedId || sending) {
            return;
        }

        sending = true;
        setChatState(selectedConversation);
        try {
            const formData = new FormData();
            formData.append('conversation_id', selectedId);
            formData.append('body', body);
            for (const file of selectedFiles) {
                formData.append('files[]', file);
            }

            const result = await api('api/messages.php?admin=1', {
                method: 'POST',
                body: formData,
            });
            if (result.upload_errors && result.upload_errors.length) {
                showError(result.upload_errors.join('\n'));
            }
            messageInput.value = '';
            selectedFiles = [];
            updateFilePreview();
            autosize();
            messageSignature = '';
            await loadMessages();
            await loadConversations();
        } catch (error) {
            showError(error.message || 'Не удалось отправить сообщение');
        } finally {
            sending = false;
            setChatState(selectedConversation);
        }
    });

    setInterval(loadConversations, 5000);
    setInterval(loadMessages, 2500);
    api('api/admin_login.php')
        .then((data) => {
            currentRole = data.role || '';
            if (adminTools) adminTools.hidden = currentRole !== 'admin';
            if (adminPageLink) adminPageLink.hidden = currentRole !== 'admin';
            setAuthorizedState(Boolean(data.authenticated));
            if (isAuthorized) {
                initEmojiPicker();
                loadConversations();
                loadStaff();
            } else {
                renderConversations();
            }
        })
        .catch(() => {
            setAuthorizedState(false);
            renderConversations();
        });
}());
