<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';

support_chat_require_admin();

$pdo = support_chat_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $where = [];
    $params = [];

    $status = trim((string)($_GET['status'] ?? ''));
    if ($status !== '') {
        support_chat_validate_status($status);
        $where[] = 'c.status = ?';
        $params[] = $status;
    }

    $channel = trim((string)($_GET['channel'] ?? ''));
    if ($channel !== '') {
        if (!in_array($channel, ['web', 'telegram'], true)) {
            support_chat_json(['ok' => false, 'error' => 'Invalid channel'], 422);
        }
        $where[] = 'c.channel = ?';
        $params[] = $channel;
    }

    $search = trim((string)($_GET['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(c.visitor_name LIKE ? OR c.visitor_handle LIKE ? OR c.external_id LIKE ?)';
        $needle = '%' . $search . '%';
        array_push($params, $needle, $needle, $needle);
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare("
        SELECT
            c.*,
            m.body AS last_message,
            m.sender AS last_sender,
            m.created_at AS last_message_at
        FROM conversations c
        LEFT JOIN messages m ON m.id = (
            SELECT id FROM messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1
        )
        $whereSql
        ORDER BY c.updated_at DESC, c.id DESC
        LIMIT 200
    ");
    $stmt->execute($params);

    support_chat_json(['ok' => true, 'conversations' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = support_chat_input();
    $conversationId = (int)($data['conversation_id'] ?? 0);
    $status = trim((string)($data['status'] ?? ''));

    if ($conversationId <= 0 || $status === '') {
        support_chat_json(['ok' => false, 'error' => 'Conversation and status are required'], 422);
    }

    try {
        $conversation = support_chat_update_conversation_status($pdo, $conversationId, $status);
    } catch (InvalidArgumentException $exception) {
        support_chat_json(['ok' => false, 'error' => $exception->getMessage()], 422);
    }

    support_chat_json(['ok' => true, 'conversation' => $conversation]);
}

support_chat_json(['ok' => false, 'error' => 'Method not allowed'], 405);
