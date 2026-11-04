<?php
/** Subprocess fixture for shutdown and uncatchable process termination. */
define('BX_DOL', true);
require dirname(__DIR__, 2) . '/classes/MyUnaMaintenanceLease.php';
class WorkerLeaseDb {
    public function getRow($sql, $params) { return array('value' => $GLOBALS['params'][$params[0]]); }
    public function query($sql, $bindings) {
        list($value, $name, $expected) = $bindings;
        if ($GLOBALS['params'][$name] !== $expected) return 0;
        $GLOBALS['params'][$name] = $value;
        return 1;
    }
    public function cacheParams($force = false) { return true; }
}
class BxDolDb { public static function getInstance() { return new WorkerLeaseDb(); } }
function setParam($name, $value) { $GLOBALS['params'][$name] = (string)$value; return true; }
$GLOBALS['params'] = array('sys_maintenance_mode' => '', 'sys_maintenance_mode_html' => 'original page', 'sys_template_cache_compress_enable' => 'on');
$lease = new MyUnaMaintenanceLease($argv[1]);
$lease->begin('worker page');
$state = $argv[2];
file_put_contents($state, json_encode($GLOBALS['params']));
register_shutdown_function(static function () use ($state) {
    file_put_contents($state, json_encode($GLOBALS['params']));
});
if (($argv[3] ?? '') === 'crash') {
    echo "READY\n";
    fflush(STDOUT);
    fgets(STDIN);
}
exit(0);
