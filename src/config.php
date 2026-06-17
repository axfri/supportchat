<?php
declare(strict_types=1);

function support_chat_base_path(string $path = ''): string
{
    $base = dirname(__DIR__);
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function support_chat_load_env(): array
{
    static $env = null;
    if ($env !== null) {
        return $env;
    }

    $env = [];
    $file = support_chat_base_path('.env');
    if (is_file($file)) {
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }

    foreach ($_ENV as $key => $value) {
        if (is_string($key) && is_scalar($value)) {
            $env[$key] = (string)$value;
        }
    }

    return $env;
}

function support_chat_env(string $key, string $default = ''): string
{
    $env = support_chat_load_env();
    return isset($env[$key]) && $env[$key] !== '' ? (string)$env[$key] : $default;
}

function support_chat_sqlite_path(): string
{
    $path = support_chat_env('SQLITE_PATH', 'storage/support.sqlite');
    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || strpos($path, '/') === 0) {
        return $path;
    }
    return support_chat_base_path($path);
}
