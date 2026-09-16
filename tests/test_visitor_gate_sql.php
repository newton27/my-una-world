<?php

$sSql = file_get_contents(__DIR__ . '/../install/sql/enable.sql');
if ($sSql === false)
    throw new RuntimeException('Unable to read install/sql/enable.sql');

if (!preg_match('/INSERT INTO `sys_alerts`.*?VALUES(.*?);/s', $sSql, $aMatch))
    throw new RuntimeException('Alert registration statement is missing');

$sAlertInsert = $aMatch[0];
$aRequiredTuples = array(
    "('system', 'before_install', @iHandler)",
    "('system', 'before_uninstall', @iHandler)",
    "('system', 'design_included', @iHandler)",
);

foreach ($aRequiredTuples as $sTuple) {
    if (substr_count($sAlertInsert, $sTuple) !== 1)
        throw new RuntimeException('Expected exactly one alert tuple: ' . $sTuple);
}

foreach (array('before_install', 'before_uninstall', 'design_included') as $sAction) {
    if (substr_count($sAlertInsert, "'" . $sAction . "'") !== 1)
        throw new RuntimeException('Expected exactly one alert action: ' . $sAction);
}

fwrite(STDOUT, "Visitor gate SQL assertions passed: 6\n");
