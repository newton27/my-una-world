<?php

define('BX_DOL', true);
define('BX_DOL_MODULE_TYPE_MODULE', 'module');

class BxDolStudioUpdater
{
    public $config;

    public function __construct($aConfig)
    {
        $this->config = $aConfig;
    }
}

$iAssertions = 0;
$aFailures = array();

function muw_updater_assert_same($mExpected, $mActual, $sLabel)
{
    global $iAssertions, $aFailures;
    $iAssertions++;
    if ($mExpected === $mActual)
        return;

    $aFailures[] = $sLabel . ': expected ' . var_export($mExpected, true) .
        ', got ' . var_export($mActual, true);
}

function muw_updater_read_config($sPath)
{
    global $aFailures;

    if (!is_file($sPath)) {
        $aFailures[] = 'Missing updater config: ' . $sPath;
        return array();
    }

    $aConfig = null;
    require $sPath;
    if (!is_array($aConfig)) {
        $aFailures[] = 'Updater config did not define $aConfig.';
        return array();
    }

    return $aConfig;
}

$sRoot = dirname(__DIR__);
$sUpdater = $sRoot . '/updates/1.3.2_1.3.3';
$aConfig = muw_updater_read_config($sUpdater . '/install/config.php');

muw_updater_assert_same('1.3.2', isset($aConfig['version_from']) ? $aConfig['version_from'] : null, 'updater source version');
muw_updater_assert_same('1.3.3', isset($aConfig['version_to']) ? $aConfig['version_to'] : null, 'updater target version');
muw_updater_assert_same('MyUNAWorld', isset($aConfig['vendor']) ? $aConfig['vendor'] : null, 'updater vendor remains unchanged');
muw_updater_assert_same('newton/my_una_world/', isset($aConfig['module_dir']) ? $aConfig['module_dir'] : null, 'updater module directory');
muw_updater_assert_same('my_una_world', isset($aConfig['module_uri']) ? $aConfig['module_uri'] : null, 'updater module URI');
muw_updater_assert_same(
    array('execute_sql', 'update_files', 'clear_db_cache'),
    isset($aConfig['install']) && is_array($aConfig['install']) ? array_keys($aConfig['install']) : array(),
    'updater action order'
);
muw_updater_assert_same(
    array(1, 1, 1),
    isset($aConfig['install']) && is_array($aConfig['install']) ? array_values($aConfig['install']) : array(),
    'updater actions are enabled'
);

$sPackageConfig = $sRoot . '/install/config.php';
$aConfig = null;
require $sPackageConfig;
$aPackageConfig = $aConfig;
muw_updater_assert_same('1.3.3', isset($aPackageConfig['version']) ? $aPackageConfig['version'] : null, 'source package version');
muw_updater_assert_same('MyUNAWorld', isset($aPackageConfig['vendor']) ? $aPackageConfig['vendor'] : null, 'source package vendor remains unchanged');

$sInstaller = $sUpdater . '/install/installer.php';
if (!is_file($sInstaller)) {
    $aFailures[] = 'Missing updater installer: ' . $sInstaller;
}
else {
    require_once $sInstaller;
    $oUpdater = new MyUnaWorldUpdater($aConfig);
    muw_updater_assert_same($aConfig, $oUpdater->config, 'updater installer delegates its config');
}

$sSqlPath = $sUpdater . '/install/sql/install.sql';
$sSql = is_file($sSqlPath) ? file_get_contents($sSqlPath) : false;
if ($sSql === false) {
    $aFailures[] = 'Missing updater SQL: ' . $sSqlPath;
}
else {
    muw_updater_assert_same(1, substr_count($sSql, "'my_una_world_lifecycle'"), 'updater locates the existing handler once');
    muw_updater_assert_same(2, substr_count($sSql, "'design_included'"), 'updater inserts and de-duplicates design_included');
    muw_updater_assert_same(true, stripos($sSql, 'NOT EXISTS') !== false, 'updater SQL is idempotent');
    muw_updater_assert_same(false, preg_match('/\b(?:DELETE|REPLACE)\b/i', $sSql) === 1, 'updater does not delete or replace rows');
    muw_updater_assert_same(false, stripos($sSql, 'before_install') !== false, 'updater leaves before_install untouched');
    muw_updater_assert_same(false, stripos($sSql, 'before_uninstall') !== false, 'updater leaves before_uninstall untouched');
    muw_updater_assert_same(false, stripos($sSql, 'INSERT INTO `sys_alerts_handlers`') !== false, 'updater reuses the existing handler');
}

$aPayloadFiles = array(
    'classes/MyUnaWorldModule.php',
    'classes/MyUnaWorldAlertsResponse.php',
    'classes/MyUnaWorldRestore.php',
    'classes/MyUnaWorldStudioPage.php',
    'install/config.php',
    'install/sql/enable.sql',
);

foreach ($aPayloadFiles as $sRelative) {
    $sSource = $sRoot . '/' . $sRelative;
    $sPayload = $sUpdater . '/source/' . $sRelative;
    muw_updater_assert_same(
        is_file($sSource) ? hash_file('sha256', $sSource) : null,
        is_file($sPayload) ? hash_file('sha256', $sPayload) : null,
        'updater payload matches source for ' . $sRelative
    );
}

if ($aFailures) {
    fwrite(STDERR, "Updater assertions failed:\n- " . implode("\n- ", $aFailures) . "\n");
    exit(1);
}

fwrite(STDOUT, 'Updater assertions passed: ' . $iAssertions . "\n");
