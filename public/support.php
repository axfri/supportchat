<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Поддержка - F-ART.bot</title>
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('support_theme') || localStorage.getItem('fart_theme') || localStorage.getItem('theme');
                var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.dataset.theme = savedTheme || (prefersDark ? 'dark' : 'light');
            } catch (err) {
                document.documentElement.dataset.theme = 'light';
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800;900&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/support.css?v=20260630-support-avatar">
</head>
<body>
    <div class="page-grid" aria-hidden="true"></div>

    <header class="site-header">
        <a class="fart-logo" href="/checkbot/" aria-label="F-ART.bot">
            <b>F</b><strong>-ART</strong><small>.bot</small>
        </a>
        <nav class="top-links" aria-label="Навигация">
            <a href="/checkbot/">Главная</a>
            <a href="/checkbot/cabinet/">Web панель</a>
            <a class="active" href="/support-chat/support.php">Поддержка</a>
        </nav>
        <button class="theme-toggle" id="themeToggle" type="button" aria-label="Переключить тему">
            <span aria-hidden="true">◐</span>
            <b>Тема</b>
        </button>
    </header>

    <main class="support-shell">
        <section class="support-stage">
            <div class="stage-kicker">Онлайн-поддержка</div>
            <h1>Напишите нам, мы рядом</h1>
            <p>Напишите, что случилось. Мы спокойно разберёмся, подскажем по шагам и вернёмся с ответом прямо в этом диалоге.</p>
            <div class="support-stats" aria-label="Параметры поддержки">
                <span><b>Рядом</b><small>поможем без лишней суеты</small></span>
                <span><b>Web</b><small>единая история чата</small></span>
            </div>
        </section>

        <button class="launcher hidden" id="launcher" type="button" aria-label="Открыть поддержку">
            <span>Чат</span>
            <strong id="launcherCount"></strong>
        </button>

        <section class="support-panel open" id="supportPanel">
            <header class="support-header">
                <div class="support-brand">
                    <span class="chat-icon" aria-hidden="true"><img src="assets/support-avatar.svg" alt=""></span>
                    <div>
                        <h2>Диалог с поддержкой</h2>
                        <p id="subtitle">Обычно отвечаем в течение нескольких минут</p>
                    </div>
                </div>
                <div class="header-actions">
                    <span class="status" id="status">online</span>
                </div>
            </header>

            <div class="messages" id="messages"></div>

            <form class="composer" id="composer">
                <textarea id="messageInput" rows="2" placeholder="Введите сообщение"></textarea>
                <div class="composer-actions">
                    <input type="file" id="fileInput" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.mp4,.webm,.mov,.mkv,.avi,.zip,.rar,.7z,.tar,.gz,.tgz" hidden>
                    <button type="button" id="attachButton" class="icon-button" title="Прикрепить файл" aria-label="Прикрепить файл">+</button>
                    <button type="submit" class="submit-button">Отправить</button>
                </div>
                <div class="file-preview" id="filePreview" style="display:none;"></div>
            </form>
        </section>
    </main>

    <script src="assets/support.js?v=20260630-delete-confirm"></script>
</body>
</html>
