<?php
require_once('../../../inc/header.inc.php');

if (!isAdmin()) {
    http_response_code(403);
    echo 'Administrator access is required.';
    exit;
}

$oModule = BxDolModule::getInstance('my_una_world');
if (!$oModule) {
    http_response_code(503);
    echo 'My UNA World is not enabled.';
    exit;
}

$sName = isset($_GET['name']) ? (string)$_GET['name'] : '';

try {
    $aReport = $oModule->getRestoreReport($sName);
}
catch (Throwable $e) {
    http_response_code(404);
    echo 'Restore report not found.';
    exit;
}

$sDownloadName = basename((string)$sName);
$sJson = json_encode(
    $aReport,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);

if (!is_string($sJson)) {
    http_response_code(500);
    echo 'Unable to encode restore report.';
    exit;
}

$sJson .= PHP_EOL;

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $sDownloadName) . '"');
header('Content-Length: ' . strlen($sJson));
header('X-Content-Type-Options: nosniff');

echo $sJson;
exit;
