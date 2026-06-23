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

    // Verify access - either visitor of the conversation or admin
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

    // Security: ensure file is within attachments directory
    $realPath = realpath($filePath);
    $attachmentsDir = realpath(support_chat_base_path('storage' . DIRECTORY_SEPARATOR . 'attachments'));
    
    if ($realPath === false || $attachmentsDir === false || strpos($realPath, $attachmentsDir) !== 0) {
        http_response_code(403);
        die('Forbidden');
    }

    header('Content-Type: ' . $attachment['mime_type']);
    header('Content-Length: ' . $attachment['file_size']);
    header('Content-Disposition: attachment; filename="' . addslashes($attachment['original_filename']) . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($filePath);
} catch (Throwable $e) {
    support_chat_log_error('download.php error: ' . $e->getMessage());
    http_response_code(500);
    die('Internal server error');
}
