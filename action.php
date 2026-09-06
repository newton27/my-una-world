<?php
require_once('../../../inc/header.inc.php');

if (!isAdmin()) {
    http_response_code(403);
    echo 'Administrator access is required.';
    exit;
}

header('Location: ' . BX_DOL_URL_STUDIO . 'module.php?name=my_una_world&page=overview');
exit;
