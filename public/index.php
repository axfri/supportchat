<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/http.php';

if (!support_chat_is_admin_authenticated()) {
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Панель поддержки</title>
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('support_admin_theme');
                var siteTheme = localStorage.getItem('fart_theme') || localStorage.getItem('theme') || localStorage.getItem('support_theme');
                var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.dataset.theme = saved || siteTheme || (systemDark ? 'dark' : 'light');
            } catch (err) {
                document.documentElement.dataset.theme = 'dark';
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css?v=20260702-paperclip">
</head>
<body>
    <main class="shell">
        <aside class="sidebar">
            <div class="brand">
                <a class="fart-logo" href="#" aria-label="F-ART.bot">
                    <b>F</b><strong>-ART</strong><small>.bot</small>
                </a>
                <div class="brand-actions">
                    <a class="admin-page-link" id="adminPageLink" href="admin.php" hidden>Админ</a>
                    <button class="admin-theme-toggle" id="adminThemeToggle" type="button" aria-label="Переключить тему">T</button>
                </div>
            </div>

            <div class="section-title compact-title">
                <h1>Диалоги</h1>
                <p>Рабочая область</p>
            </div>

            <div class="filters">
                <input id="searchInput" type="search" placeholder="Поиск">
                <div class="filter-row">
                    <select id="statusFilter" aria-label="Статус">
                        <option value="">Все статусы</option>
                        <option value="new">Новые</option>
                        <option value="open">Открытые</option>
                        <option value="closed">Закрытые</option>
                    </select>
                    <select id="channelFilter" aria-label="Канал">
                        <option value="">Все каналы</option>
                        <option value="web">Сайт</option>
                        <option value="telegram">Telegram</option>
                    </select>
                </div>
            </div>
            <div class="conversation-list" id="conversationList"></div>
        </aside>

        <section class="chat">
            <header class="chat-header">
                <div>
                    <h2 id="chatTitle">Выберите диалог</h2>
                    <p id="chatSubtitle">Новые сообщения обновляются автоматически</p>
                </div>
                <div class="header-actions">
                    <span class="badge muted" id="statusBadge">-</span>
                    <span class="badge muted" id="channelBadge">-</span>
                </div>
            </header>
            <div class="messages" id="messages"></div>
            <div class="quick-replies" id="quickReplies" aria-label="Быстрые ответы">
                <button type="button" data-reply="Здравствуйте! Чем можем помочь?">Приветствие</button>
                <button type="button" data-reply="Уточните, пожалуйста, номер заказа или логин.">Уточнить данные</button>
                <button type="button" data-reply="Передали вопрос специалисту, скоро вернемся с ответом.">Передали специалисту</button>
                <button type="button" data-reply="Спасибо за обращение! Если появятся вопросы, напишите нам снова.">Закрывающий ответ</button>
            </div>
            <form class="composer" id="composer">
                <input type="file" id="fileInput" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.mp4,.webm,.mov,.mkv,.avi,.zip,.rar,.7z,.tar,.gz,.tgz" style="display:none">
                <button type="button" id="attachButton" class="icon-button attach-button" title="Добавить файл" aria-label="Добавить файл" disabled>
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M7.2 13.1 13.8 6.5a3.3 3.3 0 0 1 4.7 4.7l-8.2 8.2a5.1 5.1 0 0 1-7.2-7.2l8.5-8.5"></path>
                    </svg>
                </button>
                <textarea id="messageInput" rows="2" placeholder="Ответить клиенту" disabled></textarea>
                <div class="composer-actions">
                    <button type="submit" id="sendButton" disabled class="submit-button">Отправить</button>
                </div>
                <div class="file-preview" id="filePreview" style="display:none;"></div>
            </form>
        </section>

        <aside class="details" id="detailsPanel">
            <div class="details-card">
                <div class="client-avatar" id="clientAvatar">?</div>
                <h3 id="clientName">Клиент не выбран</h3>
                <p id="clientHandle">Выберите диалог слева</p>
            </div>
            <div class="details-card compact">
                <span>Источник</span><strong id="detailChannel">-</strong>
                <span>Статус</span><strong id="detailStatus">-</strong>
                <span>Баланс</span><strong id="detailBalance">0.00</strong>
                <span>Язык</span><strong id="detailLanguage">-</strong>
                <span>Создан</span><strong id="detailCreated">-</strong>
                <span>Обновлен</span><strong id="detailUpdated">-</strong>
            </div>
            <div class="details-card">
                <h3>Баланс пользователя</h3>
                <form class="balance-form" id="balanceForm">
                    <input id="balanceInput" type="text" inputmode="decimal" placeholder="Новый баланс" disabled>
                    <input id="balanceComment" type="text" placeholder="Комментарий" disabled>
                    <button type="submit" class="secondary-button" disabled id="balanceSave">Сохранить</button>
                </form>
                <div class="admin-mini-list" id="balanceHistory"></div>
            </div>
            <div class="details-card">
                <h3>Перевод</h3>
                <form class="translation-form" id="translationForm">
                    <label class="translation-toggle">
                        <input id="autoTranslateSupport" type="checkbox" disabled>
                        <span>Переводить ответы пользователю</span>
                    </label>
                    <input id="replyLanguage" type="text" placeholder="Пусто = язык клиента, например en" disabled>
                    <button type="submit" class="secondary-button" id="translationSave" disabled>Сохранить</button>
                </form>
                <div class="translation-note">Менеджер пишет по-русски. Оставьте поле пустым, чтобы отправлять ответ на языке последнего сообщения клиента.</div>
            </div>
            <div class="details-card">
                <h3>Действия</h3>
                <div class="action-grid">
                    <button type="button" class="secondary-button" data-status-action="new">Новый</button>
                    <button type="button" class="secondary-button" data-status-action="open">В работе</button>
                    <button type="button" class="secondary-button" data-status-action="closed">Закрыть</button>
                    <button type="button" class="secondary-button" id="soundToggle">Звук: выкл</button>
                </div>
            </div>
            <div class="details-card">
                <h3>Заметка</h3>
                <textarea class="note" id="operatorNote" placeholder="Личная заметка оператора"></textarea>
            </div>
        </aside>
    </main>
    <script src="assets/admin.js?v=20260702-support-audit"></script>
</body>
</html>
