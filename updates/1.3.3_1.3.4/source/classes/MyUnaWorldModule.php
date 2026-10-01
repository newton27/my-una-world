<?php defined('BX_DOL') or die('hack attempt');

class MyUnaWorldModule extends BxDolModule
{
    protected function _getVisitorGatePath()
    {
        if (!isset($_SERVER['REQUEST_URI']) || !is_string($_SERVER['REQUEST_URI']))
            return null;

        try {
            $sPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        }
        catch (Throwable $e) {
            return null;
        }

        if (!is_string($sPath) || $sPath === '')
            return null;

        if ($sPath[0] !== '/')
            $sPath = '/' . $sPath;

        if ($sPath !== '/' && substr($sPath, -1) === '/' && substr($sPath, -2) !== '//') {
            $sWithoutSlash = substr($sPath, 0, -1);
            if (strpos(basename($sWithoutSlash), '.') === false)
                $sPath = $sWithoutSlash;
        }

        return $sPath;
    }

    protected function _isVisitorGatePublicPath($sPath)
    {
        return in_array($sPath, array(
            '/',
            '/login',
            '/member.php',
            '/create-account',
            '/forgot-password',
            '/terms',
            '/privacy',
        ), true);
    }

    protected function _shouldVisitorGateInspectRequest()
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || !is_string($_SERVER['REQUEST_METHOD']))
            return false;

        $sMethod = strtoupper($_SERVER['REQUEST_METHOD']);
        if ($sMethod !== 'GET' && $sMethod !== 'HEAD')
            return false;

        try {
            if (function_exists('bx_is_dynamic_request') && bx_is_dynamic_request())
                return false;
            if (function_exists('bx_is_htmx_request') && bx_is_htmx_request())
                return false;
            if (function_exists('bx_is_api') && bx_is_api())
                return false;
        }
        catch (Throwable $e) {
            return false;
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            is_string($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0)
            return false;

        $bHasFetchMode = isset($_SERVER['HTTP_SEC_FETCH_MODE']);
        $bHasFetchDest = isset($_SERVER['HTTP_SEC_FETCH_DEST']);
        if ($bHasFetchMode || $bHasFetchDest) {
            if (!$bHasFetchMode || !$bHasFetchDest ||
                !is_string($_SERVER['HTTP_SEC_FETCH_MODE']) ||
                !is_string($_SERVER['HTTP_SEC_FETCH_DEST']))
                return false;

            return strcasecmp($_SERVER['HTTP_SEC_FETCH_MODE'], 'navigate') === 0 &&
                in_array(strtolower($_SERVER['HTTP_SEC_FETCH_DEST']), array('document', 'iframe', 'frame'), true);
        }

        if (!isset($_SERVER['HTTP_ACCEPT']) || !is_string($_SERVER['HTTP_ACCEPT']))
            return false;

        $bAcceptsHtml = false;
        foreach (explode(',', $_SERVER['HTTP_ACCEPT']) as $sMediaRange) {
            $aParts = explode(';', $sMediaRange);
            $sMediaType = strtolower(trim(array_shift($aParts)));
            if ($sMediaType !== 'text/html' && $sMediaType !== 'application/xhtml+xml')
                continue;

            $bHasQuality = false;
            $sQuality = '';
            foreach ($aParts as $sParameter) {
                $sParameter = trim($sParameter);
                if (preg_match('/^q\s*=\s*(.*)$/i', $sParameter, $aQuality)) {
                    if ($bHasQuality || !preg_match('/^(?:0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/', $aQuality[1]))
                        return false;

                    $bHasQuality = true;
                    $sQuality = $aQuality[1];
                }
                elseif (preg_match('/^q(?:\s|$)/i', $sParameter)) {
                    return false;
                }
            }

            if (!$bHasQuality || (float)$sQuality > 0)
                $bAcceptsHtml = true;
        }

        return $bAcceptsHtml;
    }

    protected function _redirectGuestToHome()
    {
        if (!defined('BX_DOL_URL_ROOT') || headers_sent())
            return;

        $sRoot = BX_DOL_URL_ROOT;
        if (!is_string($sRoot) || $sRoot === '' || preg_match('/[\r\n]/', $sRoot))
            return;

        try {
            $aRoot = parse_url($sRoot);
        }
        catch (Throwable $e) {
            return;
        }

        if ($aRoot === false)
            return;

        if ($sRoot[0] !== '/') {
            if (!isset($aRoot['scheme']) ||
                !in_array(strtolower($aRoot['scheme']), array('http', 'https'), true) ||
                !isset($aRoot['host']) || $aRoot['host'] === '')
                return;
        }

        header('Location: ' . $sRoot, true, 302);
        exit;
    }

    public function handleVisitorGate()
    {
        if (PHP_SAPI === 'cli')
            return;

        try {
            $aSettings = $this->getLifecycleSettings();
            if (empty($aSettings['visitor_gate_enabled']))
                return;
        }
        catch (Throwable $e) {
            // Missing or unavailable settings preserve the existing enabled gate.
        }

        if (!function_exists('isLogged'))
            return;

        try {
            $bLogged = isLogged();
        }
        catch (Throwable $e) {
            return;
        }

        if (!is_bool($bLogged) || $bLogged)
            return;

        if (!$this->_shouldVisitorGateInspectRequest())
            return;

        $sPath = $this->_getVisitorGatePath();
        if ($sPath === null)
            return;

        if ($this->_isVisitorGatePublicPath($sPath))
            return;

        $this->_redirectGuestToHome();
    }

    protected function _getBackupEngine()
    {
        $sBackupClass = BX_DIRECTORY_PATH_MODULES . 'newton/my_una_world/classes/MyUnaWorldBackup.php';
        if (!class_exists('MyUnaWorldBackup', false)) {
            if (!is_file($sBackupClass))
                throw new RuntimeException('My UNA World backup engine file is missing: ' . $sBackupClass);

            require_once($sBackupClass);
        }

        if (!class_exists('MyUnaWorldBackup', false))
            throw new RuntimeException('My UNA World backup engine could not be loaded.');

        return new MyUnaWorldBackup($this);
    }

    protected function _getImportEngine()
    {
        $sImportClass = BX_DIRECTORY_PATH_MODULES . 'newton/my_una_world/classes/MyUnaWorldImport.php';
        if (!class_exists('MyUnaWorldImport', false)) {
            if (!is_file($sImportClass))
                throw new RuntimeException('My UNA World import engine file is missing: ' . $sImportClass);

            require_once($sImportClass);
        }

        if (!class_exists('MyUnaWorldImport', false))
            throw new RuntimeException('My UNA World import engine could not be loaded.');

        return new MyUnaWorldImport($this, $this->_getBackupEngine());
    }

    protected function _getRestoreEngine()
    {
        $sRestoreClass = BX_DIRECTORY_PATH_MODULES . 'newton/my_una_world/classes/MyUnaWorldRestore.php';
        if (!class_exists('MyUnaWorldRestore', false)) {
            if (!is_file($sRestoreClass))
                throw new RuntimeException('My UNA World restore engine file is missing: ' . $sRestoreClass);

            require_once($sRestoreClass);
        }

        if (!class_exists('MyUnaWorldRestore', false))
            throw new RuntimeException('My UNA World restore engine could not be loaded.');

        return new MyUnaWorldRestore($this, $this->_getBackupEngine());
    }

    public function queueBackup($sMode = 'full', array $aContext = array())
    {
        $hLock = $this->_openQueueWorkerLock();
        if (!flock($hLock, LOCK_EX | LOCK_NB)) {
            fclose($hLock);
            throw new RuntimeException('The backup worker is busy. Try again after it finishes.');
        }

        try {
            return $this->_queueBackupWithLock($sMode, $aContext);
        }
        finally {
            flock($hLock, LOCK_UN);
            fclose($hLock);
        }
    }

    protected function _queueBackupWithLock($sMode = 'full', array $aContext = array())
    {
        $sMode = $sMode === 'inventory' ? 'inventory' : 'full';

        $aActive = $this->_oDb->getActiveJob();
        if ($aActive) {
            throw new RuntimeException(
                'A My UNA World background backup is already queued or running. Job #' .
                (int)$aActive['id'] . ' is currently ' . $aActive['status'] . '.'
            );
        }

        $aContext['trigger'] = isset($aContext['trigger'])
            ? (string)$aContext['trigger']
            : 'manual_background';

        $aContext['reason'] = isset($aContext['reason'])
            ? (string)$aContext['reason']
            : ($sMode === 'full'
                ? 'Manual full backup queued from UNA Studio.'
                : 'Manual inventory test queued from UNA Studio.');

        $iJobId = $this->_oDb->addJob($sMode, $aContext);
        $aJob = $this->_oDb->getJob($iJobId);

        return $aJob ?: array(
            'id' => $iJobId,
            'mode' => $sMode,
            'status' => 'queued',
            'progress' => 0,
            'stage' => 'Queued',
            'message' => 'Waiting for the UNA background worker.',
        );
    }

    protected function _openQueueWorkerLock()
    {
        $sPath = rtrim($this->getBackupRoot(), DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR . '.backup-queue-worker.lock';
        $hLock = @fopen($sPath, 'c');
        if (!$hLock)
            throw new RuntimeException('Unable to open backup queue lock.');
        return $hLock;
    }

    public function processPendingBackupJob()
    {
        $hLock = $this->_openQueueWorkerLock();
        if (!flock($hLock, LOCK_EX | LOCK_NB)) {
            fclose($hLock);
            return false;
        }

        try {
            return $this->_processPendingBackupJobWithLock();
        }
        finally {
            flock($hLock, LOCK_UN);
            fclose($hLock);
        }
    }

    protected function _processPendingBackupJobWithLock()
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        /*
         * A dead PHP/cron process should not leave Studio showing "running"
         * forever. Twelve hours is deliberately generous for very large sites.
         */
        $this->_oDb->failStaleJobs(time() - 43200);

        $aJob = $this->_oDb->getQueuedJob();
        if (!$aJob)
            return false;

        $iJobId = (int)$aJob['id'];

        $this->_oDb->updateJob($iJobId, array(
            'status' => 'running',
            'started_at' => time(),
            'progress' => 1,
            'stage' => 'Starting',
            'message' => 'Background worker claimed this backup job.',
        ));

        $aContext = json_decode((string)$aJob['context_json'], true);
        if (!is_array($aContext))
            $aContext = array();

        $aContext['job_id'] = $iJobId;

        try {
            $aResult = $this->createBackup((string)$aJob['mode'], $aContext);

            $sArchive = isset($aResult['archive_name']) ? (string)$aResult['archive_name'] : '';
            $aBackup = $sArchive !== '' ? $this->getBackupByArchiveName($sArchive) : false;

            $this->_oDb->updateJob($iJobId, array(
                'status' => 'complete',
                'finished_at' => time(),
                'progress' => 100,
                'stage' => 'Complete',
                'message' => 'Backup completed successfully and passed ZIP integrity validation.',
                'archive_name' => $sArchive,
                'backup_id' => $aBackup ? (int)$aBackup['id'] : 0,
            ));

            return $this->_oDb->getJob($iJobId);
        }
        catch (Throwable $e) {
            $this->_oDb->updateJob($iJobId, array(
                'status' => 'failed',
                'finished_at' => time(),
                'stage' => 'Failed',
                'message' => $e->getMessage(),
            ));

            return $this->_oDb->getJob($iJobId);
        }
    }

    public function updateBackupJobProgress($iJobId, $iProgress, $sStage, $sMessage = '')
    {
        $iJobId = (int)$iJobId;
        if ($iJobId <= 0)
            return false;

        return $this->_oDb->updateJob($iJobId, array(
            'progress' => max(0, min(99, (int)$iProgress)),
            'stage' => (string)$sStage,
            'message' => (string)$sMessage,
        ));
    }

    public function getBackupJob($iJobId)
    {
        return $this->_oDb->getJob((int)$iJobId);
    }

    public function getActiveBackupJob()
    {
        return $this->_oDb->getActiveJob();
    }

    public function getRecentBackupJobs($iLimit = 10)
    {
        return $this->_oDb->getRecentJobs($iLimit);
    }

    public function touchBackgroundWorker()
    {
        $sPath = rtrim($this->getBackupRoot(), DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR . '.background_worker.json';

        $sJson = json_encode(array(
            'last_run' => time(),
            'last_run_iso' => date('c'),
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (is_string($sJson)) {
            @file_put_contents($sPath, $sJson . PHP_EOL, LOCK_EX);
            @chmod($sPath, 0600);
        }
    }

    public function getBackgroundWorkerStatus()
    {
        $oDb = BxDolDb::getInstance();
        $bRegistered = (bool)$oDb->getOne(
            "SELECT COUNT(*) FROM `sys_cron_jobs` WHERE `name` = 'my_una_world_backup_worker'"
        );

        $sPath = rtrim($this->getBackupRoot(), DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR . '.background_worker.json';

        $iLastRun = 0;
        if (is_file($sPath)) {
            $aData = json_decode((string)@file_get_contents($sPath), true);
            if (is_array($aData) && !empty($aData['last_run']))
                $iLastRun = (int)$aData['last_run'];
        }

        return array(
            'registered' => $bRegistered,
            'last_run' => $iLastRun,
            'last_run_iso' => $iLastRun > 0 ? date('c', $iLastRun) : '',
            'heartbeat_age_seconds' => $iLastRun > 0 ? max(0, time() - $iLastRun) : null,
            'healthy' => $bRegistered && $iLastRun > 0 && (time() - $iLastRun) <= 180,
        );
    }

    public function createBackup($sMode = 'inventory', array $aContext = array())
    {
        $aResult = $this->_getBackupEngine()->run($sMode, $aContext);

        /*
         * Retention is intentionally skipped for pre-restore/rollback safety
         * snapshots until the restore operation has completed. This prevents
         * an aggressive retention policy from pruning a source or safety
         * archive while recovery is in progress.
         */
        if (empty($aContext['retention_skip'])) {
            try {
                $aResult['retention'] = $this->applyBackupRetention(false);
            }
            catch (Throwable $e) {
                $aResult['retention'] = array(
                    'applied' => false,
                    'error' => $e->getMessage(),
                );
            }
        }

        return $aResult;
    }

    public function getLifecycleSettings()
    {
        $aDefaults = array(
            'visitor_gate_enabled' => true,
            'backup_before_install' => true,
            'backup_before_uninstall' => true,
            'block_on_failure' => true,
            'maintenance_show_header' => true,
            'maintenance_show_footer' => true,
            'maintenance_heading' => 'Scheduled Maintenance in Progress',
            'maintenance_message' => 'Our site is temporarily unavailable while My UNA World performs a verified maintenance workflow. The public site will return automatically when maintenance is complete.',
            'maintenance_patience' => 'We appreciate your patience.',
            'maintenance_return_message' => 'Estimated return time is counting down live.',
            'maintenance_footer_message' => 'Thank you for your patience while the verified workflow completes.',
            'use_maintenance_page' => true,
            'upgrade_dedupe_seconds' => 900,
            'retention_max_archives' => 0,
            'retention_max_age_days' => 0,
        );

        $sPath = $this->_getLifecycleSettingsPath();
        if (!is_file($sPath))
            return $aDefaults;

        $sJson = @file_get_contents($sPath);
        $aSaved = is_string($sJson) ? json_decode($sJson, true) : null;
        if (!is_array($aSaved))
            return $aDefaults;

        return array(
            'visitor_gate_enabled' => !isset($aSaved['visitor_gate_enabled']) || !empty($aSaved['visitor_gate_enabled']),
            'backup_before_install' => !empty($aSaved['backup_before_install']),
            'backup_before_uninstall' => !empty($aSaved['backup_before_uninstall']),
            'block_on_failure' => !empty($aSaved['block_on_failure']),
            'maintenance_show_header' => !isset($aSaved['maintenance_show_header']) || !empty($aSaved['maintenance_show_header']),
            'maintenance_show_footer' => !isset($aSaved['maintenance_show_footer']) || !empty($aSaved['maintenance_show_footer']),
            'maintenance_heading' => isset($aSaved['maintenance_heading']) ? (string)$aSaved['maintenance_heading'] : $aDefaults['maintenance_heading'],
            'maintenance_message' => isset($aSaved['maintenance_message']) ? (string)$aSaved['maintenance_message'] : $aDefaults['maintenance_message'],
            'maintenance_patience' => isset($aSaved['maintenance_patience']) ? (string)$aSaved['maintenance_patience'] : $aDefaults['maintenance_patience'],
            'maintenance_return_message' => isset($aSaved['maintenance_return_message']) ? (string)$aSaved['maintenance_return_message'] : $aDefaults['maintenance_return_message'],
            'maintenance_footer_message' => isset($aSaved['maintenance_footer_message']) ? (string)$aSaved['maintenance_footer_message'] : $aDefaults['maintenance_footer_message'],
            'use_maintenance_page' => !isset($aSaved['use_maintenance_page']) || !empty($aSaved['use_maintenance_page']),
            'upgrade_dedupe_seconds' => isset($aSaved['upgrade_dedupe_seconds'])
                ? max(60, min(3600, (int)$aSaved['upgrade_dedupe_seconds']))
                : 900,
            'retention_max_archives' => isset($aSaved['retention_max_archives'])
                ? max(0, min(1000, (int)$aSaved['retention_max_archives']))
                : 0,
            'retention_max_age_days' => isset($aSaved['retention_max_age_days'])
                ? max(0, min(3650, (int)$aSaved['retention_max_age_days']))
                : 0,
        );
    }

    public function saveLifecycleSettings(array $aInput)
    {
        $aCurrent = $this->getLifecycleSettings();
        $aSettings = array(
            // Visitor Gate is intentionally not exposed on the Settings page.
            // Preserve its current state when ordinary settings are saved.
            'visitor_gate_enabled' => array_key_exists('visitor_gate_enabled', $aInput)
                ? !empty($aInput['visitor_gate_enabled'])
                : !empty($aCurrent['visitor_gate_enabled']),
            'backup_before_install' => !empty($aInput['backup_before_install']),
            'backup_before_uninstall' => !empty($aInput['backup_before_uninstall']),
            'block_on_failure' => !empty($aInput['block_on_failure']),
            'maintenance_show_header' => !empty($aInput['maintenance_show_header']),
            'maintenance_show_footer' => !empty($aInput['maintenance_show_footer']),
            'maintenance_heading' => isset($aInput['maintenance_heading']) ? trim((string)$aInput['maintenance_heading']) : $aCurrent['maintenance_heading'],
            'maintenance_message' => isset($aInput['maintenance_message']) ? trim((string)$aInput['maintenance_message']) : $aCurrent['maintenance_message'],
            'maintenance_patience' => isset($aInput['maintenance_patience']) ? trim((string)$aInput['maintenance_patience']) : $aCurrent['maintenance_patience'],
            'maintenance_return_message' => isset($aInput['maintenance_return_message']) ? trim((string)$aInput['maintenance_return_message']) : $aCurrent['maintenance_return_message'],
            'maintenance_footer_message' => isset($aInput['maintenance_footer_message']) ? trim((string)$aInput['maintenance_footer_message']) : $aCurrent['maintenance_footer_message'],
            'use_maintenance_page' => !empty($aInput['use_maintenance_page']),
            'upgrade_dedupe_seconds' => $aCurrent['upgrade_dedupe_seconds'],
            'retention_max_archives' => isset($aInput['retention_max_archives'])
                ? max(0, min(1000, (int)$aInput['retention_max_archives']))
                : 0,
            'retention_max_age_days' => isset($aInput['retention_max_age_days'])
                ? max(0, min(3650, (int)$aInput['retention_max_age_days']))
                : 0,
            'updated_at' => date('c'),
        );

        $sJson = json_encode(
            $aSettings,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $sPath = $this->_getLifecycleSettingsPath();
        if (!is_string($sJson) || @file_put_contents($sPath, $sJson . PHP_EOL, LOCK_EX) === false)
            throw new RuntimeException('Unable to save My UNA World settings.');

        @chmod($sPath, 0600);
        return $aSettings;
    }

    public function serviceGetLifecycleStudioNotice()
    {
        if (empty($_SERVER['REQUEST_URI']))
            return '';

        $sUri = (string)$_SERVER['REQUEST_URI'];

        $bStudioModules =
            strpos($sUri, '/studio/store.php') !== false ||
            strpos($sUri, '/studio/module.php') !== false;

        if (!$bStudioModules)
            return '';

        if (strpos($sUri, 'module.php?name=my_una_world') !== false)
            return '';

        $aSettings = $this->getLifecycleSettings();

        $bInstall = !empty($aSettings['backup_before_install']);
        $bRemove = !empty($aSettings['backup_before_uninstall']);

        if (!$bInstall && !$bRemove)
            return '';

        if ($bInstall && $bRemove) {
            $sTitle = 'My UNA World safety backups are enabled';
            $sText = 'Installing, updating, or removing a module will first create a full site safety backup. The module operation continues only after the backup completes successfully.';
        }
        elseif ($bInstall) {
            $sTitle = 'My UNA World install safety backup is enabled';
            $sText = 'Installing a module will first create a full site safety backup. The installation continues only after the backup completes successfully.';
        }
        else {
            $sTitle = 'My UNA World update/removal safety backup is enabled';
            $sText = 'Updating or removing a module will first create a full site safety backup. The module operation continues only after the backup completes successfully.';
        }

        $sNotice =
            '<div id="my-una-world-lifecycle-warning" ' .
            'style="margin:0 0 16px 0;padding:14px 16px;border:1px solid #d6a600;' .
            'border-radius:6px;background:#fff8d8;color:#5c4900;line-height:1.45">' .
            '<div style="font-weight:700;margin-bottom:4px">' .
            htmlspecialchars($sTitle, ENT_QUOTES, 'UTF-8') .
            '</div>' .
            '<div>' .
            htmlspecialchars($sText, ENT_QUOTES, 'UTF-8') .
            '</div>' .
            '</div>';

        $sScript =
            '<script>' .
            'document.addEventListener("DOMContentLoaded",function(){' .
            'var n=document.getElementById("my-una-world-lifecycle-warning");' .
            'if(!n)return;' .
            'var p=document.querySelector("#bx-std-pc-content");' .
            'if(p&&n.parentNode!==p)p.insertBefore(n,p.firstChild);' .

            'var muwInstall=' . ($bInstall ? 'true' : 'false') . ';' .
            'var muwRemove=' . ($bRemove ? 'true' : 'false') . ';' .

            'function muwStatus(title,text,busy){' .
            'var x=document.getElementById("my-una-world-lifecycle-warning");' .
            'if(!x)return;' .
            'var d=x.getElementsByTagName("div");' .
            'if(d.length>0)d[0].textContent=title;' .
            'if(d.length>1)d[1].textContent=text;' .
            'x.style.opacity=busy?"1":"0.9";' .
            '}' .

            'if(window.BxDolStudioStore&&BxDolStudioStore.prototype.perform){' .
            'var op=BxDolStudioStore.prototype.perform;' .
            'BxDolStudioStore.prototype.perform=function(t,v,ok,input){' .
            'if(t==="install"&&muwInstall){' .
            'muwStatus("Creating My UNA World safety backup","A full safety backup is being created before this module installation. UNA will continue automatically after the backup succeeds.",true);' .
            '}' .
            'if(t==="update"&&muwRemove){' .
            'muwStatus("Creating My UNA World safety backup","A full safety backup is being created before this module update. UNA will continue automatically after the backup succeeds.",true);' .
            '}' .
            'return op.call(this,t,v,ok,input);' .
            '};' .
            '}' .

            'if(window.BxDolStudioStore&&BxDolStudioStore.prototype.getUpdateAndInstall){' .
            'var og=BxDolStudioStore.prototype.getUpdateAndInstall;' .
            'BxDolStudioStore.prototype.getUpdateAndInstall=function(name,button){' .
            'if(muwRemove){' .
            'muwStatus("Creating My UNA World safety backup","A full safety backup is being created before this module update. UNA will continue automatically after the backup succeeds.",true);' .
            '}' .
            'return og.call(this,name,button);' .
            '};' .
            '}' .

            'if(muwRemove&&window.BxDolStudioModule&&BxDolStudioModule.prototype.uninstall){' .
            'var ou=BxDolStudioModule.prototype.uninstall;' .
            'BxDolStudioModule.prototype.uninstall=function(name,wid,confirmed){' .
            'if(parseInt(confirmed)===1&&muwRemove){' .
            'muwStatus("Creating My UNA World safety backup","A full safety backup is being created before this module removal. UNA will continue automatically after the backup succeeds.",true);' .
            '}' .
            'return ou.call(this,name,wid,confirmed);' .
            '};' .
            '}' .

            'if(window.jQuery){' .
            'jQuery(document).ajaxComplete(function(e,x,o){' .
            'var data=typeof o.data==="string"?o.data:"";' .
            'var lifecycle=' .
            '(muwInstall&&data.indexOf("str_action=install")!==-1)||' .
            '(muwRemove&&data.indexOf("str_action=update")!==-1)||' .
            '(muwRemove&&data.indexOf("str_action=get-update-and-install")!==-1)||' .
            '(muwRemove&&data.indexOf("_action=uninstall")!==-1);' .
            'if(!lifecycle)return;' .
            'var r=x.responseJSON;' .
            'if(r&&parseInt(r.code)===0){' .
            'muwStatus("My UNA World safety operation completed","The protected module operation completed successfully.",false);' .
            '}else if(r&&parseInt(r.code)===2){' .
            'muwStatus("My UNA World safety operation queued","UNA accepted the protected module operation and queued further processing.",false);' .
            '}else{' .
            'muwStatus("Protected module operation did not complete","Check the UNA notification for the reason. The safety policy remains active.",false);' .
            '}' .
            '});' .
            '}' .

            '});' .
            '</script>';

        return $sNotice . $sScript;
    }
    public function getLifecycleStatus()
    {
        $oDb = BxDolDb::getInstance();

        return array(
            'settings' => $this->getLifecycleSettings(),
            'settings_file_exists' => is_file($this->_getLifecycleSettingsPath()),
            'alerts_handler_registered' => (bool)$oDb->getOne(
                "SELECT COUNT(*) FROM `sys_alerts_handlers`
                 WHERE `name` = 'my_una_world_lifecycle'"
            ),
            'before_install_hook_registered' => (bool)$oDb->getOne(
                "SELECT COUNT(*)
                 FROM `sys_alerts` a
                 INNER JOIN `sys_alerts_handlers` h ON h.`id` = a.`handler_id`
                 WHERE h.`name` = 'my_una_world_lifecycle'
                   AND a.`unit` = 'system'
                   AND a.`action` = 'before_install'"
            ),
            'before_uninstall_hook_registered' => (bool)$oDb->getOne(
                "SELECT COUNT(*)
                 FROM `sys_alerts` a
                 INNER JOIN `sys_alerts_handlers` h ON h.`id` = a.`handler_id`
                 WHERE h.`name` = 'my_una_world_lifecycle'
                   AND a.`unit` = 'system'
                   AND a.`action` = 'before_uninstall'"
            ),
        );
    }

    public function handleModuleLifecycleAlert($oAlert)
    {
        if (!$oAlert || $oAlert->sUnit !== 'system')
            return;

        $sAction = (string)$oAlert->sAction;
        if ($sAction !== 'before_install' && $sAction !== 'before_uninstall')
            return;

        $aConfig = isset($oAlert->aExtras['config']) && is_array($oAlert->aExtras['config'])
            ? $oAlert->aExtras['config']
            : array();

        $sName = isset($aConfig['name']) ? (string)$aConfig['name'] : '';
        $sUri = isset($aConfig['home_uri']) ? (string)$aConfig['home_uri'] : '';
        $sTitle = isset($aConfig['title']) ? (string)$aConfig['title'] : ($sName !== '' ? $sName : $sUri);
        $sVersion = isset($aConfig['version']) ? (string)$aConfig['version'] : '';

        // Never recursively back up My UNA World while it is being upgraded.
        if ($sName === 'my_una_world' || $sUri === 'my_una_world')
            return;

        $aSettings = $this->getLifecycleSettings();
        $sKey = $sName !== '' ? $sName : ($sUri !== '' ? $sUri : 'unknown_module');

        if ($sAction === 'before_install') {
            if (empty($aSettings['backup_before_install']))
                return;

            // UNA update = uninstall then install. Consume the pre-update marker.
            if ($this->_consumeRecentLifecycleMarker($sKey, (int)$aSettings['upgrade_dedupe_seconds']))
                return;
        }
        else {
            if (empty($aSettings['backup_before_uninstall']))
                return;
        }

        $sReason = $sAction === 'before_uninstall'
            ? 'Automatic safety backup before module removal/update: ' . $sTitle
            : 'Automatic safety backup before module install: ' . $sTitle;

        if ($sVersion !== '')
            $sReason .= ' ' . $sVersion;

        try {
            $this->createBackup('full', array(
                'trigger' => 'module_lifecycle',
                'lifecycle_action' => $sAction,
                'module_name' => $sName,
                'module_uri' => $sUri,
                'module_title' => $sTitle,
                'module_version' => $sVersion,
                'reason' => $sReason,
            ));

            if ($sAction === 'before_uninstall')
                $this->_writeLifecycleMarker($sKey);
        }
        catch (Throwable $e) {
            if (!empty($aSettings['block_on_failure']) &&
                isset($oAlert->aExtras) &&
                is_array($oAlert->aExtras)) {
                /*
                 * UNA passes this result slot by reference. A false result
                 * aborts the module action before the installer proceeds.
                 */
                $oAlert->aExtras['result'] = array(
                    'result' => false,
                    'message' => 'My UNA World blocked this module operation because its automatic safety backup failed: ' .
                        $e->getMessage(),
                );
            }
        }
    }

    protected function _getLifecycleSettingsPath()
    {
        return rtrim($this->_getBackupEngine()->getBackupRoot(), DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR . '.module_lifecycle_settings.json';
    }

    protected function _getLifecycleMarkerPath($sKey)
    {
        return rtrim($this->_getBackupEngine()->getBackupRoot(), DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR . '.lifecycle_' . sha1((string)$sKey) . '.json';
    }

    protected function _writeLifecycleMarker($sKey)
    {
        $sPath = $this->_getLifecycleMarkerPath($sKey);
        $sJson = json_encode(array(
            'module_key' => (string)$sKey,
            'created_at' => time(),
        ), JSON_UNESCAPED_SLASHES);

        if (is_string($sJson) && @file_put_contents($sPath, $sJson . PHP_EOL, LOCK_EX) !== false)
            @chmod($sPath, 0600);
    }

    protected function _consumeRecentLifecycleMarker($sKey, $iMaxAge)
    {
        $sPath = $this->_getLifecycleMarkerPath($sKey);
        if (!is_file($sPath))
            return false;

        $sJson = @file_get_contents($sPath);
        $aData = is_string($sJson) ? json_decode($sJson, true) : null;
        @unlink($sPath);

        if (!is_array($aData) || empty($aData['created_at']))
            return false;

        $iAge = time() - (int)$aData['created_at'];
        return $iAge >= 0 && $iAge <= max(60, (int)$iMaxAge);
    }

    public function scanBackupFolder()
    {
        return $this->_getImportEngine()->scan();
    }

    public function registerExistingBackup($sArchiveName)
    {
        return $this->_getImportEngine()->registerExisting($sArchiveName);
    }

    public function uploadExistingBackup(array $aZip, array $aVerify = array())
    {
        return $this->_getImportEngine()->uploadAndRegister($aZip, $aVerify);
    }

    public function getBackupRoot()
    {
        return $this->_getBackupEngine()->getBackupRoot();
    }

    public function applyBackupRetention($bManual = false)
    {
        $aSettings = $this->getLifecycleSettings();
        $iMax = (int)$aSettings['retention_max_archives'];
        $iDays = (int)$aSettings['retention_max_age_days'];

        $aResult = array(
            'applied' => true,
            'manual' => (bool)$bManual,
            'max_archives' => $iMax,
            'max_age_days' => $iDays,
            'deleted' => array(),
            'protected' => array(),
            'kept' => array(),
        );

        if ($iMax <= 0 && $iDays <= 0) {
            $aResult['applied'] = false;
            $aResult['reason'] = 'Retention is disabled.';
            return $aResult;
        }

        $aBackups = $this->getBackups(1000);
        if (!$aBackups)
            return $aResult;

        // Count only completed full archives that still exist.
        // Inventory reports and failed/missing records are not restore points.
        $sRetentionRoot = rtrim($this->getBackupRoot(), DIRECTORY_SEPARATOR);
        $aBackups = array_values(array_filter($aBackups, function($aBackup) use ($sRetentionRoot) {
            $sName = isset($aBackup['archive_name']) ? (string)$aBackup['archive_name'] : '';
            return isset($aBackup['status'], $aBackup['mode']) &&
                $aBackup['status'] === 'complete' &&
                $aBackup['mode'] === 'full' &&
                preg_match('/^my_una_world_full_[0-9]{8}_[0-9]{6}\\.zip$/D', $sName) &&
                !is_link($sRetentionRoot . DIRECTORY_SEPARATOR . $sName) &&
                is_file($sRetentionRoot . DIRECTORY_SEPARATOR . $sName);
        }));

        /*
         * Newest first. The database history already returns newest first in
         * current builds, but sort explicitly so retention is deterministic.
         */
        usort($aBackups, function($a, $b) {
            $iA = isset($a['created_at']) ? (int)$a['created_at'] : 0;
            $iB = isset($b['created_at']) ? (int)$b['created_at'] : 0;
            if ($iA === $iB)
                return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
            return $iB <=> $iA;
        });

        $iNow = time();
        $iCutoff = $iDays > 0 ? $iNow - ($iDays * 86400) : 0;

        foreach ($aBackups as $iIndex => $aBackup) {
            $sName = isset($aBackup['archive_name']) ? (string)$aBackup['archive_name'] : '';
            if ($sName === '')
                continue;

            $aProtection = $this->getBackupProtection($sName);
            if (!empty($aProtection['protected'])) {
                $aResult['protected'][] = array(
                    'archive_name' => $sName,
                    'report_name' => isset($aProtection['report_name']) ? $aProtection['report_name'] : '',
                );
                continue;
            }

            $bOverCount = $iMax > 0 && $iIndex >= $iMax;
            $bTooOld = $iDays > 0 &&
                isset($aBackup['created_at']) &&
                (int)$aBackup['created_at'] > 0 &&
                (int)$aBackup['created_at'] < $iCutoff;

            if (!$bOverCount && !$bTooOld) {
                $aResult['kept'][] = $sName;
                continue;
            }

            try {
                $aDeleted = $this->deleteBackup((int)$aBackup['id']);
                $aResult['deleted'][] = array(
                    'archive_name' => $sName,
                    'reason' => $bOverCount && $bTooOld
                        ? 'over maximum count and older than age limit'
                        : ($bOverCount ? 'over maximum count' : 'older than age limit'),
                    'files' => isset($aDeleted['deleted']) ? $aDeleted['deleted'] : array(),
                );
            }
            catch (Throwable $e) {
                $aResult['protected'][] = array(
                    'archive_name' => $sName,
                    'reason' => $e->getMessage(),
                );
            }
        }

        return $aResult;
    }

    public function inspectBackup($iId)
    {
        return $this->_getRestoreEngine()->inspect($iId);
    }

    public function dryRunRestore($iId)
    {
        return $this->_getRestoreEngine()->dryRun($iId);
    }

    public function restoreBackup($iId, $sConfirmation)
    {
        return $this->_getRestoreEngine()->restore($iId, $sConfirmation);
    }

    public function getRestoreReports($iLimit = 20)
    {
        return $this->_getRestoreEngine()->getRestoreReports($iLimit);
    }

    public function getRestoreReport($sReportName)
    {
        return $this->_getRestoreEngine()->getRestoreReport($sReportName);
    }

    public function verifyRestoreReport($sReportName)
    {
        return $this->_getRestoreEngine()->verifyRestoreReport($sReportName);
    }

    public function rollbackRestore($sReportName)
    {
        return $this->_getRestoreEngine()->rollback($sReportName);
    }

    public function deleteRestoreReport($sReportName)
    {
        return $this->_getRestoreEngine()->deleteRestoreReport($sReportName);
    }

    public function getBackupProtection($sArchiveName)
    {
        return $this->_getRestoreEngine()->getBackupProtection($sArchiveName);
    }

    public function deleteBackup($iId)
    {
        $iId = (int)$iId;
        if ($iId <= 0)
            throw new InvalidArgumentException('Invalid backup ID.');

        $aBackup = $this->_oDb->getBackup($iId);
        if (!$aBackup)
            throw new RuntimeException('Backup history record was not found.');

        $sName = isset($aBackup['archive_name']) ? (string)$aBackup['archive_name'] : '';

        if ($sName !== '') {
            $aProtection = $this->getBackupProtection($sName);
            if (!empty($aProtection['protected'])) {
                throw new RuntimeException(
                    'This backup is protected as the rollback safety snapshot for ' .
                    $aProtection['report_name'] . '. Complete or supersede that recovery point before deleting it.'
                );
            }
        }

        $aDeleted = array(
            'archive' => false,
            'checksum' => false,
            'verification' => false,
        );

        if ($sName !== '') {
            if (basename($sName) !== $sName ||
                !preg_match('/^my_una_world_(?:inventory|full)_\d{8}_\d{6}\.zip$/D', $sName))
                throw new RuntimeException('Backup archive name failed the safety check.');

            $oBackup = $this->_getBackupEngine();
            $sRoot = realpath($oBackup->getBackupRoot());

            if ($sRoot === false || !is_dir($sRoot))
                throw new RuntimeException('Backup directory could not be resolved safely.');

            $sArchive = $sRoot . DIRECTORY_SEPARATOR . $sName;

            $aFiles = array(
                'archive' => $sArchive,
                'checksum' => $sArchive . '.sha256',
                'verification' => $sArchive . '.verify.json',
            );

            foreach ($aFiles as $sKey => $sPath) {
                if (!file_exists($sPath))
                    continue;

                $sRealParent = realpath(dirname($sPath));
                if ($sRealParent === false || $sRealParent !== $sRoot)
                    throw new RuntimeException('Refusing to delete a file outside the My UNA World backup directory.');

                if (!is_file($sPath))
                    throw new RuntimeException('Refusing to delete a non-file backup path: ' . basename($sPath));

                if (!@unlink($sPath))
                    throw new RuntimeException('Unable to delete backup file: ' . basename($sPath));

                $aDeleted[$sKey] = true;
            }
        }

        $this->_oDb->deleteBackup($iId);

        return array(
            'id' => $iId,
            'archive_name' => $sName,
            'deleted' => $aDeleted,
        );
    }

    public function addBackupHistory(array $aBackup)
    {
        return $this->_oDb->addBackup($aBackup);
    }

    public function getBackups($iLimit = 20)
    {
        return $this->_oDb->getBackups($iLimit);
    }

    public function getBackup($iId)
    {
        return $this->_oDb->getBackup($iId);
    }

    public function getBackupByArchiveName($sName)
    {
        return $this->_oDb->getBackupByArchiveName($sName);
    }
}