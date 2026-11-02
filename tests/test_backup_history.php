<?php
define('BX_DOL',true);
class BxDolModuleDb {
    public $pdo;
    function getAll($sql,$params=array()){ $s=$this->pdo->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC); }
}
require __DIR__.'/../classes/MyUnaWorldDb.php';
class HistoryDb extends MyUnaWorldDb {
    function __construct(){
        $this->pdo=new PDO('sqlite::memory:');
        $this->pdo->exec('CREATE TABLE my_una_world_backups(id INTEGER,created_at INTEGER,archive_name TEXT,status TEXT)');
        $this->pdo->exec('CREATE TABLE my_una_world_jobs(id INTEGER,backup_id INTEGER,archive_name TEXT,status TEXT,started_at INTEGER,finished_at INTEGER)');
    }
    function ensureJobsTable(){return true;}
}
$db=new HistoryDb();
$db->pdo->exec("INSERT INTO my_una_world_backups VALUES(1,1000,'one.zip','complete'),(2,1100,'two.zip','complete')");
$db->pdo->exec("INSERT INTO my_una_world_jobs VALUES(1,1,'one.zip','complete',1000,1600),(2,1,'one.zip','complete',1000,1700),(3,2,'one.zip','complete',1100,9000),(4,2,'two.zip','failed',1100,2000)");
$rows=$db->getBackups(100);$byId=array_column($rows,null,'id');
if(count($rows)!==2 || (int)($byId[1]['worker_completed_at']??0)!==1700 || !empty($byId[2]['worker_completed_at'])){
    fwrite(STDERR,"FAIL: backup history must match completed jobs by both archive and backup ID without duplicating rows.\n");exit(1);
}
echo "Backup history assertions passed: 3\n";
