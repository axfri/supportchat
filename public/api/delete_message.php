<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';

$pdo = support_chat_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

support_chat_session_start();

if ($method !== 'POST') {
    support_chat_json(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}

$data = support_chat_input();
$messageId = (int)($data['message_id'] ?? 0);

if ($messageId <= 0) {
    support_chat_json(['ok' => false, 'error' => 'Не указан ID сообщения'], 422);
}

try {
    $sessionId = session_id();
    support_chat_delete_message_by_visitor($pdo, $messageId, $sessionId);
    support_chat_json(['ok' => true, 'message_id' => $messageId]);
} catch (InvalidArgumentException $e) {
    support_chat_json(['ok' => false, 'error' => 'Сообщение не найдено или недоступно'], 422);
} catch (Throwable $e) {
    support_chat_log_error('delete_message.php error: ' . $e->getMessage());
    support_chat_json(['ok' => false, 'error' => 'Не удалось удалить сообщение'], 500);
}
