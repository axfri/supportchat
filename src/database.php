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
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $journalMode = strtolower((string)$pdo->query('PRAGMA journal_mode')->fetchColumn());
    if ($journalMode !== 'wal') {
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    $pdo->exec('PRAGMA synchronous = NORMAL');
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
            visitor_user_id TEXT NOT NULL DEFAULT '',
            visitor_email TEXT NOT NULL DEFAULT '',
            visitor_avatar TEXT NOT NULL DEFAULT '',
            visitor_language TEXT NOT NULL DEFAULT '',
            browser_language TEXT NOT NULL DEFAULT '',
            auto_translate_support INTEGER NOT NULL DEFAULT 0,
            reply_language TEXT NOT NULL DEFAULT '',
            balance REAL NOT NULL DEFAULT 0,
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
            translated_body TEXT NOT NULL DEFAULT '',
            detected_language TEXT NOT NULL DEFAULT '',
            translated_to TEXT NOT NULL DEFAULT '',
            translation_provider TEXT NOT NULL DEFAULT '',
            translation_error TEXT NOT NULL DEFAULT '',
            translated_at TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
        )
    ");

    $columns = $pdo->query('PRAGMA table_info(messages)')->fetchAll();
    $columnNames = array_map(static fn($column) => $column['name'], $columns);
    $conversationColumns = $pdo->query('PRAGMA table_info(conversations)')->fetchAll();
    $conversationColumnNames = array_map(static fn($column) => $column['name'], $conversationColumns);

    foreach ([
        'visitor_user_id' => "TEXT NOT NULL DEFAULT ''",
        'visitor_email' => "TEXT NOT NULL DEFAULT ''",
        'visitor_avatar' => "TEXT NOT NULL DEFAULT ''",
        'visitor_language' => "TEXT NOT NULL DEFAULT ''",
        'browser_language' => "TEXT NOT NULL DEFAULT ''",
        'auto_translate_support' => "INTEGER NOT NULL DEFAULT 0",
        'reply_language' => "TEXT NOT NULL DEFAULT ''",
        'balance' => "REAL NOT NULL DEFAULT 0",
    ] as $name => $definition) {
        if (!in_array($name, $conversationColumnNames, true)) {
            $pdo->exec("ALTER TABLE conversations ADD COLUMN {$name} {$definition}");
        }
    }

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
    foreach ([
        'translated_body' => "TEXT NOT NULL DEFAULT ''",
        'detected_language' => "TEXT NOT NULL DEFAULT ''",
        'translated_to' => "TEXT NOT NULL DEFAULT ''",
        'translation_provider' => "TEXT NOT NULL DEFAULT ''",
        'translation_error' => "TEXT NOT NULL DEFAULT ''",
        'translated_at' => "TEXT",
    ] as $name => $definition) {
        if (!in_array($name, $columnNames, true)) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN {$name} {$definition}");
        }
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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_staff (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            login TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('admin', 'manager')),
            is_blocked INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS telegram_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            direction TEXT NOT NULL,
            action TEXT NOT NULL,
            telegram_chat_id TEXT NOT NULL DEFAULT '',
            telegram_message_id TEXT NOT NULL DEFAULT '',
            conversation_id INTEGER,
            payload TEXT NOT NULL DEFAULT '',
            result TEXT NOT NULL DEFAULT '',
            success INTEGER NOT NULL DEFAULT 0,
            error TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS balance_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL,
            staff_id INTEGER,
            old_balance REAL NOT NULL,
            new_balance REAL NOT NULL,
            comment TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
            FOREIGN KEY(staff_id) REFERENCES support_staff(id) ON DELETE SET NULL
        )
    ");

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_telegram_logs_created ON telegram_logs(created_at DESC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_balance_history_conversation ON balance_history(conversation_id, id DESC)');

    $legacyLogin = support_chat_env('SUPPORT_ADMIN_LOGIN', 'admin');
    $legacyPassword = support_chat_env('SUPPORT_ADMIN_PASSWORD', support_chat_env('SUPPORT_ADMIN_TOKEN'));
    $legacyHash = support_chat_env('SUPPORT_ADMIN_PASSWORD_HASH');
    if ($legacyLogin !== '' && ($legacyHash !== '' || $legacyPassword !== '')) {
        $exists = $pdo->prepare('SELECT 1 FROM support_staff WHERE login = ? LIMIT 1');
        $exists->execute([$legacyLogin]);
        if (!$exists->fetchColumn()) {
            $hash = $legacyHash !== '' ? $legacyHash : password_hash($legacyPassword, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO support_staff (login, password_hash, role, is_blocked) VALUES (?, ?, 'admin', 0)");
            $insert->execute([$legacyLogin, $hash]);
        }
    }

    $pdo->exec("UPDATE conversations SET visitor_name = 'Пользователь #' || id WHERE channel = 'web' AND (visitor_name = '' OR visitor_name = 'Посетитель сайта')");
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
    $conversation['display_name'] = support_chat_conversation_display_name($conversation);
    $conversation['visitor_avatar_url'] = support_chat_conversation_avatar_url($conversation);
    $conversation['language_label'] = support_chat_language_label((string)($conversation['visitor_language'] ?? $conversation['browser_language'] ?? ''));
    $conversation['auto_translate_support'] = (bool)($conversation['auto_translate_support'] ?? false);
    $conversation['reply_language'] = support_chat_normalize_language((string)($conversation['reply_language'] ?? ''));
    if (($conversation['channel'] ?? '') === 'web') {
        $conversation['external_id'] = '';
        $conversation['visitor_handle'] = '';
    }
    return $conversation;
}

function support_chat_conversation_display_name(array $conversation): string
{
    $name = trim((string)($conversation['visitor_name'] ?? ''));
    if ($name !== '' && $name !== 'Посетитель сайта') {
        return $name;
    }

    $handle = trim((string)($conversation['visitor_handle'] ?? ''));
    if ($handle !== '') {
        return $handle;
    }

    $email = trim((string)($conversation['visitor_email'] ?? ''));
    if ($email !== '') {
        return $email;
    }

    $userId = trim((string)($conversation['visitor_user_id'] ?? ''));
    if ($userId !== '') {
        return 'Пользователь #' . $userId;
    }

    return 'Пользователь #' . (int)($conversation['id'] ?? 0);
}

function support_chat_normalize_language(string $language): string
{
    $language = strtolower(str_replace('_', '-', trim($language)));
    $language = preg_replace('/[^a-z0-9-]/', '', $language) ?? '';
    return substr($language, 0, 32);
}

function support_chat_language_label(string $language): string
{
    $language = support_chat_normalize_language($language);
    if ($language === '') {
        return '';
    }

    $base = explode('-', $language)[0] ?? $language;
    $labels = [
        'ru' => 'Русский',
        'en' => 'English',
        'uz' => "O'zbek",
        'tg' => 'Тоҷикӣ',
        'tj' => 'Тоҷикӣ',
        'uk' => 'Українська',
        'kk' => 'Қазақша',
        'ky' => 'Кыргызча',
        'tr' => 'Türkçe',
        'az' => 'Azərbaycanca',
    ];

    return ($labels[$base] ?? strtoupper($base)) . ' (' . $language . ')';
}

function support_chat_avatar_storage_dir(): string
{
    return support_chat_base_path('storage' . DIRECTORY_SEPARATOR . 'avatars');
}

function support_chat_conversation_avatar_url(array $conversation): string
{
    $filename = basename((string)($conversation['visitor_avatar'] ?? ''));
    if ($filename === '' || (int)($conversation['id'] ?? 0) <= 0) {
        return '';
    }

    return 'api/avatar.php?id=' . (int)$conversation['id'];
}

function support_chat_store_telegram_avatar(PDO $pdo, int $conversationId, string $telegramUserId): void
{
    $telegramUserId = trim($telegramUserId);
    if ($telegramUserId === '') {
        return;
    }

    $conversation = support_chat_get_conversation($pdo, $conversationId);
    if ($conversation === null) {
        return;
    }

    $current = basename((string)($conversation['visitor_avatar'] ?? ''));
    if ($current !== '' && is_file(support_chat_avatar_storage_dir() . DIRECTORY_SEPARATOR . $current)) {
        return;
    }

    require_once __DIR__ . '/telegram.php';

    try {
        $response = support_chat_telegram_get_user_profile_photos($telegramUserId, 1);
        $photos = $response['result']['photos'][0] ?? null;
        if (empty($response['ok']) || !is_array($photos) || count($photos) === 0) {
            return;
        }

        $photo = end($photos);
        if (!is_array($photo) || empty($photo['file_id'])) {
            return;
        }

        $dir = support_chat_avatar_storage_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $fileId = (string)$photo['file_id'];
        $filename = $conversationId . '-' . substr(sha1($telegramUserId . '|' . $fileId), 0, 16) . '.jpg';
        $targetPath = $dir . DIRECTORY_SEPARATOR . $filename;
        $download = support_chat_telegram_download_file($fileId, $targetPath);
        if (empty($download['ok'])) {
            support_chat_log_telegram($pdo, 'incoming', 'avatar_download', [
                'chat_id' => (string)($conversation['external_id'] ?? ''),
                'conversation_id' => $conversationId,
                'payload' => ['telegram_user_id' => $telegramUserId],
                'result' => $download,
                'success' => false,
                'error' => (string)($download['description'] ?? 'Telegram avatar download failed'),
            ]);
            return;
        }

        $stmt = $pdo->prepare('UPDATE conversations SET visitor_avatar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$filename, $conversationId]);
    } catch (Throwable $e) {
        support_chat_log_error('Telegram avatar load failed: ' . $e->getMessage());
    }
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
        require_once __DIR__ . '/translate.php';
        support_chat_translate_visitor_message_to_ru($pdo, $messageId, $body);
    }

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

    $stmt = $pdo->prepare("INSERT INTO conversations (channel, external_id, visitor_name, status) VALUES ('web', ?, '', 'new')");
    $stmt->execute([$sessionId]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE conversations SET visitor_name = ? WHERE id = ?')->execute(['Пользователь #' . $id, $id]);
    return $id;
}

function support_chat_update_web_conversation_profile(PDO $pdo, int $conversationId, array $profile): void
{
    $name = trim((string)($profile['visitor_name'] ?? $profile['name'] ?? ''));
    $userId = trim((string)($profile['visitor_user_id'] ?? $profile['user_id'] ?? ''));
    $email = trim((string)($profile['visitor_email'] ?? $profile['email'] ?? ''));
    $language = support_chat_normalize_language((string)($profile['visitor_language'] ?? $profile['language'] ?? ''));
    $browserLanguage = support_chat_normalize_language((string)($profile['browser_language'] ?? $profile['navigator_language'] ?? $language));
    if ($name === '' && $userId === '' && $email === '' && $language === '' && $browserLanguage === '') {
        return;
    }

    if (mb_strlen($name, 'UTF-8') > 120) {
        $name = mb_substr($name, 0, 120, 'UTF-8');
    }
    if (strlen($userId) > 80) {
        $userId = substr($userId, 0, 80);
    }
    if (mb_strlen($email, 'UTF-8') > 160) {
        $email = mb_substr($email, 0, 160, 'UTF-8');
    }

    $conversation = support_chat_get_conversation($pdo, $conversationId);
    if ($conversation === null || ($conversation['channel'] ?? '') !== 'web') {
        return;
    }

    $nextName = $name !== '' ? $name : (string)($conversation['visitor_name'] ?? '');
    $nextUserId = $userId !== '' ? $userId : (string)($conversation['visitor_user_id'] ?? '');
    $nextEmail = $email !== '' ? $email : (string)($conversation['visitor_email'] ?? '');
    $nextLanguage = $language !== '' ? $language : (string)($conversation['visitor_language'] ?? '');
    $nextBrowserLanguage = $browserLanguage !== '' ? $browserLanguage : (string)($conversation['browser_language'] ?? '');
    $stmt = $pdo->prepare('
        UPDATE conversations
        SET visitor_name = ?, visitor_user_id = ?, visitor_email = ?, visitor_language = ?, browser_language = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ');
    $stmt->execute([$nextName, $nextUserId, $nextEmail, $nextLanguage, $nextBrowserLanguage, $conversationId]);
}

function support_chat_find_web_conversation(PDO $pdo, string $sessionId): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE channel = 'web' AND external_id = ? LIMIT 1");
    $stmt->execute([$sessionId]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

function support_chat_find_or_create_telegram_conversation(PDO $pdo, string $chatId, string $name, string $handle, string $language = '', string $userId = ''): int
{
    $language = support_chat_normalize_language($language);
    $userId = trim($userId);
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE channel = 'telegram' AND external_id = ? LIMIT 1");
    $stmt->execute([$chatId]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $conversation = support_chat_get_conversation($pdo, (int)$id) ?? [];
        $nextLanguage = $language !== '' ? $language : (string)($conversation['visitor_language'] ?? '');
        $nextUserId = $userId !== '' ? $userId : (string)($conversation['visitor_user_id'] ?? '');
        $update = $pdo->prepare('UPDATE conversations SET visitor_name = ?, visitor_handle = ?, visitor_user_id = ?, visitor_language = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $update->execute([$name, $handle, $nextUserId, $nextLanguage, (int)$id]);
        return (int)$id;
    }

    $stmt = $pdo->prepare("INSERT INTO conversations (channel, external_id, visitor_name, visitor_handle, visitor_user_id, visitor_language, status) VALUES ('telegram', ?, ?, ?, ?, ?, 'new')");
    $stmt->execute([$chatId, $name, $handle, $userId, $language]);
    return (int)$pdo->lastInsertId();
}

function support_chat_telegram_profile(array $message): ?array
{
    if (!isset($message['chat']) || !is_array($message['chat']) || !isset($message['chat']['id'])) {
        return null;
    }

    $chat = $message['chat'];
    $from = isset($message['from']) && is_array($message['from']) ? $message['from'] : [];
    $name = trim((string)($from['first_name'] ?? $chat['first_name'] ?? '') . ' ' . (string)($from['last_name'] ?? $chat['last_name'] ?? ''));
    return [
        'chat_id' => (string)$chat['id'],
        'name' => $name !== '' ? $name : 'Telegram #' . (string)$chat['id'],
        'handle' => isset($from['username']) ? '@' . (string)$from['username'] : (isset($chat['username']) ? '@' . (string)$chat['username'] : ''),
        'user_id' => isset($from['id']) ? (string)$from['id'] : (string)$chat['id'],
        'language' => support_chat_normalize_language((string)($from['language_code'] ?? '')),
    ];
}

function support_chat_log_telegram(PDO $pdo, string $direction, string $action, array $context = []): void
{
    try {
        $payload = array_key_exists('payload', $context)
            ? json_encode($context['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '';
        $result = array_key_exists('result', $context)
            ? json_encode($context['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '';

        $stmt = $pdo->prepare('
            INSERT INTO telegram_logs
                (direction, action, telegram_chat_id, telegram_message_id, conversation_id, payload, result, success, error)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $direction,
            $action,
            (string)($context['chat_id'] ?? ''),
            (string)($context['message_id'] ?? ''),
            isset($context['conversation_id']) ? (int)$context['conversation_id'] : null,
            is_string($payload) ? $payload : '',
            is_string($result) ? $result : '',
            !empty($context['success']) ? 1 : 0,
            (string)($context['error'] ?? ''),
        ]);
    } catch (Throwable $e) {
        support_chat_log_error('Telegram DB log failed: ' . $e->getMessage());
    }
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
        support_chat_log_telegram($pdo, 'incoming', 'profile_parse', [
            'payload' => $message,
            'success' => false,
            'error' => 'Telegram profile was not found',
        ]);
        return ['stored' => false, 'reply' => null];
    }

    $text = trim((string)($message['text'] ?? ''));
    $command = strtolower(preg_replace('/\s+.*$/', '', $text));
    $command = preg_replace('/@.+$/', '', $command);

    if ($command === '/start') {
        support_chat_log_telegram($pdo, 'incoming', 'command_start', [
            'chat_id' => $profile['chat_id'],
            'message_id' => (string)($message['message_id'] ?? ''),
            'payload' => $message,
            'success' => true,
        ]);
        return ['stored' => false, 'reply' => support_chat_telegram_start_text()];
    }

    if ($command === '/help') {
        support_chat_log_telegram($pdo, 'incoming', 'command_help', [
            'chat_id' => $profile['chat_id'],
            'message_id' => (string)($message['message_id'] ?? ''),
            'payload' => $message,
            'success' => true,
        ]);
        return ['stored' => false, 'reply' => support_chat_telegram_help_text()];
    }

    if ($text !== '' && strpos($text, '/') === 0) {
        support_chat_log_telegram($pdo, 'incoming', 'command_unknown', [
            'chat_id' => $profile['chat_id'],
            'message_id' => (string)($message['message_id'] ?? ''),
            'payload' => $message,
            'success' => false,
            'error' => 'Unknown command',
        ]);
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
    $conversationId = support_chat_find_or_create_telegram_conversation($pdo, $profile['chat_id'], $profile['name'], $profile['handle'], $profile['language'] ?? '', $profile['user_id'] ?? '');
    support_chat_store_telegram_avatar($pdo, $conversationId, (string)($profile['user_id'] ?? ''));
    if (support_chat_telegram_message_exists($pdo, $conversationId, $telegramMessageId)) {
        support_chat_log_telegram($pdo, 'incoming', 'duplicate_message', [
            'chat_id' => $profile['chat_id'],
            'message_id' => (string)$telegramMessageId,
            'conversation_id' => $conversationId,
            'payload' => $message,
            'success' => true,
        ]);
        return null;
    }

    $messageId = support_chat_add_message($pdo, $conversationId, 'visitor', $text, $telegramMessageId);
    if ($messageId > 0) {
        support_chat_store_telegram_attachments($pdo, $messageId, $message);
        support_chat_log_telegram($pdo, 'incoming', 'message_stored', [
            'chat_id' => $profile['chat_id'],
            'message_id' => (string)$telegramMessageId,
            'conversation_id' => $conversationId,
            'payload' => $message,
            'result' => ['support_message_id' => $messageId],
            'success' => true,
        ]);
    } else {
        support_chat_log_telegram($pdo, 'incoming', 'message_not_stored', [
            'chat_id' => $profile['chat_id'],
            'message_id' => (string)$telegramMessageId,
            'conversation_id' => $conversationId,
            'payload' => $message,
            'success' => false,
            'error' => 'support_chat_add_message returned 0',
        ]);
    }

    return $messageId;
}

function support_chat_telegram_attachment_candidates(array $message): array
{
    $items = [];
    $messageId = (string)($message['message_id'] ?? time());

    if (!empty($message['photo']) && is_array($message['photo'])) {
        $photo = end($message['photo']);
        if (is_array($photo) && !empty($photo['file_id'])) {
            $items[] = [
                'file_id' => (string)$photo['file_id'],
                'name' => 'telegram-photo-' . $messageId . '.jpg',
                'mime' => 'image/jpeg',
            ];
        }
    }

    $types = [
        'video' => ['prefix' => 'telegram-video-', 'ext' => 'mp4', 'mime' => 'video/mp4'],
        'document' => ['prefix' => 'telegram-file-', 'ext' => 'bin', 'mime' => 'application/octet-stream'],
        'audio' => ['prefix' => 'telegram-audio-', 'ext' => 'mp3', 'mime' => 'audio/mpeg'],
        'voice' => ['prefix' => 'telegram-voice-', 'ext' => 'ogg', 'mime' => 'audio/ogg'],
        'video_note' => ['prefix' => 'telegram-video-note-', 'ext' => 'mp4', 'mime' => 'video/mp4'],
        'animation' => ['prefix' => 'telegram-animation-', 'ext' => 'mp4', 'mime' => 'video/mp4'],
        'sticker' => ['prefix' => 'telegram-sticker-', 'ext' => 'webp', 'mime' => 'image/webp'],
    ];

    foreach ($types as $key => $defaults) {
        if (empty($message[$key]) || !is_array($message[$key]) || empty($message[$key]['file_id'])) {
            continue;
        }

        $item = $message[$key];
        $mime = (string)($item['mime_type'] ?? $defaults['mime']);
        if ($key === 'sticker') {
            if (!empty($item['is_video'])) {
                $mime = 'video/webm';
                $defaults['ext'] = 'webm';
            } elseif (!empty($item['is_animated'])) {
                $mime = 'application/x-tgsticker';
                $defaults['ext'] = 'tgs';
            }
        }
        $name = (string)($item['file_name'] ?? '');
        if ($name === '') {
            $ext = support_chat_extension_from_mime($mime);
            if ($ext === 'bin') {
                $ext = $defaults['ext'];
            }
            $name = $defaults['prefix'] . $messageId . '.' . $ext;
        }

        $items[] = [
            'file_id' => (string)$item['file_id'],
            'name' => $name,
            'mime' => $mime,
        ];
    }

    return $items;
}

function support_chat_store_telegram_attachments(PDO $pdo, int $messageId, array $message): void
{
    require_once __DIR__ . '/telegram.php';

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
            support_chat_log_telegram($pdo, 'incoming', 'attachment_download_failed', [
                'message_id' => (string)($message['message_id'] ?? ''),
                'payload' => $item,
                'result' => $download,
                'success' => false,
                'error' => (string)($download['description'] ?? 'Download failed'),
            ]);
            continue;
        }

        try {
            support_chat_store_attachment_from_path($pdo, $messageId, $tmpPath, (string)$item['name'], (string)$item['mime'], true);
        } catch (Throwable $e) {
            @unlink($tmpPath);
            support_chat_log_error('Failed to store Telegram attachment: ' . $e->getMessage());
            support_chat_log_telegram($pdo, 'incoming', 'attachment_store_failed', [
                'message_id' => (string)($message['message_id'] ?? ''),
                'payload' => $item,
                'success' => false,
                'error' => $e->getMessage(),
            ]);
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
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/oga' => 'oga',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/webm' => 'webm',
        'audio/mp4' => 'm4a',
        'video/x-matroska' => 'mkv',
        'video/x-msvideo' => 'avi',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'application/json' => 'json',
        'application/x-tgsticker' => 'tgs',
    ];
    return $map[$mimeType] ?? 'bin';
}

function support_chat_validate_file(string $filename, string $mimeType, int $fileSize): array
{
    $maxSize = 100 * 1024 * 1024;

    if ($fileSize > $maxSize) {
        return ['valid' => false, 'error' => 'Размер файла не должен быть больше 100 МБ'];
    }

    if ($fileSize < 1) {
        return ['valid' => false, 'error' => 'Файл не должен быть пустым'];
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
    return false;
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
