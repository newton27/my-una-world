<?php
/** Executes the real conditional writes; SQLite only translates MySQL BINARY syntax. */
define('BX_DOL', true);
require dirname(__DIR__) . '/classes/MyUnaMaintenanceLease.php';
class LeaseSqlDb {
    public $pdo;
    public $race = null;
    public $cached = array();
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE sys_options (name TEXT PRIMARY KEY, value TEXT COLLATE BINARY)');
        foreach (array('sys_maintenance_mode' => '', 'sys_maintenance_mode_html' => 'original page', 'sys_template_cache_compress_enable' => 'on') as $name => $value)
            $this->pdo->prepare('INSERT INTO sys_options VALUES (?, ?)')->execute(array($name, $value));
        $this->cacheParams();
    }
    public function getRow($sql, $bindings) {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }
    public function query($sql, $bindings) {
        if ($this->race !== null) {
            ($this->race)();
            $this->race = null;
        }
        $statement = $this->pdo->prepare(str_replace('BINARY ', '', $sql));
        $statement->execute($bindings);
        return $statement->rowCount();
    }
    public function cacheParams($force = false) {
        $this->cached = $this->pdo->query('SELECT name,value FROM sys_options')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
class BxDolDb { public static function getInstance() { return $GLOBALS['db']; } }
function getParam($name) { return $GLOBALS['db']->cached[$name] ?? false; }
$root = sys_get_temp_dir() . '/lease-sql-' . bin2hex(random_bytes(6));
mkdir($root . '/site', 0700, true);
$GLOBALS['db'] = new LeaseSqlDb();
$failed = 0;
function check($label, $ok) { global $failed; echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL; if (!$ok) ++$failed; }
try {
    $lease = new MyUnaMaintenanceLease($root . '/site');
    $lease->begin('scan page');
    check('native cache refresh exposes active maintenance after conditional SQL', (bool)getParam('sys_maintenance_mode') && getParam('sys_template_cache_compress_enable') === '');
    $GLOBALS['db']->race = static function () {
        $GLOBALS['db']->pdo->prepare('UPDATE sys_options SET value=? WHERE name=?')->execute(array('administrator-enabled', 'sys_maintenance_mode'));
    };
    $result = $lease->finish();
    check('real conditional UPDATE preserves a save racing restoration', $result['ok'] && getParam('sys_maintenance_mode') === 'administrator-enabled' && in_array('sys_maintenance_mode', $result['preserved'], true));

    $GLOBALS['db'] = new LeaseSqlDb();
    $GLOBALS['db']->race = static function () {
        $GLOBALS['db']->pdo->prepare('UPDATE sys_options SET value=? WHERE name=?')->execute(array('administrator-compression', 'sys_template_cache_compress_enable'));
    };
    $lease = new MyUnaMaintenanceLease($root . '/site');
    $caught = false;
    try { $lease->begin('scan page'); } catch (RuntimeException $error) { $caught = true; }
    $GLOBALS['db']->cacheParams();
    check('real conditional UPDATE refuses arming over a concurrent save', $caught && getParam('sys_template_cache_compress_enable') === 'administrator-compression' && getParam('sys_maintenance_mode') === '');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($root);
}
exit($failed ? 1 : 0);
