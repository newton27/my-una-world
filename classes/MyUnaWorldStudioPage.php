<?php defined('BX_DOL') or die('hack attempt');

class MyUnaWorldStudioPage extends BxTemplStudioModule
{
    protected $_sModuleName = 'my_una_world';
    protected $_oModule;
    protected $_bPostProcessed = false;
    protected $_aPostResult = null;
    protected $_sPostMessage = '';
    protected $_bPostSuccess = true;

    public function __construct($sModule, $mixedPageName, $sPage = '')
    {
        parent::__construct($sModule, $mixedPageName, $sPage);

        $this->_oModule = BxDolModule::getInstance($this->_sModuleName);

        $this->sPageDefault = 'overview';

        $this->aMenuItems = array(
            'overview' => array(
                'name' => 'overview',
                'icon' => 'home',
                'title' => '_my_una_world_overview',
            ),
            'backups' => array(
                'name' => 'backups',
                'icon' => 'database',
                'title' => '_my_una_world_backups',
            ),
            'recovery' => array(
                'name' => 'recovery',
                'icon' => 'life-ring',
                'title' => '_my_una_world_recovery',
            ),
            'settings' => array(
                'name' => 'settings',
                'icon' => 'cogs',
                'title' => '_my_una_world_settings',
            ),
        );
    }

    protected function getOverview()
    {
        $this->_processPost();

        $aBackups = $this->_oModule->getBackups(10);
        $aLatest = !empty($aBackups[0]) ? $aBackups[0] : array();

        $sContent = $this->_notice();
        $sContent .= $this->_backgroundJobPanel();

        $sContent .= '<div class="bx-def-padding">';
        $sContent .= '<h2>' . $this->_h(_t('_my_una_world')) . '</h2>';
        $sContent .= '<p>' . $this->_h(_t('_my_una_world_desc')) . '</p>';

        $sContent .= '<div class="bx-def-margin-top">';
        $sContent .= '<strong>' . $this->_h(_t('_my_una_world_version')) . ':</strong> 1.3.2<br>';
        $sContent .= '<strong>UNA:</strong> ' . $this->_h(function_exists('bx_get_ver') ? bx_get_ver() : '') . '<br>';
        $sContent .= '<strong>PHP:</strong> ' . $this->_h(PHP_VERSION) . '<br>';
        $sContent .= '<strong>' . $this->_h(_t('_my_una_world_backup_folder')) . ':</strong> <code>' .
            $this->_h($this->_oModule->getBackupRoot()) . '</code>';
        $sContent .= '</div>';

        if ($aLatest) {
            $sContent .= '<div class="bx-def-margin-top">';
            $sContent .= '<strong>' . $this->_h(_t('_my_una_world_latest_backup')) . ':</strong> ' .
                $this->_h($aLatest['archive_name']) . ' · ' . $this->_bytes($aLatest['archive_size']) .
                ' · ' . $this->_h($aLatest['status']);
            $sContent .= '</div>';
        }

        $sContent .= '<hr class="bx-def-hr bx-def-margin-sec-top bx-def-margin-sec-bottom">';

        $sContent .= '<h3>' . $this->_h(_t('_my_una_world_quick_actions')) . '</h3>';
        $sContent .= '<div class="bx-def-margin-top">';
        $sContent .= $this->_postButton('overview', 'backup_inventory', _t('_my_una_world_inventory_test'), false);
        $sContent .= ' ';
        $sContent .= $this->_postButton('overview', 'backup_full', _t('_my_una_world_full_backup'), true);
        $sContent .= '</div>';

        $sContent .= '<div class="bx-def-margin-top bx-def-font-small">';
        $sContent .= $this->_h(_t('_my_una_world_bootstrap_note'));
        $sContent .= '</div>';

        if (is_array($this->_aPostResult))
            $sContent .= $this->_jsonResult($this->_aPostResult);

        $sContent .= '</div>';

        return BxDolStudioTemplate::getInstance()->parseHtmlByName('module.html', array(
            'content' => $sContent,
        ));
    }

