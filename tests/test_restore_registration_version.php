<?php

define('BX_DOL', true);
define('BX_DIRECTORY_PATH_ROOT', __DIR__);

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
if ($aParams !== array('version' => '1.3.3')) {
    fwrite(STDERR, 'Registration preservation passed unexpected parameters: ' . var_export($aParams, true) . ".\n");
    exit(1);
}

fwrite(STDOUT, "Restore registration version assertion passed: 1\n");
