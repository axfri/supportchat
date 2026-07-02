<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    support_chat_json(['ok' => true]);
}

support_chat_session_start();
$pdo = support_chat_db();
$conversationId = support_chat_find_web_conversation($pdo, session_id()) ?? 0;
if ($conversationId <= 0) {
    support_chat_json(['ok' => true, 'unread' => 0, 'conversation_id' => null]);
}

$conversation = support_chat_get_conversation($pdo, $conversationId);
support_chat_json([
    'ok' => true,
    'unread' => (int)($conversation['unread_visitor'] ?? 0),
    'conversation_id' => $conversationId,
]);
