<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    support_chat_json([
        'ok' => true,
        'authenticated' => support_chat_is_admin_authenticated(),
        'login' => $_SESSION['support_admin_login'] ?? '',
    ]);
}

if ($method !== 'POST') {
    support_chat_json(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}

$data = support_chat_input();
$login = trim((string)($data['login'] ?? ''));
$password = (string)($data['password'] ?? '');

if ($login === '' || $password === '') {
    support_chat_json(['ok' => false, 'error' => 'Введите логин и пароль'], 422);
}

if (!support_chat_admin_credentials_valid($login, $password)) {
    support_chat_json(['ok' => false, 'error' => 'Неверный логин или пароль'], 401);
}

support_chat_admin_login($login);

support_chat_json([
    'ok' => true,
    'authenticated' => true,
    'login' => $login,
]);