    protected function getBackups()
    {
        $this->_processPost();

        $aBackups = $this->_oModule->getBackups(100);
        $aReports = $this->_oModule->getRestoreReports(50);

        $aProtected = array();
        foreach ($aReports as $aReport) {
            if (!empty($aReport['safety_backup_protected']) && !empty($aReport['safety_backup']))
                $aProtected[$aReport['safety_backup']] = $aReport['report_name'];
        }

        $sContent = $this->_notice();
        $sContent .= $this->_backgroundJobPanel();
        $sContent .= '<div class="bx-def-padding">';
        $sContent .= '<h2>' . $this->_h(_t('_my_una_world_backups')) . '</h2>';
        $sContent .= '<p>' . $this->_h(_t('_my_una_world_backups_desc')) . '</p>';

        if (!$aBackups) {
            $sContent .= '<p class="bx-def-font-small">' . $this->_h(_t('_my_una_world_no_backups')) . '</p>';
        }
        else {
            $sContent .= '<div class="bx-def-margin-top" style="overflow:auto">';
            $sContent .= '<table style="width:100%;border-collapse:collapse">';
            $sContent .= '<thead><tr>';
            foreach (array('Date','Mode','Status','Size','Modules','DB objects','Files','Actions') as $sHead)
                $sContent .= '<th style="text-align:left;padding:8px;border-bottom:1px solid currentColor">' . $this->_h($sHead) . '</th>';
            $sContent .= '</tr></thead><tbody>';

            foreach ($aBackups as $a) {
                $bProtected = isset($aProtected[$a['archive_name']]);
                $sContent .= '<tr>';
                $sContent .= '<td style="padding:8px;vertical-align:top">' . $this->_h(date('Y-m-d H:i:s', (int)$a['created_at'])) . '</td>';
                $sContent .= '<td style="padding:8px;vertical-align:top">' . $this->_h($a['mode']) . '</td>';
                $sContent .= '<td style="padding:8px;vertical-align:top">' . $this->_h($a['status']) .
                    ($bProtected ? '<br><strong>rollback safety</strong>' : '') . '</td>';
                $sContent .= '<td style="padding:8px;vertical-align:top">' . $this->_bytes($a['archive_size']) . '</td>';
                $sContent .= '<td style="padding:8px;vertical-align:top">' . (int)$a['modules_count'] . '</td>';
                $sContent .= '<td style="padding:8px;vertical-align:top">' . (int)$a['database_tables'] . '</td>';
                $sContent .= '<td style="padding:8px;vertical-align:top">' . (int)$a['files_count'] . '</td>';
                $sContent .= '<td style="padding:8px;vertical-align:top;min-width:330px">';

                if ($a['status'] === 'complete') {
                    $sContent .= '<a class="bx-btn" href="' . $this->_h(BX_DOL_URL_ROOT .
                        'modules/newton/my_una_world/download.php?id=' . (int)$a['id']) . '">Download</a> ';

                    if (is_file((string)$a['archive_path'] . '.verify.json')) {
                        $sContent .= '<a class="bx-btn" href="' . $this->_h(BX_DOL_URL_ROOT .
                            'modules/newton/my_una_world/download.php?id=' . (int)$a['id'] . '&type=verify') .
                            '">Verify JSON</a> ';
                    }

                    $sContent .= $this->_postButton('backups', 'inspect', 'Inspect', false, array('backup_id' => (int)$a['id']));
                    $sContent .= ' ' . $this->_postButton('backups', 'dry_restore', 'Dry Run', false, array('backup_id' => (int)$a['id']));

                    if ($a['mode'] === 'full') {
                        $sContent .= '<form method="post" action="' . $this->_h($this->_pageUrl('backups')) .
                            '" style="display:inline-block;margin:3px 0" onsubmit="return this.confirmation.value===\'RESTORE\' && confirm(\'Restore this backup? A fresh full safety backup is created first.\');">';
                        $sContent .= $this->_hiddenCommon('restore');
                        $sContent .= '<input type="hidden" name="backup_id" value="' . (int)$a['id'] . '">';
                        $sContent .= '<input type="text" name="confirmation" value="" placeholder="RESTORE" autocomplete="off" style="width:90px"> ';
                        $sContent .= '<button class="bx-btn" type="submit">Restore</button>';
                        $sContent .= '</form> ';
                    }
                }

                if (!$bProtected) {
                    $sContent .= $this->_postButton(
                        'backups',
                        'delete_backup',
                        'Delete',
                        true,
                        array('backup_id' => (int)$a['id']),
                        'Delete this backup ZIP, sidecars, and history row?'
                    );
                }

                if (!empty($a['message']))
                    $sContent .= '<div class="bx-def-font-small bx-def-margin-sec-top">' . $this->_h($a['message']) . '</div>';

                $sContent .= '</td></tr>';
            }

            $sContent .= '</tbody></table></div>';
        }

        if (is_array($this->_aPostResult))
            $sContent .= $this->_jsonResult($this->_aPostResult);

        $sContent .= '</div>';

        return BxDolStudioTemplate::getInstance()->parseHtmlByName('module.html', array(
            'content' => $sContent,
        ));
    }

