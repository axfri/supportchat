<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    support_chat_json(['ok' => true]);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    support_chat_json([
        'ok' => true,
        'authenticated' => support_chat_is_admin_authenticated(),
        'login' => $_SESSION['support_admin_login'] ?? '',
        'role' => $_SESSION['support_admin_role'] ?? '',
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

$user = support_chat_admin_user_by_credentials($login, $password);
if ($user === null) {
    support_chat_json(['ok' => false, 'error' => 'Неверный логин или пароль'], 401);
}

support_chat_admin_login((string)$user['login'], (string)$user['role'], (int)$user['id']);

support_chat_json([
    'ok' => true,
    'authenticated' => true,
    'login' => (string)$user['login'],
    'role' => (string)$user['role'],
]);
