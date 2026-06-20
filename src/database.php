<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function support_chat_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = support_chat_sqlite_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    support_chat_migrate($pdo);

    return $pdo;
}

function support_chat_migrate(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            channel TEXT NOT NULL CHECK(channel IN ('web', 'telegram')),
            external_id TEXT,
            visitor_name TEXT NOT NULL DEFAULT '',
            visitor_handle TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'open',
            unread_support INTEGER NOT NULL DEFAULT 0,
            unread_visitor INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(channel, external_id)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL,
            sender TEXT NOT NULL CHECK(sender IN ('visitor', 'support', 'system')),
            body TEXT NOT NULL,
            telegram_message_id TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_conversations_updated ON conversations(updated_at DESC)');
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_messages_telegram_unique ON messages(conversation_id, telegram_message_id) WHERE telegram_message_id IS NOT NULL AND telegram_message_id != ''");
}

function support_chat_statuses(): array
{
    return ['new', 'open', 'closed'];
}

function support_chat_validate_status(string $status): string
{
    if (!in_array($status, support_chat_statuses(), true)) {
        throw new InvalidArgumentException('Invalid status');
    }
    return $status;
}

function support_chat_get_conversation(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $conversation = $stmt->fetch();
    return is_array($conversation) ? $conversation : null;
}