    protected function getRecovery()
    {
        $this->_processPost();

        $aScan = $this->_oModule->scanBackupFolder();
        $aReports = $this->_oModule->getRestoreReports(50);

        $sContent = $this->_notice();
        $sContent .= '<div class="bx-def-padding">';
        $sContent .= '<h2>' . $this->_h(_t('_my_una_world_recovery')) . '</h2>';
        $sContent .= '<p>' . $this->_h(_t('_my_una_world_recovery_desc')) . '</p>';

        $sContent .= '<h3 class="bx-def-margin-top">' . $this->_h(_t('_my_una_world_scan_folder')) . '</h3>';
        $sContent .= '<p class="bx-def-font-small">' .
            $this->_h(_t('_my_una_world_scan_folder_desc')) . '<br><code>' .
            $this->_h($this->_oModule->getBackupRoot()) . '</code></p>';

        if (!$aScan) {
            $sContent .= '<p class="bx-def-font-small">' . $this->_h(_t('_my_una_world_no_archives_found')) . '</p>';
        }
        else {
            $sContent .= '<div style="overflow:auto"><table style="width:100%;border-collapse:collapse">';
            $sContent .= '<thead><tr><th style="text-align:left;padding:8px">Archive</th><th style="text-align:left;padding:8px">Size</th><th style="text-align:left;padding:8px">Sidecars</th><th style="text-align:left;padding:8px">Status</th><th style="text-align:left;padding:8px">Action</th></tr></thead><tbody>';

            foreach ($aScan as $a) {
                $sContent .= '<tr>';
                $sContent .= '<td style="padding:8px">' . $this->_h($a['archive_name']) . '</td>';
                $sContent .= '<td style="padding:8px">' . $this->_bytes($a['archive_size']) . '</td>';
                $sContent .= '<td style="padding:8px">' .
                    ($a['verify_sidecar'] ? 'Verify JSON ' : '') .
                    ($a['checksum_sidecar'] ? 'SHA-256' : '') . '</td>';
                $sContent .= '<td style="padding:8px">' . ($a['registered'] ? 'Registered' : 'Not registered') . '</td>';
                $sContent .= '<td style="padding:8px">';
                if (!$a['registered']) {
                    $sContent .= $this->_postButton(
                        'recovery',
                        'register_existing',
                        'Register Backup',
                        false,
                        array('archive_name' => $a['archive_name'])
                    );
                }
                else {
                    $sContent .= '<a class="bx-btn" href="' . $this->_h($this->_pageUrl('backups')) . '">Open Backups</a>';
                }
                $sContent .= '</td></tr>';
            }

            $sContent .= '</tbody></table></div>';
        }

        $sContent .= '<h3 class="bx-def-margin-top">' . $this->_h(_t('_my_una_world_upload_backup')) . '</h3>';
        $sContent .= '<p class="bx-def-font-small">' . $this->_h(_t('_my_una_world_upload_backup_desc')) . '</p>';
        $sContent .= '<form method="post" enctype="multipart/form-data" action="' . $this->_h($this->_pageUrl('recovery')) . '">';
        $sContent .= $this->_hiddenCommon('upload_existing');
        $sContent .= '<div class="bx-def-margin-sec-bottom"><label><strong>Backup ZIP</strong><br><input type="file" name="backup_zip" accept=".zip,application/zip" required></label></div>';
        $sContent .= '<div class="bx-def-margin-sec-bottom"><label><strong>Verify JSON (optional)</strong><br><input type="file" name="backup_verify" accept=".json,application/json"></label></div>';
        $sContent .= '<button class="bx-btn" type="submit">Upload &amp; Register</button>';
        $sContent .= '</form>';

        $sContent .= '<h3 class="bx-def-margin-top">' . $this->_h(_t('_my_una_world_restore_reports')) . '</h3>';

        if (!$aReports) {
            $sContent .= '<p class="bx-def-font-small">' . $this->_h(_t('_my_una_world_no_restore_reports')) . '</p>';
        }
        else {
            $sContent .= '<div style="overflow:auto"><table style="width:100%;border-collapse:collapse">';
            $sContent .= '<thead><tr><th style="text-align:left;padding:8px">Date</th><th style="text-align:left;padding:8px">Operation</th><th style="text-align:left;padding:8px">Status</th><th style="text-align:left;padding:8px">Source</th><th style="text-align:left;padding:8px">Safety backup</th><th style="text-align:left;padding:8px">Verification</th><th style="text-align:left;padding:8px">Actions</th></tr></thead><tbody>';

            foreach ($aReports as $r) {
                $sContent .= '<tr>';
                $sContent .= '<td style="padding:8px;vertical-align:top">' . $this->_h($r['started_at'] ?: $r['report_name']) . '</td>';
                $sContent .= '<td style="padding:8px;vertical-align:top">' . $this->_h($r['operation']) . '</td>';
                $sContent .= '<td style="padding:8px;vertical-align:top">' . $this->_h($r['status']) . '</td>';
                $sContent .= '<td style="padding:8px;vertical-align:top">' . $this->_h($r['source_archive']) . '</td>';
                $sContent .= '<td style="padding:8px;vertical-align:top">' . $this->_h($r['safety_backup']) .
                    ($r['safety_backup_protected'] ? '<br><strong>Protected rollback safety</strong>' : '') . '</td>';
                $sContent .= '<td style="padding:8px;vertical-align:top">' .
                    ($r['verification_passed'] === true ? 'PASSED' : ($r['verification_passed'] === false ? 'FAILED' : 'Not run')) . '</td>';
                $sContent .= '<td style="padding:8px;vertical-align:top;min-width:300px">';
                $sContent .= '<a class="bx-btn" href="' . $this->_h(BX_DOL_URL_ROOT .
                    'modules/newton/my_una_world/report.php?name=' . rawurlencode($r['report_name'])) . '">Report JSON</a> ';
                $sContent .= $this->_postButton(
                    'recovery',
                    'verify_report',
                    'Verify Current State',
                    false,
                    array('report_name' => $r['report_name'])
                );
                $sContent .= ' ' . $this->_postButton(
                    'recovery',
                    'delete_report',
                    'Delete Report',
                    true,
                    array('report_name' => $r['report_name']),
                    'Delete only this report JSON? Backup ZIP files will not be deleted.'
                );

                if ($r['can_rollback']) {
                    $sContent .= ' ' . $this->_postButton(
                        'recovery',
                        'rollback',
                        'Rollback to Safety Backup',
                        true,
                        array('report_name' => $r['report_name']),
                        'Rollback the latest restore to its protected safety backup? Another full safety backup is created first.'
                    );
                }

                $sContent .= '<div class="bx-def-font-small bx-def-margin-sec-top"><code>' .
                    $this->_h($r['report_name']) . '</code></div>';
                $sContent .= '</td></tr>';
            }

            $sContent .= '</tbody></table></div>';
        }

        if (is_array($this->_aPostResult))
            $sContent .= $this->_jsonResult($this->_aPostResult);

        $sContent .= '</div>';

        return BxDolStudioTemplate::getInstance()->parseHtmlByName('module.html', array(
            'content' => $sContent,
        ));
    }

