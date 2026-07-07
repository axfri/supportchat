<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/http.php';

if (support_chat_is_admin_authenticated() && (($_SESSION['support_admin_role'] ?? '') === 'admin')) {
    header('Location: admin.php');
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход администратора</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at 20% 12%, rgba(8, 119, 255, .18), transparent 30%),
                #0c0d12;
            color: #f5f7fb;
        }
        .login-box {
            width: 100%;
            max-width: 420px;
            padding: 30px;
            border: 1px solid #273044;
            border-radius: 18px;
            background: #121620;
            box-shadow: 0 18px 48px rgba(0, 0, 0, .28);
        }
        .logo {
            margin: 0 0 20px;
            font-size: 28px;
            font-weight: 900;
        }
        .logo b { color: #0877ff; }
        h1 {
            margin: 0 0 8px;
            font-size: 26px;
            line-height: 1.2;
        }
        p {
            margin: 0 0 24px;
            color: #98a8c1;
            font-size: 14px;
            line-height: 1.45;
        }
        label {
            display: block;
            margin: 0 0 8px;
            color: #b8c6dc;
            font-size: 14px;
            font-weight: 700;
        }
        input {
            width: 100%;
            height: 48px;
            margin: 0 0 16px;
            padding: 0 14px;
            border: 1px solid #273044;
            border-radius: 12px;
            background: #171c27;
            color: #f5f7fb;
            font-size: 15px;
            outline: none;
        }
        input:focus {
            border-color: #0877ff;
            box-shadow: 0 0 0 3px rgba(8, 119, 255, .16);
        }
        button {
            width: 100%;
            height: 48px;
            border: 0;
            border-radius: 12px;
            background: #0877ff;
            color: #ffffff;
            font-size: 15px;
            font-weight: 900;
            cursor: pointer;
        }
        button:disabled { opacity: .65; cursor: default; }
        .error {
            display: none;
            margin: 0 0 16px;
            padding: 11px 12px;
            border: 1px solid rgba(255, 83, 96, .35);
            border-radius: 12px;
            background: rgba(255, 83, 96, .12);
            color: #ff9aa4;
            font-size: 14px;
            line-height: 1.35;
        }
        .back {
            display: inline-flex;
            margin-top: 16px;
            color: #7bb6ff;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <form class="login-box" id="loginForm" autocomplete="on">
        <div class="logo"><b>F</b>-ART.bot</div>
        <h1>Вход администратора</h1>
        <p>Для администратора используется отдельный вход. Логин: <b>admin</b>, пароль: <b>admin</b>.</p>
        <div class="error" id="loginError"></div>
        <label for="login">Логин</label>
        <input id="login" name="login" type="text" value="admin" autocomplete="username" autofocus required>
        <label for="password">Пароль</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button id="submitButton" type="submit">Войти в админку</button>
        <a class="back" href="login.php">Вход для менеджеров</a>
    </form>
    <script>
        const form = document.getElementById('loginForm');
        const errorBox = document.getElementById('loginError');
        const button = document.getElementById('submitButton');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            errorBox.style.display = 'none';
            errorBox.textContent = '';
            button.disabled = true;

            try {
                const response = await fetch('api/admin_login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        login: document.getElementById('login').value.trim(),
                        password: document.getElementById('password').value,
                        required_role: 'admin'
                    })
                });
                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.error || 'Не удалось войти');
                }
                window.location.href = 'admin.php';
            } catch (error) {
                errorBox.textContent = error.message || 'Не удалось войти';
                errorBox.style.display = 'block';
                document.getElementById('password').value = '';
            } finally {
                button.disabled = false;
            }
        });
    </script>
</body>
</html>
