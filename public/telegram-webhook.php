<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/http.php';
require_once __DIR__ . '/../src/telegram.php';

$expectedSecret = support_chat_env('TELEGRAM_WEBHOOK_SECRET');
$provided = '';
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
    support_chat_json(['ok' => true]);
}

$message = $update['message'] ?? null;
if (!is_array($message)) {
    support_chat_json(['ok' => true]);
}

try {
    $pdo = support_chat_db();
    $result = support_chat_handle_telegram_message($pdo, $message);
    if (!empty($result['reply']) && isset($message['chat']['id'])) {
        support_chat_telegram_send((string)$message['chat']['id'], (string)$result['reply']);
    }
} catch (Throwable $e) {
    support_chat_log_error('Webhook handler error: ' . $e->getMessage());
}

support_chat_json(['ok' => true]);