    protected function getSettings()
    {
        $this->_processPost();

        $aStatus = $this->_oModule->getLifecycleStatus();
        $aSettings = $aStatus['settings'];

        $sContent = $this->_notice();
        $sContent .= '<div class="bx-def-padding">';
        $sContent .= '<h2>' . $this->_h(_t('_my_una_world_settings')) . '</h2>';
        $sContent .= '<form method="post" action="' . $this->_h($this->_pageUrl('settings')) . '">';
        $sContent .= $this->_hiddenCommon('save_settings');

        $sContent .= $this->_checkbox(
            'backup_before_install',
            _t('_my_una_world_backup_before_install'),
            !empty($aSettings['backup_before_install']),
            _t('_my_una_world_backup_before_install_desc')
        );

        $sContent .= $this->_checkbox(
            'backup_before_uninstall',
            _t('_my_una_world_backup_before_uninstall'),
            !empty($aSettings['backup_before_uninstall']),
            _t('_my_una_world_backup_before_uninstall_desc')
        );

        $sContent .= $this->_checkbox(
            'block_on_failure',
            _t('_my_una_world_block_on_failure'),
            !empty($aSettings['block_on_failure']),
            _t('_my_una_world_block_on_failure_desc')
        );

        $sContent .= '<hr class="bx-def-hr bx-def-margin-sec-top bx-def-margin-sec-bottom">';
        $sContent .= '<h3>' . $this->_h(_t('_my_una_world_retention')) . '</h3>';
        $sContent .= '<p class="bx-def-font-small">' .
            $this->_h(_t('_my_una_world_retention_desc')) . '</p>';

        $sContent .= '<div class="bx-def-margin-bottom"><label><strong>' .
            $this->_h(_t('_my_una_world_retention_max_archives')) .
            '</strong><br><input type="number" min="0" max="1000" step="1" name="retention_max_archives" value="' .
            (int)$aSettings['retention_max_archives'] . '" style="max-width:160px"></label><br>' .
            '<span class="bx-def-font-small">' .
            $this->_h(_t('_my_una_world_retention_max_archives_desc')) .
            '</span></div>';

        $sContent .= '<div class="bx-def-margin-bottom"><label><strong>' .
            $this->_h(_t('_my_una_world_retention_max_age_days')) .
            '</strong><br><input type="number" min="0" max="3650" step="1" name="retention_max_age_days" value="' .
            (int)$aSettings['retention_max_age_days'] . '" style="max-width:160px"></label><br>' .
            '<span class="bx-def-font-small">' .
            $this->_h(_t('_my_una_world_retention_max_age_days_desc')) .
            '</span></div>';

        $sContent .= '<button class="bx-btn bx-def-margin-top" type="submit">' .
            $this->_h(_t('_my_una_world_save_settings')) . '</button>';
        $sContent .= '</form>';

        $sContent .= '<form method="post" action="' . $this->_h($this->_pageUrl('settings')) .
            '" class="bx-def-margin-top" onsubmit="return confirm(\'Run the configured retention cleanup now? Protected rollback safety backups will not be deleted.\');">';
        $sContent .= $this->_hiddenCommon('run_retention');
        $sContent .= '<button class="bx-btn" type="submit">' .
            $this->_h(_t('_my_una_world_run_cleanup')) . '</button>';
        $sContent .= '</form>';

        $sContent .= '<div class="bx-def-margin-top bx-def-font-small">';
        $sContent .= '<strong>Lifecycle alert handler:</strong> ' . ($aStatus['alerts_handler_registered'] ? 'registered' : 'MISSING') . '<br>';
        $sContent .= '<strong>before_install:</strong> ' . ($aStatus['before_install_hook_registered'] ? 'active' : 'MISSING') . '<br>';
        $sContent .= '<strong>before_uninstall:</strong> ' . ($aStatus['before_uninstall_hook_registered'] ? 'active' : 'MISSING') . '<br>';
        $sContent .= '<strong>Upgrade dedupe:</strong> ' . (int)$aSettings['upgrade_dedupe_seconds'] . ' seconds<br>';

        $aWorker = $this->_oModule->getBackgroundWorkerStatus();
        $sContent .= '<strong>Background backup worker:</strong> ' .
            ($aWorker['registered'] ? 'registered' : 'MISSING') . '<br>';
        $sContent .= '<strong>Worker heartbeat:</strong> ' .
            ($aWorker['last_run_iso'] !== '' ? $this->_h($aWorker['last_run_iso']) : 'not seen yet') . '<br>';
        $sContent .= '<strong>Worker health:</strong> ' .
            ($aWorker['healthy'] ? 'active' : 'waiting/stale');
        $sContent .= '</div>';

        $sContent .= '</div>';

        return BxDolStudioTemplate::getInstance()->parseHtmlByName('module.html', array(
            'content' => $sContent,
        ));
    }

