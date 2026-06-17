<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/telegram.php';

$offsetFile = support_chat_base_path('storage/telegram_offset.txt');
$offset = is_file($offsetFile) ? (int)trim((string)file_get_contents($offsetFile)) : 0;

echo "Telegram polling started\n";

while (true) {
    $response = support_chat_telegram_get_updates($offset);
    if (empty($response['ok']) || !isset($response['result']) || !is_array($response['result'])) {
        sleep(3);
        continue;
    }

    foreach ($response['result'] as $update) {
        $offset = max($offset, ((int)($update['update_id'] ?? 0)) + 1);
        file_put_contents($offsetFile, (string)$offset);

        $message = $update['message'] ?? null;
        if (!is_array($message) || !isset($message['chat']['id'])) {
            continue;
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
    }
}
