<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';
require_once __DIR__ . '/../../src/telegram.php';

$pdo = support_chat_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isAdmin = isset($_GET['admin']) && $_GET['admin'] === '1';

if ($isAdmin) {
    support_chat_require_admin();
} else {
    support_chat_session_start();
}

if ($method === 'GET') {
    if ($isAdmin) {
        $conversationId = (int)($_GET['conversation_id'] ?? 0);
    } else {
        $conversationId = support_chat_find_or_create_web_conversation($pdo, session_id());
    }

    if ($conversationId <= 0) {
        support_chat_json(['ok' => false, 'error' => 'Conversation is required'], 422);
    }

    $stmt = $pdo->prepare('SELECT * FROM messages WHERE conversation_id = ? ORDER BY id ASC LIMIT 500');
    $stmt->execute([$conversationId]);

    try {
        if ($isAdmin) {
            $pdo->prepare('UPDATE conversations SET unread_support = 0 WHERE id = ?')->execute([$conversationId]);
        } else {
            $pdo->prepare('UPDATE conversations SET unread_visitor = 0 WHERE id = ?')->execute([$conversationId]);
        }
    } catch (Throwable $e) {
        support_chat_log_error('Failed to update unread flags: ' . $e->getMessage());
    }

    support_chat_json([
        'ok' => true,
        'conversation_id' => $conversationId,
        'messages' => $stmt->fetchAll(),
    ]);
}

if ($method === 'POST') {
    $data = support_chat_input();
    $body = trim((string)($data['body'] ?? ''));

    if ($body === '') {
        support_chat_json(['ok' => false, 'error' => 'Message is empty'], 422);
    }

    try {
        if ($isAdmin) {
            $conversationId = (int)($data['conversation_id'] ?? 0);
            if ($conversationId <= 0) {
                support_chat_json(['ok' => false, 'error' => 'Conversation is required'], 422);
            }

            // Verify conversation exists
            $conv = support_chat_get_conversation($pdo, $conversationId);
            if ($conv === null) {
                support_chat_json(['ok' => false, 'error' => 'Conversation not found'], 404);
            }

            $messageId = support_chat_add_message($pdo, $conversationId, 'support', $body);

            // If telegram, try to send and log failures
            if ($conv['channel'] === 'telegram' && $conv['external_id'] !== '') {
                $res = support_chat_telegram_send((string)$conv['external_id'], $body);
                if (empty($res['ok'])) {
                    $log = ['when' => date('c'), 'conversation_id' => $conversationId, 'telegram_response' => $res];
                    @file_put_contents(support_chat_base_path('storage' . DIRECTORY_SEPARATOR . 'telegram_errors.log'), json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
                }
            }
        } else {
            $conversationId = support_chat_find_or_create_web_conversation($pdo, session_id());
            $messageId = support_chat_add_message($pdo, $conversationId, 'visitor', $body);
        }
    } catch (InvalidArgumentException $exception) {
        support_chat_json(['ok' => false, 'error' => $exception->getMessage()], 422);
    } catch (Throwable $e) {
        support_chat_log_error('messages.php POST error: ' . $e->getMessage());
        support_chat_json(['ok' => false, 'error' => 'Internal server error'], 500);
    }

    support_chat_json(['ok' => true, 'message_id' => $messageId, 'conversation_id' => $conversationId]);
}

support_chat_json(['ok' => false, 'error' => 'Method not allowed'], 405);
