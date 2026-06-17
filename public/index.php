<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Панель поддержки</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <main class="shell">
        <aside class="sidebar">
            <div class="brand">
                <div>
                    <h1>Поддержка</h1>
                    <p>Веб-чат и Telegram</p>
                </div>
                <button class="icon-button" id="refreshButton" title="Обновить">↻</button>
            </div>

            <form class="auth" id="authForm">
                <label for="adminToken">Ключ доступа</label>
                <div class="auth-row">
                    <input id="adminToken" type="password" autocomplete="current-password" placeholder="SUPPORT_ADMIN_TOKEN">
                    <button type="submit">Войти</button>
                </div>
            </form>

            <div class="conversation-list" id="conversationList"></div>
        </aside>

        <section class="chat">
            <header class="chat-header">
                <div>
                    <h2 id="chatTitle">Выберите диалог</h2>
                    <p id="chatSubtitle">Новые сообщения обновляются автоматически</p>
                </div>
                <span class="badge muted" id="channelBadge">-</span>
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
