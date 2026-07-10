<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';

$pdo = support_chat_db();
$attachmentId = (int)($_GET['id'] ?? 0);

if ($attachmentId <= 0) {
    http_response_code(404);
    die('Not found');
}

try {
    support_chat_session_start();

    $stmt = $pdo->prepare('SELECT a.*, m.conversation_id FROM attachments a JOIN messages m ON a.message_id = m.id WHERE a.id = ?');
    $stmt->execute([$attachmentId]);
    $attachment = $stmt->fetch();

    if (!$attachment) {
        http_response_code(404);
        die('Not found');
    }

    $isAdmin = isset($_GET['admin']) && $_GET['admin'] === '1';
    if ($isAdmin) {
        support_chat_require_admin();
    } else {
        $conversationId = (int)$attachment['conversation_id'];
        $conversation = support_chat_get_conversation($pdo, $conversationId);
        if ($conversation === null || $conversation['channel'] !== 'web' || $conversation['external_id'] !== session_id()) {
            http_response_code(403);
            die('Forbidden');
        }
    }

    $filePath = support_chat_get_attachment_path($attachment['filename']);
    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        die('File not found');
    }

    $realPath = realpath($filePath);
    $attachmentsDir = realpath(support_chat_base_path('storage' . DIRECTORY_SEPARATOR . 'attachments'));
    if ($realPath === false || $attachmentsDir === false || strpos($realPath, $attachmentsDir) !== 0) {
        http_response_code(403);
        die('Forbidden');
    }

    $mimeType = (string)$attachment['mime_type'];
    $fileSize = filesize($filePath) ?: (int)$attachment['file_size'];
    $isMedia = strpos($mimeType, 'video/') === 0 || strpos($mimeType, 'image/') === 0 || strpos($mimeType, 'audio/') === 0;
    $inline = (isset($_GET['inline']) && $_GET['inline'] === '1') || $isMedia;
    $filename = str_replace(['"', "\r", "\n"], '', (string)$attachment['original_filename']);

    header('Content-Type: ' . $mimeType);
    header('Accept-Ranges: bytes');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . addslashes($filename) . '"');
    header('Cache-Control: private, max-age=86400');

    $start = 0;
    $end = $fileSize - 1;
    $status = 200;

    if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string)$_SERVER['HTTP_RANGE'], $match)) {
        if ($match[1] !== '') {
            $start = (int)$match[1];
        }
        if ($match[2] !== '') {
            $end = (int)$match[2];
        }
        if ($start > $end || $start >= $fileSize) {
            header('Content-Range: bytes */' . $fileSize);
            http_response_code(416);
            exit;
        }
        $end = min($end, $fileSize - 1);
        $status = 206;
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    }

    $length = $end - $start + 1;
    header('Content-Length: ' . $length);
    if ($status === 200) {
        http_response_code(200);
    }

    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        http_response_code(500);
        die('Could not open file');
    }
    fseek($handle, $start);
    $remaining = $length;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(8192, $remaining));
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
        flush();
    }
    fclose($handle);
} catch (Throwable $e) {
    support_chat_log_error('download.php error: ' . $e->getMessage());
    http_response_code(500);
    die('Internal server error');
}
