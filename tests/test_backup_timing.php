<?php
define('BX_DOL', true);
$path = __DIR__ . '/../classes/MyUnaWorldBackupTiming.php';
if (is_file($path)) require $path;
$failures = array(); $checks = 0;
function checkTiming($ok, $label) { global $checks, $failures; $checks++; if (!$ok) $failures[] = $label; }
function resolveTiming($record, $verification = array()) {
    return class_exists('MyUnaWorldBackupTiming') ? MyUnaWorldBackupTiming::resolve($record, $verification) : array();
}
$record = array('id'=>7, 'created_at'=>1000, 'status'=>'complete', 'archive_name'=>'backup.zip', 'sha256'=>str_repeat('a',64));
$worker = resolveTiming($record + array('worker_completed_at'=>1600));
checkTiming(($worker['started_at'] ?? null) === 1000 && ($worker['completed_at'] ?? null) === 1600 && ($worker['duration_seconds'] ?? null) === 600, 'worker history supplies real completion and elapsed seconds');
$legacy = resolveTiming($record, array('archive_name'=>'backup.zip','sha256'=>str_repeat('a',64),'stats'=>array('finished_at'=>'1970-01-01T00:25:00Z','duration_seconds'=>500)));
checkTiming(array_key_exists('completed_at',$legacy) && $legacy['completed_at'] === null && $legacy['duration_seconds'] === null, 'legacy preparation timestamp is never called completion');
$verified = array('archive_name'=>'backup.zip','sha256'=>str_repeat('a',64),'integrity'=>'passed','timing'=>array('version'=>1,'started_at'=>1000,'completed_at'=>1800,'phases'=>array('database'=>600.2,'archive'=>100.5)));
$new = resolveTiming($record,$verified);
checkTiming(($new['completed_at'] ?? null) === 1800 && ($new['duration_seconds'] ?? null) === 800 && ($new['phases']['database'] ?? null) === 600.2, 'versioned matching sidecar provides completion and phase timings');
$other = $verified; $other['archive_name']='other.zip';
checkTiming((resolveTiming($record,$other)['completed_at'] ?? null) === null, 'another archive cannot supply completion');
$other=$verified; $other['sha256']=str_repeat('b',64);
checkTiming((resolveTiming($record,$other)['completed_at'] ?? null) === null, 'a mismatched digest cannot supply completion');
$other=$verified; $other['timing']['completed_at']=999;
checkTiming((resolveTiming($record,$other)['completed_at'] ?? null) === null, 'completion before start is invalid');
$other=$verified; $other['timing']['version']=2;
checkTiming((resolveTiming($record,$other)['completed_at'] ?? null) === null, 'an unknown timing schema is not silently trusted');
$failed=$record; $failed['status']='failed';
checkTiming((resolveTiming($failed,$verified)['completed_at'] ?? null) === null, 'failed records never acquire successful completion evidence');
$other=$verified; $other['timing']['phases']=array('database'=>-1,'archive'=>'bad','<script>'=>20);
checkTiming((resolveTiming($record,$other)['phases'] ?? null) === array(), 'invalid and unexpected phase values are excluded');
checkTiming(class_exists('MyUnaWorldBackupTiming') && MyUnaWorldBackupTiming::duration(7226)==='2h 00m 26s', 'long durations remain clear across hours');
checkTiming(class_exists('MyUnaWorldBackupTiming') && MyUnaWorldBackupTiming::timestamp(1000)==='1970-01-01 00:16:40 UTC', 'timestamps name the timezone');
if ($failures) { fwrite(STDERR,"Timing failures:\n- ".implode("\n- ",$failures)."\n"); exit(1); }
echo "Backup timing assertions passed: $checks\n";
