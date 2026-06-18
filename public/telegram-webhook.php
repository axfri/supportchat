<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/http.php';
require_once __DIR__ . '/../src/telegram.php';

$expectedSecret = support_chat_env('TELEGRAM_WEBHOOK_SECRET');
$provided = '';
// Accept secret via GET or X-Telegram-Bot-Api-Secret-Token header
if (isset($_GET['secret'])) {
    $provided = (string)$_GET['secret'];
} elseif (isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])) {
    $provided = (string)$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'];
}

if ($expectedSecret !== '' && !hash_equals($expectedSecret, $provided)) {
    support_chat_json(['ok' => false, 'error' => 'Forbidden'], 403);
}

$raw = file_get_contents('php://input') ?: '';
$update = json_decode($raw, true);
if (!is_array($update)) {
    // Nothing to do
    support_chat_json(['ok' => true]);
}

$message = $update['message'] ?? null;
if (!is_array($message) || !isset($message['chat']['id'])) {
    support_chat_json(['ok' => true]);
}

try {
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
} catch (Throwable $e) {
    // Log and return ok to avoid Telegram retry storms (handler can be inspected later)
    support_chat_log_error('Webhook handler error: ' . $e->getMessage());
}

support_chat_json(['ok' => true]);
