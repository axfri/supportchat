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
            is_deleted_by_visitor INTEGER NOT NULL DEFAULT 0,
            is_deleted_for_user INTEGER NOT NULL DEFAULT 0,
            deleted_at TEXT,
            deleted_for_user_at TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
        )
    ");

    $columns = $pdo->query('PRAGMA table_info(messages)')->fetchAll();
    $columnNames = array_map(static fn($column) => $column['name'], $columns);

    if (!in_array('is_deleted_by_visitor', $columnNames, true)) {
        $pdo->exec('ALTER TABLE messages ADD COLUMN is_deleted_by_visitor INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('is_deleted_for_user', $columnNames, true)) {
        $pdo->exec('ALTER TABLE messages ADD COLUMN is_deleted_for_user INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('deleted_at', $columnNames, true)) {
        $pdo->exec('ALTER TABLE messages ADD COLUMN deleted_at TEXT');
    }
    if (!in_array('deleted_for_user_at', $columnNames, true)) {
        $pdo->exec('ALTER TABLE messages ADD COLUMN deleted_for_user_at TEXT');
    }
    if (!in_array('delivery_error', $columnNames, true)) {
        $pdo->exec('ALTER TABLE messages ADD COLUMN delivery_error TEXT');
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message_id INTEGER NOT NULL,
            filename TEXT NOT NULL,
            original_filename TEXT NOT NULL,
            mime_type TEXT NOT NULL,
            file_size INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(message_id) REFERENCES messages(id) ON DELETE CASCADE
        )
    ");

    $attachmentColumns = $pdo->query('PRAGMA table_info(attachments)')->fetchAll();
    $attachmentColumnNames = array_map(static fn($column) => $column['name'], $attachmentColumns);
    if (!in_array('telegram_message_id', $attachmentColumnNames, true)) {
        $pdo->exec('ALTER TABLE attachments ADD COLUMN telegram_message_id TEXT');
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_conversations_updated ON conversations(updated_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attachments_message ON attachments(message_id)');
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

function support_chat_admin_conversation_payload(array $conversation): array
{
    $conversation['dialog_id'] = (int)($conversation['id'] ?? 0);
    if (($conversation['channel'] ?? '') === 'web') {
        $conversation['external_id'] = '';
        $conversation['visitor_handle'] = '';
    }
    return $conversation;
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

function support_chat_set_message_delivery_error(PDO $pdo, int $messageId, string $error): void
{
    $stmt = $pdo->prepare('UPDATE messages SET delivery_error = ? WHERE id = ?');
    $stmt->execute([$error !== '' ? $error : null, $messageId]);
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

function support_chat_find_web_conversation(PDO $pdo, string $sessionId): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE channel = 'web' AND external_id = ? LIMIT 1");
    $stmt->execute([$sessionId]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
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

    if ($command === '/start') {
        return ['stored' => false, 'reply' => support_chat_telegram_start_text()];
    }

    if ($command === '/help') {
        return ['stored' => false, 'reply' => support_chat_telegram_help_text()];
    }

    if ($text !== '' && strpos($text, '/') === 0) {
        return ['stored' => false, 'reply' => 'Неизвестная команда. Нажмите /help, чтобы посмотреть список команд.'];
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

    $text = trim((string)($message['text'] ?? $message['caption'] ?? ''));
    if ($text === '') {
        $items = support_chat_telegram_attachment_candidates($message);
        $first = $items[0] ?? null;
        $mime = (string)($first['mime'] ?? '');
        if (strpos($mime, 'image/') === 0) {
            $text = 'Фото';
        } elseif (strpos($mime, 'video/') === 0) {
            $text = 'Видео';
        } elseif ($first) {
            $text = (string)($first['name'] ?? 'Файл');
        } else {
            $text = 'Файл';
        }
    }

    $telegramMessageId = isset($message['message_id']) ? (string)$message['message_id'] : null;
    $conversationId = support_chat_find_or_create_telegram_conversation($pdo, $profile['chat_id'], $profile['name'], $profile['handle']);
    if (support_chat_telegram_message_exists($pdo, $conversationId, $telegramMessageId)) {
        return null;
    }

    $messageId = support_chat_add_message($pdo, $conversationId, 'visitor', $text, $telegramMessageId);
    if ($messageId > 0) {
        support_chat_store_telegram_attachments($pdo, $messageId, $message);
    }

    return $messageId;
}

function support_chat_telegram_attachment_candidates(array $message): array
{
    $items = [];

    if (!empty($message['photo']) && is_array($message['photo'])) {
        $photo = end($message['photo']);
        if (is_array($photo) && !empty($photo['file_id'])) {
            $items[] = [
                'file_id' => (string)$photo['file_id'],
                'name' => 'telegram-photo-' . (string)($message['message_id'] ?? time()) . '.jpg',
                'mime' => 'image/jpeg',
            ];
        }
    }

    if (!empty($message['video']) && is_array($message['video']) && !empty($message['video']['file_id'])) {
        $items[] = [
            'file_id' => (string)$message['video']['file_id'],
            'name' => (string)($message['video']['file_name'] ?? ('telegram-video-' . (string)($message['message_id'] ?? time()) . '.mp4')),
            'mime' => (string)($message['video']['mime_type'] ?? 'video/mp4'),
        ];
    }

    if (!empty($message['document']) && is_array($message['document']) && !empty($message['document']['file_id'])) {
        $items[] = [
            'file_id' => (string)$message['document']['file_id'],
            'name' => (string)($message['document']['file_name'] ?? ('telegram-file-' . (string)($message['message_id'] ?? time()))),
            'mime' => (string)($message['document']['mime_type'] ?? 'application/octet-stream'),
        ];
    }

    return $items;
}

function support_chat_store_telegram_attachments(PDO $pdo, int $messageId, array $message): void
{
    $items = support_chat_telegram_attachment_candidates($message);
    if (count($items) === 0) {
        return;
    }

    $tmpDir = support_chat_base_path('storage' . DIRECTORY_SEPARATOR . 'telegram-tmp');
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0775, true);
    }

    foreach ($items as $item) {
        $ext = strtolower(pathinfo((string)$item['name'], PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = support_chat_extension_from_mime((string)$item['mime']);
            $item['name'] .= '.' . $ext;
        }

        $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(12)) . '.' . $ext;
        $download = support_chat_telegram_download_file((string)$item['file_id'], $tmpPath);
        if (empty($download['ok'])) {
            @file_put_contents(support_chat_base_path('storage' . DIRECTORY_SEPARATOR . 'telegram_errors.log'), json_encode([
                'when' => date('c'),
                'message_id' => $messageId,
                'download_error' => $download,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
            continue;
        }

        try {
            support_chat_store_attachment_from_path($pdo, $messageId, $tmpPath, (string)$item['name'], (string)$item['mime'], true);
        } catch (Throwable $e) {
            @unlink($tmpPath);
            support_chat_log_error('Failed to store Telegram attachment: ' . $e->getMessage());
        }
    }
}

function support_chat_allowed_file_types(): array
{
    return [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'mkv' => 'video/x-matroska',
        'avi' => 'video/x-msvideo',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar',
        'gz' => 'application/gzip',
        'tgz' => 'application/gzip',
    ];
}

function support_chat_extension_from_mime(string $mimeType): string
{
    $mimeType = strtolower(trim($mimeType));
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/x-rar-compressed' => 'rar',
        'application/vnd.rar' => 'rar',
        'application/x-7z-compressed' => '7z',
        'application/x-tar' => 'tar',
        'application/gzip' => 'gz',
        'application/x-gzip' => 'gz',
    ];
    return $map[$mimeType] ?? 'bin';
}

function support_chat_validate_file(string $filename, string $mimeType, int $fileSize): array
{
    $allowed = support_chat_allowed_file_types();
    $maxSize = 100 * 1024 * 1024;

    if ($fileSize > $maxSize) {
        return ['valid' => false, 'error' => 'Размер файла не должен быть больше 100 МБ'];
    }

    if ($fileSize < 1) {
        return ['valid' => false, 'error' => 'Файл не должен быть пустым'];
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        return ['valid' => false, 'error' => 'Формат файла не поддерживается'];
    }

    $mimeType = strtolower(trim($mimeType));
    $expected = $allowed[$ext];
    $aliases = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png', 'image/x-png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'mp4' => ['video/mp4', 'application/mp4', 'application/octet-stream'],
        'webm' => ['video/webm', 'application/octet-stream'],
        'mov' => ['video/quicktime', 'video/mp4', 'application/octet-stream'],
        'mkv' => ['video/x-matroska', 'application/octet-stream'],
        'avi' => ['video/x-msvideo', 'application/octet-stream'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'rar' => ['application/x-rar-compressed', 'application/vnd.rar', 'application/octet-stream'],
        '7z' => ['application/x-7z-compressed', 'application/octet-stream'],
        'tar' => ['application/x-tar', 'application/octet-stream'],
        'gz' => ['application/gzip', 'application/x-gzip', 'application/octet-stream'],
        'tgz' => ['application/gzip', 'application/x-gzip', 'application/octet-stream'],
    ];

    $validMimes = $aliases[$ext] ?? [$expected];
    if ($mimeType !== '' && !in_array($mimeType, $validMimes, true)) {
        return ['valid' => false, 'error' => 'Тип файла не соответствует расширению'];
    }

    return ['valid' => true];
}

function support_chat_store_attachment(PDO $pdo, int $messageId, string $sourceFilePath, string $originalFilename, string $mimeType): int
{
    return support_chat_store_attachment_from_path($pdo, $messageId, $sourceFilePath, $originalFilename, $mimeType, false);
}

function support_chat_store_attachment_from_path(PDO $pdo, int $messageId, string $sourceFilePath, string $originalFilename, string $mimeType, bool $moveRegularFile): int
{
    $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = support_chat_extension_from_mime($mimeType);
        $originalFilename .= '.' . $ext;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $storagePath = support_chat_base_path('storage' . DIRECTORY_SEPARATOR . 'attachments');
    if (!is_dir($storagePath)) {
        mkdir($storagePath, 0775, true);
    }

    $targetPath = $storagePath . DIRECTORY_SEPARATOR . $filename;
    $stored = $moveRegularFile ? @rename($sourceFilePath, $targetPath) : @move_uploaded_file($sourceFilePath, $targetPath);
    if (!$stored && $moveRegularFile) {
        $stored = @copy($sourceFilePath, $targetPath);
        if ($stored) {
            @unlink($sourceFilePath);
        }
    }
    if (!$stored) {
        throw new RuntimeException('Failed to store attachment');
    }

    $fileSize = filesize($targetPath) ?: 0;
    $stmt = $pdo->prepare('INSERT INTO attachments (message_id, filename, original_filename, mime_type, file_size) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$messageId, $filename, $originalFilename, $mimeType, $fileSize]);

    return (int)$pdo->lastInsertId();
}

function support_chat_get_attachments(PDO $pdo, int $messageId): array
{
    $stmt = $pdo->prepare('SELECT * FROM attachments WHERE message_id = ? ORDER BY id ASC');
    $stmt->execute([$messageId]);
    return $stmt->fetchAll();
}

function support_chat_get_attachment_path(string $filename): string
{
    return support_chat_base_path('storage' . DIRECTORY_SEPARATOR . 'attachments' . DIRECTORY_SEPARATOR . $filename);
}

function support_chat_delete_message_by_visitor(PDO $pdo, int $messageId, string $visitorSessionId): bool
{
    $stmt = $pdo->prepare('
        SELECT m.id, m.sender, c.channel, c.external_id
        FROM messages m
        JOIN conversations c ON m.conversation_id = c.id
        WHERE m.id = ? LIMIT 1
    ');
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();

    if (!$message) {
        throw new InvalidArgumentException('Message not found');
    }

    if ($message['channel'] !== 'web' || $message['external_id'] !== $visitorSessionId || $message['sender'] !== 'visitor') {
        throw new InvalidArgumentException('Unauthorized');
    }

    $stmt = $pdo->prepare('UPDATE messages SET is_deleted_by_visitor = 1, deleted_at = CURRENT_TIMESTAMP WHERE id = ?');
    return $stmt->execute([$messageId]);
}

function support_chat_delete_message_for_user(PDO $pdo, int $messageId): bool
{
    $stmt = $pdo->prepare('SELECT id, sender FROM messages WHERE id = ? LIMIT 1');
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();

    if (!$message) {
        throw new InvalidArgumentException('Message not found');
    }

    $stmt = $pdo->prepare('UPDATE messages SET is_deleted_for_user = 1, deleted_for_user_at = CURRENT_TIMESTAMP, deleted_at = CURRENT_TIMESTAMP WHERE id = ?');
    return $stmt->execute([$messageId]);
}

function support_chat_get_message(PDO $pdo, int $messageId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ? LIMIT 1');
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    return is_array($message) ? $message : null;
}
