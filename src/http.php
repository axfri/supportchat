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

function support_chat_log_error(string $message): void
{
    $file = support_chat_base_path('storage' . DIRECTORY_SEPARATOR . 'error.log');
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $entry = '[' . date('c') . '] ' . $message . "\n";
    @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
}

function support_chat_error(string $message, int $status = 500): void
{
    support_chat_log_error($message);
    support_chat_json(['ok' => false, 'error' => $message], $status);
}

function support_chat_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if ($raw !== '') {
            support_chat_log_error('Malformed JSON input, length: ' . strlen($raw));
        }
        return [];
    }
    return is_array($data) ? $data : [];
}

function support_chat_rate_limit(string $key, int $limit = 5, int $windowSeconds = 15): void
{
    support_chat_session_start();

    $now = time();
    $bucketKey = 'rate_limit_' . preg_replace('/[^a-z0-9_:-]/i', '_', $key);
    $bucket = $_SESSION[$bucketKey] ?? ['start' => $now, 'count' => 0];

    if (!is_array($bucket) || ($now - (int)($bucket['start'] ?? 0)) >= $windowSeconds) {
        $bucket = ['start' => $now, 'count' => 0];
    }

    $bucket['count'] = (int)$bucket['count'] + 1;
    $_SESSION[$bucketKey] = $bucket;

    if ($bucket['count'] > $limit) {
        support_chat_json(['ok' => false, 'error' => 'Too many messages, please wait'], 429);
    }
}

function support_chat_require_admin(): void
{
    support_chat_session_start();
    if (!empty($_SESSION['support_admin_authenticated'])) {
        return;
    }

    $expected = support_chat_env('SUPPORT_ADMIN_TOKEN');
    if ($expected === '') {
        support_chat_error('Admin token is not configured', 500);
    }

    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) {
        $token = trim($match[1]);
    } elseif (isset($_GET['admin_token'])) {
        $token = (string)$_GET['admin_token'];
    }

    if (!is_string($token) || !hash_equals($expected, $token)) {
        support_chat_json(['ok' => false, 'error' => 'Unauthorized'], 401);
    }
}

function support_chat_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('support_chat_sid');
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 30,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function support_chat_admin_credentials_valid(string $login, string $password): bool
{
    $login = trim($login);
    $expectedLogin = support_chat_env('SUPPORT_ADMIN_LOGIN');
    if ($expectedLogin === '') {
        $expectedLogin = 'admin';
    }

    if (!hash_equals($expectedLogin, $login)) {
        return false;
    }

    $hash = support_chat_env('SUPPORT_ADMIN_PASSWORD_HASH');
    if ($hash !== '') {
        return password_verify($password, $hash);
    }

    $plainPassword = support_chat_env('SUPPORT_ADMIN_PASSWORD');
    if ($plainPassword === '') {
        $plainPassword = support_chat_env('SUPPORT_ADMIN_TOKEN');
    }

    return $plainPassword !== '' && hash_equals($plainPassword, $password);
}

function support_chat_admin_login(string $login): void
{
    support_chat_session_start();
    session_regenerate_id(true);
    $_SESSION['support_admin_authenticated'] = true;
    $_SESSION['support_admin_login'] = $login;
}

function support_chat_admin_logout(): void
{
    support_chat_session_start();
    unset($_SESSION['support_admin_authenticated'], $_SESSION['support_admin_login']);
}

function support_chat_is_admin_authenticated(): bool
{
    support_chat_session_start();
    return !empty($_SESSION['support_admin_authenticated']);
}
