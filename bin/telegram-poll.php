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
        if (!is_array($message)) {
            continue;
        }

        $pdo = support_chat_db();
        $result = support_chat_handle_telegram_message($pdo, $message);
        if (!empty($result['reply']) && isset($message['chat']['id'])) {
            support_chat_telegram_send((string)$message['chat']['id'], (string)$result['reply']);
        }
    }
}
