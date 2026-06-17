<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/http.php';

$expectedSecret = support_chat_env('TELEGRAM_WEBHOOK_SECRET');
if ($expectedSecret !== '' && !hash_equals($expectedSecret, (string)($_GET['secret'] ?? ''))) {
    support_chat_json(['ok' => false, 'error' => 'Forbidden'], 403);
}

$update = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($update)) {
    support_chat_json(['ok' => true]);
}

$message = $update['message'] ?? null;
if (!is_array($message) || !isset($message['chat']['id'])) {
    support_chat_json(['ok' => true]);
}

$text = trim((string)($message['text'] ?? ''));
if ($text === '') {
    $text = '[не текстовое сообщение]';
}

$chat = $message['chat'];
$chatId = (string)$chat['id'];
$name = trim((string)($chat['first_name'] ?? '') . ' ' . (string)($chat['last_name'] ?? ''));
$name = $name !== '' ? $name : 'Telegram user';
$handle = isset($chat['username']) ? '@' . (string)$chat['username'] : '';

$pdo = support_chat_db();
$conversationId = support_chat_find_or_create_telegram_conversation($pdo, $chatId, $name, $handle);
support_chat_add_message($pdo, $conversationId, 'visitor', $text, isset($message['message_id']) ? (string)$message['message_id'] : null);

support_chat_json(['ok' => true]);
