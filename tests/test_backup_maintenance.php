<?php
// Regression: a full backup must arm native public maintenance before the engine runs.
define('BX_DOL', true);
$root = sys_get_temp_dir() . '/muw-maintenance-' . bin2hex(random_bytes(5));
mkdir($root . '/public_html', 0700, true);
define('BX_DIRECTORY_PATH_ROOT', $root . '/public_html/');
class BxDolModule { protected $_oDb; }
class BxDolDb {
    public static function getInstance() { return new self(); }
    public function getRow($sql, $params = array()) {
        global $options;
        if (!str_contains($sql, 'sys_options') || count($params) !== 1)
            throw new RuntimeException('Unexpected option query');
        return isset($options[$params[0]]) ? array('value' => $options[$params[0]]) : false;
    }
    public function query($sql, $params = array()) {
        global $options;
        if (count($params) !== 3 || !str_contains($sql, 'UPDATE `sys_options`')) throw new RuntimeException('Unexpected mutation');
        list($value,$name,$expected) = $params;
        if (($options[$name] ?? null) !== $expected) return 0;
        $options[$name] = $value; return 1;
    }
    public function cacheParams($force = false) { return true; }
}
$options = array();
function getParam($name) { global $options; return $options[$name] ?? null; }
function setParam($name, $value) { global $options; $options[$name] = (string)$value; return true; }
require __DIR__ . '/../classes/MyUnaWorldModule.php';
$failures = array(); $checks = 0;
function checkMaintenance($ok, $label) { global $failures, $checks; $checks++; if (!$ok) $failures[] = $label; }
class MaintenanceEngine {
    private $module;
    function __construct($module) { $this->module = $module; }
    function run($mode, array $context) {
        if ($mode === 'full') {
            checkMaintenance((bool)getParam('sys_maintenance_mode'), 'full backup must run behind native maintenance');
            checkMaintenance(getParam('sys_template_cache_compress_enable') === '', 'Studio assets remain available during native maintenance');
            if ($this->module->custom && !$this->module->preexisting) {
                $html = getParam('sys_maintenance_mode_html');
                checkMaintenance(str_contains($html, 'World &lt;custom&gt; heading'), 'saved maintenance heading renders safely');
                checkMaintenance(str_contains($html, 'id="mud-maintenance"'), 'World page is actually installed for the run');
                checkMaintenance(!str_contains($html, 'PRIVATE-BACKUP-PATH'), 'public page does not disclose private backup details');
            } else {
                checkMaintenance(getParam('sys_maintenance_mode_html') === 'Original UNA maintenance', 'existing or opted-out maintenance page is preserved');
            }
        } else checkMaintenance(getParam('sys_maintenance_mode') === '', 'inventory test does not interrupt public access');
        if ($this->module->fail) throw new RuntimeException('Simulated backup failure');
        return array('archive_name' => 'complete.zip');
    }
}
class MaintenanceModule extends MyUnaWorldModule {
    public $custom = true; public $preexisting = false; public $fail = false;
    protected function _getBackupEngine() { return new MaintenanceEngine($this); }
    public function getLifecycleSettings() { return array('use_maintenance_page' => $this->custom, 'maintenance_show_header' => true, 'maintenance_show_footer' => true, 'maintenance_heading' => 'World <custom> heading', 'maintenance_message' => 'Please wait safely.', 'maintenance_patience' => 'Thank you.', 'maintenance_return_message' => 'Estimated return time is counting down live.', 'maintenance_footer_message' => 'Community maintenance'); }
    public function getBackups($limit = 20) { return array(); }
    public function applyBackupRetention($manual = false) { return array('applied' => false); }
}
function resetMaintenanceOptions($active = '') {
    global $options;
    $options = array('sys_maintenance_mode' => $active, 'sys_maintenance_mode_html' => 'Original UNA maintenance', 'sys_template_cache_compress_enable' => 'on');
    return $options;
}
try {
    foreach (array(false, true) as $fail) {
        $original = resetMaintenanceOptions(); $module = new MaintenanceModule(); $module->fail = $fail;
        try { $module->createBackup('full'); checkMaintenance(!$fail, 'backup error propagates'); }
        catch (RuntimeException $error) { checkMaintenance($fail && $error->getMessage() === 'Simulated backup failure', 'original backup error is preserved'); }
        checkMaintenance($options === $original, 'native state is exactly restored after success or failure');
    }
    $original = resetMaintenanceOptions(); $module = new MaintenanceModule(); $module->custom = false;
    $module->createBackup('full'); checkMaintenance($options === $original, 'page opt-out still blocks and restores access');
    $original = resetMaintenanceOptions('on'); $module = new MaintenanceModule(); $module->preexisting = true;
    $module->createBackup('full'); checkMaintenance($options === $original, 'backup must not reopen preexisting maintenance');
    $original = resetMaintenanceOptions(); (new MaintenanceModule())->createBackup('inventory');
    checkMaintenance($options === $original, 'inventory leaves maintenance configuration unchanged');
    if ($failures) { fwrite(STDERR, 'Maintenance failures:' . PHP_EOL . '- ' . implode(PHP_EOL . '- ', $failures) . PHP_EOL); exit(1); }
    echo 'Backup maintenance assertions passed: ' . $checks . PHP_EOL;
} finally {
    foreach (glob($root . '/.my-una-maintenance-*') as $file) if (is_file($file)) unlink($file);
    rmdir($root . '/public_html'); rmdir($root);
}
