<?php defined('BX_DOL') or die('hack attempt');

class MyUnaWorldDb extends BxDolModuleDb
{
    public function __construct(&$oConfig)
    {
        parent::__construct($oConfig);
    }

    public function ensureJobsTable()
    {
        return $this->query(
            "CREATE TABLE IF NOT EXISTS `my_una_world_jobs` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `created_at` int(11) NOT NULL DEFAULT '0',
                `started_at` int(11) NOT NULL DEFAULT '0',
                `finished_at` int(11) NOT NULL DEFAULT '0',
                `mode` varchar(32) NOT NULL DEFAULT 'full',
                `status` varchar(32) NOT NULL DEFAULT 'queued',
                `progress` tinyint(3) unsigned NOT NULL DEFAULT '0',
                `stage` varchar(80) NOT NULL DEFAULT '',
                `message` text NOT NULL,
                `archive_name` varchar(255) NOT NULL DEFAULT '',
                `backup_id` int(10) unsigned NOT NULL DEFAULT '0',
                `context_json` mediumtext NOT NULL,
                PRIMARY KEY (`id`),
                KEY `status_created` (`status`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public function addJob($sMode, array $aContext = array())
    {
        $this->ensureJobsTable();

        $sContext = json_encode(
            $aContext,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if (!is_string($sContext))
            $sContext = '{}';

        $this->query(
            "INSERT INTO `my_una_world_jobs`
             (`created_at`,`mode`,`status`,`progress`,`stage`,`message`,`context_json`)
             VALUES
             (:created_at,:mode,'queued',0,'Queued','Waiting for the UNA background worker.',:context_json)",
            array(
                'created_at' => time(),
                'mode' => $sMode === 'inventory' ? 'inventory' : 'full',
                'context_json' => $sContext,
            )
        );

        return (int)$this->lastId();
    }

    public function getJob($iId)
    {
        $this->ensureJobsTable();

        return $this->getRow(
            "SELECT * FROM `my_una_world_jobs` WHERE `id` = :id LIMIT 1",
            array('id' => (int)$iId)
        );
    }

    public function getActiveJob()
    {
        $this->ensureJobsTable();

        return $this->getRow(
            "SELECT * FROM `my_una_world_jobs`
             WHERE `status` IN ('queued','running')
             ORDER BY `id` ASC
             LIMIT 1"
        );
    }

    public function getQueuedJob()
    {
        $this->ensureJobsTable();

        return $this->getRow(
            "SELECT * FROM `my_una_world_jobs`
             WHERE `status` = 'queued'
             ORDER BY `id` ASC
             LIMIT 1"
        );
    }

    public function getRecentJobs($iLimit = 10)
    {
        $this->ensureJobsTable();
        $iLimit = max(1, min(50, (int)$iLimit));

        return $this->getAll(
            "SELECT * FROM `my_una_world_jobs`
             ORDER BY `id` DESC
             LIMIT " . $iLimit
        );
    }

    public function updateJob($iId, array $a)
    {
        $this->ensureJobsTable();

        $aAllowed = array(
            'started_at', 'finished_at', 'status', 'progress', 'stage',
            'message', 'archive_name', 'backup_id'
        );

        $aSet = array();
        $aParams = array('id' => (int)$iId);

        foreach ($aAllowed as $sField) {
            if (!array_key_exists($sField, $a))
                continue;

            $aSet[] = "`{$sField}` = :{$sField}";
            $aParams[$sField] = in_array($sField, array('started_at','finished_at','progress','backup_id'), true)
                ? (int)$a[$sField]
                : (string)$a[$sField];
        }

        if (!$aSet)
            return true;

        return $this->query(
            "UPDATE `my_una_world_jobs` SET " . implode(', ', $aSet) . " WHERE `id` = :id",
            $aParams
        );
    }

    public function failStaleJobs($iBefore)
    {
        $this->ensureJobsTable();

        return $this->query(
            "UPDATE `my_una_world_jobs`
             SET `status` = 'failed',
                 `finished_at` = :finished_at,
                 `stage` = 'Failed',
                 `message` = 'Background backup worker became stale before completion.'
             WHERE `status` = 'running'
               AND `started_at` > 0
               AND `started_at` < :before",
            array(
                'finished_at' => time(),
                'before' => (int)$iBefore,
            )
        );
    }

    public function addBackup(array $a)
    {
        return $this->query(
            "INSERT INTO `my_una_world_backups`
            (`created_at`,`mode`,`status`,`archive_path`,`archive_name`,`archive_size`,`sha256`,
             `modules_count`,`database_tables`,`files_count`,`bytes_source`,`message`)
            VALUES
            (:created_at,:mode,:status,:archive_path,:archive_name,:archive_size,:sha256,
             :modules_count,:database_tables,:files_count,:bytes_source,:message)",
            array(
                'created_at' => isset($a['created_at']) ? (int)$a['created_at'] : time(),
                'mode' => isset($a['mode']) ? (string)$a['mode'] : 'full',
                'status' => isset($a['status']) ? (string)$a['status'] : 'complete',
                'archive_path' => isset($a['archive_path']) ? (string)$a['archive_path'] : '',
                'archive_name' => isset($a['archive_name']) ? (string)$a['archive_name'] : '',
                'archive_size' => isset($a['archive_size']) ? (int)$a['archive_size'] : 0,
                'sha256' => isset($a['sha256']) ? (string)$a['sha256'] : '',
                'modules_count' => isset($a['modules_count']) ? (int)$a['modules_count'] : 0,
                'database_tables' => isset($a['database_tables']) ? (int)$a['database_tables'] : 0,
                'files_count' => isset($a['files_count']) ? (int)$a['files_count'] : 0,
                'bytes_source' => isset($a['bytes_source']) ? (int)$a['bytes_source'] : 0,
                'message' => isset($a['message']) ? (string)$a['message'] : '',
            )
        );
    }

    public function getBackups($iLimit = 20)
    {
        $iLimit = max(1, min(100, (int)$iLimit));
        return $this->getAll("SELECT * FROM `my_una_world_backups` ORDER BY `id` DESC LIMIT " . $iLimit);
    }

    public function getBackup($iId)
    {
        return $this->getRow(
            "SELECT * FROM `my_una_world_backups` WHERE `id` = :id LIMIT 1",
            array('id' => (int)$iId)
        );
    }

    public function deleteBackup($iId)
    {
        return $this->query(
            "DELETE FROM `my_una_world_backups` WHERE `id` = :id",
            array('id' => (int)$iId)
        );
    }

    public function getBackupByArchiveName($sName)
    {
        return $this->getRow(
            "SELECT * FROM `my_una_world_backups` WHERE `archive_name` = :archive_name ORDER BY `id` DESC LIMIT 1",
            array('archive_name' => (string)$sName)
        );
    }
}
