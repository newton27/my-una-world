<?php
$root=sys_get_temp_dir().'/muw-status-'.bin2hex(random_bytes(5));
$module=$root.'/modules/newton/my_una_world';mkdir($module,0700,true);mkdir($root.'/inc',0700,true);
copy(__DIR__.'/../status.php',$module.'/status.php');
$header= <<<'PHP'
<?php
function isAdmin(){return getenv('MUW_TEST_ADMIN')==='yes';}
class BxDolModule {static function getInstance($name){return new StatusFixture();}}
class StatusFixture {
 function getBackupJob($id){return $this->getActiveBackupJob();}
 function getActiveBackupJob(){if(getenv('MUW_TEST_EMPTY')==='yes')return false;return array('id'=>1,'created_at'=>1000,'started_at'=>1010,'finished_at'=>0,'mode'=>'full','status'=>'running','progress'=>15,'stage'=>'Database','message'=>'Exporting table 1 of 2.','archive_name'=>'','backup_id'=>0);}
 function getBackgroundWorkerStatus(){return array('healthy'=>true);}
}
PHP;
file_put_contents($root.'/inc/header.inc.php',$header);
$base=getenv();$checks=0;
try {
 foreach(array(array('no','no'),array('yes','no'),array('yes','yes')) as $case){
  $env=array_merge($base,array('MUW_TEST_ADMIN'=>$case[0],'MUW_TEST_EMPTY'=>$case[1]));
  $process=proc_open(array(PHP_BINARY,'status.php'),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes,$module,$env);
  $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
  $data=json_decode($out,true);
  if($code!==0||$err!==''||!is_array($data))throw new RuntimeException('Status fixture did not return valid JSON: '.$out.$err);
  if($case[0]==='no') {if($data['ok']!==false || isset($data['job']))throw new RuntimeException('Unauthenticated users received backup data');}
  else {if(!is_int($data['server_time']??null)||abs(time()-$data['server_time'])>3)throw new RuntimeException('Status needs server time for correct live elapsed duration');}
  $checks++;
 }
 echo "Backup status assertions passed: $checks\n";
} finally {foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($root);}
