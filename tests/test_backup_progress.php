<?php
define('BX_DOL',true);
$root=sys_get_temp_dir().'/muw-timing-'.bin2hex(random_bytes(5));
define('BX_DIRECTORY_PATH_ROOT',$root.'/site/');
define('BX_DOL_URL_ROOT','https://example.invalid/');
class BxDolDb { public static $instance; public static function getInstance(){return self::$instance;} }
class ProgressDb {
    public $rows;
    function __construct(){ $this->rows=array(); for($i=1;$i<=610;$i++) $this->rows[]=array('id'=>$i,'body'=>'PRIVATE-CONTENT-SENTINEL-'.$i); }
    function getAll($sql){
        if(preg_match('/SELECT \* FROM `fixture_rows` LIMIT (\d+),(\d+)/',$sql,$m))return array_slice($this->rows,(int)$m[1],(int)$m[2]);
        throw new RuntimeException('Unexpected query: '.$sql);
    }
    function getRow($sql){if($sql==='SHOW CREATE TABLE `fixture_rows`')return array('Table'=>'fixture_rows','Create Table'=>'CREATE TABLE `fixture_rows` (`id` INTEGER, `body` TEXT)');throw new RuntimeException($sql);}
}
class ProgressModule {
    public $progress=array(); public $history=array();
    function updateBackupJobProgress($id,$percent,$stage,$message){$this->progress[]=array('percent'=>$percent,'stage'=>$stage,'message'=>$message);return true;}
    function addBackupHistory($a){$this->history[]=$a;return true;}
}
require __DIR__.'/../classes/MyUnaWorldBackup.php';
class ProgressEngine extends MyUnaWorldBackup {
    private $clock=1000;
    protected function _now(){ $this->clock+=3;return (float)$this->clock; }
    protected function _resolveBackupRoot(){global $root;return $root.'/backups';}
    protected function _getInstalledModules(){return array('fixture'=>array('name'=>'fixture','version'=>'1')) ;}
    protected function _discoverInstalledModulePaths(array $a){return array('fixture'=>'vendor/fixture');}
    protected function _getDatabaseObjects(){return array(array('Tables_in_test'=>'fixture_rows','Table_type'=>'BASE TABLE'));}
}
function cleanupProgress($path){if(!is_dir($path))return;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($path);}
$failures=array();$checks=0;
function checkProgress($ok,$label){global $checks,$failures;$checks++;if(!$ok)$failures[]=$label;}
try {
    mkdir($root.'/backups',0700,true);mkdir(BX_DIRECTORY_PATH_ROOT.'modules/vendor/fixture',0700,true);mkdir(BX_DIRECTORY_PATH_ROOT.'storage',0700,true);
    file_put_contents(BX_DIRECTORY_PATH_ROOT.'modules/vendor/fixture/example.php','<?php /* fixture */');
    file_put_contents(BX_DIRECTORY_PATH_ROOT.'storage/source.jpg','source bytes');
    BxDolDb::$instance=new ProgressDb();$module=new ProgressModule();$engine=new ProgressEngine($module);
    $result=$engine->run('full',array('job_id'=>1));
    $verify=json_decode(file_get_contents($result['archive_path'].'.verify.json'),true);
    checkProgress(($verify['timing']['version']??null)===1,'verification contains the versioned completed timing record');
    checkProgress(($verify['timing']['completed_at']??0)>($verify['timing']['started_at']??PHP_INT_MAX),'completed time follows actual start');
    checkProgress(isset($verify['timing']['phases']['database'],$verify['timing']['phases']['archive'],$verify['timing']['phases']['checksum']),'database, finalization and checksum are measured separately');
    $zip=new ZipArchive();checkProgress($zip->open($result['archive_path'],ZipArchive::CHECKCONS)===true,'the actual completed ZIP passes consistency validation');
    $manifest=json_decode($zip->getFromName('manifest.json'),true);$sql=$zip->getFromName('database/database.sql');$zip->close();
    checkProgress(isset($manifest['prepared_at'])&&!isset($manifest['finished_at']),'embedded preparation cannot masquerade as completed verification');
    checkProgress(hash_file('sha256',$result['archive_path'])===$verify['sha256'],'completed timing does not alter the verified archive digest');
    checkProgress(($verify['stats']['database_rows']??0)===610,'all source rows remain represented');
    $pdo=new PDO('sqlite::memory:');$sql=preg_replace('/^SET .*;\s*$/m','',$sql);$pdo->exec($sql);
    checkProgress((int)$pdo->query('SELECT COUNT(*) FROM fixture_rows')->fetchColumn()===610,'real SQL export restores every row');
    $database=array_values(array_filter($module->progress,fn($p)=>$p['stage']==='Database'&&str_contains($p['message'],'fixture_rows')));
    checkProgress(count($database)>=2 && count($database)<=4,'database reports table and row progress while throttling writes');
    checkProgress(!str_contains(json_encode($module->progress),'PRIVATE-CONTENT-SENTINEL'),'progress contains no database row content');
    checkProgress(isset($verify['timing']['slowest_tables'][0]['table'])&&$verify['timing']['slowest_tables'][0]['table']==='fixture_rows','per-table timing identifies the slowest export without content');
    if($failures){fwrite(STDERR,"Progress failures:\n- ".implode("\n- ",$failures)."\n");exit(1);}
    echo "Backup progress assertions passed: $checks\n";
} finally {cleanupProgress($root);}
