<?php
/** Native option lifecycle, shared flock and durable recovery contract. */
define('BX_DOL', true);
$file = dirname(__DIR__) . '/classes/MyUnaMaintenanceLease.php';
if (!is_file($file)) { fwrite(STDERR, "FAIL maintenance lease helper is missing\n"); exit(1); }
require $file;
class LeaseDb {
    public function getRow($sql, $params) { return array_key_exists($params[0], $GLOBALS['params']) ? array('value' => $GLOBALS['params'][$params[0]]) : array(); }
    public function query($sql, $bindings) {
        list($value, $name, $expected) = $bindings;
        if (isset($GLOBALS['raceOther'])) {
            $GLOBALS['params']['sys_maintenance_mode'] = 'on';
            unset($GLOBALS['raceOther']);
        }
        if (isset($GLOBALS['race'][$name])) {
            $GLOBALS['params'][$name] = $GLOBALS['race'][$name];
            unset($GLOBALS['race'][$name]);
        }
        if (($GLOBALS['failSet'] ?? '') === $name) return false;
        if ($GLOBALS['params'][$name] !== $expected) return 0;
        return setParam($name, $value) ? 1 : false;
    }
    public function cacheParams($force = false) { return true; }
}
class BxDolDb { public static function getInstance() { return new LeaseDb(); } }
function getParam($name) { return $GLOBALS['params'][$name] ?? false; }
function setParam($name, $value) {
    if (($GLOBALS['failSet'] ?? '') === $name) return false;
    $GLOBALS['writes'][] = $name;
    $GLOBALS['params'][$name] = (string)$value;
    return true;
}
$root = sys_get_temp_dir() . '/una-lease-' . bin2hex(random_bytes(6));
mkdir($root . '/site', 0700, true);
$site = $root . '/site/';
$prefix = $root . '/.my-una-maintenance-' . sha1(realpath($site) . DIRECTORY_SEPARATOR);
$failed = 0;
function check($label, $ok) { global $failed; echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL; if (!$ok) ++$failed; }
function resetLeaseFixture() {
    $GLOBALS['params'] = array('sys_maintenance_mode' => '', 'sys_maintenance_mode_html' => 'original page', 'sys_template_cache_compress_enable' => 'on');
    $GLOBALS['failSet'] = '';
    $GLOBALS['writes'] = array();
    $GLOBALS['race'] = array();
}
try {
    resetLeaseFixture();
    $original = $GLOBALS['params'];
    $lease = new MyUnaMaintenanceLease($site);
    $begin = $lease->begin('scan page');
    check('caller can preserve the exact original native options in a backup', ($begin['original'] ?? null) === $original);
    check('lease activates native mode only after preparing page and Studio assets', getParam('sys_maintenance_mode') === 'my_una:' . $begin['owner'] && strpos(getParam('sys_maintenance_mode_html'), 'scan page') === 0 && getParam('sys_template_cache_compress_enable') === '' && $GLOBALS['writes'] === array('sys_template_cache_compress_enable', 'sys_maintenance_mode_html', 'sys_maintenance_mode'));
    check('durable rollback journal is private and outside public root', is_file($prefix . '.json') && (fileperms($prefix . '.json') & 0777) === 0600);
    $second = new MyUnaMaintenanceLease($site);
    $caught = false;
    try { $second->begin('other page'); } catch (RuntimeException $error) { $caught = true; }
    check('another operation cannot overwrite an active lease', $caught && strpos(getParam('sys_maintenance_mode_html'), 'scan page') === 0);
    $recovery = MyUnaMaintenanceLease::recover($site);
    check('recovery identifies an active owner without changing its operation', !$recovery['ok'] && ($recovery['busy'] ?? false) === true && (bool)getParam('sys_maintenance_mode'));
    $result = $lease->finish();
    check('finish restores exact options and removes rollback journal', $result['ok'] && $GLOBALS['params'] === $original && !file_exists($prefix . '.json'));
    check('finish can be repeated safely', $lease->finish()['ok']);

    resetLeaseFixture();
    $lease = new MyUnaMaintenanceLease($site);
    $lease->begin(null);
    check('null page preserves configured HTML while locking access', (bool)getParam('sys_maintenance_mode') && getParam('sys_maintenance_mode_html') === 'original page');
    $lease->finish();

    resetLeaseFixture();
    $GLOBALS['params']['sys_maintenance_mode'] = 'administrator-enabled';
    $original = $GLOBALS['params'];
    $lease = new MyUnaMaintenanceLease($site);
    $lease->begin('must not replace administrator page');
    check('existing maintenance and page are never replaced', getParam('sys_maintenance_mode') === 'administrator-enabled' && getParam('sys_maintenance_mode_html') === 'original page');
    $lease->finish();
    check('finish preserves existing maintenance', $GLOBALS['params'] === $original);

    resetLeaseFixture();
    $lease = new MyUnaMaintenanceLease($site);
    $lease->begin('scan page');
    $GLOBALS['params']['sys_maintenance_mode'] = 'another owner';
    $GLOBALS['params']['sys_maintenance_mode_html'] = 'changed page';
    $GLOBALS['params']['sys_template_cache_compress_enable'] = 'changed compression';
    $result = $lease->finish();
    check('cleanup preserves settings changed by another owner', $result['ok'] && getParam('sys_maintenance_mode') === 'another owner' && getParam('sys_maintenance_mode_html') === 'changed page' && getParam('sys_template_cache_compress_enable') === 'changed compression');

    resetLeaseFixture();
    $lease = new MyUnaMaintenanceLease($site);
    $lease->begin('scan page');
    $GLOBALS['race']['sys_maintenance_mode_html'] = 'concurrent Studio save';
    $result = $lease->finish();
    check('atomic restoration preserves a Studio save racing the final write', $result['ok'] && getParam('sys_maintenance_mode_html') === 'concurrent Studio save');

    resetLeaseFixture();
    $lease = new MyUnaMaintenanceLease($site);
    $GLOBALS['raceOther'] = true;
    $caught = false;
    try { $lease->begin('scan page'); } catch (RuntimeException $error) { $caught = true; }
    check('failed arming never undoes administrator maintenance it never acquired', $caught && getParam('sys_maintenance_mode') === 'on');

    resetLeaseFixture();
    $original = $GLOBALS['params'];
    $GLOBALS['failSet'] = 'sys_maintenance_mode';
    $lease = new MyUnaMaintenanceLease($site);
    $caught = false;
    try { $lease->begin('scan page'); } catch (RuntimeException $error) { $caught = true; }
    check('failed activation rolls back page and compression before reporting failure', $caught && $GLOBALS['params'] === $original && !file_exists($prefix . '.json'));

    resetLeaseFixture();
    $original = $GLOBALS['params'];
    $lease = new MyUnaMaintenanceLease($site);
    $lease->begin('scan page');
    $GLOBALS['failSet'] = 'sys_maintenance_mode';
    $result = $lease->finish();
    check('failed rollback keeps Studio assets available and retains recovery evidence', !$result['ok'] && getParam('sys_template_cache_compress_enable') === '' && is_file($prefix . '.json'));
    $GLOBALS['failSet'] = '';
    $result = MyUnaMaintenanceLease::recover($site);
    check('a later successful recovery restores a failed rollback exactly', $result['ok'] && $GLOBALS['params'] === $original && !file_exists($prefix . '.json'));

    resetLeaseFixture();
    $original = $GLOBALS['params'];
    $GLOBALS['params'] = array('sys_maintenance_mode' => 'on', 'sys_maintenance_mode_html' => 'abandoned page', 'sys_template_cache_compress_enable' => '');
    $journal = array('schema' => 2, 'applied' => array('sys_maintenance_mode', 'sys_maintenance_mode_html', 'sys_template_cache_compress_enable'), 'pending' => null, 'site_root' => realpath($site) . DIRECTORY_SEPARATOR, 'owner' => 'abandoned', 'original' => $original, 'expected' => $GLOBALS['params']);
    file_put_contents($prefix . '.json', json_encode($journal));
    chmod($prefix . '.json', 0600);
    $result = MyUnaMaintenanceLease::recover($site);
    check('an abandoned journal is recovered only after obtaining its lock', $result['ok'] && $GLOBALS['params'] === $original && !file_exists($prefix . '.json'));

    foreach (array('my_una:crash-owner', 'on') as $currentMode) {
        resetLeaseFixture();
        $original = $GLOBALS['params'];
        $expected = $original;
        $expected['sys_maintenance_mode'] = 'my_una:crash-owner';
        $GLOBALS['params']['sys_maintenance_mode'] = $currentMode;
        $journal = array('schema' => 2, 'site_root' => realpath($site) . DIRECTORY_SEPARATOR,
            'owner' => 'crash-owner', 'original' => $original, 'expected' => $expected,
            'applied' => array(), 'pending' => 'sys_maintenance_mode');
        file_put_contents($prefix . '.json', json_encode($journal));
        $result = MyUnaMaintenanceLease::recover($site);
        check('a crash before its applied checkpoint restores only the unique owned switch: ' . $currentMode,
            $result['ok'] && getParam('sys_maintenance_mode') === ($currentMode === 'on' ? 'on' : ''));
    }

    resetLeaseFixture();
    $original = $GLOBALS['params'];
    $expected = $original;
    $expected['sys_template_cache_compress_enable'] = '';
    $GLOBALS['params']['sys_template_cache_compress_enable'] = '';
    file_put_contents($prefix . '.json', json_encode(array('schema' => 2,
        'site_root' => realpath($site) . DIRECTORY_SEPARATOR, 'owner' => 'pending-assets',
        'original' => $original, 'expected' => $expected, 'applied' => array(),
        'pending' => 'sys_template_cache_compress_enable')));
    $result = MyUnaMaintenanceLease::recover($site);
    check('an ambiguous compression checkpoint preserves usable direct assets and reports preservation', $result['ok'] && getParam('sys_template_cache_compress_enable') === '' && in_array('sys_template_cache_compress_enable', $result['preserved'], true));
    $GLOBALS['params'] = $original;

    file_put_contents($prefix . '.json', 'invalid journal');
    $result = MyUnaMaintenanceLease::recover($site);
    check('invalid rollback evidence fails safely without guessing', !$result['ok'] && file_exists($prefix . '.json') && $GLOBALS['params'] === $original);
    unlink($prefix . '.json');

    foreach (array('shutdown', 'crash') as $exitMode) {
        resetLeaseFixture();
        $original = $GLOBALS['params'];
        $state = $root . '/worker-state.json';
        $process = proc_open(array(PHP_BINARY, __DIR__ . '/fixtures/maintenance-lease-worker.php', $site, $state, $exitMode), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot start maintenance subprocess fixture');
        if ($exitMode === 'crash') {
            check('separate process holds the shared lease during its actual work', trim(fgets($pipes[1])) === 'READY' && !MyUnaMaintenanceLease::recover($site)['ok']);
            proc_terminate($process, 9);
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $workerError = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        if ($workerError !== '') throw new RuntimeException($workerError);
        $GLOBALS['params'] = json_decode(file_get_contents($state), true);
        if ($exitMode === 'shutdown')
            check('normal PHP shutdown restores exact original state', $GLOBALS['params'] === $original && !file_exists($prefix . '.json'));
        else {
            check('uncatchable termination leaves durable recovery evidence', (bool)getParam('sys_maintenance_mode') && is_file($prefix . '.json'));
            $result = MyUnaMaintenanceLease::recover($site);
            check('process death recovery restores exact original state', $result['ok'] && $GLOBALS['params'] === $original && !file_exists($prefix . '.json'));
        }
        unlink($state);
    }
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $entry) { if ($entry->isDir()) rmdir($entry->getPathname()); else unlink($entry->getPathname()); }
    rmdir($root);
}
exit($failed ? 1 : 0);