function support_chat_update_conversation_status(PDO $pdo, int $id, string $status, bool $addSystemMessage = true): array
{
    $status = support_chat_validate_status($status);
    $conversation = support_chat_get_conversation($pdo, $id);
    if ($conversation === null) {
        throw new InvalidArgumentException('Conversation not found');
    }

    if ((string)$conversation['status'] !== $status) {
        $stmt = $pdo->prepare('UPDATE conversations SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$status, $id]);

        if ($addSystemMessage) {
            $labels = [
                'new' => 'Диалог помечен как новый',
                'open' => 'Диалог открыт',
                'closed' => 'Диалог закрыт',
            ];
            support_chat_add_message($pdo, $id, 'system', $labels[$status] ?? 'Статус диалога изменен');
        }
    }

    return support_chat_get_conversation($pdo, $id) ?? $conversation;
}

function support_chat_telegram_message_exists(PDO $pdo, int $conversationId, ?string $telegramMessageId): bool
{
    $telegramMessageId = trim((string)$telegramMessageId);
    if ($telegramMessageId === '') {
        return false;
    }

    $stmt = $pdo->prepare('SELECT 1 FROM messages WHERE conversation_id = ? AND telegram_message_id = ? LIMIT 1');
    $stmt->execute([$conversationId, $telegramMessageId]);
    return (bool)$stmt->fetchColumn();
}

function support_chat_add_message(PDO $pdo, int $conversationId, string $sender, string $body, ?string $telegramMessageId = null): int
{
    $conversation = support_chat_get_conversation($pdo, $conversationId);
    if ($conversation === null) {
        throw new InvalidArgumentException('Conversation not found');
    }

    $body = trim($body);
    if ($body === '') {
        throw new InvalidArgumentException('Message is empty');
    }
    if (strlen($body) > 12000) {
        throw new InvalidArgumentException('Message is too long');
    }
    if ($sender === 'visitor' && support_chat_telegram_message_exists($pdo, $conversationId, $telegramMessageId)) {
        return 0;
    }

    $stmt = $pdo->prepare('INSERT INTO messages (conversation_id, sender, body, telegram_message_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$conversationId, $sender, $body, $telegramMessageId]);
    $messageId = (int)$pdo->lastInsertId();

    if ($sender === 'visitor') {
        $stmt = $pdo->prepare("UPDATE conversations SET status = CASE WHEN status = 'closed' THEN 'new' ELSE status END, unread_support = unread_support + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    } elseif ($sender === 'support') {
        $stmt = $pdo->prepare("UPDATE conversations SET status = 'open', unread_visitor = unread_visitor + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    } else {
        $stmt = $pdo->prepare('UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    }
    $stmt->execute([$conversationId]);

    return $messageId;
}

function support_chat_find_or_create_web_conversation(PDO $pdo, string $sessionId): int
{
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE channel = 'web' AND external_id = ? LIMIT 1");
    $stmt->execute([$sessionId]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int)$id;
    }

    $stmt = $pdo->prepare("INSERT INTO conversations (channel, external_id, visitor_name, status) VALUES ('web', ?, ?, 'new')");
    $stmt->execute([$sessionId, 'Посетитель сайта']);
    return (int)$pdo->lastInsertId();
}

function support_chat_find_or_create_telegram_conversation(PDO $pdo, string $chatId, string $name, string $handle): int
{
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE channel = 'telegram' AND external_id = ? LIMIT 1");
    $stmt->execute([$chatId]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $update = $pdo->prepare('UPDATE conversations SET visitor_name = ?, visitor_handle = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $update->execute([$name, $handle, (int)$id]);
        return (int)$id;
    }

    $stmt = $pdo->prepare("INSERT INTO conversations (channel, external_id, visitor_name, visitor_handle, status) VALUES ('telegram', ?, ?, ?, 'new')");
    $stmt->execute([$chatId, $name, $handle]);
    return (int)$pdo->lastInsertId();
}

function support_chat_telegram_profile(array $message): ?array
{
    if (!isset($message['chat']) || !is_array($message['chat']) || !isset($message['chat']['id'])) {
        return null;
    }

    $chat = $message['chat'];
    $name = trim((string)($chat['first_name'] ?? '') . ' ' . (string)($chat['last_name'] ?? ''));
    return [
        'chat_id' => (string)$chat['id'],
        'name' => $name !== '' ? $name : 'Telegram user',
        'handle' => isset($chat['username']) ? '@' . (string)$chat['username'] : '',
    ];
}

function support_chat_telegram_start_text(): string
{
    return "Здравствуйте! Вы написали в поддержку F-ART.bot.\n\nОпишите вопрос одним сообщением. Операторы постараются ответить быстрее.";
}

function support_chat_telegram_help_text(): string
{
    return "Команды бота:\n/start - начать диалог с поддержкой\n/help - помощь\n\nЧтобы связаться с оператором, просто отправьте сообщение в этот чат.";
}

function support_chat_handle_telegram_message(PDO $pdo, array $message): array
{
    $profile = support_chat_telegram_profile($message);
    if ($profile === null) {
        return ['stored' => false, 'reply' => null];
    }

    $text = trim((string)($message['text'] ?? ''));
    $command = strtolower(preg_replace('/\s+.*$/', '', $text));
    $command = preg_replace('/@.+$/', '', $command);
    $conversationId = support_chat_find_or_create_telegram_conversation($pdo, $profile['chat_id'], $profile['name'], $profile['handle']);

    if ($command === '/start') {
        support_chat_update_conversation_status($pdo, $conversationId, 'open', false);
        return ['stored' => false, 'reply' => support_chat_telegram_start_text()];
    }

    if ($command === '/help') {
        return ['stored' => false, 'reply' => support_chat_telegram_help_text()];
    }

    if ($text !== '' && strpos($text, '/') === 0) {
        return ['stored' => false, 'reply' => "Неизвестная команда. Нажмите /help, чтобы посмотреть список команд."];
    }

    $messageId = support_chat_ingest_telegram_message($pdo, $message);
    return ['stored' => $messageId !== null, 'reply' => null];
}

function support_chat_ingest_telegram_message(PDO $pdo, array $message): ?int
{
    $profile = support_chat_telegram_profile($message);
    if ($profile === null) {
        return null;
    }

    $text = trim((string)($message['text'] ?? ''));
    if ($text === '') {
        $text = '[не текстовое сообщение]';
    }

    $telegramMessageId = isset($message['message_id']) ? (string)$message['message_id'] : null;
    $conversationId = support_chat_find_or_create_telegram_conversation($pdo, $profile['chat_id'], $profile['name'], $profile['handle']);
    if (support_chat_telegram_message_exists($pdo, $conversationId, $telegramMessageId)) {
        return null;
    }

    return support_chat_add_message($pdo, $conversationId, 'visitor', $text, $telegramMessageId);
}
