<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';
require_once __DIR__ . '/../../src/telegram.php';
require_once __DIR__ . '/../../src/translate.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    support_chat_json(['ok' => true]);
}

$pdo = support_chat_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isAdmin = isset($_GET['admin']) && $_GET['admin'] === '1';
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$isMultipart = strpos($contentType, 'multipart/form-data') !== false;
$data = !empty($_POST) ? $_POST : ($isMultipart ? [] : support_chat_input());

if ($isAdmin) {
    support_chat_require_admin();
} else {
    support_chat_session_start();
}

function support_chat_normalize_uploaded_files(): ?array
{
    $files = $_FILES['files'] ?? null;
    if (empty($files) && !empty($_FILES)) {
        $files = ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []];
        foreach ($_FILES as $file) {
            if (!isset($file['name'])) {
                continue;
            }
            if (is_array($file['name'])) {
                foreach ($file['name'] as $i => $name) {
                    $files['name'][] = $name;
                    $files['type'][] = $file['type'][$i] ?? '';
                    $files['tmp_name'][] = $file['tmp_name'][$i] ?? '';
                    $files['error'][] = $file['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                    $files['size'][] = $file['size'][$i] ?? 0;
                }
            } else {
                $files['name'][] = $file['name'];
                $files['type'][] = $file['type'] ?? '';
                $files['tmp_name'][] = $file['tmp_name'] ?? '';
                $files['error'][] = $file['error'] ?? UPLOAD_ERR_NO_FILE;
                $files['size'][] = $file['size'] ?? 0;
            }
        }
    }

    if (empty($files) || !isset($files['name'])) {
        return null;
    }

    if (!is_array($files['name'])) {
        $files = [
            'name' => [$files['name']],
            'type' => [$files['type']],
            'tmp_name' => [$files['tmp_name']],
            'error' => [$files['error']],
            'size' => [$files['size']],
        ];
    }

    return $files;
}

function support_chat_has_uploaded_files(?array $files): bool
{
    return !empty($files) && count(array_filter($files['name'], static fn($name) => trim((string)$name) !== '')) > 0;
}

function support_chat_parse_size(string $value): int
{
    $value = trim($value);
    $last = strtolower($value[strlen($value) - 1] ?? '');
    $num = (int)$value;
    if ($last === 'g') {
        return $num * 1024 * 1024 * 1024;
    }
    if ($last === 'm') {
        return $num * 1024 * 1024;
    }
    if ($last === 'k') {
        return $num * 1024;
    }
    return $num;
}

function support_chat_upload_error_text(string $name, int $code): string
{
    $name = basename($name);
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return $name . ': файл слишком большой';
    }
    if ($code === UPLOAD_ERR_PARTIAL) {
        return $name . ': файл загружен только частично';
    }
    if ($code === UPLOAD_ERR_NO_FILE) {
        return $name . ': файл не был загружен';
    }
    return $name . ': не удалось загрузить файл';
}

function support_chat_attachment_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM attachments WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $attachment = $stmt->fetch();
    return is_array($attachment) ? $attachment : null;
}