    protected function _backgroundJobPanel()
    {
        $aJob = $this->_oModule->getActiveBackupJob();
        $aRecent = $this->_oModule->getRecentBackupJobs(1);

        if (!$aJob && $aRecent) {
            $aLast = $aRecent[0];
            if (in_array((string)$aLast['status'], array('complete','failed'), true) &&
                (int)$aLast['finished_at'] > 0 &&
                time() - (int)$aLast['finished_at'] <= 300) {
                $aJob = $aLast;
            }
        }

        if (!$aJob)
            return '';

        $iId = (int)$aJob['id'];
        $iProgress = max(0, min(100, (int)$aJob['progress']));
        $sStatus = (string)$aJob['status'];
        $bActive = in_array($sStatus, array('queued','running'), true);

        $sContent = '<div class="bx-def-padding bx-def-margin-bottom" style="border:1px solid rgba(128,128,128,.35);border-radius:8px">';
        $sContent .= '<strong id="muw-job-title">' .
            $this->_h($bActive ? _t('_my_una_world_backup_active') : _t('_my_una_world_backup_recent')) .
            '</strong>';
        $sContent .= '<div class="bx-def-font-small bx-def-margin-sec-top" id="muw-job-stage">' .
            $this->_h($aJob['stage']) . ' · ' . $this->_h($aJob['message']) . '</div>';
        $sContent .= '<div class="bx-def-margin-sec-top">';
        $sContent .= '<progress id="muw-job-progress" value="' . $iProgress .
            '" max="100" style="width:100%;height:18px"></progress>';
        $sContent .= '<div class="bx-def-font-small"><span id="muw-job-percent">' .
            $iProgress . '%</span> · Job #' . $iId . ' · ' . $this->_h($aJob['mode']) .
            ' · <span id="muw-job-status">' . $this->_h($sStatus) . '</span></div>';
        $sContent .= '</div>';

        if ($bActive) {
            $sContent .= '<div class="bx-def-font-small bx-def-margin-sec-top"><strong>' .
                $this->_h(_t('_my_una_world_safe_to_leave')) .
                '</strong> ' . $this->_h(_t('_my_una_world_safe_to_leave_desc')) . '</div>';
        }

        $sContent .= '</div>';

        if ($bActive) {
            $sStatusUrl = BX_DOL_URL_ROOT . 'modules/newton/my_una_world/status.php?id=' . $iId;
            $sContent .= '<script>(function(){' .
                'var url=' . json_encode($sStatusUrl) . ';' .
                'var timer=null;' .
                'function poll(){fetch(url,{credentials:"same-origin",cache:"no-store"}).then(function(r){return r.json();}).then(function(d){' .
                'if(!d||!d.ok||!d.job)return;' .
                'var j=d.job,p=document.getElementById("muw-job-progress"),pc=document.getElementById("muw-job-percent"),st=document.getElementById("muw-job-status"),sg=document.getElementById("muw-job-stage");' .
                'if(p)p.value=j.progress||0;if(pc)pc.textContent=(j.progress||0)+"%";if(st)st.textContent=j.status||"";if(sg)sg.textContent=(j.stage||"")+" · "+(j.message||"");' .
                'if(j.status==="complete"||j.status==="failed"){if(timer)clearInterval(timer);setTimeout(function(){window.location.replace(window.location.pathname+window.location.search);},1200);}' .
                '}).catch(function(){});}' .
                'timer=setInterval(poll,2000);poll();' .
                '})();</script>';
        }

        return $sContent;
    }

