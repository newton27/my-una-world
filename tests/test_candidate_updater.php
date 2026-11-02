<?php
define('BX_DOL',true);define('BX_DOL_MODULE_TYPE_MODULE','module');
function candidateConfig($path){$aConfig=null;require $path;return $aConfig;}
$root=dirname(__DIR__);$update=$root.'/updates/1.3.3_1.3.4';
$config=candidateConfig($update.'/install/config.php');$module=candidateConfig($root.'/install/config.php');
if($config['version_from']!=='1.3.3'||$config['version_to']!==$module['version']||$config['install']['update_files']!==1||$config['install']['execute_sql']!==0)throw new RuntimeException('Native candidate update identity/actions do not match');
$files=array('classes/MyUnaWorldModule.php','classes/MyUnaWorldStudioPage.php','classes/MyUnaWorldBackup.php','classes/MyUnaWorldBackupTiming.php','classes/MyUnaWorldDb.php','status.php','install/config.php');
foreach($files as $file){$target=$update.'/source/'.$file;if(!is_file($target)||hash_file('sha256',$target)!==hash_file('sha256',$root.'/'.$file))throw new RuntimeException('Native updater is missing current runtime bytes: '.$file);}
echo 'Candidate updater assertions passed: '.(count($files)+1)."\n";
