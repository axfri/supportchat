<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    support_chat_json(['ok' => true]);
}

support_chat_require_role('admin');

$pdo = support_chat_db();
$limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));
$stmt = $pdo->prepare('SELECT * FROM telegram_logs ORDER BY id DESC LIMIT ?');
$stmt->execute([$limit]);

support_chat_json(['ok' => true, 'logs' => $stmt->fetchAll()]);
