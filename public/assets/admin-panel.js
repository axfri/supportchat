(function () {
    const userSearch = document.getElementById('userSearch');
    const usersTable = document.getElementById('usersTable');
    const loadMoreUsers = document.getElementById('loadMoreUsers');
    const staffForm = document.getElementById('staffForm');
    const staffLogin = document.getElementById('staffLogin');
    const staffPassword = document.getElementById('staffPassword');
    const staffRole = document.getElementById('staffRole');
    const staffList = document.getElementById('staffList');
    const telegramLogList = document.getElementById('telegramLogList');
    const refreshLogs = document.getElementById('refreshLogs');
    const prevLogs = document.getElementById('prevLogs');
    const nextLogs = document.getElementById('nextLogs');
    const logsPageInfo = document.getElementById('logsPageInfo');
    const themeToggle = document.getElementById('themeToggle');
    const balanceModal = document.getElementById('balanceModal');
    const balanceForm = document.getElementById('balanceForm');
    const balanceUserName = document.getElementById('balanceUserName');
    const balanceConversationId = document.getElementById('balanceConversationId');
    const balanceValue = document.getElementById('balanceValue');
    const balanceComment = document.getElementById('balanceComment');
    const balanceStatus = document.getElementById('balanceStatus');

    let usersOffset = 0;
    let usersHasMore = true;
    let usersLoading = false;
    let logsOffset = 0;
    let logsHasMore = false;
    const logsLimit = 50;

    function api(path, options) {
        return fetch(path, Object.assign({ credentials: 'same-origin' }, options || {}))
            .then((response) => response.json().then((data) => {
                if (!response.ok || !data.ok) {
                    throw new Error(data.error || 'Ошибка запроса');
                }
                return data;
            }));
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
        const date = new Date(String(value).replace(' ', 'T') + 'Z');
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function normalizeMoneyInput(input) {
        const value = String(input || '').trim().replace(/\s+/g, '').replace(',', '.');
        return /^-?\d+(\.\d{1,2})?$/.test(value) ? value : '';
    }

    function setTheme(theme, persist) {
        const next = theme === 'light' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        if (themeToggle) themeToggle.textContent = next === 'dark' ? 'Светлая' : 'Тёмная';
        if (persist !== false) localStorage.setItem('support_admin_theme', next);
    }

    function userName(user) {
        return user.display_name || user.visitor_name || user.visitor_handle || user.visitor_email || ('Пользователь #' + user.id);
    }

    function channelText(channel) {
        return channel === 'telegram' ? 'Telegram' : 'Сайт';
    }

    function renderUsers(items, append) {
        if (!usersTable) return;
        if (!append) usersTable.innerHTML = '';
        if (!items.length && !append) {
            usersTable.innerHTML = '<tr><td colspan="6"><div class="empty">Пользователи не найдены</div></td></tr>';
            return;
        }

        const html = items.map((user) => {
            const name = userName(user);
            const handle = user.visitor_handle || user.visitor_email || user.visitor_user_id || user.external_id || '';
            return `
                <tr>
                    <td><strong>${escapeHtml(name)}</strong>${handle ? `<small>${escapeHtml(handle)}</small>` : ''}</td>
                    <td>${escapeHtml(channelText(user.channel))}</td>
                    <td>${escapeHtml(user.language_label || user.visitor_language || user.browser_language || '-')}</td>
                    <td><strong>${Number(user.balance || 0).toFixed(2)}</strong></td>
                    <td>${escapeHtml(formatDate(user.updated_at))}</td>
                    <td><button class="row-action" type="button" data-balance-id="${user.id}" data-balance-name="${escapeHtml(name)}" data-balance-value="${Number(user.balance || 0).toFixed(2)}">Баланс</button></td>
                </tr>
            `;
        }).join('');
        usersTable.insertAdjacentHTML('beforeend', html);
    }

    async function loadUsers(append) {
        if (!usersTable || !loadMoreUsers || usersLoading || (!usersHasMore && append)) return;
        usersLoading = true;
        loadMoreUsers.disabled = true;
        try {
            const params = new URLSearchParams();
            params.set('limit', '30');
            params.set('offset', String(append ? usersOffset : 0));
            const search = userSearch ? userSearch.value.trim() : '';
            if (search) params.set('search', search);
            const data = await api('api/admin_users.php?' + params.toString());
            usersOffset = data.next_offset || 0;
            usersHasMore = Boolean(data.has_more);
            renderUsers(data.users || [], Boolean(append));
            loadMoreUsers.hidden = !usersHasMore;
        } catch (error) {
            usersTable.innerHTML = `<tr><td colspan="6"><div class="empty">${escapeHtml(error.message)}</div></td></tr>`;
        } finally {
            usersLoading = false;
            loadMoreUsers.disabled = false;
        }
    }

    function renderStaff(items) {
        if (!staffList) return;
        staffList.innerHTML = (items || []).map((item) => `
            <div class="stack-item">
                <div class="stack-item__top">
                    <strong>${escapeHtml(item.login)} · ${escapeHtml(item.role)}</strong>
                    <span>${Number(item.is_blocked) ? 'заблокирован' : 'активен'}</span>
                </div>
                <div class="stack-actions">
                    <button type="button" data-staff-action="password" data-staff-id="${item.id}">Сменить пароль</button>
                    <button type="button" class="${Number(item.is_blocked) ? '' : 'danger'}" data-staff-action="${Number(item.is_blocked) ? 'unblock' : 'block'}" data-staff-id="${item.id}">
                        ${Number(item.is_blocked) ? 'Разблокировать' : 'Заблокировать'}
                    </button>
                </div>
            </div>
        `).join('') || '<div class="empty">Менеджеров пока нет</div>';
    }

    async function loadStaff() {
        if (!staffList) return;
        const data = await api('api/staff.php');
        renderStaff(data.staff || []);
    }

    function parseJsonField(value) {
        const raw = String(value || '').trim();
        if (raw === '' || raw === '[]' || raw === '{}') return null;
        try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed) && parsed.length === 0) return null;
            if (parsed && typeof parsed === 'object' && Object.keys(parsed).length === 0) return null;
            return parsed;
        } catch (error) {
            return raw;
        }
    }

    function compactJson(value) {
        const parsed = parseJsonField(value);
        if (parsed === null) return '';
        if (typeof parsed === 'string') return parsed;
        return JSON.stringify(parsed, null, 2);
    }

    function payloadSummary(value) {
        const parsed = parseJsonField(value);
        if (parsed === null || typeof parsed === 'string') return '';
        const parts = [];
        const text = parsed.text || parsed.caption || parsed.message?.text || parsed.message?.caption;
        const type = parsed.photo ? 'photo' : parsed.video ? 'video' : parsed.document ? 'document' : '';
        const replyTo = parsed.reply_to_message_id || parsed.reply_to_message?.message_id;
        if (text) parts.push(String(text).slice(0, 120));
        if (type) parts.push(type);
        if (replyTo) parts.push('reply_to ' + replyTo);
        return parts.join(' · ');
    }

    function renderLogs(items) {
        if (!telegramLogList) return;
        telegramLogList.innerHTML = (items || []).map((item) => {
            const payload = compactJson(item.payload);
            const result = compactJson(item.result);
            const summary = payloadSummary(item.payload) || payloadSummary(item.result);
            const directionText = item.direction === 'incoming' ? 'incoming: входящее' : 'outgoing: исходящее';
            return `
                <div class="stack-item log-item log-${escapeHtml(item.direction)}">
                    <div class="stack-item__top">
                        <strong><span class="direction-pill">${escapeHtml(directionText)}</span> ${escapeHtml(item.action)} · ${Number(item.success) ? 'ok' : 'error'}</strong>
                        <span>${escapeHtml(formatDate(item.created_at))}</span>
                    </div>
                    <span>chat ${escapeHtml(item.telegram_chat_id || '-')} · message ${escapeHtml(item.telegram_message_id || '-')} · dialog ${escapeHtml(item.conversation_id || '-')}</span>
                    ${summary ? `<p class="log-summary">${escapeHtml(summary)}</p>` : ''}
                    ${item.error ? `<code>${escapeHtml(item.error)}</code>` : ''}
                    ${payload ? `<details><summary>payload</summary><code>${escapeHtml(payload)}</code></details>` : ''}
                    ${result ? `<details><summary>result</summary><code>${escapeHtml(result)}</code></details>` : ''}
                </div>
            `;
        }).join('') || '<div class="empty">Лог пуст</div>';
    }

    function updateLogsPager() {
        if (logsPageInfo) {
            const page = Math.floor(logsOffset / logsLimit) + 1;
            logsPageInfo.textContent = `Страница ${page} · по ${logsLimit} записей`;
        }
        if (prevLogs) prevLogs.disabled = logsOffset <= 0;
        if (nextLogs) nextLogs.disabled = !logsHasMore;
    }

    async function loadLogs(offset) {
        if (!telegramLogList) return;
        logsOffset = Math.max(0, Number(offset || 0));
        telegramLogList.innerHTML = '<div class="empty">Загрузка...</div>';
        updateLogsPager();
        const params = new URLSearchParams({ limit: String(logsLimit), offset: String(logsOffset) });
        const data = await api('api/telegram_logs.php?' + params.toString());
        logsHasMore = Boolean(data.has_more);
        logsOffset = Number(data.offset || logsOffset);
        renderLogs(data.logs || []);
        updateLogsPager();
    }

    function openBalanceModal(id, name, value) {
        if (!balanceModal) return;
        balanceConversationId.value = id;
        balanceValue.value = Number(value || 0).toFixed(2);
        balanceComment.value = '';
        balanceStatus.textContent = '';
        balanceStatus.classList.remove('error');
        balanceUserName.textContent = name || ('Диалог #' + id);
        balanceModal.hidden = false;
        balanceValue.focus();
    }

    function closeBalanceModal() {
        if (balanceModal) balanceModal.hidden = true;
    }

    if (userSearch) {
        userSearch.addEventListener('input', () => {
            window.clearTimeout(userSearch._timer);
            userSearch._timer = window.setTimeout(() => {
                usersOffset = 0;
                usersHasMore = true;
                loadUsers(false);
            }, 250);
        });
    }

    if (loadMoreUsers) loadMoreUsers.addEventListener('click', () => loadUsers(true));

    if (usersTable) {
        usersTable.addEventListener('click', (event) => {
            const button = event.target.closest('[data-balance-id]');
            if (!button) return;
            openBalanceModal(button.dataset.balanceId, button.dataset.balanceName, button.dataset.balanceValue);
        });
    }

    document.querySelectorAll('[data-close-modal]').forEach((item) => item.addEventListener('click', closeBalanceModal));

    if (balanceForm) {
        balanceForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            balanceStatus.textContent = 'Сохранение...';
            balanceStatus.classList.remove('error');
            const normalizedBalance = normalizeMoneyInput(balanceValue.value);
            if (!normalizedBalance) {
                balanceStatus.textContent = 'Введите корректный баланс, например 902.00';
                balanceStatus.classList.add('error');
                return;
            }
            try {
                await api('api/balance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: Number(balanceConversationId.value),
                        balance: normalizedBalance,
                        comment: balanceComment.value.trim(),
                    }),
                });
                balanceStatus.textContent = 'Баланс сохранён';
                usersOffset = 0;
                usersHasMore = true;
                await loadUsers(false);
                window.setTimeout(closeBalanceModal, 500);
            } catch (error) {
                balanceStatus.textContent = error.message || 'Не удалось сохранить баланс';
                balanceStatus.classList.add('error');
            }
        });
    }

    if (staffForm) {
        staffForm.addEventListener('submit', async (event) => {
            event.preventDefault();
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
        });
    }

    if (staffList) {
        staffList.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-staff-action]');
            if (!button) return;
            const payload = { action: button.dataset.staffAction, id: Number(button.dataset.staffId) };
            if (payload.action === 'password') {
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
        });
    }

    if (refreshLogs) refreshLogs.addEventListener('click', () => loadLogs(logsOffset));
    if (prevLogs) prevLogs.addEventListener('click', () => loadLogs(Math.max(0, logsOffset - logsLimit)));
    if (nextLogs) nextLogs.addEventListener('click', () => loadLogs(logsOffset + logsLimit));

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            setTheme(document.documentElement.dataset.theme === 'light' ? 'dark' : 'light', true);
        });
    }

    setTheme(document.documentElement.dataset.theme || 'dark', false);
    loadUsers(false);
    loadStaff();
    loadLogs(0);
}());
