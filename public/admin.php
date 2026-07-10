<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/http.php';

if (!support_chat_is_admin_authenticated() || (($_SESSION['support_admin_role'] ?? '') !== 'admin')) {
    header('Location: admin-login.php');
    exit;
}

header('Location: admin-users.php');
exit;
