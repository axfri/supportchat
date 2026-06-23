<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Поддержка - F-ART.bot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css?v=20260621-sidebar-grid">
</head>
<body>
    <main class="shell">
        <aside class="sidebar">
            <div class="brand">
                <a class="fart-logo" href="#" aria-label="F-ART.bot">
                    <b>F</b><strong>-ART</strong><small>.bot</small>
                </a>
            </div>

            <div class="section-title">
                <h1>Поддержка</h1>
                <p>Веб-чат и Telegram</p>
            </div>

            <form class="auth" id="authForm">
                <label for="adminToken">Ключ доступа</label>
                <div class="auth-row">
                    <input id="adminToken" type="password" autocomplete="current-password" placeholder="SUPPORT_ADMIN_TOKEN">
                    <button type="submit">Войти</button>
                </div>
            </form>

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
                <textarea id="messageInput" rows="2" placeholder="Ответить клиенту" disabled></textarea>
                <div class="composer-actions">
                    <input type="file" id="fileInput" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.mp4,.webm,.mov,.mkv,.avi,.zip,.rar,.7z,.tar,.gz,.tgz" style="display:none">
                    <button type="button" id="attachButton" class="icon-button" title="Прикрепить файл" disabled>📎</button>
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
                <span>Источник</span>
                <strong id="detailChannel">-</strong>
                <span>Статус</span>
                <strong id="detailStatus">-</strong>
                <span>Создан</span>
                <strong id="detailCreated">-</strong>
                <span>Обновлен</span>
                <strong id="detailUpdated">-</strong>
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

    <script src="assets/admin.js"></script>
</body>
</html>
