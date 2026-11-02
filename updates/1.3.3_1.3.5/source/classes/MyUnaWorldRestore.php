<?php defined('BX_DOL') or die('hack attempt');

class MyUnaWorldRestore
{
    const CURRENT_TOOL_VERSION = '1.3.5';

    protected $_oModule;
    protected $_oDb;
    protected $_oBackup;
    protected $_sSiteRoot;
    protected $_sBackupRoot;

    public function __construct($oModule, $oBackup)
    {
        $this->_oModule = $oModule;
        $this->_oDb = BxDolDb::getInstance();
        $this->_oBackup = $oBackup;
        $this->_sSiteRoot = rtrim(BX_DIRECTORY_PATH_ROOT, DIRECTORY_SEPARATOR);
        $this->_sBackupRoot = $oBackup->getBackupRoot();
    }

    public function inspect($iBackupId)
    {
        $aResolved = $this->_resolveBackup($iBackupId);
        $sArchive = $aResolved['archive_path'];
        $aRecord = $aResolved['record'];

        @set_time_limit(0);

        $sActualSha = hash_file('sha256', $sArchive);
        if (!is_string($sActualSha) || $sActualSha === '')
            throw new RuntimeException('Unable to calculate the backup SHA-256 checksum.');

        $sStoredSha = isset($aRecord['sha256']) ? strtolower(trim((string)$aRecord['sha256'])) : '';
        $bChecksum = $sStoredSha !== '' && hash_equals($sStoredSha, strtolower($sActualSha));

        $oZip = new ZipArchive();
        $iOpen = $oZip->open($sArchive, ZipArchive::CHECKCONS);
        if ($iOpen !== true)
            throw new RuntimeException('ZIP integrity validation failed with error code: ' . $iOpen);

        try {
            $this->_validateZipEntries($oZip);
            $aManifest = $this->_readZipJson($oZip, 'manifest.json', true);
            $aSystem = $this->_readZipJson($oZip, 'report/system.json', true);
            $aModules = $this->_readZipJson($oZip, 'report/modules.json', false);

            $iDbIndex = $oZip->locateName('database/database.sql', ZipArchive::FL_NOCASE);
            $bDatabase = $iDbIndex !== false;
            $iDatabaseBytes = 0;
            if ($bDatabase) {
                $aDbStat = $oZip->statIndex($iDbIndex);
                $iDatabaseBytes = is_array($aDbStat) && isset($aDbStat['size']) ? (int)$aDbStat['size'] : 0;
            }

            $aCompatibility = $this->_compatibility($aSystem, $aManifest, $aModules, $bChecksum, $bDatabase);

            return array(
                'backup_id' => (int)$iBackupId,
                'archive_name' => $aRecord['archive_name'],
                'archive_size' => (int)filesize($sArchive),
                'sha256_expected' => $sStoredSha,
                'sha256_actual' => strtolower($sActualSha),
                'checksum_match' => $bChecksum,
                'zip_integrity' => 'passed',
                'database_present' => $bDatabase,
                'database_bytes' => $iDatabaseBytes,
                'manifest' => $aManifest,
                'system' => array(
                    'product' => isset($aSystem['product']) ? $aSystem['product'] : '',
                    'module_version' => isset($aSystem['module_version']) ? $aSystem['module_version'] : '',
                    'backup_mode' => isset($aSystem['backup_mode']) ? $aSystem['backup_mode'] : '',
                    'created_at' => isset($aSystem['created_at']) ? $aSystem['created_at'] : '',
                    'site_url' => isset($aSystem['site_url']) ? $aSystem['site_url'] : '',
                    'una_version' => isset($aSystem['una_version']) ? $aSystem['una_version'] : '',
                    'php_version' => isset($aSystem['php_version']) ? $aSystem['php_version'] : '',
                ),
                'compatibility' => $aCompatibility,
            );
        }
        finally {
            $oZip->close();
        }
    }

    public function dryRun($iBackupId)
    {
        $aInspect = $this->inspect($iBackupId);
        $aResolved = $this->_resolveBackup($iBackupId);
        $sArchive = $aResolved['archive_path'];

        $oZip = new ZipArchive();
        $iOpen = $oZip->open($sArchive, ZipArchive::CHECKCONS);
        if ($iOpen !== true)
            throw new RuntimeException('Unable to open the backup for dry-run analysis.');

        $aPlan = array(
            'backup_id' => (int)$iBackupId,
            'archive_name' => $aInspect['archive_name'],
            'can_restore' => !empty($aInspect['compatibility']['can_restore']),
            'database_bytes' => 0,
            'module_files' => 0,
            'module_bytes' => 0,
            'storage_files' => 0,
            'storage_bytes' => 0,
            'existing_files_to_overwrite' => 0,
            'new_files_to_create' => 0,
            'protected_tool_files_skipped' => 0,
            'file_restore_mode' => 'safe overlay (existing unrelated files are not deleted)',
            'database_restore_mode' => 'drop/recreate tables and views contained in database/database.sql',
            'pre_restore_backup_required' => true,
            'warnings' => $aInspect['compatibility']['warnings'],
            'blocking_issues' => $aInspect['compatibility']['blocking_issues'],
        );

        try {
            $this->_validateZipEntries($oZip);

            for ($i = 0; $i < $oZip->numFiles; $i++) {
                $aStat = $oZip->statIndex($i);
                if (!is_array($aStat) || empty($aStat['name']))
                    continue;

                $sName = (string)$aStat['name'];
                if (substr($sName, -1) === '/')
                    continue;

                $iSize = isset($aStat['size']) ? (int)$aStat['size'] : 0;

                if ($sName === 'database/database.sql') {
                    $aPlan['database_bytes'] = $iSize;
                    continue;
                }

                if (strpos($sName, 'modules/newton/my_una_world/') === 0) {
                    $aPlan['protected_tool_files_skipped']++;
                    continue;
                }

                if (strpos($sName, 'modules/') === 0) {
                    $aPlan['module_files']++;
                    $aPlan['module_bytes'] += $iSize;
                }
                elseif (strpos($sName, 'storage/') === 0) {
                    $aPlan['storage_files']++;
                    $aPlan['storage_bytes'] += $iSize;
                }
                else {
                    continue;
                }

                $sTarget = $this->_targetForEntry($sName);
                if (is_file($sTarget))
                    $aPlan['existing_files_to_overwrite']++;
                else
                    $aPlan['new_files_to_create']++;
            }

            return $aPlan;
        }
        finally {
            $oZip->close();
        }
    }

    public function restore($iBackupId, $sConfirmation)
    {
        if (trim((string)$sConfirmation) !== 'RESTORE')
            throw new RuntimeException('Restore confirmation failed. Type RESTORE exactly.');

        return $this->_performRestore((int)$iBackupId, 'restore', '');
    }


