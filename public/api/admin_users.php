<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    support_chat_json(['ok' => true]);
}

support_chat_require_role('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    support_chat_json(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}

$pdo = support_chat_db();
$limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$search = trim((string)($_GET['search'] ?? ''));

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(visitor_name LIKE ? OR visitor_handle LIKE ? OR visitor_user_id LIKE ? OR visitor_email LIKE ? OR external_id LIKE ?)';
    $needle = '%' . $search . '%';
    array_push($params, $needle, $needle, $needle, $needle, $needle);
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$queryParams = $params;
$queryParams[] = $limit + 1;
$queryParams[] = $offset;

$stmt = $pdo->prepare("
    SELECT
        id,
        channel,
        external_id,
        visitor_name,
        visitor_handle,
        visitor_user_id,
        visitor_email,
        visitor_avatar,
        visitor_language,
        browser_language,
        balance,
        status,
        created_at,
        updated_at
    FROM conversations
    $whereSql
    ORDER BY updated_at DESC, id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$rows = $stmt->fetchAll();

$hasMore = count($rows) > $limit;
if ($hasMore) {
    array_pop($rows);
}

$users = array_map('support_chat_admin_conversation_payload', $rows);
support_chat_json([
    'ok' => true,
    'users' => $users,
    'has_more' => $hasMore,
    'next_offset' => $offset + count($users),
]);
