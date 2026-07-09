<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function support_chat_translation_key(): string
{
    return support_chat_env('GOOGLE_TRANSLATE_API_KEY', support_chat_env('GOOGLE_CLOUD_TRANSLATE_API_KEY', ''));
}

function support_chat_translation_provider(): string
{
    $provider = strtolower(trim(support_chat_env('TRANSLATION_PROVIDER', 'lingva')));
    return in_array($provider, ['libretranslate', 'lingva', 'google'], true) ? $provider : 'lingva';
}

function support_chat_translation_enabled(): bool
{
    if (support_chat_translation_provider() === 'google') {
        return support_chat_translation_key() !== '';
    }
    if (support_chat_translation_provider() === 'libretranslate') {
        return support_chat_libretranslate_url() !== '';
    }
    return support_chat_lingva_urls() !== [];
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

function support_chat_libretranslate_url(): string
{
    return rtrim(trim(support_chat_env('LIBRETRANSLATE_API_URL', support_chat_env('TRANSLATION_API_URL', 'http://127.0.0.1:5000'))), '/');
}

function support_chat_libretranslate_key(): string
{
    return support_chat_env('LIBRETRANSLATE_API_KEY', support_chat_env('TRANSLATION_API_KEY', ''));
}

function support_chat_libretranslate_detected_language(array $decoded, string $source): string
{
    foreach ([
        $decoded['detectedLanguage']['language'] ?? null,
        $decoded['detected_language'] ?? null,
        $decoded['detectedSourceLanguage'] ?? null,
    ] as $candidate) {
        $language = support_chat_extract_lingva_language($candidate);
        if ($language !== '') {
            return $language;
        }
    }
    return support_chat_translation_base_language($source);
}

function support_chat_libretranslate_translate(string $text, string $target, string $source = ''): array
{
    $text = trim($text);
    $target = support_chat_translation_base_language($target);
    $source = support_chat_translation_base_language($source);
    if ($text === '' || $target === '') {
        return ['ok' => false, 'error' => 'Translation text or target language is empty'];
    }

    $url = support_chat_libretranslate_url();
    if ($url === '') {
        return ['ok' => false, 'error' => 'LibreTranslate API URL is not configured'];
    }

    $payload = [
        'q' => $text,
        'source' => $source !== '' ? $source : 'auto',
        'target' => $target,
        'format' => 'text',
    ];
    $key = support_chat_libretranslate_key();
    if ($key !== '') {
        $payload['api_key'] = $key;
    }

    $response = false;
    $httpStatus = 0;
    $error = '';
    $endpoint = $url . '/translate';
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json; charset=utf-8',
                'User-Agent: SupportChat/1.0',
            ],
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Accept: application/json\r\nContent-Type: application/json; charset=utf-8\r\nUser-Agent: SupportChat/1.0\r\n",
                'content' => $body,
                'timeout' => 35,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($endpoint, false, $context);
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
        return ['ok' => false, 'error' => $error !== '' ? $error : 'LibreTranslate request failed', 'http_status' => $httpStatus];
    }

    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'Bad LibreTranslate response', 'http_status' => $httpStatus, 'raw' => (string)$response];
    }
    if ($httpStatus >= 400 || isset($decoded['error'])) {
        return ['ok' => false, 'error' => (string)($decoded['error'] ?? 'LibreTranslate API error'), 'http_status' => $httpStatus, 'raw' => $decoded];
    }

    $translated = (string)($decoded['translatedText'] ?? $decoded['translation'] ?? '');
    if ($translated === '') {
        return ['ok' => false, 'error' => 'LibreTranslate response has no translation', 'http_status' => $httpStatus, 'raw' => $decoded];
    }

    return [
        'ok' => true,
        'translated_text' => html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'detected_language' => support_chat_libretranslate_detected_language($decoded, $source),
        'target_language' => $target,
        'provider' => 'libretranslate',
    ];
}

function support_chat_lingva_urls(): array
{
    $urls = [];
    $configured = support_chat_env('LINGVA_TRANSLATE_API_URL', support_chat_env('TRANSLATION_API_URL', 'https://lingva.ml'));
    $fallbacks = support_chat_env('LINGVA_TRANSLATE_FALLBACK_URLS', '');
    foreach (array_merge([$configured], explode(',', $fallbacks)) as $url) {
        $url = rtrim(trim($url), '/');
        if ($url === '') {
            continue;
        }
        $url = preg_replace('~/api/v1$~', '', $url) ?: $url;
        if (!in_array($url, $urls, true)) {
            $urls[] = $url;
        }
    }
    return $urls;
}

