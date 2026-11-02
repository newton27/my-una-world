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
    array('execute_sql', 'update_files', 'update_languages', 'clear_db_cache'),
    isset($aConfig['install']) && is_array($aConfig['install']) ? array_keys($aConfig['install']) : array(),
    'updater action order'
);
muw_updater_assert_same(
    array(1, 1, 1, 1),
    isset($aConfig['install']) && is_array($aConfig['install']) ? array_values($aConfig['install']) : array(),
    'updater actions are enabled'
);

$sPackageConfig = $sRoot . '/install/config.php';
$aConfig = null;
require $sPackageConfig;
$aPackageConfig = $aConfig;
muw_updater_assert_same('1.3.4', isset($aPackageConfig['version']) ? $aPackageConfig['version'] : null, 'current candidate package version');
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
    // UNA 15 BxDolDb::executeSQL trims each line and concatenates without
    // inserting spaces (inc/classes/BxDolDb.php). Capture the actual queries
    // presented to MySQL so line breaks cannot join SQL tokens accidentally.
    $aLoadedQueries = array();
    $sLoadedQuery = '';
    foreach (explode("\n", $sSql) as $sLine) {
        $sLine = trim($sLine);
        if ($sLine === '' || $sLine[0] === '#' || substr($sLine, 0, 2) === '--')
            continue;
        $sLoadedQuery .= $sLine;
        if (substr($sLine, -1) !== ';')
            continue;
        $aLoadedQueries[] = preg_replace('/\s+/', ' ', trim($sLoadedQuery));
        $sLoadedQuery = '';
    }
    muw_updater_assert_same(2, count($aLoadedQueries), 'native SQL loader emits two complete statements');
    muw_updater_assert_same(
        "SET @iHandler = (SELECT `id` FROM `sys_alerts_handlers` WHERE `name` = 'my_una_world_lifecycle' LIMIT 1);",
        isset($aLoadedQueries[0]) ? $aLoadedQueries[0] : null,
        'native SQL loader preserves handler lookup token boundaries'
    );
    muw_updater_assert_same(
        "INSERT INTO `sys_alerts` (`unit`, `action`, `handler_id`) SELECT 'system', 'design_included', @iHandler WHERE @iHandler IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `sys_alerts` WHERE `unit` = 'system' AND `action` = 'design_included' AND `handler_id` = @iHandler);",
        isset($aLoadedQueries[1]) ? $aLoadedQueries[1] : null,
        'native SQL loader preserves idempotent alert insert token boundaries'
    );
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
    'install/langs/en.xml',
    'install/sql/enable.sql',
);

// Immutable 1.3.3 source at release commit 1789fe2a4f9ce1dc37c00752a93cc811cc5cf61c.
// Historical updater bytes must not follow later candidate changes.
$aReleasedBlobs = array(
    'classes/MyUnaWorldModule.php'=>'5456e1e79af6dbacd521775c22d487a6990e6d32',
    'classes/MyUnaWorldAlertsResponse.php'=>'746e22789829b57154b5c81f8a360468e14c0dc2',
    'classes/MyUnaWorldRestore.php'=>'c7070776d58120c1103d2a2d21c91b08c20f6f38',
    'classes/MyUnaWorldStudioPage.php'=>'8cdb1d6e47724055d28d14d321e4304974b7b7ef',
    'install/config.php'=>'262bc28bbd0a6e022554f0c3cc5d2c1a83e6cfe2',
    'install/langs/en.xml'=>'f378f10f6a8888088af172542f02844b96644b1a',
    'install/sql/enable.sql'=>'bc37313b5e41ea9df4f8f375d7aa4244e01cd081',
);
foreach ($aPayloadFiles as $sRelative) {
    $sPayload = $sUpdater . '/source/' . $sRelative;
    $sBytes = is_file($sPayload) ? file_get_contents($sPayload) : false;
    muw_updater_assert_same(
        $aReleasedBlobs[$sRelative],
        $sBytes !== false ? sha1('blob ' . strlen($sBytes) . "\0" . $sBytes) : null,
        'historical updater payload matches released 1.3.3 for ' . $sRelative
    );
}

$sUpdaterLanguage = $sUpdater . '/install/langs/en.xml';
if (!is_file($sUpdaterLanguage)) {
    $aFailures[] = 'Missing native updater language payload: ' . $sUpdaterLanguage;
}
else {
    $oUpdaterLanguage = simplexml_load_file($sUpdaterLanguage, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($oUpdaterLanguage === false) {
        $aFailures[] = 'Native updater language payload is not valid XML.';
    }
    else {
        $aAdded = array();
        foreach ($oUpdaterLanguage->string_add as $oString)
            $aAdded[(string)$oString['name']] = (string)$oString;

        muw_updater_assert_same(
            'Enable Visitor Gate',
            isset($aAdded['_my_una_world_visitor_gate_enabled']) ? $aAdded['_my_una_world_visitor_gate_enabled'] : null,
            'updater adds Visitor Gate label through native string_add payload'
        );
        muw_updater_assert_same(
            'Restricts logged-out visitors to Home, Login, Create Account, Forgot Password, Terms, and Privacy. Logged-in members and administrators remain unrestricted.',
            isset($aAdded['_my_una_world_visitor_gate_enabled_desc']) ? $aAdded['_my_una_world_visitor_gate_enabled_desc'] : null,
            'updater adds Visitor Gate description through native string_add payload'
        );
        muw_updater_assert_same(2, count($aAdded), 'updater language payload adds only the two Visitor Gate strings');
        muw_updater_assert_same(0, count($oUpdaterLanguage->string), 'updater language payload does not use full-resource string elements');
        muw_updater_assert_same(0, count($oUpdaterLanguage->string_upd), 'updater language payload does not update unrelated strings');
        muw_updater_assert_same(0, count($oUpdaterLanguage->string_del), 'updater language payload does not delete strings');
    }
}

if ($aFailures) {
    fwrite(STDERR, "Updater assertions failed:\n- " . implode("\n- ", $aFailures) . "\n");
    exit(1);
}

fwrite(STDOUT, 'Updater assertions passed: ' . $iAssertions . "\n");
