<?php

$page = require __DIR__ . '/fixtures/backup_page.php';
$failures = array();
$checks = 0;

// A stale hard-coded release label must fail when UNA registers another version.
foreach (array('1.3.5', '9.8.7', '<registered-version>') as $version) {
    BxDolModule::$instance->_aModule['version'] = $version;
    $html = $page->renderOverview();
    $checks++;
    $expected = '<strong>_my_una_world_version:</strong> ' .
        htmlspecialchars($version, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br>';
    if (strpos($html, $expected) === false)
        $failures[] = 'Overview must display the registered module version safely: ' . $version;
}

if ($failures) {
    fwrite(STDERR, "Overview version failures:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Overview version assertions passed: $checks\n";
