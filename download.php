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

$iId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sType = isset($_GET['type']) && $_GET['type'] === 'verify' ? 'verify' : 'archive';
$aBackup = $iId > 0 ? $oModule->getBackup($iId) : false;

if (!$aBackup || $aBackup['status'] !== 'complete') {
    http_response_code(404);
    echo 'Backup not found.';
    exit;
}

$sArchivePath = (string)$aBackup['archive_path'];
$sArchiveName = basename((string)$aBackup['archive_name']);

if ($sArchivePath === '' || $sArchiveName === '') {
    http_response_code(404);
    echo 'Backup archive is missing from disk.';
    exit;
}

if ($sType === 'verify') {
    $sPath = $sArchivePath . '.verify.json';
    $sName = $sArchiveName . '.verify.json';
    $sContentType = 'application/json; charset=utf-8';
} else {
    $sPath = $sArchivePath;
    $sName = $sArchiveName;
    $sContentType = 'application/zip';
}

if (!is_file($sPath)) {
    http_response_code(404);
    echo $sType === 'verify'
        ? 'Verification report is not available for this backup.'
        : 'Backup archive is missing from disk.';
    exit;
}

header('Content-Type: ' . $sContentType);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $sName) . '"');
header('Content-Length: ' . filesize($sPath));
header('X-Content-Type-Options: nosniff');

$h = fopen($sPath, 'rb');
while (!feof($h)) {
    echo fread($h, 1024 * 1024);
    flush();
}
fclose($h);
exit;
