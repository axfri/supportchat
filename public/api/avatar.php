<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';

try {
    support_chat_session_start();
    support_chat_require_admin();

    $conversationId = (int)($_GET['id'] ?? 0);
    if ($conversationId <= 0) {
        http_response_code(404);
        die('Not found');
    }

    $pdo = support_chat_db();
    $conversation = support_chat_get_conversation($pdo, $conversationId);
    if ($conversation === null) {
        http_response_code(404);
        die('Not found');
    }

    $filename = basename((string)($conversation['visitor_avatar'] ?? ''));
    if ($filename === '') {
        http_response_code(404);
        die('Not found');
    }

    $filePath = support_chat_avatar_storage_dir() . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($filePath)) {
        http_response_code(404);
        die('Not found');
    }

    $realPath = realpath($filePath);
    $avatarDir = realpath(support_chat_avatar_storage_dir());
    if ($realPath === false || $avatarDir === false || strpos($realPath, $avatarDir) !== 0) {
        http_response_code(403);
        die('Forbidden');
    }

    $mimeType = function_exists('mime_content_type') ? (string)mime_content_type($filePath) : 'image/jpeg';
    if (strpos($mimeType, 'image/') !== 0) {
        $mimeType = 'image/jpeg';
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string)(filesize($filePath) ?: 0));
    header('Content-Disposition: inline; filename="avatar.jpg"');
    header('Cache-Control: private, max-age=86400');
    readfile($filePath);
} catch (Throwable $e) {
    support_chat_log_error('avatar.php error: ' . $e->getMessage());
    http_response_code(500);
    die('Internal server error');
}
