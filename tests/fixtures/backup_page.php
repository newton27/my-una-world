<?php
define('BX_DOL',true);define('BX_DOL_URL_ROOT','https://example.invalid/');
$_SERVER['REQUEST_METHOD']='GET';
class BxDolModule {public static $instance;public static function getInstance($name){return self::$instance;}}
class BxTemplStudioModule {public $sManageUrl='/studio/module.php?name=my_una_world';public $sPageDefault;public $aMenuItems;function __construct($m,$n,$p=''){}}
class BxDolStudioTemplate {static function getInstance(){return new self();}function parseHtmlByName($name,$vars){return $vars['content'];}}
class BxDolForm {static function getCsrfToken(){return 'fixture-csrf';}}
function getParam($name){return 'on';}
function bx_append_url_params($url,$params){return $url.'&'.http_build_query($params);}
function _t($key){return array('_my_una_world_backups'=>'Backups','_my_una_world_backups_desc'=>'Manage backup archives and recovery actions.','_my_una_world_backup_active'=>'Backup in progress')[$key]??$key;}
class BackupPageModule {
    public $_aModule=array('name'=>'my_una_world','version'=>'1.3.5');
    public $active=false;
    function getBackupRoot(){return '/private/backups/';}
    function getBackups($limit){
        $start=strtotime('2026-11-01 14:00:00 UTC');
        $base=array('mode'=>'full','status'=>'complete','archive_size'=>122269928,'modules_count'=>85,'database_tables'=>1123,'files_count'=>5310,'bytes_source'=>184215435,'message'=>'Manual backup.','archive_path'=>'/nonexistent/backup.zip');
        return array(
            array_merge($base,array('id'=>1,'created_at'=>$start,'archive_name'=>'my_una_world_full_20261101_140000.zip','timing'=>array('started_at'=>$start,'completed_at'=>$start+3600,'duration_seconds'=>3600,'phases'=>array('database'=>3300,'archive'=>240,'checksum'=>20),'slowest_tables'=>array(array('table'=>'fixture_rows','rows'=>2500000,'seconds'=>3000))))),
            array_merge($base,array('id'=>2,'created_at'=>$start-7200,'archive_name'=>'my_una_world_full_20261101_120000.zip','timing'=>array('started_at'=>$start-7200,'completed_at'=>$start-6900,'duration_seconds'=>300,'phases'=>array(),'slowest_tables'=>array()))),
            array_merge($base,array('id'=>3,'mode'=>'inventory','created_at'=>$start-14400,'archive_name'=>'my_una_world_inventory_20261101_100000.zip','message'=>'<script>alert("no")</script>','timing'=>array('started_at'=>$start-14400,'completed_at'=>null,'duration_seconds'=>null,'phases'=>array(),'slowest_tables'=>array())))
        );
    }
    function getRestoreReports($limit){return array(array('safety_backup_protected'=>true,'safety_backup'=>'my_una_world_full_20261101_120000.zip','report_name'=>'restore-fixture.json'));}
    function getActiveBackupJob(){return $this->active;}
    function getRecentBackupJobs($limit){return array();}
}
require __DIR__.'/../../classes/MyUnaWorldStudioPage.php';
class BackupPageSubject extends MyUnaWorldStudioPage {
    public function renderBackups(){return $this->getBackups();}
    public function renderOverview(){return $this->getOverview();}
}
BxDolModule::$instance=new BackupPageModule();
$page=new BackupPageSubject('my_una_world','backups');
return $page;
