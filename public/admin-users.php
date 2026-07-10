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
    <title>Пользователи поддержки</title>
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
                <a class="active" href="admin-users.php">Пользователи</a>
                <a href="admin-staff.php">Менеджеры</a>
                <a href="admin-logs.php">Логи</a>
            </nav>
            <button class="theme-toggle" id="themeToggle" type="button">Тема</button>
        </header>

        <section class="admin-card admin-page-card">
            <div class="card-head">
                <div>
                    <h2>Пользователи и балансы</h2>
                    <p>Поиск по имени, Telegram, email или ID. Баланс меняется отдельно по выбранному пользователю.</p>
                </div>
                <input id="userSearch" type="search" placeholder="Поиск пользователя">
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Пользователь</th>
                            <th>Канал</th>
                            <th>Язык</th>
                            <th>Баланс</th>
                            <th>Обновлён</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="usersTable"></tbody>
                </table>
            </div>
            <button class="secondary-btn page-more-btn" id="loadMoreUsers" type="button">Показать ещё</button>
        </section>
    </main>

    <div class="modal" id="balanceModal" hidden>
        <div class="modal-backdrop" data-close-modal></div>
        <form class="modal-panel" id="balanceForm">
            <button class="modal-close" type="button" data-close-modal>×</button>
            <h2>Изменить баланс</h2>
            <p id="balanceUserName"></p>
            <input id="balanceConversationId" type="hidden">
            <label>
                <span>Новый баланс</span>
                <input id="balanceValue" type="text" inputmode="decimal" required>
            </label>
            <label>
                <span>Комментарий</span>
                <input id="balanceComment" type="text" placeholder="Причина изменения">
            </label>
            <button type="submit">Сохранить</button>
            <div class="status-line" id="balanceStatus"></div>
        </form>
    </div>

    <script src="assets/admin-panel.js?v=20260710-admin-pages"></script>
</body>
</html>
