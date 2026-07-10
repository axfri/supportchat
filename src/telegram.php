<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function support_chat_telegram_api_url(string $method): string
{
    $token = support_chat_env('TELEGRAM_BOT_TOKEN');
    return 'https://api.telegram.org/bot' . $token . '/' . $method;
}

function support_chat_telegram_request(string $method, array $payload, array $files = []): array
{
    $token = support_chat_env('TELEGRAM_BOT_TOKEN');
    if ($token === '') {
        return ['ok' => false, 'description' => 'Telegram bot token is not configured'];
    }

    if (!function_exists('curl_init')) {
        if (!empty($files)) {
            return ['ok' => false, 'description' => 'PHP curl extension is required for Telegram file upload'];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($payload),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents(support_chat_telegram_api_url($method), false, $context);
        $decoded = is_string($response) ? json_decode($response, true) : null;
        return is_array($decoded) ? $decoded : ['ok' => false, 'description' => 'Bad Telegram response'];
    }

    foreach ($files as $field => $file) {
        $payload[$field] = new CURLFile($file['path'], $file['mime'] ?: 'application/octet-stream', $file['name'] ?: basename($file['path']));
    }

    $ch = curl_init(support_chat_telegram_api_url($method));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'description' => $error !== '' ? $error : 'Telegram request failed', 'http_status' => $httpStatus];
    }

    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'description' => 'Bad Telegram response', 'http_status' => $httpStatus, 'raw' => (string)$response];
    }

    if ($httpStatus > 0 && !isset($decoded['http_status'])) {
        $decoded['http_status'] = $httpStatus;
    }

    return $decoded;
}

function support_chat_telegram_send(string $chatId, string $text): array
{
    return support_chat_telegram_request('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => 'true',
    ]);
}

function support_chat_telegram_send_attachment(string $chatId, string $path, string $name, string $mimeType, string $caption = ''): array
{
    if (!is_file($path)) {
        return ['ok' => false, 'description' => 'Attachment file was not found'];
    }

    $mimeType = strtolower(trim($mimeType));
    $field = 'document';
    $method = 'sendDocument';

    if (strpos($mimeType, 'image/') === 0) {
        $field = 'photo';
        $method = 'sendPhoto';
    } elseif (strpos($mimeType, 'video/') === 0) {
        $field = 'video';
        $method = 'sendVideo';
    } elseif (strpos($mimeType, 'audio/') === 0) {
        $field = 'audio';
        $method = 'sendAudio';
    }

    $payload = [
        'chat_id' => $chatId,
    ];

    $caption = trim($caption);
    if ($caption !== '') {
        $payload['caption'] = function_exists('mb_substr') ? mb_substr($caption, 0, 1024) : substr($caption, 0, 1024);
    }

    $response = support_chat_telegram_request($method, $payload, [
        $field => [
            'path' => $path,
            'mime' => $mimeType,
            'name' => $name,
        ],
    ]);
    if (!empty($response['ok']) || $method === 'sendDocument') {
        return $response;
    }

    return support_chat_telegram_request('sendDocument', $payload, [
        'document' => [
            'path' => $path,
            'mime' => $mimeType,
            'name' => $name,
        ],
    ]);
}

function support_chat_telegram_delete_message(string $chatId, string $messageId): array
{
    return support_chat_telegram_request('deleteMessage', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
    ]);
}

function support_chat_telegram_get_file(string $fileId): array
{
    return support_chat_telegram_request('getFile', [
        'file_id' => $fileId,
    ]);
}

function support_chat_telegram_get_user_profile_photos(string $userId, int $limit = 1): array
{
    return support_chat_telegram_request('getUserProfilePhotos', [
        'user_id' => $userId,
        'limit' => (string)max(1, min(10, $limit)),
    ]);
}

function support_chat_telegram_download_file(string $fileId, string $targetPath): array
{
    $token = support_chat_env('TELEGRAM_BOT_TOKEN');
    if ($token === '') {
        return ['ok' => false, 'description' => 'Telegram bot token is not configured'];
    }

    $file = support_chat_telegram_get_file($fileId);
    if (empty($file['ok']) || empty($file['result']['file_path'])) {
        return ['ok' => false, 'description' => $file['description'] ?? 'Telegram file path was not returned', 'telegram_response' => $file];
    }

    $dir = dirname($targetPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $url = 'https://api.telegram.org/file/bot' . $token . '/' . ltrim((string)$file['result']['file_path'], '/');
    $in = @fopen($url, 'rb');
    if (!is_resource($in)) {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'description' => 'Telegram file download failed'];
        }

        $out = @fopen($targetPath, 'wb');
        if (!is_resource($out)) {
            return ['ok' => false, 'description' => 'Could not create local file'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $out,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
        ]);
        $ok = curl_exec($ch);
        $error = curl_error($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($out);

        if (!$ok || $httpStatus >= 400) {
            @unlink($targetPath);
            return ['ok' => false, 'description' => $error !== '' ? $error : 'Telegram file download failed', 'http_status' => $httpStatus];
        }

        if (!is_file($targetPath) || filesize($targetPath) < 1) {
            @unlink($targetPath);
            return ['ok' => false, 'description' => 'Downloaded Telegram file is empty'];
        }

        return ['ok' => true, 'path' => $targetPath, 'telegram_file' => $file['result']];
    }

    $out = @fopen($targetPath, 'wb');
    if (!is_resource($out)) {
        fclose($in);
        return ['ok' => false, 'description' => 'Could not create local file'];
    }

    stream_copy_to_stream($in, $out);
    fclose($in);
    fclose($out);

    if (!is_file($targetPath) || filesize($targetPath) < 1) {
        @unlink($targetPath);
        return ['ok' => false, 'description' => 'Downloaded Telegram file is empty'];
    }

    return ['ok' => true, 'path' => $targetPath, 'telegram_file' => $file['result']];
}

function support_chat_telegram_get_updates(int $offset): array
{
    return support_chat_telegram_request('getUpdates', [
        'offset' => (string)$offset,
        'timeout' => '25',
        'allowed_updates' => json_encode(['message'], JSON_UNESCAPED_SLASHES),
    ]);
}
