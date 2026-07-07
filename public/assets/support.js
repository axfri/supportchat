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
    const SUPPORT_I18N = {
        ru: {
            support: 'Поддержка', home: 'Главная', webPanel: 'Web панель', theme: 'Тема', light: 'Светлая', dark: 'Тёмная',
            lightTheme: 'Светлая тема', darkTheme: 'Тёмная тема', switchLight: 'Переключить на светлую тему', switchDark: 'Переключить на тёмную тему',
            onlineSupport: 'Онлайн-поддержка', title: 'Напишите нам, мы рядом',
            text: 'Напишите, что случилось. Мы спокойно разберёмся, подскажем по шагам и вернёмся с ответом прямо в этом диалоге.',
            nearby: 'Рядом', nearbyText: 'поможем без лишней суеты', webText: 'единая история чата',
            dialog: 'Диалог с поддержкой', subtitle: 'Обычно отвечаем в течение нескольких минут',
            placeholder: 'Введите сообщение', newPlaceholder: 'Напишите новое сообщение', send: 'Отправить', sending: 'Отправка',
            hello: 'Здравствуйте!', welcome: 'Опишите вопрос одним сообщением, оператор ответит в этом чате.',
            supportAuthor: 'Поддержка', systemAuthor: 'Система', you: 'Вы', closed: 'Диалог закрыт, новое сообщение откроет его снова',
            connectionProblem: 'Проблема соединения, пробуем обновить чат', addFile: 'Добавить файл', insertEmoji: 'Вставить эмодзи',
            photo: 'Фото', video: 'Видео', file: 'Файл', files: 'Файлы', deleted: 'Сообщение удалено', deliveryError: 'Ошибка доставки',
            loadError: 'Ошибка загрузки', sendError: 'Ошибка отправки', networkError: 'Ошибка сети', download: 'Скачать',
        },
        en: {
            support: 'Support', home: 'Home', webPanel: 'Web panel', theme: 'Theme', light: 'Light', dark: 'Dark',
            lightTheme: 'Light theme', darkTheme: 'Dark theme', switchLight: 'Switch to light theme', switchDark: 'Switch to dark theme',
            onlineSupport: 'Online support', title: 'Message us, we are nearby',
            text: 'Tell us what happened. We will check it calmly, guide you step by step and reply in this dialog.',
            nearby: 'Nearby', nearbyText: 'we will help without extra noise', webText: 'single chat history',
            dialog: 'Support dialog', subtitle: 'We usually reply within a few minutes',
            placeholder: 'Enter a message', newPlaceholder: 'Write a new message', send: 'Send', sending: 'Sending',
            hello: 'Hello!', welcome: 'Describe your question in one message, an operator will reply in this chat.',
            supportAuthor: 'Support', systemAuthor: 'System', you: 'You', closed: 'The dialog is closed, a new message will reopen it',
            connectionProblem: 'Connection problem, trying to refresh the chat', addFile: 'Add file', insertEmoji: 'Insert emoji',
            photo: 'Photo', video: 'Video', file: 'File', files: 'Files', deleted: 'Message deleted', deliveryError: 'Delivery error',
            loadError: 'Loading error', sendError: 'Sending error', networkError: 'Network error', download: 'Download',
        },
        tg: { support: 'Дастгирӣ', home: 'Асосӣ', webPanel: 'Web панел', light: 'Равшан', dark: 'Торик', onlineSupport: 'Дастгирии онлайн', title: 'Ба мо нависед, мо наздикем', text: 'Нависед, чӣ шуд. Мо оромона месанҷем ва дар ҳамин чат ҷавоб медиҳем.', nearby: 'Наздик', nearbyText: 'бе саросемагӣ кӯмак мекунем', webText: 'таърихи ягонаи чат', dialog: 'Чат бо дастгирӣ', subtitle: 'Одатан дар чанд дақиқа ҷавоб медиҳем', placeholder: 'Паём нависед', send: 'Фиристодан', sending: 'Фиристода мешавад', hello: 'Салом!', welcome: 'Саволро бо як паём нависед, оператор дар ҳамин чат ҷавоб медиҳад.', supportAuthor: 'Дастгирӣ', systemAuthor: 'Система', you: 'Шумо' },
        uz: { support: 'Yordam', home: 'Asosiy', webPanel: 'Web panel', light: "Yorug'", dark: "Qorong'i", onlineSupport: 'Onlayn yordam', title: 'Bizga yozing, biz yoningizdamiz', text: 'Nima bo‘lganini yozing. Biz vaziyatni ko‘rib chiqamiz va shu chatda javob beramiz.', nearby: 'Yonida', nearbyText: 'ortiqcha shovqinsiz yordam beramiz', webText: 'yagona chat tarixi', dialog: 'Yordam bilan dialog', subtitle: 'Odatda bir necha daqiqada javob beramiz', placeholder: 'Xabar kiriting', send: 'Yuborish', sending: 'Yuborilmoqda', hello: 'Salom!', welcome: 'Savolingizni bitta xabar bilan yozing, operator shu chatda javob beradi.', supportAuthor: 'Yordam', systemAuthor: 'Tizim', you: 'Siz' },
        ky: { support: 'Колдоо', home: 'Башкы', webPanel: 'Web панел', light: 'Жарык', dark: 'Караңгы', onlineSupport: 'Онлайн колдоо', title: 'Бизге жазыңыз, биз жакынбыз', text: 'Эмне болгонун жазыңыз. Биз текшерип, ушул чатта жооп беребиз.', nearby: 'Жакын', nearbyText: 'ашыкча убарасыз жардам беребиз', webText: 'чаттын бирдиктүү тарыхы', dialog: 'Колдоо менен диалог', subtitle: 'Адатта бир нече мүнөттө жооп беребиз', placeholder: 'Билдирүү киргизиңиз', send: 'Жөнөтүү', sending: 'Жөнөтүлүүдө', hello: 'Салам!', welcome: 'Сурооңузду бир билдирүү менен жазыңыз, оператор ушул чатта жооп берет.', supportAuthor: 'Колдоо', systemAuthor: 'Система', you: 'Сиз' },
        kk: { support: 'Қолдау', home: 'Басты', webPanel: 'Web панель', light: 'Жарық', dark: 'Қараңғы', onlineSupport: 'Онлайн қолдау', title: 'Бізге жазыңыз, біз жақынбыз', text: 'Не болғанын жазыңыз. Біз тексеріп, осы чатта жауап береміз.', nearby: 'Жақын', nearbyText: 'артық әуре қылмай көмектесеміз', webText: 'чаттың бірыңғай тарихы', dialog: 'Қолдау диалогы', subtitle: 'Әдетте бірнеше минут ішінде жауап береміз', placeholder: 'Хабарлама енгізіңіз', send: 'Жіберу', sending: 'Жіберілуде', hello: 'Сәлеметсіз бе!', welcome: 'Сұрағыңызды бір хабарламада жазыңыз, оператор осы чатта жауап береді.', supportAuthor: 'Қолдау', systemAuthor: 'Жүйе', you: 'Сіз' },
        uk: { support: 'Підтримка', home: 'Головна', webPanel: 'Web панель', light: 'Світла', dark: 'Темна', onlineSupport: 'Онлайн-підтримка', title: 'Напишіть нам, ми поруч', text: 'Напишіть, що сталося. Ми спокійно розберемося і відповімо в цьому чаті.', nearby: 'Поруч', nearbyText: 'допоможемо без зайвої метушні', webText: 'єдина історія чату', dialog: 'Діалог з підтримкою', subtitle: 'Зазвичай відповідаємо протягом кількох хвилин', placeholder: 'Введіть повідомлення', send: 'Надіслати', sending: 'Надсилання', hello: 'Вітаємо!', welcome: 'Опишіть питання одним повідомленням, оператор відповість у цьому чаті.', supportAuthor: 'Підтримка', systemAuthor: 'Система', you: 'Ви' },
        be: { support: 'Падтрымка', home: 'Галоўная', webPanel: 'Web панэль', light: 'Светлая', dark: 'Цёмная', onlineSupport: 'Анлайн-падтрымка', title: 'Напішыце нам, мы побач', text: 'Напішыце, што здарылася. Мы спакойна разбяромся і адкажам у гэтым чаце.', nearby: 'Побач', nearbyText: 'дапаможам без лішняй мітусні', webText: 'адзіная гісторыя чата', dialog: 'Дыялог з падтрымкай', subtitle: 'Звычайна адказваем на працягу некалькіх хвілін', placeholder: 'Увядзіце паведамленне', send: 'Адправіць', sending: 'Адпраўка', hello: 'Вітаем!', welcome: 'Апішэце пытанне адным паведамленнем, аператар адкажа ў гэтым чаце.', supportAuthor: 'Падтрымка', systemAuthor: 'Сістэма', you: 'Вы' },
        hy: { support: 'Աջակցություն', home: 'Գլխավոր', webPanel: 'Web վահանակ', light: 'Բաց', dark: 'Մուգ', onlineSupport: 'Առցանց աջակցություն', title: 'Գրեք մեզ, մենք մոտ ենք', text: 'Գրեք, թե ինչ է պատահել։ Մենք կստուգենք և կպատասխանենք այս չատում։', nearby: 'Մոտ ենք', nearbyText: 'կօգնենք առանց ավելորդ աղմուկի', webText: 'չատի միասնական պատմություն', dialog: 'Աջակցության երկխոսություն', subtitle: 'Սովորաբար պատասխանում ենք մի քանի րոպեում', placeholder: 'Մուտքագրեք հաղորդագրություն', send: 'Ուղարկել', sending: 'Ուղարկվում է', hello: 'Բարև!', welcome: 'Նկարագրեք հարցը մեկ հաղորդագրությամբ, օպերատորը կպատասխանի այս չատում։', supportAuthor: 'Աջակցություն', systemAuthor: 'Համակարգ', you: 'Դուք' },
        az: { support: 'Dəstək', home: 'Əsas', webPanel: 'Web panel', light: 'Açıq', dark: 'Tünd', onlineSupport: 'Onlayn dəstək', title: 'Bizə yazın, yanınızdayıq', text: 'Nə baş verdiyini yazın. Biz yoxlayıb bu çatda cavab verəcəyik.', nearby: 'Yanınızda', nearbyText: 'artıq səs-küysüz kömək edəcəyik', webText: 'vahid çat tarixçəsi', dialog: 'Dəstək dialoqu', subtitle: 'Adətən bir neçə dəqiqə ərzində cavab veririk', placeholder: 'Mesaj daxil edin', send: 'Göndər', sending: 'Göndərilir', hello: 'Salam!', welcome: 'Sualınızı bir mesajla yazın, operator bu çatda cavab verəcək.', supportAuthor: 'Dəstək', systemAuthor: 'Sistem', you: 'Siz' },
    };

    function normalizeLanguage(value) {
        const language = String(value || '').toLowerCase();
        if (language.startsWith('en')) return 'en';
        if (language.startsWith('tg') || language.startsWith('tj')) return 'tg';
        if (language.startsWith('uz')) return 'uz';
        if (language.startsWith('ky')) return 'ky';
        if (language.startsWith('kk')) return 'kk';
        if (language.startsWith('uk')) return 'uk';
        if (language.startsWith('be')) return 'be';
        if (language.startsWith('hy')) return 'hy';
        if (language.startsWith('az')) return 'az';
        return 'ru';
    }

    function supportLanguage() {
        const user = window.FArtSupportUser || {};
        return normalizeLanguage(user.visitor_language || user.language || user.browser_language || navigator.language || 'ru');
    }

    function text(key) {
        const language = supportLanguage();
        return Object.assign({}, SUPPORT_I18N.ru, language === 'ru' ? {} : SUPPORT_I18N.en, SUPPORT_I18N[language] || {})[key] || key;
    }

    function applyStaticText() {
        document.documentElement.lang = supportLanguage();
        document.title = text('support') + ' - F-ART.bot';
        const topLinks = document.querySelectorAll('.top-links a');
        if (topLinks[0]) topLinks[0].textContent = text('home');
        if (topLinks[1]) topLinks[1].textContent = text('webPanel');
        if (topLinks[2]) topLinks[2].textContent = text('support');
        const stage = document.querySelector('.support-stage');
        if (stage) {
            const kicker = stage.querySelector('.stage-kicker');
            const title = stage.querySelector('h1');
            const paragraph = stage.querySelector('p');
            const stats = stage.querySelectorAll('.support-stats span');
            if (kicker) kicker.textContent = text('onlineSupport');
            if (title) title.textContent = text('title');
            if (paragraph) paragraph.textContent = text('text');
            if (stats[0]) {
                stats[0].querySelector('b').textContent = text('nearby');
                stats[0].querySelector('small').textContent = text('nearbyText');
            }
            if (stats[1]) {
                stats[1].querySelector('b').textContent = 'Web';
                stats[1].querySelector('small').textContent = text('webText');
            }
        }
        const heading = document.querySelector('.support-brand h2');
        if (heading) heading.textContent = text('dialog');
        if (subtitle) subtitle.textContent = text('subtitle');
        if (messageInput) messageInput.placeholder = text('placeholder');
        if (sendButton) sendButton.textContent = text('send');
        if (attachButton) {
            attachButton.title = text('addFile');
            attachButton.setAttribute('aria-label', text('addFile'));
        }
    }

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
        themeToggle.innerHTML = `<span aria-hidden="true">${isDark ? '☼' : '◐'}</span><b>${isDark ? text('light') : text('dark')}</b>`;
        themeToggle.title = isDark ? text('lightTheme') : text('darkTheme');
        themeToggle.setAttribute('aria-label', isDark ? text('switchLight') : text('switchDark'));
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
            if (mime.startsWith('image/')) return text('photo') + ': ' + escapeHtml(att.original_filename || 'image');
            if (mime.startsWith('video/')) return text('video') + ': ' + escapeHtml(att.original_filename || 'video');
            return text('file') + ': ' + escapeHtml(att.original_filename || 'file');
        }
        return text('files') + ': ' + attachments.length;
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
        button.title = text('insertEmoji');
        button.setAttribute('aria-label', text('insertEmoji'));
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
                <button type="button" class="file-view-modal__close" data-file-close aria-label="Close">x</button>
                <div class="file-view-modal__body"></div>
                <a class="file-view-modal__download" href="#" download>${text('download')}</a>
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
                    <strong>${text('hello')}</strong>
                    <span>${text('welcome')}</span>
                </div>
            `;
            return;
        }

        messages.innerHTML = items.map((message) => {
            const isDeleted = Boolean(message.is_deleted_for_user);
            const senderClass = message.sender === 'support' ? 'support' : message.sender === 'system' ? 'system' : 'visitor';
            const author = message.sender === 'support' ? text('supportAuthor') : message.sender === 'system' ? text('systemAuthor') : text('you');
            return `
            <div class="message-row ${senderClass}${isDeleted ? ' message-deleted' : ''}">
                <div class="message-line">
                    <article class="message ${senderClass}${message.delivery_error ? ' message-delivery-error' : ''}">
                        <div class="message-body">${isDeleted ? '<em>' + text('deleted') + '</em>' : messageBodyText(message)}</div>
                        ${!isDeleted && message.delivery_error ? `<div class="message-error-note">${text('deliveryError')}: ${escapeHtml(message.delivery_error)}</div>` : ``}
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
            subtitle.textContent = text('closed');
            messageInput.placeholder = text('newPlaceholder');
        } else {
            status.textContent = 'online';
            subtitle.textContent = text('subtitle');
            messageInput.placeholder = text('placeholder');
        }
    }

    function loadMessages(beforeId = 0) {
        const url = beforeId > 0 ? 'api/messages.php?before_id=' + encodeURIComponent(beforeId) : 'api/messages.php';
        return fetch(url)
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    throw new Error(data.error || text('loadError'));
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
                subtitle.textContent = text('connectionProblem');
            });
    }

    async function sendMessage(body) {
        if (sending) return;
        sending = true;
        sendButton.disabled = true;
        messageInput.disabled = true;
        attachButton.disabled = true;
        sendButton.textContent = text('sending');
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
                throw new Error(data.error || text('sendError'));
            }
            messageInput.value = '';
            selectedFiles = [];
            updateFilePreview();
            autosize();
            await loadMessages();
        } catch (err) {
            alert(err.message || text('networkError'));
        } finally {
            sending = false;
            sendButton.disabled = false;
            messageInput.disabled = false;
            attachButton.disabled = false;
            sendButton.textContent = text('send');
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

    applyStaticText();
    initTheme();
    initEmojiPicker();
    setInterval(loadMessages, 2500);
    loadMessages();
    autosize();
}());
