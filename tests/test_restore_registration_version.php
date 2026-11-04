<?php

define('BX_DOL', true);
define('BX_DIRECTORY_PATH_ROOT', __DIR__);
define('BX_DOL_URL_ROOT', 'https://current.example.invalid/');

class RestoreRegistrationDbStub
{
    public $queries = array();

    public function getRow($sSql)
    {
        return array('name' => 'my_una_world');
    }

    public function query($sSql, $aParams = array())
    {
        $this->queries[] = array('sql' => $sSql, 'params' => $aParams);
        return true;
    }
}

class BxDolDb
{
    public static $instance;

    public static function getInstance()
    {
        return self::$instance;
    }
}

class RestoreRegistrationBackupStub
{
    public function getBackupRoot()
    {
        return __DIR__;
    }
}

require_once __DIR__ . '/../classes/MyUnaWorldRestore.php';

class RestoreRegistrationTestSubject extends MyUnaWorldRestore
{
    public function preserveCurrentToolRegistration()
    {
        $this->_preserveCurrentToolRegistration();
    }

    public function inspectCrossSiteCompatibility()
    {
        return $this->_compatibility(
            array('product' => 'My UNA World', 'site_url' => 'https://backup.example.invalid/'),
            array('mode' => 'full'),
            array(array('name' => 'my_una_world')),
            true,
            true
        );
    }
}

$oDb = new RestoreRegistrationDbStub();
BxDolDb::$instance = $oDb;
$oRestore = new RestoreRegistrationTestSubject(new stdClass(), new RestoreRegistrationBackupStub());
$oRestore->preserveCurrentToolRegistration();

if (count($oDb->queries) !== 1) {
    fwrite(STDERR, 'Expected one registration update, got ' . count($oDb->queries) . ".\n");
    exit(1);
}

$aParams = $oDb->queries[0]['params'];
if ($aParams !== array('version' => '1.3.6')) {
    fwrite(STDERR, 'Registration preservation passed unexpected parameters: ' . var_export($aParams, true) . ".\n");
    exit(1);
}

$aCompatibility = $oRestore->inspectCrossSiteCompatibility();
if ($aCompatibility['can_restore'] || !in_array('1.3.6 only performs same-site restores. Backup host is backup.example.invalid and current host is current.example.invalid.', $aCompatibility['blocking_issues'], true)) {
    fwrite(STDERR, "Cross-site restore must remain blocked with the current release identified.\n");
    exit(1);
}

fwrite(STDOUT, "Restore registration version assertions passed: 2\n");
