<?php
declare(strict_types=1);

$isEmbed = isset($_GET['embed']) && $_GET['embed'] === '1';
$supportUser = [
    'visitor_name' => trim((string)($_GET['visitor_name'] ?? '')),
    'visitor_user_id' => trim((string)($_GET['visitor_user_id'] ?? '')),
    'visitor_email' => trim((string)($_GET['visitor_email'] ?? '')),
    'visitor_balance' => trim((string)($_GET['visitor_balance'] ?? '')),
    'visitor_language' => trim((string)($_GET['visitor_language'] ?? '')),
    'browser_language' => trim((string)($_GET['browser_language'] ?? '')),
];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Поддержка - F-ART.bot</title>
    <script>
        window.FArtSupportUser = <?= json_encode($supportUser, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
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
    <link rel="stylesheet" href="assets/support.css?v=20260702-paperclip">
</head>
<body class="<?= $isEmbed ? 'support-embedded' : '' ?>">
    <div class="page-grid" aria-hidden="true"></div>

    <?php if (!$isEmbed): ?>
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
    <?php endif; ?>

    <main class="support-shell">
        <?php if (!$isEmbed): ?>
        <section class="support-stage">
            <div class="stage-kicker">Онлайн-поддержка</div>
            <h1>Напишите нам, мы рядом</h1>
            <p>Напишите, что случилось. Мы спокойно разберёмся, подскажем по шагам и вернёмся с ответом прямо в этом диалоге.</p>
            <div class="support-stats" aria-label="Параметры поддержки">
                <span><b>Рядом</b><small>поможем без лишней суеты</small></span>
                <span><b>Web</b><small>единая история чата</small></span>
            </div>
        </section>
        <?php endif; ?>

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
                <input type="file" id="fileInput" multiple hidden>
                <button type="button" id="attachButton" class="icon-button attach-button" title="Добавить файл" aria-label="Добавить файл">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M7.2 13.1 13.8 6.5a3.3 3.3 0 0 1 4.7 4.7l-8.2 8.2a5.1 5.1 0 0 1-7.2-7.2l8.5-8.5"></path>
                    </svg>
                </button>
                <textarea id="messageInput" rows="2" placeholder="Введите сообщение"></textarea>
                <div class="composer-actions">
                    <button type="submit" class="submit-button">Отправить</button>
                </div>
                <div class="file-preview" id="filePreview" style="display:none;"></div>
            </form>
        </section>
    </main>

    <script src="assets/support.js?v=20260707-i18n"></script>
</body>
</html>
