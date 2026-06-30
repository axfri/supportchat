<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';

support_chat_admin_logout();

support_chat_json(['ok' => true]);
