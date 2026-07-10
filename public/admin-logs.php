<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/http.php';

if (!support_chat_is_admin_authenticated() || (($_SESSION['support_admin_role'] ?? '') !== 'admin')) {
    header('Location: admin-login.php');
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Логи поддержки</title>
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
    <link rel="stylesheet" href="assets/admin-panel.css?v=20260710-admin-pages">
</head>
<body>
    <main class="admin-shell">
        <header class="admin-topbar">
            <a class="fart-logo" href="./" aria-label="F-ART.bot">
                <b>F</b><strong>-ART</strong><small>.bot</small>
            </a>
            <nav class="admin-nav" aria-label="Навигация">
                <a href="./">Диалоги</a>
                <a href="admin-users.php">Пользователи</a>
                <a href="admin-staff.php">Менеджеры</a>
                <a class="active" href="admin-logs.php">Логи</a>
            </nav>
            <button class="theme-toggle" id="themeToggle" type="button">Тема</button>
        </header>

        <section class="admin-card admin-page-card logs-card">
            <div class="card-head">
                <div>
                    <h2>Telegram-лог</h2>
                    <p><b>incoming</b> — входящее событие от пользователя/Telegram в наш бот. <b>outgoing</b> — исходящий запрос от поддержки или бота в Telegram.</p>
                </div>
                <button class="secondary-btn" id="refreshLogs" type="button">Обновить</button>
            </div>
            <div class="log-toolbar">
                <span id="logsPageInfo">Страница 1</span>
                <div class="pager">
                    <button class="secondary-btn" id="prevLogs" type="button">Назад</button>
                    <button class="secondary-btn" id="nextLogs" type="button">Вперёд</button>
                </div>
            </div>
            <div class="stack-list log-list" id="telegramLogList"></div>
        </section>
    </main>

    <script src="assets/admin-panel.js?v=20260710-admin-pages"></script>
</body>
</html>
