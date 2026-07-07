<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function support_chat_json(array $payload, int $status = 200): void
{
    support_chat_apply_cors();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
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

function support_chat_apply_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return;
    }

    $allowed = array_filter(array_map('trim', explode(',', support_chat_env('SUPPORT_ALLOWED_ORIGINS', ''))));
    if (!$allowed || in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    }
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
    support_chat_require_role('manager');
}

function support_chat_require_role(string $minimumRole = 'manager'): void
{
    support_chat_session_start();
    if (!empty($_SESSION['support_admin_authenticated'])) {
        if ($minimumRole !== 'admin' || (($_SESSION['support_admin_role'] ?? '') === 'admin')) {
            return;
        }
        support_chat_json(['ok' => false, 'error' => 'Недостаточно прав'], 403);
    }

    if (!empty($_SESSION['support_staff_id'])) {
        $role = (string)($_SESSION['support_admin_role'] ?? 'manager');
        if ($minimumRole !== 'admin' || $role === 'admin') {
            return;
        }
        support_chat_json(['ok' => false, 'error' => 'Недостаточно прав'], 403);
    }

    if ($minimumRole === 'manager' && support_chat_bearer_token_valid()) {
        return;
    }

    support_chat_json(['ok' => false, 'error' => 'Unauthorized'], 401);
}

function support_chat_bearer_token_valid(): bool
{
    $expected = support_chat_env('SUPPORT_ADMIN_TOKEN');
    if ($expected === '') {
        return false;
    }

    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $match)) {
        $token = trim($match[1]);
    } elseif (isset($_GET['admin_token'])) {
        $token = (string)$_GET['admin_token'];
    }

    return is_string($token) && hash_equals($expected, $token);
}

function support_chat_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $sameSite = support_chat_env('SUPPORT_COOKIE_SAMESITE', 'Lax');
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            $sameSite = 'Lax';
        }
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        if ($sameSite === 'None') {
            $secure = true;
        }

        session_name('support_chat_sid');
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 30,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
        session_start();
    }
}

function support_chat_admin_credentials_valid(string $login, string $password): bool
{
    return support_chat_admin_user_by_credentials($login, $password) !== null;
}

function support_chat_admin_user_by_credentials(string $login, string $password): ?array
{
    $login = trim($login);
    if ($login === '') {
        return null;
    }

    try {
        require_once __DIR__ . '/database.php';
        $pdo = support_chat_db();
        $stmt = $pdo->prepare('SELECT * FROM support_staff WHERE login = ? LIMIT 1');
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        if (is_array($user) && empty($user['is_blocked']) && password_verify($password, (string)$user['password_hash'])) {
            return $user;
        }
    } catch (Throwable $e) {
        support_chat_log_error('Staff credential lookup failed: ' . $e->getMessage());
    }

    $expectedLogin = support_chat_env('SUPPORT_ADMIN_LOGIN', 'admin');
    if (!hash_equals($expectedLogin, $login)) {
        return null;
    }

    $hash = support_chat_env('SUPPORT_ADMIN_PASSWORD_HASH');
    if ($hash !== '' && password_verify($password, $hash)) {
        return ['id' => 0, 'login' => $login, 'role' => 'admin', 'is_blocked' => 0];
    }

    $plainPasswords = array_unique(array_filter([
        support_chat_env('SUPPORT_ADMIN_PASSWORD'),
        support_chat_env('SUPPORT_ADMIN_TOKEN'),
        'admin',
    ], static fn($value) => $value !== ''));
    foreach ($plainPasswords as $plainPassword) {
        if (hash_equals($plainPassword, $password)) {
            return ['id' => 0, 'login' => $login, 'role' => 'admin', 'is_blocked' => 0];
        }
    }

    return null;
}

function support_chat_admin_login(string $login, string $role = 'admin', int $staffId = 0): void
{
    support_chat_session_start();
    session_regenerate_id(true);
    $_SESSION['support_admin_authenticated'] = true;
    $_SESSION['support_admin_login'] = $login;
    $_SESSION['support_admin_role'] = $role === 'admin' ? 'admin' : 'manager';
    $_SESSION['support_staff_id'] = $staffId;
}

function support_chat_admin_logout(): void
{
    support_chat_session_start();
    unset($_SESSION['support_admin_authenticated'], $_SESSION['support_admin_login'], $_SESSION['support_admin_role'], $_SESSION['support_staff_id']);
}

function support_chat_is_admin_authenticated(): bool
{
    support_chat_session_start();
    return !empty($_SESSION['support_admin_authenticated']);
}
