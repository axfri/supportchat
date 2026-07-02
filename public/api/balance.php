<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    support_chat_json(['ok' => true]);
}

support_chat_require_role('manager');

$pdo = support_chat_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function support_chat_checkbot_users_file(): string
{
    return support_chat_env('CHECKBOT_USERS_FILE', '');
}

function support_chat_sync_checkbot_balance(array $conversation, ?float $newBalance = null, string $comment = ''): ?float
{
    $file = support_chat_checkbot_users_file();
    if ($file === '' || !is_file($file) || !is_readable($file) || ($newBalance !== null && !is_writable($file))) {
        return null;
    }

    $raw = file_get_contents($file);
    $users = json_decode($raw !== false ? $raw : '', true);
    if (!is_array($users)) {
        return null;
    }

    $visitorUserId = trim((string)($conversation['visitor_user_id'] ?? ''));
    $visitorEmail = mb_strtolower(trim((string)($conversation['visitor_email'] ?? '')), 'UTF-8');
    $foundKey = null;

    foreach ($users as $key => $user) {
        if (!is_array($user)) {
            continue;
        }
        $ids = [
            (string)($user['id'] ?? ''),
            (string)($user['telegram_id'] ?? ''),
            (string)($user['telegram_user_id'] ?? ''),
        ];
        $email = mb_strtolower(trim((string)($user['email'] ?? $user['login'] ?? '')), 'UTF-8');
        if (($visitorUserId !== '' && in_array($visitorUserId, $ids, true)) || ($visitorEmail !== '' && $email === $visitorEmail)) {
            $foundKey = $key;
            break;
        }
    }

    if ($foundKey === null) {
        return null;
    }

    $current = is_numeric($users[$foundKey]['balance'] ?? null) ? round((float)$users[$foundKey]['balance'], 2) : 0.0;
    if ($newBalance === null) {
        return $current;
    }

    $newBalance = round($newBalance, 2);
    $transactions = isset($users[$foundKey]['balance_transactions']) && is_array($users[$foundKey]['balance_transactions'])
        ? $users[$foundKey]['balance_transactions']
        : [];
    array_unshift($transactions, [
        'type' => 'support_admin',
        'comment' => $comment,
        'amount' => round($newBalance - $current, 2),
        'balance_before' => $current,
        'balance_after' => $newBalance,
        'created_at' => date('c'),
    ]);
    $users[$foundKey]['balance'] = $newBalance;
    $users[$foundKey]['balance_transactions'] = array_slice($transactions, 0, 100);
    $users[$foundKey]['updated_at'] = date('c');

    file_put_contents($file, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
    return $newBalance;
}

if ($method === 'GET') {
    $conversationId = (int)($_GET['conversation_id'] ?? 0);
    if ($conversationId <= 0) {
        support_chat_json(['ok' => false, 'error' => 'Диалог не выбран'], 422);
    }
    $conversation = support_chat_get_conversation($pdo, $conversationId);
    if ($conversation === null) {
        support_chat_json(['ok' => false, 'error' => 'Диалог не найден'], 404);
    }
    $checkbotBalance = support_chat_sync_checkbot_balance($conversation);
    if ($checkbotBalance !== null && round((float)$conversation['balance'], 2) !== $checkbotBalance) {
        $pdo->prepare('UPDATE conversations SET balance = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$checkbotBalance, $conversationId]);
        $conversation['balance'] = $checkbotBalance;
    }
    $stmt = $pdo->prepare('SELECT bh.*, s.login AS staff_login FROM balance_history bh LEFT JOIN support_staff s ON s.id = bh.staff_id WHERE bh.conversation_id = ? ORDER BY bh.id DESC LIMIT 20');
    $stmt->execute([$conversationId]);
    support_chat_json(['ok' => true, 'balance' => (float)$conversation['balance'], 'history' => $stmt->fetchAll()]);
}

if ($method !== 'POST') {
    support_chat_json(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}

$data = support_chat_input();
$conversationId = (int)($data['conversation_id'] ?? 0);
$newBalanceRaw = str_replace(',', '.', trim((string)($data['balance'] ?? '')));
$comment = trim((string)($data['comment'] ?? ''));

if ($conversationId <= 0 || !is_numeric($newBalanceRaw)) {
    support_chat_json(['ok' => false, 'error' => 'Укажите корректный диалог и сумму'], 422);
}

$newBalance = round((float)$newBalanceRaw, 2);
if ($newBalance < -100000000 || $newBalance > 100000000) {
    support_chat_json(['ok' => false, 'error' => 'Сумма выходит за допустимый диапазон'], 422);
}

try {
    $pdo->beginTransaction();
    $conversation = support_chat_get_conversation($pdo, $conversationId);
    if ($conversation === null) {
        throw new InvalidArgumentException('Диалог не найден');
    }
    $oldBalance = (float)$conversation['balance'];
    $syncedBalance = support_chat_sync_checkbot_balance($conversation, $newBalance, $comment);
    if ($syncedBalance !== null) {
        $newBalance = $syncedBalance;
    }
    $pdo->prepare('UPDATE conversations SET balance = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$newBalance, $conversationId]);
    $pdo->prepare('INSERT INTO balance_history (conversation_id, staff_id, old_balance, new_balance, comment) VALUES (?, ?, ?, ?, ?)')
        ->execute([$conversationId, (int)($_SESSION['support_staff_id'] ?? 0) ?: null, $oldBalance, $newBalance, $comment]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    support_chat_log_error('balance.php error: ' . $e->getMessage());
    support_chat_json(['ok' => false, 'error' => 'Не удалось изменить баланс'], 500);
}

support_chat_json(['ok' => true, 'balance' => $newBalance]);
