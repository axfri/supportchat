<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/http.php';

if (support_chat_is_admin_authenticated()) {
    header('Location: ./');
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }
        .login-box {
            width: 100%;
            max-width: 360px;
            padding: 28px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, 0.08);
        }
        h1 {
            margin: 0 0 22px;
            font-size: 22px;
            font-weight: 700;
            line-height: 1.2;
        }
        label {
            display: block;
            margin: 0 0 7px;
            font-size: 14px;
            color: #374151;
        }
        input {
            width: 100%;
            height: 44px;
            margin: 0 0 16px;
            padding: 0 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            color: #111827;
            font-size: 15px;
            outline: none;
        }
        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }
        button {
            width: 100%;
            height: 44px;
            border: 0;
            border-radius: 8px;
            background: #2563eb;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }
        button:disabled { opacity: .65; cursor: default; }
        .error {
            display: none;
            margin: 0 0 16px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 14px;
            line-height: 1.35;
        }
    </style>
</head>
<body>
    <form class="login-box" id="loginForm" autocomplete="on">
        <h1>Вход</h1>
        <div class="error" id="loginError"></div>
        <label for="login">Логин</label>
        <input id="login" name="login" type="text" autocomplete="username" autofocus required>
        <label for="password">Пароль</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button id="submitButton" type="submit">Войти</button>
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
                        password: document.getElementById('password').value
                    })
                });
                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.error || 'Не удалось войти');
                }
                window.location.href = './';
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