function support_chat_extract_lingva_language($value): string
{
    if (is_array($value)) {
        foreach (['language', 'lang', 'code', 'iso'] as $key) {
            if (isset($value[$key])) {
                $language = support_chat_extract_lingva_language($value[$key]);
                if ($language !== '') {
                    return $language;
                }
            }
        }
        return '';
    }

    $language = strtolower(trim((string)$value));
    $map = [
        'english' => 'en',
        'russian' => 'ru',
        'tajik' => 'tg',
        'uzbek' => 'uz',
        'kyrgyz' => 'ky',
        'kazakh' => 'kk',
        'ukrainian' => 'uk',
        'belarusian' => 'be',
        'armenian' => 'hy',
        'azerbaijani' => 'az',
    ];
    return support_chat_translation_base_language($map[$language] ?? $language);
}

function support_chat_lingva_detected_language(array $decoded, string $source): string
{
    foreach ([
        $decoded['detectedSource'] ?? null,
        $decoded['detectedSourceLanguage'] ?? null,
        $decoded['source'] ?? null,
        $decoded['info']['detectedSource'] ?? null,
        $decoded['info']['detectedSourceLanguage'] ?? null,
    ] as $candidate) {
        $language = support_chat_extract_lingva_language($candidate);
        if ($language !== '') {
            return $language;
        }
    }
    return support_chat_translation_base_language($source);
}

function support_chat_lingva_translate(string $text, string $target, string $source = ''): array
{
    $text = trim($text);
    $target = support_chat_translation_base_language($target);
    $source = support_chat_translation_base_language($source);
    if ($text === '' || $target === '') {
        return ['ok' => false, 'error' => 'Translation text or target language is empty'];
    }

    $sourcePath = $source !== '' ? $source : 'auto';
    $lastError = 'Lingva Translate API URL is not configured';
    $lastStatus = 0;
    foreach (support_chat_lingva_urls() as $baseUrl) {
        $url = $baseUrl . '/api/v1/' . rawurlencode($sourcePath) . '/' . rawurlencode($target) . '/' . rawurlencode($text);
        $response = false;
        $httpStatus = 0;
        $error = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: Mozilla/5.0 SupportChat/1.0',
                ],
            ]);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Accept: application/json\r\nUser-Agent: Mozilla/5.0 SupportChat/1.0\r\n",
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

        $lastStatus = $httpStatus;
        if ($response === false || $response === '') {
            $lastError = $error !== '' ? $error : 'Lingva Translate request failed';
            continue;
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            $lastError = 'Bad Lingva Translate response';
            continue;
        }
        if ($httpStatus >= 400 || isset($decoded['error'])) {
            $lastError = (string)($decoded['error'] ?? 'Lingva Translate API error');
            continue;
        }

        $translated = (string)($decoded['translation'] ?? $decoded['translatedText'] ?? $decoded['data']['translation'] ?? '');
        if ($translated === '') {
            $lastError = 'Lingva Translate response has no translation';
            continue;
        }

        return [
            'ok' => true,
            'translated_text' => html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'detected_language' => support_chat_lingva_detected_language($decoded, $source),
            'target_language' => $target,
            'provider' => 'lingva',
        ];
    }

    return ['ok' => false, 'error' => $lastError, 'http_status' => $lastStatus];
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

function support_chat_translate_text(string $text, string $target, string $source = ''): array
{
    if (support_chat_translation_provider() === 'google') {
        return support_chat_google_translate($text, $target, $source);
    }
    if (support_chat_translation_provider() === 'libretranslate') {
        return support_chat_libretranslate_translate($text, $target, $source);
    }
    return support_chat_lingva_translate($text, $target, $source);
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
    $stmt->execute([support_chat_translation_provider(), $error, $messageId]);
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
        support_chat_store_message_translation_error($pdo, $messageId, 'Translation provider is not configured');
        return;
    }

    $translation = support_chat_translate_text($text, 'ru');
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
        $error = 'Translation provider is not configured';
        support_chat_store_message_translation_error($pdo, $messageId, $error);
        return ['ok' => false, 'text' => $text, 'error' => $error];
    }

    $targetLanguage = support_chat_translation_base_language($targetLanguage);
    if ($targetLanguage === '' || $targetLanguage === 'ru') {
        return ['ok' => true, 'text' => $text, 'target_language' => 'ru'];
    }

    $translation = support_chat_translate_text($text, $targetLanguage, 'ru');
    if (empty($translation['ok'])) {
        support_chat_store_message_translation_error($pdo, $messageId, (string)($translation['error'] ?? 'Translation failed'));
        return ['ok' => false, 'text' => $text, 'error' => (string)($translation['error'] ?? 'Translation failed')];
    }

    support_chat_store_message_translation($pdo, $messageId, $translation);
    return ['ok' => true, 'text' => (string)$translation['translated_text'], 'target_language' => $targetLanguage];
}
