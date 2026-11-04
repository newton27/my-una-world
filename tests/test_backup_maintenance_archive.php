<?php
// A restored archive must contain the site's original options, not the temporary lockout.
define('BX_DOL', true);
$root = sys_get_temp_dir() . '/muw-maintenance-sql-' . bin2hex(random_bytes(5));
mkdir($root . '/public_html', 0700, true); mkdir($root . '/backups', 0700);
define('BX_DIRECTORY_PATH_ROOT', $root . '/public_html/');
define('BX_DOL_URL_ROOT', 'https://example.invalid/');
class BxDolModule { protected $_oDb; }
class BxDolDb { public static $instance; public static function getInstance() { return self::$instance; } }
class ArchiveOptionsDb {
    public $pdo;
    function __construct() {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->exec('CREATE TABLE sys_options(name TEXT PRIMARY KEY, value TEXT)');
        $q = $this->pdo->prepare('INSERT INTO sys_options VALUES(?,?)');
        foreach (array('sys_maintenance_mode'=>'', 'sys_maintenance_mode_html'=>'Original UNA page', 'sys_template_cache_compress_enable'=>'on', 'site_title'=>'Community title') as $name=>$value) $q->execute(array($name,$value));
    }
    function getAll($sql) {
        if ($sql === 'SHOW FULL TABLES') return array(array('Tables_in_fixture'=>'sys_options','Table_type'=>'BASE TABLE'));
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    function getRow($sql, $params = array()) {
        if ($sql === 'SHOW CREATE TABLE `sys_options`') return array('Table'=>'sys_options', 'Create Table'=>'CREATE TABLE sys_options(name TEXT PRIMARY KEY, value TEXT)');
        $q=$this->pdo->prepare($sql); $q->execute($params); return $q->fetch(PDO::FETCH_ASSOC);
    }
    function query($sql,$params=array()) { $q=$this->pdo->prepare(str_replace('BINARY ', '', $sql));$q->execute($params); return $q->rowCount(); }
    function cacheParams($force=false) { return true; }
}
function getParam($name) { return BxDolDb::$instance->getRow('SELECT value FROM sys_options WHERE name=?',array($name))['value']; }
function setParam($name,$value) { $q=BxDolDb::$instance->pdo->prepare('UPDATE sys_options SET value=? WHERE name=?');return $q->execute(array($value,$name)); }
require __DIR__.'/../classes/MyUnaWorldModule.php';
require __DIR__.'/../classes/MyUnaWorldBackup.php';
class ArchiveOptionsEngine extends MyUnaWorldBackup {
    protected function _resolveBackupRoot() { global $root; return $root.'/backups'; }
    protected function _getInstalledModules() { return array(); }
    protected function _discoverInstalledModulePaths(array $modules) { return array(); }
    protected function _dumpDatabase($path) {
        if (!getParam('sys_maintenance_mode')) throw new RuntimeException('Full export must occur during active maintenance');
        return parent::_dumpDatabase($path);
    }
}
class ArchiveOptionsModule extends MyUnaWorldModule {
    protected function _getBackupEngine() { return new ArchiveOptionsEngine($this); }
    public function getLifecycleSettings() { return array('use_maintenance_page'=>false); }
    public function addBackupHistory(array $backup) { return 1; }
    public function applyBackupRetention($manual=false) { return array('applied'=>false); }
}
try {
    BxDolDb::$instance = new ArchiveOptionsDb();
    $before = BxDolDb::$instance->pdo->query('SELECT * FROM sys_options ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR);
    $backup=(new ArchiveOptionsModule())->createBackup('full');
    $zip=new ZipArchive(); if($zip->open($backup['archive_path'],ZipArchive::CHECKCONS)!==true)throw new RuntimeException('Archive is invalid');
    $sql=$zip->getFromName('database/database.sql'); $zip->close();
    $restored=new PDO('sqlite::memory:'); $restored->exec(preg_replace('/^SET .*;\s*$/m','',$sql));
    $after=$restored->query('SELECT * FROM sys_options ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR);
    if ($after !== $before) { fwrite(STDERR,"FAIL: restoring the real archive replays temporary maintenance options instead of the original public state.\n"); exit(1); }
    if (getParam('sys_maintenance_mode') !== '') throw new RuntimeException('Live public access was not restored');
    echo "Backup maintenance archive assertions passed: 3\n";
} finally {
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f) { if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname()); }
    rmdir($root);
}
