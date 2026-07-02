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
        $pdo = support_chat_db();
        support_chat_log_telegram($pdo, 'incoming', 'poll_get_updates_failed', [
            'result' => $response,
            'success' => false,
            'error' => (string)($response['description'] ?? 'getUpdates failed'),
        ]);
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
        try {
            $result = support_chat_handle_telegram_message($pdo, $message);
            if (!empty($result['reply']) && isset($message['chat']['id'])) {
                $send = support_chat_telegram_send((string)$message['chat']['id'], (string)$result['reply']);
                support_chat_log_telegram($pdo, 'outgoing', 'command_reply', [
                    'chat_id' => (string)$message['chat']['id'],
                    'message_id' => (string)($send['result']['message_id'] ?? ''),
                    'payload' => ['text' => (string)$result['reply']],
                    'result' => $send,
                    'success' => !empty($send['ok']),
                    'error' => empty($send['ok']) ? (string)($send['description'] ?? 'Telegram request failed') : '',
                ]);
            }
        } catch (Throwable $e) {
            support_chat_log_error('Telegram poll handler error: ' . $e->getMessage());
            support_chat_log_telegram($pdo, 'incoming', 'poll_handler_error', [
                'payload' => $message,
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