    protected function _processPost()
    {
        if ($this->_bPostProcessed)
            return;

        $this->_bPostProcessed = true;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['muw_action']))
            return;

        $sToken = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';

        if (getParam('sys_security_form_token_enable') === 'on' &&
            ($sToken === '' || !BxDolForm::isCsrfTokenValid($sToken))) {
            $this->_bPostSuccess = false;
            $this->_sPostMessage = 'The form expired. Reload the Studio page and try again.';
            return;
        }

        $sAction = (string)$_POST['muw_action'];

        try {
            switch ($sAction) {
                case 'backup_inventory':
                    $this->_aPostResult = $this->_oModule->queueBackup('inventory');
                    $this->_sPostMessage =
                        'Inventory backup queued. It will run in the UNA background worker; you may leave this page.';
                    break;

                case 'backup_full':
                    $this->_aPostResult = $this->_oModule->queueBackup('full');
                    $this->_sPostMessage =
                        'Full backup queued. It will run in the UNA background worker; you may leave this page.';
                    break;

                case 'delete_backup':
                    $this->_aPostResult = $this->_oModule->deleteBackup((int)$_POST['backup_id']);
                    $this->_sPostMessage = 'Backup deleted.';
                    break;

                case 'inspect':
                    $this->_aPostResult = $this->_oModule->inspectBackup((int)$_POST['backup_id']);
                    $this->_sPostMessage = !empty($this->_aPostResult['compatibility']['can_restore'])
                        ? 'Backup passed inspection and is eligible for restore.'
                        : 'Backup inspection completed. Restore is currently blocked.';
                    break;

                case 'dry_restore':
                    $this->_aPostResult = $this->_oModule->dryRunRestore((int)$_POST['backup_id']);
                    $this->_sPostMessage = 'Restore dry run completed. No site data was changed.';
                    break;

                case 'restore':
                    $this->_aPostResult = $this->_oModule->restoreBackup(
                        (int)$_POST['backup_id'],
                        isset($_POST['confirmation']) ? (string)$_POST['confirmation'] : ''
                    );
                    $this->_bPostSuccess = !empty($this->_aPostResult['verification']['passed']);
                    $this->_sPostMessage = $this->_bPostSuccess
                        ? 'Restore completed and passed automatic post-restore verification.'
                        : 'Restore completed, but post-restore verification found problems.';
                    break;

                case 'register_existing':
                    $this->_aPostResult = $this->_oModule->registerExistingBackup(
                        isset($_POST['archive_name']) ? (string)$_POST['archive_name'] : ''
                    );
                    $this->_sPostMessage = !empty($this->_aPostResult['already_registered'])
                        ? 'Backup was already registered.'
                        : 'Existing backup validated and registered in Backup History.';
                    break;

                case 'upload_existing':
                    $this->_aPostResult = $this->_oModule->uploadExistingBackup(
                        isset($_FILES['backup_zip']) ? $_FILES['backup_zip'] : array(),
                        isset($_FILES['backup_verify']) ? $_FILES['backup_verify'] : array()
                    );
                    $this->_sPostMessage = 'Backup uploaded, validated, and registered in Backup History.';
                    break;

                case 'verify_report':
                    $this->_aPostResult = $this->_oModule->verifyRestoreReport((string)$_POST['report_name']);
                    $this->_bPostSuccess = !empty($this->_aPostResult['verification']['passed']);
                    $this->_sPostMessage = $this->_bPostSuccess
                        ? 'Current site state passed restore verification.'
                        : 'Restore verification found differences.';
                    break;

                case 'delete_report':
                    $this->_aPostResult = $this->_oModule->deleteRestoreReport((string)$_POST['report_name']);
                    $this->_sPostMessage = 'Restore report deleted. Backup archives were not deleted.';
                    break;

                case 'rollback':
                    $this->_aPostResult = $this->_oModule->rollbackRestore((string)$_POST['report_name']);
                    $this->_bPostSuccess = !empty($this->_aPostResult['verification']['passed']);
                    $this->_sPostMessage = $this->_bPostSuccess
                        ? 'Rollback completed and passed automatic verification.'
                        : 'Rollback completed, but verification found problems.';
                    break;

                case 'save_settings':
                    $this->_aPostResult = $this->_oModule->saveLifecycleSettings(array(
                        'backup_before_install' => isset($_POST['backup_before_install']),
                        'backup_before_uninstall' => isset($_POST['backup_before_uninstall']),
                        'block_on_failure' => isset($_POST['block_on_failure']),
                        'retention_max_archives' => isset($_POST['retention_max_archives']) ? (int)$_POST['retention_max_archives'] : 0,
                        'retention_max_age_days' => isset($_POST['retention_max_age_days']) ? (int)$_POST['retention_max_age_days'] : 0,
                    ));
                    $this->_sPostMessage = 'My UNA World settings saved.';
                    break;

                case 'run_retention':
                    $this->_aPostResult = $this->_oModule->applyBackupRetention(true);
                    $iDeleted = isset($this->_aPostResult['deleted']) && is_array($this->_aPostResult['deleted'])
                        ? count($this->_aPostResult['deleted'])
                        : 0;
                    $this->_sPostMessage = 'Retention cleanup completed. Deleted backups: ' . $iDeleted . '.';
                    break;

                default:
                    throw new RuntimeException('Unknown My UNA World Studio action.');
            }
        }
        catch (Throwable $e) {
            $this->_bPostSuccess = false;
            $this->_sPostMessage = $e->getMessage();
        }
    }

    protected function _notice()
    {
        if ($this->_sPostMessage === '')
            return '';

        $sClass = $this->_bPostSuccess ? 'bx-def-color-green2' : 'bx-def-color-red2';

        return '<div class="bx-def-padding-sec bx-def-margin-bottom ' . $sClass . '"><strong>' .
            $this->_h($this->_bPostSuccess ? 'Success' : 'Attention') . '</strong><br>' .
            $this->_h($this->_sPostMessage) . '</div>';
    }

    protected function _postButton($sPage, $sAction, $sCaption, $bConfirm = false, array $aHidden = array(), $sConfirm = '')
    {
        $sHtml = '<form method="post" action="' . $this->_h($this->_pageUrl($sPage)) .
            '" style="display:inline-block;margin:3px 0"';

        if ($bConfirm) {
            if ($sConfirm === '')
                $sConfirm = 'Continue with this operation?';
            $sHtml .= ' onsubmit="return confirm(\'' . $this->_js($sConfirm) . '\');"';
        }

        $sHtml .= '>';
        $sHtml .= $this->_hiddenCommon($sAction);

        foreach ($aHidden as $sName => $mixedValue)
            $sHtml .= '<input type="hidden" name="' . $this->_h($sName) . '" value="' .
                $this->_h($mixedValue) . '">';

        $sHtml .= '<button class="bx-btn" type="submit">' . $this->_h($sCaption) . '</button>';
        $sHtml .= '</form>';

        return $sHtml;
    }

    protected function _hiddenCommon($sAction)
    {
        return '<input type="hidden" name="csrf" value="' . $this->_h(BxDolForm::getCsrfToken()) . '">' .
            '<input type="hidden" name="muw_action" value="' . $this->_h($sAction) . '">';
    }

    protected function _checkbox($sName, $sCaption, $bChecked, $sDescription)
    {
        return '<div class="bx-def-margin-bottom"><label><input type="checkbox" name="' .
            $this->_h($sName) . '" value="1" ' . ($bChecked ? 'checked' : '') . '> <strong>' .
            $this->_h($sCaption) . '</strong></label><br><span class="bx-def-font-small">' .
            $this->_h($sDescription) . '</span></div>';
    }

    protected function _jsonResult(array $aResult)
    {
        $sJson = json_encode(
            $aResult,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return '<div class="bx-def-margin-top"><pre style="white-space:pre-wrap;overflow:auto;max-height:520px">' .
            $this->_h($sJson) . '</pre></div>';
    }

    protected function _pageUrl($sPage)
    {
        return bx_append_url_params($this->sManageUrl, array('page' => $sPage));
    }

    protected function _bytes($iBytes)
    {
        $iBytes = (float)$iBytes;
        $aUnits = array('B','KB','MB','GB','TB');
        $i = 0;
        while ($iBytes >= 1024 && $i < count($aUnits) - 1) {
            $iBytes /= 1024;
            $i++;
        }
        return $this->_h(number_format($iBytes, $i === 0 ? 0 : 2) . ' ' . $aUnits[$i]);
    }

    protected function _h($mixed)
    {
        return htmlspecialchars((string)$mixed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function _js($s)
    {
        return str_replace(array('\\', "'", "\r", "\n"), array('\\\\', "\\'", '', ' '), (string)$s);
    }
}
