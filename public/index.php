<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Поддержка - F-ART.bot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <main class="shell">
        <aside class="sidebar">
            <div class="brand">
                <a class="fart-logo" href="#" aria-label="F-ART.bot">
                    <b>F</b><strong>-ART</strong><small>.bot</small>
                </a>
                <button class="icon-button" id="refreshButton" title="Обновить">↻</button>
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
                    <button class="secondary-button" id="statusButton" type="button" disabled>Закрыть</button>
                </div>
            </header>

            <div class="messages" id="messages"></div>

            <form class="composer" id="composer">
                <textarea id="messageInput" rows="2" placeholder="Ответить клиенту" disabled></textarea>
                <button type="submit" id="sendButton" disabled>Отправить</button>
            </form>
        </section>
    </main>

    <script src="assets/admin.js"></script>
</body>
</html>