function support_chat_log_telegram_result(int $conversationId, array $response, string $context): void
{
    if (!empty($response['ok'])) {
        return;
    }

    @file_put_contents(support_chat_base_path('storage' . DIRECTORY_SEPARATOR . 'telegram_errors.log'), json_encode([
        'when' => date('c'),
        'conversation_id' => $conversationId,
        'context' => $context,
        'telegram_response' => $response,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

function support_chat_log_outgoing_telegram(PDO $pdo, int $conversationId, array $conversation, string $action, array $payload, array $response): void
{
    support_chat_log_telegram($pdo, 'outgoing', $action, [
        'chat_id' => (string)($conversation['external_id'] ?? ''),
        'message_id' => (string)($response['result']['message_id'] ?? ''),
        'conversation_id' => $conversationId,
        'payload' => $payload,
        'result' => $response,
        'success' => !empty($response['ok']),
        'error' => empty($response['ok']) ? (string)($response['description'] ?? 'Telegram request failed') : '',
    ]);
}

function support_chat_update_message_telegram_id(PDO $pdo, int $messageId, string $telegramMessageId): void
{
    if ($telegramMessageId === '') {
        return;
    }

    $stmt = $pdo->prepare('UPDATE messages SET telegram_message_id = ? WHERE id = ?');
    $stmt->execute([$telegramMessageId, $messageId]);
}

function support_chat_update_attachment_telegram_id(PDO $pdo, int $attachmentId, string $telegramMessageId): void
{
    if ($telegramMessageId === '') {
        return;
    }

    $stmt = $pdo->prepare('UPDATE attachments SET telegram_message_id = ? WHERE id = ?');
    $stmt->execute([$telegramMessageId, $attachmentId]);
}

function support_chat_send_admin_message_to_telegram(PDO $pdo, int $messageId, int $conversationId, array $conversation, string $body, array $attachments): array
{
    $errors = [];
    if (($conversation['channel'] ?? '') !== 'telegram' || trim((string)($conversation['external_id'] ?? '')) === '') {
        return $errors;
    }

    $chatId = (string)$conversation['external_id'];
    if (count($attachments) === 0) {
        if ($body !== '') {
            $payload = ['text' => $body];
            $response = support_chat_telegram_send($chatId, $body);
            support_chat_log_outgoing_telegram($pdo, $conversationId, $conversation, 'sendMessage', $payload, $response);
            support_chat_log_telegram_result($conversationId, $response, 'sendMessage');
            if (empty($response['ok'])) {
                $errors[] = $response['description'] ?? 'Telegram не принял сообщение';
            } else {
                support_chat_update_message_telegram_id($pdo, $messageId, (string)($response['result']['message_id'] ?? ''));
            }
        }
        return $errors;
    }

    foreach ($attachments as $index => $attachment) {
        $path = support_chat_get_attachment_path((string)$attachment['filename']);
        $caption = $index === 0 ? $body : '';
        $payload = [
            'filename' => (string)$attachment['original_filename'],
            'mime_type' => (string)$attachment['mime_type'],
            'caption' => $caption,
        ];
        $response = support_chat_telegram_send_attachment(
            $chatId,
            $path,
            (string)$attachment['original_filename'],
            (string)$attachment['mime_type'],
            $caption
        );
        support_chat_log_outgoing_telegram($pdo, $conversationId, $conversation, 'sendAttachment', $payload, $response);
        support_chat_log_telegram_result($conversationId, $response, 'sendAttachment');
        if (empty($response['ok'])) {
            $errors[] = ((string)$attachment['original_filename']) . ': ' . ($response['description'] ?? 'Telegram не принял файл');
        } else {
            $telegramMessageId = (string)($response['result']['message_id'] ?? '');
            support_chat_update_attachment_telegram_id($pdo, (int)$attachment['id'], $telegramMessageId);
            if ($index === 0) {
                support_chat_update_message_telegram_id($pdo, $messageId, $telegramMessageId);
            }
        }
    }

    return $errors;
}

function support_chat_delete_admin_message_from_telegram(PDO $pdo, int $messageId): array
{
    $stmt = $pdo->prepare('
        SELECT m.id, m.sender, m.conversation_id, m.telegram_message_id, c.channel, c.external_id
        FROM messages m
        JOIN conversations c ON c.id = m.conversation_id
        WHERE m.id = ?
        LIMIT 1
    ');
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();

    if (!$message || ($message['sender'] ?? '') !== 'support' || ($message['channel'] ?? '') !== 'telegram') {
        return [];
    }

    $chatId = trim((string)($message['external_id'] ?? ''));
    if ($chatId === '') {
        return [];
    }

    $ids = [];
    $mainId = trim((string)($message['telegram_message_id'] ?? ''));
    if ($mainId !== '') {
        $ids[] = $mainId;
    }

    $attachments = support_chat_get_attachments($pdo, $messageId);
    foreach ($attachments as $attachment) {
        $attachmentMessageId = trim((string)($attachment['telegram_message_id'] ?? ''));
        if ($attachmentMessageId !== '') {
            $ids[] = $attachmentMessageId;
        }
    }

    $ids = array_values(array_unique($ids));
    $errors = [];
    foreach ($ids as $id) {
        $response = support_chat_telegram_delete_message($chatId, $id);
        support_chat_log_telegram($pdo, 'outgoing', 'deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $id,
            'conversation_id' => (int)($message['conversation_id'] ?? 0),
            'payload' => ['message_id' => $id],
            'result' => $response,
            'success' => !empty($response['ok']),
            'error' => empty($response['ok']) ? (string)($response['description'] ?? 'Telegram request failed') : '',
        ]);
        support_chat_log_telegram_result((int)$message['id'], $response, 'deleteMessage');
        if (empty($response['ok'])) {
            $description = (string)($response['description'] ?? 'Telegram не удалил сообщение');
            if (stripos($description, 'message to delete not found') === false) {
                $errors[] = $description;
            }
        }
    }

    return $errors;
}

if ($method === 'GET') {
    if ($isAdmin) {
        $conversationId = (int)($_GET['conversation_id'] ?? 0);
    } else {
        $conversationId = support_chat_find_web_conversation($pdo, session_id()) ?? 0;
    }

    if ($conversationId <= 0) {
        if ($isAdmin) {
            support_chat_json(['ok' => false, 'error' => 'Conversation is required'], 422);
        }
        support_chat_json([
            'ok' => true,
            'conversation_id' => null,
            'conversation' => null,
            'messages' => [],
        ]);
    }

    $limit = max(1, min(80, (int)($_GET['limit'] ?? 30)));
    $beforeId = (int)($_GET['before_id'] ?? 0);
    $afterId = (int)($_GET['after_id'] ?? 0);
    $params = [$conversationId];
    $where = 'conversation_id = ?';
    if ($beforeId > 0) {
        $where .= ' AND id < ?';
        $params[] = $beforeId;
    } elseif ($afterId > 0) {
        $where .= ' AND id > ?';
        $params[] = $afterId;
    }
    $params[] = $limit + 1;
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE {$where} ORDER BY id DESC LIMIT ?");
    $stmt->execute($params);
    $messages = array_reverse($stmt->fetchAll());
    $hasMoreBefore = count($messages) > $limit;
    if ($hasMoreBefore) {
        array_shift($messages);
    }

    foreach ($messages as &$message) {
        $message['attachments'] = support_chat_get_attachments($pdo, (int)$message['id']);
        $message['is_deleted_by_visitor'] = false;
        $message['is_deleted_for_user'] = (bool)($message['is_deleted_for_user'] ?? 0);

        if (!$isAdmin && $message['is_deleted_for_user']) {
            $message = null;
        }
    }
    unset($message);

    if (!$isAdmin) {
        $messages = array_values(array_filter($messages, static fn($message) => $message !== null));
    }

    try {
        if ($isAdmin) {
            $pdo->prepare('UPDATE conversations SET unread_support = 0 WHERE id = ?')->execute([$conversationId]);
        } else {
            $pdo->prepare('UPDATE conversations SET unread_visitor = 0 WHERE id = ?')->execute([$conversationId]);
        }
    } catch (Throwable $e) {
        support_chat_log_error('Failed to update unread flags: ' . $e->getMessage());
    }

    $conversation = support_chat_get_conversation($pdo, $conversationId);
    if ($isAdmin && is_array($conversation)) {
        $conversation = support_chat_admin_conversation_payload($conversation);
    }

    support_chat_json([
        'ok' => true,
        'conversation_id' => $conversationId,
        'conversation' => $conversation,
        'messages' => $messages,
        'has_more_before' => $hasMoreBefore,
        'limit' => $limit,
    ]);
}

if ($method === 'DELETE' || ($method === 'POST' && (($data['action'] ?? '') === 'delete_for_user'))) {
    if (!$isAdmin) {
        support_chat_json(['ok' => false, 'error' => 'Unauthorized'], 401);
    }

    $messageId = (int)($data['message_id'] ?? 0);
    if ($messageId <= 0) {
        support_chat_json(['ok' => false, 'error' => 'Не указан ID сообщения'], 422);
    }

    try {
        $telegramErrors = support_chat_delete_admin_message_from_telegram($pdo, $messageId);
        $success = support_chat_delete_message_for_user($pdo, $messageId);
        if (!$success) {
            throw new RuntimeException('Failed to update message');
        }
        support_chat_json(['ok' => true, 'message_id' => $messageId, 'telegram_errors' => $telegramErrors]);
    } catch (InvalidArgumentException $e) {
        support_chat_json(['ok' => false, 'error' => 'Сообщение не найдено или недоступно'], 422);
    } catch (Throwable $e) {
        support_chat_log_error('messages.php delete_for_user error: ' . $e->getMessage());
        support_chat_json(['ok' => false, 'error' => 'Не удалось удалить сообщение'], 500);
    }
}

if ($method === 'POST' && $isAdmin && (($data['action'] ?? '') === 'translation_settings')) {
    $conversationId = (int)($data['conversation_id'] ?? 0);
    $enabled = !empty($data['enabled']) ? 1 : 0;
    $replyLanguage = support_chat_translation_base_language((string)($data['reply_language'] ?? ''));
    if ($conversationId <= 0) {
        support_chat_json(['ok' => false, 'error' => 'Диалог не выбран'], 422);
    }
    $conversation = support_chat_get_conversation($pdo, $conversationId);
    if ($conversation === null) {
        support_chat_json(['ok' => false, 'error' => 'Диалог не найден'], 404);
    }
    $stmt = $pdo->prepare('UPDATE conversations SET auto_translate_support = ?, reply_language = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$enabled, $replyLanguage, $conversationId]);
    $updated = support_chat_get_conversation($pdo, $conversationId);
    support_chat_json(['ok' => true, 'conversation' => support_chat_admin_conversation_payload($updated ?: $conversation)]);
}

if ($method === 'POST') {
    $body = trim((string)($data['body'] ?? ''));
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMax = support_chat_parse_size((string)ini_get('post_max_size') ?: '0');
    $files = support_chat_normalize_uploaded_files();
    $hasFiles = support_chat_has_uploaded_files($files);

    if ($contentLength > 0 && $postMax > 0 && $contentLength > $postMax) {
        support_chat_json(['ok' => false, 'error' => 'Загружаемый файл слишком большой для конфигурации сервера'], 413);
    }

    if ($body === '' && !$hasFiles) {
        support_chat_json(['ok' => false, 'error' => 'Нужно написать сообщение или прикрепить файл'], 422);
    }

    $storedAttachments = [];
    $uploadErrors = [];

    try {
        $conv = null;
        if ($isAdmin) {
            $conversationId = (int)($data['conversation_id'] ?? 0);
            if ($conversationId <= 0) {
                support_chat_json(['ok' => false, 'error' => 'Диалог не выбран'], 422);
            }

            $conv = support_chat_get_conversation($pdo, $conversationId);
            if ($conv === null) {
                support_chat_json(['ok' => false, 'error' => 'Диалог не найден'], 404);
            }
            if ($conv['status'] === 'closed') {
                support_chat_json(['ok' => false, 'error' => 'Диалог закрыт'], 409);
            }

            $messageId = support_chat_add_message($pdo, $conversationId, 'support', $body !== '' ? $body : '[файл]');
            if (!empty($conv['auto_translate_support']) && $body !== '') {
                $targetLanguage = support_chat_reply_language_for_conversation($pdo, $conv);
                $translation = support_chat_translate_support_message($pdo, $messageId, $body, $targetLanguage);
                if (!empty($translation['ok'])) {
                    $body = (string)($translation['text'] ?? $body);
                } elseif (!empty($translation['error'])) {
                    $uploadErrors[] = 'Перевод ответа не выполнен: ' . (string)$translation['error'];
                }
            }
        } else {
            support_chat_rate_limit('web_message');
            $conversationId = support_chat_find_or_create_web_conversation($pdo, session_id());
            support_chat_update_web_conversation_profile($pdo, $conversationId, $data);
            $messageId = support_chat_add_message($pdo, $conversationId, 'visitor', $body !== '' ? $body : '[файл]');
        }

        if ($hasFiles && is_array($files)) {
            foreach ($files['name'] as $i => $name) {
                $name = (string)$name;
                if (trim($name) === '') {
                    continue;
                }

                $fileError = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                if ($fileError !== UPLOAD_ERR_OK) {
                    $uploadErrors[] = support_chat_upload_error_text($name, $fileError);
                    continue;
                }

                $tmpName = (string)($files['tmp_name'][$i] ?? '');
                $detectedMime = function_exists('mime_content_type') ? (string)mime_content_type($tmpName) : (string)($files['type'][$i] ?? '');
                $validation = support_chat_validate_file($name, $detectedMime, (int)($files['size'][$i] ?? 0));
                if (!$validation['valid']) {
                    $uploadErrors[] = basename($name) . ': ' . $validation['error'];
                    continue;
                }

                try {
                    $attachmentId = support_chat_store_attachment($pdo, $messageId, $tmpName, $name, $detectedMime);
                    $attachment = support_chat_attachment_by_id($pdo, $attachmentId);
                    if ($attachment !== null) {
                        $storedAttachments[] = $attachment;
                    }
                } catch (RuntimeException $e) {
                    support_chat_log_error('Failed to store attachment: ' . $e->getMessage());
                    $uploadErrors[] = 'Не удалось сохранить файл ' . basename($name);
                }
            }

            if (count($storedAttachments) === 0 && $body === '') {
                support_chat_json(['ok' => false, 'error' => $uploadErrors[0] ?? 'Файл не удалось загрузить'], 422);
            }
        }

        if ($isAdmin && is_array($conv)) {
            $telegramErrors = support_chat_send_admin_message_to_telegram($pdo, $messageId, $conversationId, $conv, $body, $storedAttachments);
            if ($telegramErrors) {
                $deliveryError = implode("\n", $telegramErrors);
                support_chat_set_message_delivery_error($pdo, $messageId, $deliveryError);
                foreach ($telegramErrors as $telegramError) {
                    $uploadErrors[] = 'Сообщение сохранено в чате, но не доставлено в Telegram: ' . $telegramError;
                }
            } else {
                support_chat_set_message_delivery_error($pdo, $messageId, '');
            }
        }
    } catch (InvalidArgumentException $exception) {
        support_chat_json(['ok' => false, 'error' => $exception->getMessage()], 422);
    } catch (Throwable $e) {
        support_chat_log_error('messages.php POST error: ' . $e->getMessage());
        support_chat_json(['ok' => false, 'error' => 'Внутренняя ошибка сервера'], 500);
    }

    support_chat_json([
        'ok' => true,
        'message_id' => $messageId,
        'conversation_id' => $conversationId,
        'upload_errors' => $uploadErrors ?? [],
    ]);
}

support_chat_json(['ok' => false, 'error' => 'Method not allowed'], 405);
