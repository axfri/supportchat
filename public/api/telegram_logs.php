<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    support_chat_json(['ok' => true]);
}

support_chat_require_role('admin');

$pdo = support_chat_db();
$limit = max(1, min(50, (int)($_GET['limit'] ?? 50)));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$stmt = $pdo->prepare('SELECT * FROM telegram_logs ORDER BY id DESC LIMIT ? OFFSET ?');
$stmt->execute([$limit + 1, $offset]);
$rows = $stmt->fetchAll();

$hasMore = count($rows) > $limit;
if ($hasMore) {
    array_pop($rows);
}

support_chat_json([
    'ok' => true,
    'logs' => $rows,
    'limit' => $limit,
    'offset' => $offset,
    'has_more' => $hasMore,
    'next_offset' => $offset + count($rows),
    'prev_offset' => max(0, $offset - $limit),
]);
