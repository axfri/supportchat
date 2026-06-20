<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Поддержка - F-ART.bot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/support.css">
</head>
<body>
    <main class="support-shell">
        <section class="support-panel">
            <header class="support-header">
                <div class="support-brand">
                    <span class="fart-logo"><b>F</b><strong>-ART</strong><small>.bot</small></span>
                    <div>
                        <h1>Поддержка</h1>
                        <p>Напишите вопрос, оператор ответит здесь</p>
                    </div>
                </div>
                <span class="status" id="status">online</span>
            </header>

            <div class="messages" id="messages"></div>

            <form class="composer" id="composer">
                <textarea id="messageInput" rows="2" placeholder="Введите сообщение"></textarea>
                <button type="submit">Отправить</button>
            </form>
        </section>
    </main>

    <script src="assets/support.js"></script>
</body>
</html>
