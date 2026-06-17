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
}

function support_chat_get_conversation(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $conversation = $stmt->fetch();
    return is_array($conversation) ? $conversation : null;
}

function support_chat_add_message(PDO $pdo, int $conversationId, string $sender, string $body, ?string $telegramMessageId = null): int
{
    if (!support_chat_get_conversation($pdo, $conversationId)) {
        throw new InvalidArgumentException('Conversation not found');
    }

    $body = trim($body);
    if ($body === '') {
        throw new InvalidArgumentException('Message is empty');
    }
    if (strlen($body) > 12000) {
        throw new InvalidArgumentException('Message is too long');
    }

    $stmt = $pdo->prepare('INSERT INTO messages (conversation_id, sender, body, telegram_message_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$conversationId, $sender, $body, $telegramMessageId]);
    $messageId = (int)$pdo->lastInsertId();

    if ($sender === 'visitor') {
        $stmt = $pdo->prepare('UPDATE conversations SET unread_support = unread_support + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    } elseif ($sender === 'support') {
        $stmt = $pdo->prepare('UPDATE conversations SET unread_visitor = unread_visitor + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
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

    $stmt = $pdo->prepare("INSERT INTO conversations (channel, external_id, visitor_name) VALUES ('web', ?, ?)");
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

    $stmt = $pdo->prepare("INSERT INTO conversations (channel, external_id, visitor_name, visitor_handle) VALUES ('telegram', ?, ?, ?)");
    $stmt->execute([$chatId, $name, $handle]);
    return (int)$pdo->lastInsertId();
}
