<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function support_chat_translation_key(): string
{
    return support_chat_env('GOOGLE_TRANSLATE_API_KEY', support_chat_env('GOOGLE_CLOUD_TRANSLATE_API_KEY', ''));
}

function support_chat_translation_enabled(): bool
{
    return support_chat_translation_key() !== '';
}

function support_chat_translation_base_language(string $language): string
{
    $language = strtolower(str_replace('_', '-', trim($language)));
    $language = preg_replace('/[^a-z0-9-]/', '', $language) ?? '';
    $base = explode('-', $language)[0] ?? '';
    if ($base === 'tj') {
        return 'tg';
    }
    return substr($base, 0, 8);
}

function support_chat_google_translate(string $text, string $target, string $source = ''): array
{
    $text = trim($text);
    $target = support_chat_translation_base_language($target);
    $source = support_chat_translation_base_language($source);
    $key = support_chat_translation_key();

    if ($key === '') {
        return ['ok' => false, 'error' => 'Google Translate API key is not configured'];
    }
    if ($text === '' || $target === '') {
        return ['ok' => false, 'error' => 'Translation text or target language is empty'];
    }

    $payload = [
        'q' => $text,
        'target' => $target,
        'format' => 'text',
    ];
    if ($source !== '') {
        $payload['source'] = $source;
    }

    $url = 'https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode($key);
    $response = false;
    $httpStatus = 0;
    $error = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($payload),
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : [];
        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (preg_match('~^HTTP/\S+\s+(\d+)~', $header, $match)) {
                    $httpStatus = (int)$match[1];
                    break;
                }
            }
        }
    }

    if ($response === false || $response === '') {
        return ['ok' => false, 'error' => $error !== '' ? $error : 'Google Translate request failed', 'http_status' => $httpStatus];
    }

    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'Bad Google Translate response', 'http_status' => $httpStatus, 'raw' => (string)$response];
    }
    if ($httpStatus >= 400 || isset($decoded['error'])) {
        $message = (string)($decoded['error']['message'] ?? 'Google Translate API error');
        return ['ok' => false, 'error' => $message, 'http_status' => $httpStatus, 'raw' => $decoded];
    }

    $translation = $decoded['data']['translations'][0] ?? null;
    if (!is_array($translation)) {
        return ['ok' => false, 'error' => 'Google Translate response has no translation', 'http_status' => $httpStatus, 'raw' => $decoded];
    }

    return [
        'ok' => true,
        'translated_text' => html_entity_decode((string)($translation['translatedText'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'detected_language' => support_chat_translation_base_language((string)($translation['detectedSourceLanguage'] ?? $source)),
        'target_language' => $target,
        'provider' => 'google',
    ];
}

function support_chat_message_translatable_text(string $text): bool
{
    $text = trim($text);
    if ($text === '' || preg_match('/^\[[^\]]{1,32}\]$/u', $text)) {
        return false;
    }
    return strtolower($text) !== 'file';
}

function support_chat_store_message_translation(PDO $pdo, int $messageId, array $translation, string $error = ''): void
{
    $stmt = $pdo->prepare('
        UPDATE messages
        SET translated_body = ?,
            detected_language = ?,
            translated_to = ?,
            translation_provider = ?,
            translation_error = ?,
            translated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ');
    $stmt->execute([
        (string)($translation['translated_text'] ?? ''),
        (string)($translation['detected_language'] ?? ''),
        (string)($translation['target_language'] ?? ''),
        (string)($translation['provider'] ?? ''),
        $error,
        $messageId,
    ]);
}

function support_chat_store_message_translation_error(PDO $pdo, int $messageId, string $error): void
{
    $stmt = $pdo->prepare('
        UPDATE messages
        SET translation_provider = ?,
            translation_error = ?,
            translated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ');
    $stmt->execute(['google', $error, $messageId]);
}

function support_chat_store_conversation_detected_language(PDO $pdo, int $messageId, string $language): void
{
    $language = support_chat_translation_base_language($language);
    if ($language === '') {
        return;
    }

    $stmt = $pdo->prepare('
        UPDATE conversations
        SET visitor_language = CASE WHEN visitor_language = \'\' THEN ? ELSE visitor_language END
        WHERE id = (SELECT conversation_id FROM messages WHERE id = ? LIMIT 1)
    ');
    $stmt->execute([$language, $messageId]);
}

function support_chat_detected_conversation_language(PDO $pdo, int $conversationId): string
{
    $stmt = $pdo->prepare('
        SELECT detected_language
        FROM messages
        WHERE conversation_id = ?
          AND sender = \'visitor\'
          AND detected_language <> \'\'
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt->execute([$conversationId]);
    return support_chat_translation_base_language((string)$stmt->fetchColumn());
}

function support_chat_reply_language_for_conversation(PDO $pdo, array $conversation): string
{
    $manual = support_chat_translation_base_language((string)($conversation['reply_language'] ?? ''));
    if ($manual !== '') {
        return $manual;
    }

    $detected = support_chat_detected_conversation_language($pdo, (int)($conversation['id'] ?? 0));
    if ($detected !== '') {
        return $detected;
    }

    return support_chat_translation_base_language((string)($conversation['visitor_language'] ?? $conversation['browser_language'] ?? ''));
}

function support_chat_translate_visitor_message_to_ru(PDO $pdo, int $messageId, string $text): void
{
    if (!support_chat_message_translatable_text($text)) {
        return;
    }
    if (!support_chat_translation_enabled()) {
        support_chat_store_message_translation_error($pdo, $messageId, 'Google Translate API key is not configured');
        return;
    }

    $translation = support_chat_google_translate($text, 'ru');
    if (empty($translation['ok'])) {
        support_chat_store_message_translation_error($pdo, $messageId, (string)($translation['error'] ?? 'Translation failed'));
        return;
    }

    $detected = support_chat_translation_base_language((string)($translation['detected_language'] ?? ''));
    support_chat_store_conversation_detected_language($pdo, $messageId, $detected);
    if ($detected === 'ru') {
        $translation['translated_text'] = '';
    }
    support_chat_store_message_translation($pdo, $messageId, $translation);
}

function support_chat_translate_support_message(PDO $pdo, int $messageId, string $text, string $targetLanguage): array
{
    if (!support_chat_message_translatable_text($text)) {
        return ['ok' => false, 'text' => $text, 'error' => 'Translation text is empty'];
    }
    if (!support_chat_translation_enabled()) {
        $error = 'Google Translate API key is not configured';
        support_chat_store_message_translation_error($pdo, $messageId, $error);
        return ['ok' => false, 'text' => $text, 'error' => $error];
    }

    $targetLanguage = support_chat_translation_base_language($targetLanguage);
    if ($targetLanguage === '' || $targetLanguage === 'ru') {
        return ['ok' => true, 'text' => $text, 'target_language' => 'ru'];
    }

    $translation = support_chat_google_translate($text, $targetLanguage, 'ru');
    if (empty($translation['ok'])) {
        support_chat_store_message_translation_error($pdo, $messageId, (string)($translation['error'] ?? 'Translation failed'));
        return ['ok' => false, 'text' => $text, 'error' => (string)($translation['error'] ?? 'Translation failed')];
    }

    support_chat_store_message_translation($pdo, $messageId, $translation);
    return ['ok' => true, 'text' => (string)$translation['translated_text'], 'target_language' => $targetLanguage];
}
