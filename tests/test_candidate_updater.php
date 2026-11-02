<?php
define('BX_DOL', true);
define('BX_DOL_MODULE_TYPE_MODULE', 'module');

function candidateConfig($path)
{
    if (!is_file($path))
        return array();
    $aConfig = null;
    require $path;
    return $aConfig;
}

$root = dirname(__DIR__);
$module = candidateConfig($root . '/install/config.php');
$files = array(
    'classes/MyUnaWorldModule.php',
    'classes/MyUnaWorldStudioPage.php',
    'classes/MyUnaWorldBackup.php',
    'classes/MyUnaWorldBackupTiming.php',
    'classes/MyUnaWorldDb.php',
    'classes/MyUnaWorldRestore.php',
    'status.php',
    'install/config.php',
);
$failures = array();
$checks = 0;
foreach (array('1.3.3' => '133', '1.3.4' => '134') as $from => $uriVersion) {
    $update = $root . '/updates/' . $from . '_1.3.5';
    $config = candidateConfig($update . '/install/config.php');
    $expected = array(
        'version_from' => $from,
        'version_to' => '1.3.5',
        'home_dir' => 'newton/my_una_world/updates/update_' . $from . '_1.3.5/',
        'home_uri' => 'my_una_world_update_' . $uriVersion . '_135',
        'module_dir' => 'newton/my_una_world/',
        'module_uri' => 'my_una_world',
        'install' => array('execute_sql' => 0, 'update_files' => 1, 'update_languages' => 0, 'clear_db_cache' => 1),
    );
    foreach ($expected as $key => $value) {
        $checks++;
        if (!array_key_exists($key, $config) || $config[$key] !== $value)
            $failures[] = $from . ' native updater identity/action mismatch: ' . $key;
    }
    foreach ($files as $file) {
        $checks++;
        $target = $update . '/source/' . $file;
        if (!is_file($target) || hash_file('sha256', $target) !== hash_file('sha256', $root . '/' . $file))
            $failures[] = $from . ' native updater is missing current runtime bytes: ' . $file;
    }
}
$checks++;
if ($module['version'] !== '1.3.5')
    $failures[] = 'Full installer must register version 1.3.5';

if ($failures) {
    fwrite(STDERR, "Candidate updater failures:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Candidate updater assertions passed: $checks\n";