    public function getRestoreReports($iLimit = 20)
    {
        $iLimit = max(1, min(100, (int)$iLimit));
        $aNames = $this->_listReportFiles();
        $sLatestRestore = $this->_latestRestoreReportName();
        $aResult = array();

        foreach ($aNames as $sName) {
            if (count($aResult) >= $iLimit)
                break;

            try {
                $aReport = $this->_readReportFile($sName);
            }
            catch (Throwable $e) {
                continue;
            }

            $sOperation = isset($aReport['operation'])
                ? (string)$aReport['operation']
                : (strpos($sName, 'rollback_') === 0 ? 'rollback' : 'restore');

            $sSafety = isset($aReport['safety_backup']) ? (string)$aReport['safety_backup'] : '';
            $aSafety = $sSafety !== '' ? $this->_oModule->getBackupByArchiveName($sSafety) : false;
            $bSafetyExists = $aSafety && !empty($aSafety['archive_path']) && is_file($aSafety['archive_path']);

            $sRollbackStatus = isset($aReport['rollback_status']) ? (string)$aReport['rollback_status'] : '';
            $bRollbackConsumed = in_array($sRollbackStatus, array('complete', 'verification_failed'), true);

            $mVerificationPassed = null;
            if (isset($aReport['verification']) && is_array($aReport['verification']) &&
                array_key_exists('passed', $aReport['verification'])) {
                $mVerificationPassed = (bool)$aReport['verification']['passed'];
            }

            $aResult[] = array(
                'report_name' => $sName,
                'operation' => $sOperation,
                'status' => isset($aReport['status']) ? (string)$aReport['status'] : '',
                'started_at' => isset($aReport['started_at']) ? (string)$aReport['started_at'] : '',
                'finished_at' => isset($aReport['finished_at']) ? (string)$aReport['finished_at'] : '',
                'source_archive' => isset($aReport['source_archive']) ? (string)$aReport['source_archive'] : '',
                'safety_backup' => $sSafety,
                'safety_backup_exists' => $bSafetyExists,
                'verification_passed' => $mVerificationPassed,
                'rollback_status' => $sRollbackStatus,
                'rollback_report' => isset($aReport['rollback_report']) ? (string)$aReport['rollback_report'] : '',
                'can_rollback' => $sOperation === 'restore' &&
                    $sName === $sLatestRestore &&
                    !$bRollbackConsumed &&
                    $sRollbackStatus !== 'running' &&
                    $bSafetyExists,
                'safety_backup_protected' => $sOperation === 'restore' &&
                    $sName === $sLatestRestore &&
                    !$bRollbackConsumed &&
                    $bSafetyExists,
            );
        }

        return $aResult;
    }

    public function getRestoreReport($sReportName)
    {
        $aReport = $this->_readReportFile($sReportName);
        $aReport['report_name'] = basename((string)$sReportName);
        return $aReport;
    }

    public function verifyRestoreReport($sReportName)
    {
        @set_time_limit(0);

        $sReportName = $this->_normalizeReportName($sReportName);
        $aReport = $this->_readReportFile($sReportName);

        $sSource = isset($aReport['source_archive']) ? (string)$aReport['source_archive'] : '';
        if ($sSource === '')
            throw new RuntimeException('Restore report does not identify its source backup.');

        $aSource = $this->_oModule->getBackupByArchiveName($sSource);
        if (!$aSource)
            throw new RuntimeException('The source backup is no longer present in Backup History: ' . $sSource);

        /*
         * Re-run archive checksum/integrity inspection first, then compare
         * the live site to the source backup.
         */
        $this->inspect((int)$aSource['id']);

        $aVerification = $this->_verifyPostRestore(
            (int)$aSource['id'],
            isset($aReport['safety_backup']) ? (string)$aReport['safety_backup'] : ''
        );

        $aReport['verification'] = $aVerification;
        $aReport['verified_at'] = date('c');
        $aReport['status'] = !empty($aVerification['passed']) ? 'verified' : 'verification_failed';

        $this->_writeJson($this->_reportPath($sReportName), $aReport);

        $aReport['report_name'] = $sReportName;
        return $aReport;
    }

    public function rollback($sReportName)
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $sReportName = $this->_normalizeReportName($sReportName);
        if (strpos($sReportName, 'restore_') !== 0)
            throw new RuntimeException('Rollback can only start from a restore report.');

        $sLatest = $this->_latestRestoreReportName();
        if ($sLatest === '' || $sLatest !== $sReportName)
            throw new RuntimeException('Only the most recent restore can be rolled back automatically.');

        $aReport = $this->_readReportFile($sReportName);
        $sExistingStatus = isset($aReport['rollback_status']) ? (string)$aReport['rollback_status'] : '';
        if (in_array($sExistingStatus, array('complete', 'verification_failed'), true))
            throw new RuntimeException('This restore has already been rolled back.');

        $sSafety = isset($aReport['safety_backup']) ? (string)$aReport['safety_backup'] : '';
        if ($sSafety === '')
            throw new RuntimeException('The restore report does not contain a safety backup.');

        $aSafety = $this->_oModule->getBackupByArchiveName($sSafety);
        if (!$aSafety || empty($aSafety['archive_path']) || !is_file($aSafety['archive_path']))
            throw new RuntimeException('The rollback safety backup is missing: ' . $sSafety);

        $aReport['rollback_status'] = 'running';
        $aReport['rollback_started_at'] = date('c');
        $this->_writeJson($this->_reportPath($sReportName), $aReport);

