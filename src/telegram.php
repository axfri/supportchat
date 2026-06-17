<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function support_chat_telegram_request(string $method, array $payload): array
{
    $token = support_chat_env('TELEGRAM_BOT_TOKEN');
    if ($token === '') {
        return ['ok' => false, 'description' => 'Telegram bot token is not configured'];
    }

    $url = 'https://api.telegram.org/bot' . rawurlencode($token) . '/' . $method;
    $body = http_build_query($payload);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);

    $response = file_get_contents($url, false, $context);
    $decoded = is_string($response) ? json_decode($response, true) : null;
    return is_array($decoded) ? $decoded : ['ok' => false, 'description' => 'Bad Telegram response'];
}

function support_chat_telegram_send(string $chatId, string $text): array
{
    return support_chat_telegram_request('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => 'true',
    ]);
}

function support_chat_telegram_get_updates(int $offset): array
{
    return support_chat_telegram_request('getUpdates', [
        'offset' => (string)$offset,
        'timeout' => '25',
        'allowed_updates' => json_encode(['message'], JSON_UNESCAPED_SLASHES),
    ]);
}
