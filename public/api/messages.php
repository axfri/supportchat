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

    if ($isAdmin) {
        $pdo->prepare('UPDATE conversations SET unread_support = 0 WHERE id = ?')->execute([$conversationId]);
    } else {
        $pdo->prepare('UPDATE conversations SET unread_visitor = 0 WHERE id = ?')->execute([$conversationId]);
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

    try {
        if ($isAdmin) {
            $conversationId = (int)($data['conversation_id'] ?? 0);
            if ($conversationId <= 0) {
                support_chat_json(['ok' => false, 'error' => 'Conversation is required'], 422);
            }

            $messageId = support_chat_add_message($pdo, $conversationId, 'support', $body);
            $stmt = $pdo->prepare('SELECT channel, external_id FROM conversations WHERE id = ? LIMIT 1');
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch();

            if ($conversation && $conversation['channel'] === 'telegram' && $conversation['external_id'] !== '') {
                support_chat_telegram_send((string)$conversation['external_id'], $body);
            }
        } else {
            $conversationId = support_chat_find_or_create_web_conversation($pdo, session_id());
            $messageId = support_chat_add_message($pdo, $conversationId, 'visitor', $body);
        }
    } catch (InvalidArgumentException $exception) {
        support_chat_json(['ok' => false, 'error' => $exception->getMessage()], 422);
    }

    support_chat_json(['ok' => true, 'message_id' => $messageId, 'conversation_id' => $conversationId]);
}

support_chat_json(['ok' => false, 'error' => 'Method not allowed'], 405);
