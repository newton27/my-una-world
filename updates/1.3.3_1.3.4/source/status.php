<?php
require_once('../../../inc/header.inc.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'Administrator access is required.'));
    exit;
}

$oModule = BxDolModule::getInstance('my_una_world');
if (!$oModule) {
    http_response_code(503);
    echo json_encode(array('ok' => false, 'error' => 'My UNA World is not enabled.'));
    exit;
}

$iId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$aJob = $iId > 0 ? $oModule->getBackupJob($iId) : $oModule->getActiveBackupJob();

if (!$aJob) {
    echo json_encode(array(
        'ok' => true,
        'server_time' => time(),
        'job' => null,
        'worker' => $oModule->getBackgroundWorkerStatus(),
    ));
    exit;
}

echo json_encode(array(
    'ok' => true,
    'server_time' => time(),
    'job' => array(
        'id' => (int)$aJob['id'],
        'created_at' => (int)$aJob['created_at'],
        'started_at' => (int)$aJob['started_at'],
        'finished_at' => (int)$aJob['finished_at'],
        'mode' => (string)$aJob['mode'],
        'status' => (string)$aJob['status'],
        'progress' => (int)$aJob['progress'],
        'stage' => (string)$aJob['stage'],
        'message' => (string)$aJob['message'],
        'archive_name' => (string)$aJob['archive_name'],
        'backup_id' => (int)$aJob['backup_id'],
    ),
    'worker' => $oModule->getBackgroundWorkerStatus(),
), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
