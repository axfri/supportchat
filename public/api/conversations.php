<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';

support_chat_require_admin();

$pdo = support_chat_db();
$stmt = $pdo->query("
    SELECT
        c.*,
        m.body AS last_message,
        m.sender AS last_sender,
        m.created_at AS last_message_at
    FROM conversations c
    LEFT JOIN messages m ON m.id = (
        SELECT id FROM messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1
    )
    ORDER BY c.updated_at DESC, c.id DESC
    LIMIT 200
");

support_chat_json(['ok' => true, 'conversations' => $stmt->fetchAll()]);
