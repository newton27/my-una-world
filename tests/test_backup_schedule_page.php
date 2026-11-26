<?php
require __DIR__.'/fixtures/backup_page.php';
require __DIR__.'/../classes/MyUnaWorldSchedule.php';
class SchedulePageModule extends BackupPageModule {
    public $root;
    function getBackupRoot(){return $this->root;}
    function getBackupSchedule(){return new MyUnaWorldSchedule($this);}
}
class SchedulePage extends BackupPageSubject {
    function renderSchedule(){$this->_processPost();return $this->_notice().$this->_schedulePanel();}
}
$root=sys_get_temp_dir().'/muw-schedule-page-'.bin2hex(random_bytes(6));mkdir($root,0700);
$module=new SchedulePageModule();$module->root=$root;BxDolModule::$instance=$module;
$checks=0;
function checkSchedulePage($ok,$label){global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
try {
    $page=new SchedulePage('my_una_world','settings');$html=$page->renderSchedule();
    $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($html);libxml_clear_errors();$xp=new DOMXPath($dom);
    checkSchedulePage($xp->query('//select[@name="schedule_frequency"]/option')->length===4,'all frequency choices render');
    foreach(array('schedule_start_date','schedule_start_time','schedule_hours') as $field)
        checkSchedulePage($xp->query('//input[@name="'.$field.'"]')->length===1,'schedule field renders: '.$field);
    checkSchedulePage($xp->query('//select[@name="schedule_timezone"]/option[@value="America/New_York"]')->length===1,'timezone selector includes New York');
    checkSchedulePage(str_contains($html,'Next scheduled run:')&&str_contains($html,'Disabled'),'disabled status explicit');
    $_SERVER['REQUEST_METHOD']='POST';
    $_POST=array('muw_action'=>'save_schedule','csrf'=>'wrong','schedule_enabled'=>'on','schedule_frequency'=>'daily','schedule_hours'=>'48','schedule_start_date'=>'2026-11-26','schedule_start_time'=>'03:00','schedule_timezone'=>'America/New_York');
    $page=new SchedulePage('my_una_world','settings');$html=$page->renderSchedule();
    checkSchedulePage(str_contains($html,'form expired')&&!is_file($root.'/.backup-schedule.json'),'invalid CSRF cannot save a schedule');
    $_POST['csrf']='fixture-csrf';$page=new SchedulePage('my_una_world','settings');$html=$page->renderSchedule();
    checkSchedulePage($module->getBackupSchedule()->getSettings()['enabled']&&str_contains($html,'schedule saved'),'valid Studio POST persists schedule');
    checkSchedulePage(!is_file($root.'/.lifecycle-settings.json'),'schedule save leaves lifecycle settings untouched');
    unset($_POST['schedule_enabled']);$page=new SchedulePage('my_una_world','settings');$page->renderSchedule();
    checkSchedulePage(!$module->getBackupSchedule()->getSettings()['enabled'],'unchecked enable control disables schedule');
    echo "Backup schedule page assertions passed: $checks\n";
}finally{foreach(glob($root.'/{*,.*}',GLOB_BRACE)as$path)if(is_file($path))unlink($path);rmdir($root);}
