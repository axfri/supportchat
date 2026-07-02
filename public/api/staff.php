<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    support_chat_json(['ok' => true]);
}

support_chat_require_role('admin');

$pdo = support_chat_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $rows = $pdo->query('SELECT id, login, role, is_blocked, created_at, updated_at FROM support_staff ORDER BY id ASC')->fetchAll();
    support_chat_json(['ok' => true, 'staff' => $rows]);
}

if ($method !== 'POST') {
    support_chat_json(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}

$data = support_chat_input();
$action = (string)($data['action'] ?? 'create');
$id = (int)($data['id'] ?? 0);
$login = trim((string)($data['login'] ?? ''));
$password = (string)($data['password'] ?? '');
$role = (string)($data['role'] ?? 'manager');

if (!in_array($role, ['admin', 'manager'], true)) {
    support_chat_json(['ok' => false, 'error' => 'Некорректная роль'], 422);
}

try {
    if ($action === 'create') {
        if ($login === '' || $password === '') {
            support_chat_json(['ok' => false, 'error' => 'Введите логин и пароль'], 422);
        }
        if (strlen($password) < 6) {
            support_chat_json(['ok' => false, 'error' => 'Пароль должен быть не короче 6 символов'], 422);
        }
        $stmt = $pdo->prepare('INSERT INTO support_staff (login, password_hash, role) VALUES (?, ?, ?)');
        $stmt->execute([$login, password_hash($password, PASSWORD_DEFAULT), $role]);
    } elseif ($action === 'password') {
        if ($id <= 0 || strlen($password) < 6) {
            support_chat_json(['ok' => false, 'error' => 'Укажите сотрудника и новый пароль от 6 символов'], 422);
        }
        $stmt = $pdo->prepare('UPDATE support_staff SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    } elseif ($action === 'block' || $action === 'unblock') {
        if ($id <= 0) {
            support_chat_json(['ok' => false, 'error' => 'Укажите сотрудника'], 422);
        }
        $blocked = $action === 'block' ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE support_staff SET is_blocked = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$blocked, $id]);
    } else {
        support_chat_json(['ok' => false, 'error' => 'Неизвестное действие'], 422);
    }
} catch (Throwable $e) {
    support_chat_log_error('staff.php error: ' . $e->getMessage());
    support_chat_json(['ok' => false, 'error' => 'Не удалось сохранить сотрудника'], 500);
}

support_chat_json(['ok' => true]);
