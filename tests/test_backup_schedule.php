<?php
define('BX_DOL', true);
class BxDolModule { protected $_oDb; }
require __DIR__ . '/../classes/MyUnaWorldModule.php';
require __DIR__ . '/../classes/MyUnaWorldSchedule.php';
class ScheduleDb {
    public $jobs = array(); public $active = false;
    function getJobByContext($context) {foreach ($this->jobs as $job) if ($job['context'] === $context) return $job; return false;}
    function getActiveJob() {return $this->active ? array('id'=>99, 'status'=>'running') : false;}
    function addJob($mode, $context) {$id = count($this->jobs)+1; $this->jobs[$id] = array('id'=>$id, 'mode'=>$mode, 'context'=>$context); return $id;}
    function getJob($id) {return $this->jobs[$id];}
}
class ScheduleModule extends MyUnaWorldModule {
    public $root; public $db;
    function __construct($root) {$this->root=$root; $this->_oDb=$this->db=new ScheduleDb();}
    function getBackupRoot() {return $this->root;}
}
class BrokenSchedule extends MyUnaWorldSchedule {
    protected function write(array $data) {throw new RuntimeException('Simulated persistence failure');}
}
$checks=0;
function checkSchedule($ok,$label) {global $checks; $checks++; if (!$ok) throw new RuntimeException($label);}
function rejectsSchedule($callback,$label) {try {$callback();} catch (InvalidArgumentException $e) {checkSchedule(true,$label);return;}checkSchedule(false,$label);}
$root=sys_get_temp_dir().'/muw-schedule-'.bin2hex(random_bytes(6));mkdir($root,0700);
try {
    $module=new ScheduleModule($root);$schedule=$module->getBackupSchedule();
    $config=array('enabled'=>true,'frequency'=>'daily','hours'=>48,'start_date'=>'2026-03-07','start_time'=>'03:00','timezone'=>'America/New_York');
    checkSchedule(!$schedule->getSettings()['enabled'] && $schedule->process()===false,'disabled by default');
    checkSchedule(MyUnaWorldSchedule::nextRun($config,strtotime('2026-03-07T08:00:00Z'))===strtotime('2026-03-08T07:00:00Z'),'daily local time crosses spring DST');
    $config['start_date']='2026-10-31';
    checkSchedule(MyUnaWorldSchedule::nextRun($config,strtotime('2026-10-31T07:00:00Z'))===strtotime('2026-11-01T08:00:00Z'),'daily local time crosses fall DST');
    $config['frequency']='weekly';$config['start_date']='2026-03-07';
    checkSchedule(MyUnaWorldSchedule::nextRun($config,strtotime('2026-03-07T08:00:00Z'))===strtotime('2026-03-14T07:00:00Z'),'weekly preserves weekday and local time');
    $config['frequency']='48hours';
    checkSchedule(MyUnaWorldSchedule::nextRun($config,strtotime('2026-03-07T08:00:00Z'))===strtotime('2026-03-09T08:00:00Z'),'48 hours uses elapsed hours');
    $config['frequency']='custom';$config['hours']=6;
    checkSchedule(MyUnaWorldSchedule::nextRun($config,strtotime('2026-03-07T08:00:00Z'))===strtotime('2026-03-07T14:00:00Z'),'custom six hour interval');
    foreach (array(array('hours'=>0),array('hours'=>'1.5'),array('frequency'=>'monthly'),array('timezone'=>'invalid'),array('start_date'=>'2026-02-30'),array('start_date'=>'2026-03-08','start_time'=>'02:30')) as $bad)
        rejectsSchedule(function()use($schedule,$config,$bad){$schedule->save(array_merge($config,$bad));},'invalid schedule rejected');
    $config['frequency']='daily';$config['hours']=48;$now=strtotime('2026-03-06T12:00:00Z');
    $saved=$schedule->save($config,$now);$due=$saved['next_run'];
    checkSchedule($due===strtotime('2026-03-07T08:00:00Z'),'future start date respected');
    checkSchedule($schedule->process($due-1)===false && count($module->db->jobs)===0,'no early job');
    $module->db->active=true;
    checkSchedule($schedule->process($due)===false && $schedule->getSettings()['next_run']===$due,'busy queue retains due slot');
    checkSchedule($schedule->getSettings()['last_error']!=='','busy queue explains waiting');
    $module->db->active=false;
    $broken=new BrokenSchedule($module);
    try {$broken->process($due);}catch(RuntimeException $e){}
    checkSchedule(count($module->db->jobs)===1,'job inserted before simulated save crash');
    $job=$schedule->process($due);
    checkSchedule($job['id']===1 && count($module->db->jobs)===1,'retry after crash reuses durable job');
    checkSchedule($schedule->process($due)===false,'same tick does not queue twice');
    $next=$schedule->getSettings()['next_run'];
    $schedule->save($config,$next+10);
    checkSchedule($schedule->getSettings()['next_run']===$next,'unchanged settings do not erase overdue slot');
    $schedule->process(strtotime('2026-03-20T12:00:00Z'));
    checkSchedule(count($module->db->jobs)===2 && $schedule->getSettings()['next_run']>strtotime('2026-03-20T12:00:00Z'),'downtime queues one catch-up');
    $config['enabled']=false;$schedule->save($config,$now);
    checkSchedule($schedule->process($due+999999)===false,'disabling stops future jobs');
    file_put_contents($root.'/.backup-schedule.json','broken');
    checkSchedule(!$schedule->getSettings()['enabled'],'corrupt schedule fails closed');
    $schedule->save($config,$now);
    checkSchedule($schedule->getSettings()['last_error']==='','saving repairs corrupt settings');
    // Verify queue lock contention cannot create a duplicate scheduled job.
    $lock=fopen($root.'/.backup-queue-worker.lock','c');flock($lock,LOCK_EX);
    try {$module->queueScheduledBackup('busy');checkSchedule(false,'locked queue must reject');}
    catch(RuntimeException $e){checkSchedule(count($module->db->jobs)===2,'locked queue does not insert');}
    flock($lock,LOCK_UN);fclose($lock);
    echo "Backup schedule assertions passed: $checks\n";
} finally {foreach(glob($root.'/{*,.*}',GLOB_BRACE) as $path)if(is_file($path))unlink($path);rmdir($root);}
