<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/database.php';

support_chat_json(['ok' => false, 'error' => 'Удаление сообщений доступно только оператору'], 403);