        try {
            $aResult = $this->_performRestore((int)$aSafety['id'], 'rollback', $sReportName);

            $aReport = $this->_readReportFile($sReportName);
            $bVerified = isset($aResult['verification']['passed']) && $aResult['verification']['passed'];
            $aReport['rollback_status'] = $bVerified ? 'complete' : 'verification_failed';
            $aReport['rolled_back_at'] = date('c');
            $aReport['rollback_source'] = $sSafety;
            $aReport['rollback_report'] = isset($aResult['restore_report']) ? $aResult['restore_report'] : '';
            $aReport['rollback_safety_backup'] = isset($aResult['safety_backup']) ? $aResult['safety_backup'] : '';
            $this->_writeJson($this->_reportPath($sReportName), $aReport);

            $aResult['rollback_of_report'] = $sReportName;
            return $aResult;
        }
        catch (Throwable $e) {
            try {
                $aReport = $this->_readReportFile($sReportName);
                $aReport['rollback_status'] = 'failed';
                $aReport['rollback_failed_at'] = date('c');
                $aReport['rollback_error'] = $e->getMessage();
                $this->_writeJson($this->_reportPath($sReportName), $aReport);
            }
            catch (Throwable $ignored) {
            }

            throw $e;
        }
    }

    public function deleteRestoreReport($sReportName)
    {
        $sReportName = $this->_normalizeReportName($sReportName);
        $sPath = $this->_reportPath($sReportName);

        if (!is_file($sPath))
            throw new RuntimeException('Restore report is already missing: ' . $sReportName);

        /*
         * Deleting a report removes only the small JSON report. It never
         * deletes a source backup, safety backup, ZIP, verify sidecar, or
         * any site data.
         */
        $aReport = $this->_readReportFile($sReportName);

        if (!@unlink($sPath))
            throw new RuntimeException('Unable to delete restore report: ' . $sReportName);

        return array(
            'report_name' => $sReportName,
            'operation' => isset($aReport['operation'])
                ? (string)$aReport['operation']
                : (strpos($sReportName, 'rollback_') === 0 ? 'rollback' : 'restore'),
            'source_archive' => isset($aReport['source_archive']) ? (string)$aReport['source_archive'] : '',
            'safety_backup' => isset($aReport['safety_backup']) ? (string)$aReport['safety_backup'] : '',
            'deleted' => true,
            'backup_archives_deleted' => false,
        );
    }

    public function getBackupProtection($sArchiveName)
    {
        $sArchiveName = basename((string)$sArchiveName);
        $sLatest = $this->_latestRestoreReportName();

        if ($sLatest === '')
            return array('protected' => false, 'report_name' => '');

        try {
            $aReport = $this->_readReportFile($sLatest);
        }
        catch (Throwable $e) {
            return array('protected' => false, 'report_name' => '');
        }

        $sRollbackStatus = isset($aReport['rollback_status']) ? (string)$aReport['rollback_status'] : '';
        $bConsumed = in_array($sRollbackStatus, array('complete', 'verification_failed'), true);

        if (!$bConsumed &&
            isset($aReport['safety_backup']) &&
            basename((string)$aReport['safety_backup']) === $sArchiveName) {
            return array(
                'protected' => true,
                'report_name' => $sLatest,
            );
        }

        return array('protected' => false, 'report_name' => '');
    }

    protected function _performRestore($iBackupId, $sOperation = 'restore', $sParentReport = '')
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $sOperation = $sOperation === 'rollback' ? 'rollback' : 'restore';

        $aInspect = $this->inspect($iBackupId);
        if (empty($aInspect['compatibility']['can_restore'])) {
            $sReasons = implode('; ', $aInspect['compatibility']['blocking_issues']);
            throw new RuntimeException('Restore is blocked: ' . ($sReasons !== '' ? $sReasons : 'compatibility checks failed.'));
        }

        $aTargetRecord = $this->_oModule->getBackup((int)$iBackupId);
        if (!$aTargetRecord)
            throw new RuntimeException('The selected backup history record disappeared before restore.');

        /*
         * Safety invariant: never modify the site unless a fresh full backup
         * of the current state has completed successfully first.
         */
        $aSafetyBackup = $this->_oModule->createBackup('full', array(
            'trigger' => $sOperation === 'rollback' ? 'pre_rollback_safety' : 'pre_restore_safety',
            'retention_skip' => true,
            'reason' => $sOperation === 'rollback'
                ? 'Automatic safety backup before rollback.'
                : 'Automatic safety backup before restore.',
        ));

        $aResolved = $this->_resolveBackup($iBackupId);
        $sArchive = $aResolved['archive_path'];

        $iStarted = time();
        $sPrefix = $sOperation === 'rollback' ? 'rollback_' : 'restore_';
        $sRestoreReport = $this->_sBackupRoot . DIRECTORY_SEPARATOR .
            $sPrefix . date('Ymd_His', $iStarted) . '.json';

        $aResult = array(
            'operation' => $sOperation,
            'status' => 'running',
            'started_at' => date('c', $iStarted),
            'source_backup_id' => (int)$iBackupId,
            'source_archive' => $aTargetRecord['archive_name'],
            'safety_backup' => isset($aSafetyBackup['archive_name']) ? $aSafetyBackup['archive_name'] : '',
            'parent_report' => (string)$sParentReport,
            'files_restored' => 0,
            'module_files_restored' => 0,
            'storage_files_restored' => 0,
            'protected_tool_files_skipped' => 0,
            'bytes_restored' => 0,
            'sql_statements_executed' => 0,
            'view_statements_retried' => 0,
            'restore_report' => basename($sRestoreReport),
        );

        try {
            $oZip = new ZipArchive();
            $iOpen = $oZip->open($sArchive, ZipArchive::CHECKCONS);
            if ($iOpen !== true)
                throw new RuntimeException('Unable to open the source backup for restore.');

            try {
                $this->_validateZipEntries($oZip);

                $aFileStats = $this->_restoreFiles($oZip);
                foreach ($aFileStats as $sKey => $mixedValue)
                    $aResult[$sKey] = $mixedValue;

                $sSqlPath = $this->_extractDatabaseDump($oZip);
            }
            finally {
                $oZip->close();
            }

            try {
                $aSqlStats = $this->_executeSqlFile($sSqlPath);
                $aResult['sql_statements_executed'] = $aSqlStats['executed'];
                $aResult['view_statements_retried'] = $aSqlStats['view_retries'];
            }
            finally {
                if (isset($sSqlPath) && is_file($sSqlPath))
                    @unlink($sSqlPath);
            }

            /*
             * Backups can contain an older My UNA World registration.
             * Keep the current recovery engine installed and enabled.
             */
            $this->_preserveCurrentToolRegistration();

            /*
             * The selected backup and the just-created safety backup were
             * created after their respective DB dumps, so make sure history
             * contains both after the old database state is restored.
             */
            $this->_ensureHistoryRecord($aTargetRecord);
            $this->_ensureHistoryRecord($aSafetyBackup);

            $this->_clearUnaCaches();

            /*
             * 1.1.1 verifies the live restored state before marking the
             * operation successful.
             */
            $aVerification = $this->_verifyPostRestore(
                (int)$iBackupId,
                isset($aSafetyBackup['archive_name']) ? (string)$aSafetyBackup['archive_name'] : ''
            );

            $aResult['verification'] = $aVerification;
            $aResult['status'] = !empty($aVerification['passed']) ? 'verified' : 'verification_failed';
            $aResult['finished_at'] = date('c');
            $aResult['duration_seconds'] = time() - $iStarted;
            $this->_writeJson($sRestoreReport, $aResult);

            /*
             * Recovery is complete now, so the configured retention policy
             * may safely run. The active rollback safety archive remains
             * protected by getBackupProtection().
             */
            try {
                $aResult['retention'] = $this->_oModule->applyBackupRetention(false);
            }
            catch (Throwable $e) {
                $aResult['retention'] = array(
                    'applied' => false,
                    'error' => $e->getMessage(),
                );
            }

            return $aResult;
        }
        catch (Throwable $e) {
            $aResult['status'] = 'failed';
            $aResult['finished_at'] = date('c');
            $aResult['duration_seconds'] = time() - $iStarted;
            $aResult['error'] = $e->getMessage();

            try {
                $this->_writeJson($sRestoreReport, $aResult);
            }
            catch (Throwable $ignored) {
            }

            throw new RuntimeException(
                ucfirst($sOperation) . ' stopped: ' . $e->getMessage() .
                ' A pre-operation safety backup was created as ' .
                (isset($aSafetyBackup['archive_name']) ? $aSafetyBackup['archive_name'] : 'an archive') . '.'
            );
        }
    }

    protected function _verifyPostRestore($iBackupId, $sSafetyBackup = '')
    {
        $aVerification = array(
            'passed' => false,
            'checked_at' => date('c'),
            'database_reachable' => false,
            'database_objects_expected' => 0,
            'database_objects_live' => 0,
            'registered_modules_expected' => 0,
            'registered_modules_live' => 0,
            'module_paths_expected' => 0,
            'module_paths_present' => 0,
            'archive_files_expected' => 0,
            'archive_files_present' => 0,
            'archive_files_missing' => 0,
            'archive_size_mismatches' => 0,
            'protected_tool_files_skipped' => 0,
            'storage_files_expected' => 0,
            'storage_files_live' => 0,
            'safety_backup_present' => false,
            'tool_registration_ok' => false,
            'tool_registration_repaired' => false,
            'critical_directories' => array(),
            'errors' => array(),
            'warnings' => array(),
        );

        try {
            $mPing = $this->_oDb->getOne("SELECT 1");
            $aVerification['database_reachable'] = ((string)$mPing === '1');
            if (!$aVerification['database_reachable'])
                $aVerification['errors'][] = 'Database connectivity check failed.';
        }
        catch (Throwable $e) {
            $aVerification['errors'][] = 'Database connectivity check failed: ' . $e->getMessage();
        }

        try {
            $aTool = $this->_oDb->getRow(
                "SELECT `version`, `enabled`, `path`, `uri`, `class_prefix`, `db_prefix`, `vendor`
                 FROM `sys_modules`
                 WHERE `name` = 'my_una_world'
                 LIMIT 1"
            );

            $bRegistrationOk =
                is_array($aTool) &&
                isset(
                    $aTool['version'],
                    $aTool['enabled'],
                    $aTool['path'],
                    $aTool['uri'],
                    $aTool['class_prefix'],
                    $aTool['db_prefix'],
                    $aTool['vendor']
                ) &&
                (string)$aTool['version'] === self::CURRENT_TOOL_VERSION &&
                (int)$aTool['enabled'] === 1 &&
                trim((string)$aTool['path'], "/\\") === 'newton/my_una_world' &&
                (string)$aTool['uri'] === 'my_una_world' &&
                (string)$aTool['class_prefix'] === 'MyUnaWorld' &&
                (string)$aTool['db_prefix'] === 'my_una_world_' &&
                (string)$aTool['vendor'] === 'MyUNAWorld';

            if (!$bRegistrationOk && is_array($aTool)) {
                /*
                 * A restore can legitimately bring back an older sys_modules
                 * row from a backup made before the current My UNA World
                 * release. The live 1.1.2 files are protected from restore,
                 * so repair only this module's own registration metadata and
                 * verify it again.
                 */
                $this->_preserveCurrentToolRegistration();
                $aVerification['tool_registration_repaired'] = true;

                $aTool = $this->_oDb->getRow(
                    "SELECT `version`, `enabled`, `path`, `uri`, `class_prefix`, `db_prefix`, `vendor`
                     FROM `sys_modules`
                     WHERE `name` = 'my_una_world'
                     LIMIT 1"
                );

                $bRegistrationOk =
                    is_array($aTool) &&
                    isset(
                        $aTool['version'],
                        $aTool['enabled'],
                        $aTool['path'],
                        $aTool['uri'],
                        $aTool['class_prefix'],
                        $aTool['db_prefix'],
                        $aTool['vendor']
                    ) &&
                    (string)$aTool['version'] === self::CURRENT_TOOL_VERSION &&
                    (int)$aTool['enabled'] === 1 &&
                    trim((string)$aTool['path'], "/\\") === 'newton/my_una_world' &&
                    (string)$aTool['uri'] === 'my_una_world' &&
                    (string)$aTool['class_prefix'] === 'MyUnaWorld' &&
                    (string)$aTool['db_prefix'] === 'my_una_world_' &&
                    (string)$aTool['vendor'] === 'MyUNAWorld';

                if ($bRegistrationOk) {
                    $aVerification['warnings'][] =
                        'My UNA World registration metadata was restored from an older backup and was automatically repaired to ' .
                        self::CURRENT_TOOL_VERSION . '.';
                }
            }

            $aVerification['tool_registration_ok'] = $bRegistrationOk;

            if (!$aVerification['tool_registration_ok'])
                $aVerification['errors'][] =
                    'My UNA World current-version registration could not be repaired after restore.';
        }
        catch (Throwable $e) {
            $aVerification['errors'][] =
                'Unable to verify or repair My UNA World registration: ' . $e->getMessage();
        }

        $aResolved = $this->_resolveBackup((int)$iBackupId);
        $oZip = new ZipArchive();
        $iOpen = $oZip->open($aResolved['archive_path'], ZipArchive::CHECKCONS);
        if ($iOpen !== true) {
            $aVerification['errors'][] = 'Unable to reopen the source backup during post-restore verification.';
            return $aVerification;
        }

        try {
            $this->_validateZipEntries($oZip);
            $aManifest = $this->_readZipJson($oZip, 'manifest.json', true);
            $aSystem = $this->_readZipJson($oZip, 'report/system.json', true);

            $aVerification['database_objects_expected'] =
                isset($aManifest['database_tables']) ? (int)$aManifest['database_tables'] : 0;
            $aVerification['registered_modules_expected'] =
                isset($aManifest['modules_count']) ? (int)$aManifest['modules_count'] : 0;
            $aVerification['storage_files_expected'] =
                isset($aManifest['storage_files']) ? (int)$aManifest['storage_files'] : 0;

            try {
                $aObjects = $this->_oDb->getAll("SHOW FULL TABLES");
                $aVerification['database_objects_live'] = is_array($aObjects) ? count($aObjects) : 0;
                if ($aVerification['database_objects_expected'] > 0 &&
                    $aVerification['database_objects_live'] !== $aVerification['database_objects_expected']) {
                    $aVerification['errors'][] =
                        'Database object count mismatch: expected ' .
                        $aVerification['database_objects_expected'] . ', found ' .
                        $aVerification['database_objects_live'] . '.';
                }
            }
            catch (Throwable $e) {
                $aVerification['errors'][] = 'Unable to count restored database objects: ' . $e->getMessage();
            }

            try {
                $aVerification['registered_modules_live'] = (int)$this->_oDb->getOne(
                    "SELECT COUNT(*) FROM `sys_modules`"
                );
                if ($aVerification['registered_modules_expected'] > 0 &&
                    $aVerification['registered_modules_live'] !== $aVerification['registered_modules_expected']) {
                    $aVerification['errors'][] =
                        'Installed-module registry count mismatch: expected ' .
                        $aVerification['registered_modules_expected'] . ', found ' .
                        $aVerification['registered_modules_live'] . '.';
                }
            }
            catch (Throwable $e) {
                $aVerification['errors'][] = 'Unable to count installed module registrations: ' . $e->getMessage();
            }

            foreach (array('inc', 'modules', 'storage') as $sDir) {
                $sPath = $this->_sSiteRoot . DIRECTORY_SEPARATOR . $sDir;
                $bExists = is_dir($sPath);
                $aVerification['critical_directories'][$sDir] = $bExists;

                if (!$bExists && ($sDir !== 'storage' || $aVerification['storage_files_expected'] > 0))
                    $aVerification['errors'][] = 'Critical directory is missing after restore: ' . $sDir;
            }

            $aMissingSamples = array();
            $aSizeSamples = array();

            for ($i = 0; $i < $oZip->numFiles; $i++) {
                $aStat = $oZip->statIndex($i);
                if (!is_array($aStat) || empty($aStat['name']))
                    continue;

                $sName = (string)$aStat['name'];
                if (substr($sName, -1) === '/')
                    continue;

                if (strpos($sName, 'modules/newton/my_una_world/') === 0) {
                    $aVerification['protected_tool_files_skipped']++;
                    continue;
                }

                if (strpos($sName, 'modules/') !== 0 && strpos($sName, 'storage/') !== 0)
                    continue;

                $aVerification['archive_files_expected']++;
                $sTarget = $this->_targetForEntry($sName);

                if (!is_file($sTarget)) {
                    $aVerification['archive_files_missing']++;
                    if (count($aMissingSamples) < 10)
                        $aMissingSamples[] = $sName;
                    continue;
                }

                $aVerification['archive_files_present']++;
                $iExpectedSize = isset($aStat['size']) ? (int)$aStat['size'] : 0;
                $iLiveSize = (int)filesize($sTarget);

                if ($iExpectedSize !== $iLiveSize) {
                    $aVerification['archive_size_mismatches']++;
                    if (count($aSizeSamples) < 10)
                        $aSizeSamples[] = $sName;
                }
            }

            if ($aVerification['archive_files_missing'] > 0) {
                $aVerification['errors'][] =
                    $aVerification['archive_files_missing'] .
                    ' backed-up module/storage file(s) are missing after restore.' .
                    ($aMissingSamples ? ' Samples: ' . implode(', ', $aMissingSamples) : '');
            }

            if ($aVerification['archive_size_mismatches'] > 0) {
                $aVerification['errors'][] =
                    $aVerification['archive_size_mismatches'] .
                    ' restored file(s) have a different size than the backup.' .
                    ($aSizeSamples ? ' Samples: ' . implode(', ', $aSizeSamples) : '');
            }

            $aModulePaths = isset($aSystem['module_paths']) && is_array($aSystem['module_paths'])
                ? $aSystem['module_paths']
                : array();
            $aVerification['module_paths_expected'] = count($aModulePaths);
            $aMissingModules = array();

            foreach ($aModulePaths as $sModuleName => $sRelative) {
                $sRelative = trim((string)$sRelative, "/\\");
                if ($sRelative === '')
                    continue;

                $sPath = $this->_sSiteRoot . DIRECTORY_SEPARATOR . 'modules' .
                    DIRECTORY_SEPARATOR . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $sRelative);

                if (is_dir($sPath)) {
                    $aVerification['module_paths_present']++;
                }
                elseif (count($aMissingModules) < 20) {
                    $aMissingModules[] = (string)$sModuleName;
                }
            }

            if ($aVerification['module_paths_present'] !== $aVerification['module_paths_expected']) {
                $aVerification['errors'][] =
                    'Installed module source directories are missing after restore.' .
                    ($aMissingModules ? ' Missing: ' . implode(', ', $aMissingModules) : '');
            }

            $sStorage = $this->_sSiteRoot . DIRECTORY_SEPARATOR . 'storage';
            $aVerification['storage_files_live'] = is_dir($sStorage) ? $this->_countFiles($sStorage) : 0;

            if ($aVerification['storage_files_live'] < $aVerification['storage_files_expected']) {
                $aVerification['errors'][] =
                    'Live storage contains fewer files than the source backup: expected at least ' .
                    $aVerification['storage_files_expected'] . ', found ' .
                    $aVerification['storage_files_live'] . '.';
            }
            elseif ($aVerification['storage_files_live'] > $aVerification['storage_files_expected']) {
                $aVerification['warnings'][] =
                    'Live storage contains additional files not present in the source backup. ' .
                    'This is allowed by safe overlay mode.';
            }
        }
        finally {
            $oZip->close();
        }

        if ($sSafetyBackup !== '') {
            $aSafety = $this->_oModule->getBackupByArchiveName($sSafetyBackup);
            $aVerification['safety_backup_present'] =
                $aSafety && !empty($aSafety['archive_path']) && is_file($aSafety['archive_path']);

            if (!$aVerification['safety_backup_present'])
                $aVerification['errors'][] = 'Pre-operation safety backup is no longer available.';
        }
        else {
            $aVerification['warnings'][] = 'No safety backup name was supplied to verification.';
        }

        $aVerification['passed'] = empty($aVerification['errors']);
        return $aVerification;
    }

    protected function _countFiles($sRoot)
    {
        $iCount = 0;
        if (!is_dir($sRoot))
            return 0;

        $oIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($oIterator as $oFile) {
            if ($oFile->isFile() && !$oFile->isLink())
                $iCount++;
        }

        return $iCount;
    }

    protected function _normalizeReportName($sReportName)
    {
        $sName = basename((string)$sReportName);
        if (!preg_match('/^(?:restore|rollback)_\d{8}_\d{6}\.json$/D', $sName))
            throw new RuntimeException('Restore report name failed the safety check.');

        return $sName;
    }

    protected function _reportPath($sReportName)
    {
        $sName = $this->_normalizeReportName($sReportName);
        $sRoot = realpath($this->_sBackupRoot);

        if ($sRoot === false || !is_dir($sRoot))
            throw new RuntimeException('Backup directory could not be resolved safely.');

        return $sRoot . DIRECTORY_SEPARATOR . $sName;
    }

    protected function _readReportFile($sReportName)
    {
        $sName = $this->_normalizeReportName($sReportName);
        $sPath = $this->_reportPath($sName);

        if (!is_file($sPath))
            throw new RuntimeException('Restore report is missing: ' . $sName);

        $sJson = file_get_contents($sPath);
        $aData = json_decode($sJson, true);

        if (!is_array($aData))
            throw new RuntimeException('Restore report contains invalid JSON: ' . $sName);

        return $aData;
    }

    protected function _listReportFiles()
    {
        $aNames = array();

        foreach (array('restore_*.json', 'rollback_*.json') as $sPattern) {
            $aFiles = glob($this->_sBackupRoot . DIRECTORY_SEPARATOR . $sPattern);
            if (!is_array($aFiles))
                continue;

            foreach ($aFiles as $sPath) {
                $sName = basename($sPath);
                if (preg_match('/^(?:restore|rollback)_\d{8}_\d{6}\.json$/D', $sName))
                    $aNames[] = $sName;
            }
        }

        usort($aNames, function($a, $b) {
            preg_match('/_(\d{8}_\d{6})\.json$/', $a, $aMatch);
            preg_match('/_(\d{8}_\d{6})\.json$/', $b, $bMatch);
            $aKey = isset($aMatch[1]) ? $aMatch[1] : '';
            $bKey = isset($bMatch[1]) ? $bMatch[1] : '';
            return strcmp($bKey, $aKey);
        });

        return array_values(array_unique($aNames));
    }

    protected function _latestRestoreReportName()
    {
        $aFiles = glob($this->_sBackupRoot . DIRECTORY_SEPARATOR . 'restore_*.json');
        if (!is_array($aFiles) || !$aFiles)
            return '';

        $aNames = array();
        foreach ($aFiles as $sPath) {
            $sName = basename($sPath);
            if (preg_match('/^restore_\d{8}_\d{6}\.json$/D', $sName))
                $aNames[] = $sName;
        }

        if (!$aNames)
            return '';

        rsort($aNames, SORT_STRING);
        return $aNames[0];
    }

    protected function _resolveBackup($iBackupId)
    {
        $iBackupId = (int)$iBackupId;
        if ($iBackupId <= 0)
            throw new InvalidArgumentException('Invalid backup ID.');

        $aRecord = $this->_oModule->getBackup($iBackupId);
        if (!$aRecord)
            throw new RuntimeException('Backup history record was not found.');

        if ((string)$aRecord['status'] !== 'complete')
            throw new RuntimeException('Only completed backups can be inspected or restored.');

        $sName = basename((string)$aRecord['archive_name']);
        if ($sName === '' ||
            !preg_match('/^my_una_world_(?:inventory|full)_\d{8}_\d{6}\.zip$/D', $sName))
            throw new RuntimeException('Backup archive name failed the safety check.');

        $sRoot = realpath($this->_sBackupRoot);
        if ($sRoot === false || !is_dir($sRoot))
            throw new RuntimeException('Backup directory could not be resolved safely.');

        $sArchive = $sRoot . DIRECTORY_SEPARATOR . $sName;
        if (!is_file($sArchive))
            throw new RuntimeException('Backup archive is missing from disk: ' . $sName);

        $sRealArchive = realpath($sArchive);
        if ($sRealArchive === false || realpath(dirname($sRealArchive)) !== $sRoot)
            throw new RuntimeException('Backup archive failed the path-safety check.');

        return array(
            'record' => $aRecord,
            'archive_path' => $sRealArchive,
            'backup_root' => $sRoot,
        );
    }

    protected function _compatibility(array $aSystem, array $aManifest, array $aModules, $bChecksum, $bDatabase)
    {
        $aBlock = array();
        $aWarnings = array();

        if (!$bChecksum)
            $aBlock[] = 'SHA-256 checksum does not match the backup history record.';

        if (!$bDatabase)
            $aBlock[] = 'database/database.sql is missing.';

        if (!isset($aSystem['product']) || (string)$aSystem['product'] !== 'My UNA World')
            $aBlock[] = 'The archive does not identify itself as a My UNA World backup.';

        $sMode = isset($aManifest['mode']) ? (string)$aManifest['mode'] :
            (isset($aSystem['backup_mode']) ? (string)$aSystem['backup_mode'] : '');
        if ($sMode !== 'full')
            $aBlock[] = 'Inventory archives cannot be restored; a full backup is required.';

        $sBackupUna = isset($aSystem['una_version']) ? (string)$aSystem['una_version'] : '';
        $sCurrentUna = function_exists('bx_get_ver') ? (string)bx_get_ver() : '';

        if ($sBackupUna === '' || $sCurrentUna === '') {
            $aWarnings[] = 'UNA version comparison is unavailable.';
        }
        elseif ($sBackupUna !== $sCurrentUna) {
            $iBackupMajor = $this->_versionMajor($sBackupUna);
            $iCurrentMajor = $this->_versionMajor($sCurrentUna);
            if ($iBackupMajor > 0 && $iCurrentMajor > 0 && $iBackupMajor !== $iCurrentMajor)
                $aBlock[] = 'UNA major version mismatch: backup ' . $sBackupUna . ', current ' . $sCurrentUna . '.';
            else
                $aWarnings[] = 'UNA versions differ: backup ' . $sBackupUna . ', current ' . $sCurrentUna . '.';
        }

        $sBackupPhp = isset($aSystem['php_version']) ? (string)$aSystem['php_version'] : '';
        if ($sBackupPhp !== '' && $this->_majorMinor($sBackupPhp) !== $this->_majorMinor(PHP_VERSION))
            $aWarnings[] = 'PHP versions differ: backup ' . $sBackupPhp . ', current ' . PHP_VERSION . '.';

        $sBackupUrl = isset($aSystem['site_url']) ? (string)$aSystem['site_url'] : '';
        $sCurrentUrl = defined('BX_DOL_URL_ROOT') ? (string)BX_DOL_URL_ROOT : '';
        $sBackupHost = $sBackupUrl !== '' ? parse_url($sBackupUrl, PHP_URL_HOST) : '';
        $sCurrentHost = $sCurrentUrl !== '' ? parse_url($sCurrentUrl, PHP_URL_HOST) : '';

        if ($sBackupHost && $sCurrentHost && strcasecmp($sBackupHost, $sCurrentHost) !== 0) {
            $aBlock[] = self::CURRENT_TOOL_VERSION . ' only performs same-site restores. Backup host is ' .
                $sBackupHost . ' and current host is ' . $sCurrentHost . '.';
        }

        $bToolFound = false;
        foreach ($aModules as $aModule) {
            if (is_array($aModule) && isset($aModule['name']) && $aModule['name'] === 'my_una_world') {
                $bToolFound = true;
                break;
            }
        }
        if (!$bToolFound)
            $aBlock[] = 'This first restore release requires a backup created after My UNA World was installed.';

        if (!empty($aManifest['unmatched_installed_modules'])) {
            $aWarnings[] = 'Backup reported module registrations with missing source folders: ' .
                implode(', ', (array)$aManifest['unmatched_installed_modules']) . '.';
        }

        return array(
            'can_restore' => empty($aBlock),
            'blocking_issues' => $aBlock,
            'warnings' => $aWarnings,
            'backup_una_version' => $sBackupUna,
            'current_una_version' => $sCurrentUna,
            'backup_php_version' => $sBackupPhp,
            'current_php_version' => PHP_VERSION,
            'same_site' => !($sBackupHost && $sCurrentHost) || strcasecmp($sBackupHost, $sCurrentHost) === 0,
        );
    }

    protected function _validateZipEntries(ZipArchive $oZip)
    {
        for ($i = 0; $i < $oZip->numFiles; $i++) {
            $aStat = $oZip->statIndex($i);
            if (!is_array($aStat) || !isset($aStat['name']))
                throw new RuntimeException('Unable to read a ZIP directory entry.');

            $sName = (string)$aStat['name'];
            if ($sName === '')
                continue;

            if (strpos($sName, "\0") !== false ||
                strpos($sName, '\\') !== false ||
                $sName[0] === '/' ||
                preg_match('#(^|/)\.\.(/|$)#', $sName))
                throw new RuntimeException('Unsafe path detected inside backup ZIP: ' . $sName);

            if ($sName === 'manifest.json' ||
                strpos($sName, 'report/') === 0 ||
                strpos($sName, 'database/') === 0 ||
                strpos($sName, 'modules/') === 0 ||
                strpos($sName, 'storage/') === 0)
                continue;

            throw new RuntimeException('Unexpected path detected inside backup ZIP: ' . $sName);
        }
    }

    protected function _readZipJson(ZipArchive $oZip, $sName, $bRequired)
    {
        $sData = $oZip->getFromName($sName);
        if ($sData === false) {
            if ($bRequired)
                throw new RuntimeException('Required backup report is missing: ' . $sName);
            return array();
        }

        $aData = json_decode($sData, true);
        if (!is_array($aData))
            throw new RuntimeException('Backup report contains invalid JSON: ' . $sName);

        return $aData;
    }

    protected function _targetForEntry($sName)
    {
        if (strpos($sName, 'modules/') !== 0 && strpos($sName, 'storage/') !== 0)
            throw new RuntimeException('Refusing to map a non-restorable ZIP entry: ' . $sName);

        if (strpos($sName, "\0") !== false ||
            strpos($sName, '\\') !== false ||
            $sName[0] === '/' ||
            preg_match('#(^|/)\.\.(/|$)#', $sName))
            throw new RuntimeException('Unsafe restore path detected: ' . $sName);

        return $this->_sSiteRoot . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $sName);
    }

    protected function _restoreFiles(ZipArchive $oZip)
    {
        $aStats = array(
            'files_restored' => 0,
            'module_files_restored' => 0,
            'storage_files_restored' => 0,
            'protected_tool_files_skipped' => 0,
            'bytes_restored' => 0,
        );

        for ($i = 0; $i < $oZip->numFiles; $i++) {
            $aStat = $oZip->statIndex($i);
            if (!is_array($aStat) || empty($aStat['name']))
                continue;

            $sName = (string)$aStat['name'];
            if (substr($sName, -1) === '/')
                continue;

            if (strpos($sName, 'modules/newton/my_una_world/') === 0) {
                $aStats['protected_tool_files_skipped']++;
                continue;
            }

            $bModule = strpos($sName, 'modules/') === 0;
            $bStorage = strpos($sName, 'storage/') === 0;
            if (!$bModule && !$bStorage)
                continue;

            $sTarget = $this->_targetForEntry($sName);
            $sDir = dirname($sTarget);
            if (!is_dir($sDir) && !@mkdir($sDir, 0750, true) && !is_dir($sDir))
                throw new RuntimeException('Unable to create restore directory: ' . $sDir);

            $hIn = $oZip->getStream($sName);
            if (!$hIn)
                throw new RuntimeException('Unable to read ZIP entry during restore: ' . $sName);

            $sTmp = $sTarget . '.muw_restore_' . bin2hex(random_bytes(4));
            $hOut = @fopen($sTmp, 'wb');
            if (!$hOut) {
                fclose($hIn);
                throw new RuntimeException('Unable to create temporary restore file: ' . $sTmp);
            }

            try {
                while (!feof($hIn)) {
                    $sChunk = fread($hIn, 1024 * 1024);
                    if ($sChunk === false)
                        throw new RuntimeException('Read failure while restoring: ' . $sName);
                    if ($sChunk !== '' && fwrite($hOut, $sChunk) === false)
                        throw new RuntimeException('Write failure while restoring: ' . $sTarget);
                }
            }
            finally {
                fclose($hIn);
                fclose($hOut);
            }

            @chmod($sTmp, 0640);
            if (!@rename($sTmp, $sTarget)) {
                @unlink($sTmp);
                throw new RuntimeException('Unable to replace restored file: ' . $sTarget);
            }

            $iSize = isset($aStat['size']) ? (int)$aStat['size'] : 0;
            $aStats['files_restored']++;
            $aStats['bytes_restored'] += $iSize;
            if ($bModule)
                $aStats['module_files_restored']++;
            else
                $aStats['storage_files_restored']++;
        }

        return $aStats;
    }

    protected function _extractDatabaseDump(ZipArchive $oZip)
    {
        $hIn = $oZip->getStream('database/database.sql');
        if (!$hIn)
            throw new RuntimeException('Unable to read database/database.sql from the backup.');

        $sPath = $this->_sBackupRoot . DIRECTORY_SEPARATOR .
            '.restore_database_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.sql';

        $hOut = @fopen($sPath, 'wb');
        if (!$hOut) {
            fclose($hIn);
            throw new RuntimeException('Unable to create temporary database restore file.');
        }

        try {
            while (!feof($hIn)) {
                $sChunk = fread($hIn, 1024 * 1024);
                if ($sChunk === false)
                    throw new RuntimeException('Unable to read database dump from ZIP.');
                if ($sChunk !== '' && fwrite($hOut, $sChunk) === false)
                    throw new RuntimeException('Unable to write temporary database restore file.');
            }
        }
        finally {
            fclose($hIn);
            fclose($hOut);
        }

        @chmod($sPath, 0600);
        return $sPath;
    }

    protected function _executeSqlFile($sPath)
    {
        $h = @fopen($sPath, 'rb');
        if (!$h)
            throw new RuntimeException('Unable to open the temporary database dump.');

        $sBuffer = '';
        $sQuote = '';
        $bEscaped = false;
        $iExecuted = 0;
        $aDeferredViews = array();

        try {
            while (($sLine = fgets($h)) !== false) {
                if ($sQuote === '' && trim($sBuffer) === '' &&
                    preg_match('/^\s*--(?:\s|$)/', $sLine))
                    continue;

                $iLen = strlen($sLine);
                for ($i = 0; $i < $iLen; $i++) {
                    $sChar = $sLine[$i];

                    if ($sQuote !== '') {
                        $sBuffer .= $sChar;

                        if ($bEscaped) {
                            $bEscaped = false;
                            continue;
                        }

                        if ($sQuote !== '`' && $sChar === '\\') {
                            $bEscaped = true;
                            continue;
                        }

                        if ($sChar === $sQuote) {
                            if ($sQuote === '`' && $i + 1 < $iLen && $sLine[$i + 1] === '`') {
                                $sBuffer .= $sLine[++$i];
                                continue;
                            }
                            $sQuote = '';
                        }

                        continue;
                    }

                    if ($sChar === "'" || $sChar === '"' || $sChar === '`') {
                        $sQuote = $sChar;
                        $sBuffer .= $sChar;
                        continue;
                    }

                    if ($sChar === ';') {
                        $sStatement = trim($sBuffer);
                        $sBuffer = '';

                        if ($sStatement === '')
                            continue;

                        $mResult = $this->_oDb->query($sStatement);
                        if ($mResult === false) {
                            if (preg_match('/^\s*CREATE\b.*\bVIEW\b/is', $sStatement)) {
                                $aDeferredViews[] = $sStatement;
                                continue;
                            }

                            throw new RuntimeException(
                                'Database restore failed near: ' .
                                substr(preg_replace('/\s+/', ' ', $sStatement), 0, 220)
                            );
                        }

                        $iExecuted++;
                        continue;
                    }

                    $sBuffer .= $sChar;
                }
            }

            if (trim($sBuffer) !== '') {
                $mResult = $this->_oDb->query(trim($sBuffer));
                if ($mResult === false)
                    throw new RuntimeException('Database restore ended with an invalid SQL statement.');
                $iExecuted++;
            }
        }
        finally {
            fclose($h);
        }

        $iRetries = 0;
        if ($aDeferredViews) {
            $aPending = $aDeferredViews;

            for ($iPass = 0; $iPass < 5 && $aPending; $iPass++) {
                $aNext = array();
                $iProgress = 0;

                foreach ($aPending as $sStatement) {
                    $mResult = $this->_oDb->query($sStatement);
                    if ($mResult === false) {
                        $aNext[] = $sStatement;
                        continue;
                    }

                    $iExecuted++;
                    $iRetries++;
                    $iProgress++;
                }

                $aPending = $aNext;
                if ($iProgress === 0)
                    break;
            }

            if ($aPending) {
                throw new RuntimeException(
                    'Database tables restored, but ' . count($aPending) .
                    ' view definition(s) could not be recreated.'
                );
            }
        }

        return array(
            'executed' => $iExecuted,
            'view_retries' => $iRetries,
        );
    }

    protected function _preserveCurrentToolRegistration()
    {
        $aCurrent = $this->_oDb->getRow(
            "SELECT * FROM `sys_modules` WHERE `name` = 'my_una_world' LIMIT 1"
        );

        if (!$aCurrent)
            throw new RuntimeException('Restored database no longer contains the My UNA World module registration.');

        $this->_oDb->query(
            "UPDATE `sys_modules`
             SET `version` = :version,
                 `enabled` = 1,
                 `path` = 'newton/my_una_world/',
                 `uri` = 'my_una_world',
                 `class_prefix` = 'MyUnaWorld',
                 `db_prefix` = 'my_una_world_',
                 `vendor` = 'MyUNAWorld'
             WHERE `name` = 'my_una_world'",
            array('version' => self::CURRENT_TOOL_VERSION)
        );
    }

    protected function _ensureHistoryRecord(array $aRecord)
    {
        if (empty($aRecord['archive_name']))
            return;

        $aExisting = $this->_oModule->getBackupByArchiveName($aRecord['archive_name']);
        if ($aExisting)
            return;

        $this->_oModule->addBackupHistory(array(
            'created_at' => isset($aRecord['created_at']) ? (int)$aRecord['created_at'] : time(),
            'mode' => isset($aRecord['mode']) ? (string)$aRecord['mode'] : 'full',
            'status' => isset($aRecord['status']) ? (string)$aRecord['status'] : 'complete',
            'archive_path' => isset($aRecord['archive_path']) ? (string)$aRecord['archive_path'] : '',
            'archive_name' => (string)$aRecord['archive_name'],
            'archive_size' => isset($aRecord['archive_size']) ? (int)$aRecord['archive_size'] : 0,
            'sha256' => isset($aRecord['sha256']) ? (string)$aRecord['sha256'] : '',
            'modules_count' => isset($aRecord['modules_count']) ? (int)$aRecord['modules_count'] : 0,
            'database_tables' => isset($aRecord['database_tables']) ? (int)$aRecord['database_tables'] : 0,
            'files_count' => isset($aRecord['files_count']) ? (int)$aRecord['files_count'] : 0,
            'bytes_source' => isset($aRecord['bytes_source']) ? (int)$aRecord['bytes_source'] : 0,
            'message' => isset($aRecord['message']) ? (string)$aRecord['message'] : '',
        ));
    }

    protected function _clearUnaCaches()
    {
        foreach (array('cache', 'cache_public') as $sDir) {
            $sPath = $this->_sSiteRoot . DIRECTORY_SEPARATOR . $sDir;
            if (!is_dir($sPath))
                continue;

            $oIterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sPath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($oIterator as $oFile) {
                if ($oFile->isDir() && !$oFile->isLink())
                    @rmdir($oFile->getPathname());
                else
                    @unlink($oFile->getPathname());
            }
        }
    }

    protected function _versionMajor($sVersion)
    {
        return preg_match('/(\d+)/', (string)$sVersion, $aMatch) ? (int)$aMatch[1] : 0;
    }

    protected function _majorMinor($sVersion)
    {
        return preg_match('/(\d+)\.(\d+)/', (string)$sVersion, $aMatch)
            ? $aMatch[1] . '.' . $aMatch[2]
            : (string)$sVersion;
    }

    protected function _writeJson($sPath, array $aData)
    {
        $sJson = json_encode(
            $aData,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if (!is_string($sJson) || file_put_contents($sPath, $sJson . PHP_EOL) === false)
            throw new RuntimeException('Unable to write restore report: ' . $sPath);
    }
}
