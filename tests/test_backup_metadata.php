<?php
define('BX_DOL',true);
class BxDolModule { protected $_oDb; }
require __DIR__.'/../classes/MyUnaWorldModule.php';
class MetadataDb {public $rows;function getBackups($limit){return $this->rows;}}
class MetadataModule extends MyUnaWorldModule {
    private $root;
    function __construct($root,$records){$this->root=$root;$this->_oDb=new MetadataDb();$this->_oDb->rows=$records;}
    function getBackupRoot(){return $this->root;}
}
$root=sys_get_temp_dir().'/muw-metadata-'.bin2hex(random_bytes(5));mkdir($root,0700);
$name='my_una_world_full_20261101_100000.zip';
$record=array('id'=>1,'created_at'=>1000,'status'=>'complete','archive_name'=>$name,'sha256'=>str_repeat('a',64));
$verification=array('archive_name'=>$name,'sha256'=>str_repeat('a',64),'integrity'=>'passed','timing'=>array('version'=>1,'started_at'=>1000,'completed_at'=>1800));
$outside=$root.'-outside';
try {
    file_put_contents($root.'/'.$name.'.verify.json',json_encode($verification));
    $module=new MetadataModule($root,array($record));$r=$module->getBackups();
    if(($r[0]['timing']['completed_at']??null)!==1800)throw new RuntimeException('matching sidecar timing must be attached without opening any archive');
    unlink($root.'/'.$name.'.verify.json');file_put_contents($outside,json_encode($verification));symlink($outside,$root.'/'.$name.'.verify.json');
    $r=$module->getBackups();if(($r[0]['timing']['completed_at']??null)!==null)throw new RuntimeException('sidecars outside the private root must not be followed');
    unlink($root.'/'.$name.'.verify.json');file_put_contents($root.'/'.$name.'.verify.json',str_repeat(' ',262145));
    $r=$module->getBackups();if(($r[0]['timing']['completed_at']??null)!==null)throw new RuntimeException('oversized sidecars must not be decoded');
    echo "Backup metadata assertions passed: 3\n";
} finally {if(is_file($root.'/'.$name.'.verify.json')||is_link($root.'/'.$name.'.verify.json'))unlink($root.'/'.$name.'.verify.json');if(is_file($outside))unlink($outside);rmdir($root);}
