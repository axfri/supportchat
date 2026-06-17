<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function support_chat_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function support_chat_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function support_chat_require_admin(): void
{
    $expected = support_chat_env('SUPPORT_ADMIN_TOKEN');
    if ($expected === '') {
        support_chat_json(['ok' => false, 'error' => 'Admin token is not configured'], 500);
    }

    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) {
        $token = trim($match[1]);
    } elseif (isset($_GET['admin_token'])) {
        $token = (string)$_GET['admin_token'];
    }

    if (!hash_equals($expected, $token)) {
        support_chat_json(['ok' => false, 'error' => 'Unauthorized'], 401);
    }
}

function support_chat_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('support_chat_sid');
        session_start();
    }
}
