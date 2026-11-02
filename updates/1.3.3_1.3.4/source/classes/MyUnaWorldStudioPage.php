<?php defined('BX_DOL') or die('hack attempt');
require_once __DIR__ . '/MyUnaWorldBackupTiming.php';

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
        $sContent .= '<strong>' . $this->_h(_t('_my_una_world_version')) . ':</strong> 1.3.3<br>';
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
        $sContent .= $this->_backupPageStyles();
        $sContent .= '<div class="bx-def-padding muw-backups-page">';
        $sContent .= '<header class="muw-backups-header"><div><h2>' . $this->_h(_t('_my_una_world_backups')) . '</h2>';
        $sContent .= '<p>Backup history, completion times, and recovery controls. All times are UTC.</p></div><div class="muw-backups-tools">';
        $sContent .= $this->_postButton('backups', 'backup_full', 'Create Full Backup', true);
        $sContent .= $this->_postButton('backups', 'backup_inventory', 'Inventory Test', false);
        $sContent .= '</div></header>';

        if (!$aBackups) {
            $sContent .= '<p class="bx-def-font-small">' . $this->_h(_t('_my_una_world_no_backups')) . '</p>';
        }
        else {
            $sContent .= '<div class="muw-backup-grid">';
            foreach ($aBackups as $a) {
                $bProtected = isset($aProtected[$a['archive_name']]);
                $aTiming = isset($a['timing']) ? $a['timing'] : MyUnaWorldBackupTiming::resolve($a);
                $bComplete = $a['status'] === 'complete';
                $sContent .= '<article class="muw-backup-card"><header class="muw-backup-card-head"><div><strong>' .
                    ($a['mode'] === 'full' ? 'Full backup' : 'Inventory test') . '</strong><div class="muw-backup-file">' .
                    $this->_h($a['archive_name'] ?: 'No completed archive') . '</div></div><span class="muw-backup-status ' .
                    ($bComplete ? 'muw-complete' : 'muw-failed') . '">' . $this->_h(ucfirst($a['status'])) . '</span></header>';
                if ($bProtected)
                    $sContent .= '<p class="muw-backup-protected">Protected rollback safety backup</p>';
                $sContent .= '<dl class="muw-backup-times">';
                foreach (array('Started'=>MyUnaWorldBackupTiming::timestamp($aTiming['started_at']),
                    'Completed'=>MyUnaWorldBackupTiming::timestamp($aTiming['completed_at']),
                    'Duration'=>MyUnaWorldBackupTiming::duration($aTiming['duration_seconds'])) as $sLabel=>$sValue)
                    $sContent .= '<div><dt>' . $sLabel . '</dt><dd>' . $this->_h($sValue) . '</dd></div>';
                $sContent .= '</dl><div class="muw-backup-metrics">';
                foreach (array('Archive'=>$this->_bytes($a['archive_size']), 'Files'=>number_format((int)$a['files_count']),
                    'Modules'=>(int)$a['modules_count'], 'DB tables'=>(int)$a['database_tables']) as $sLabel=>$sValue)
                    $sContent .= '<span><strong>' . $this->_h($sValue) . '</strong> ' . $sLabel . '</span>';
                $sContent .= '</div>';
                if (!empty($a['message']))
                    $sContent .= '<p class="muw-backup-note">' . $this->_h($a['message']) . '</p>';
                $sContent .= $this->_backupTimingDetails($aTiming);
                $sContent .= '<div class="muw-backup-primary-actions">';
                if ($bComplete) {
                    $sContent .= '<a class="bx-btn" href="' . $this->_h(BX_DOL_URL_ROOT .
                        'modules/newton/my_una_world/download.php?id=' . (int)$a['id']) . '">Download</a> ';

                    if (is_file((string)$a['archive_path'] . '.verify.json')) {
                        $sContent .= '<a class="bx-btn" href="' . $this->_h(BX_DOL_URL_ROOT .
                            'modules/newton/my_una_world/download.php?id=' . (int)$a['id'] . '&type=verify') .
                            '">Verify JSON</a> ';
                    }

                    $sContent .= $this->_postButton('backups', 'inspect', 'Inspect', false, array('backup_id' => (int)$a['id']));
                }
                $sContent .= '</div><details class="muw-backup-recovery"><summary>Recovery actions</summary><div>';
                if ($bComplete) {
                    $sContent .= $this->_postButton('backups', 'dry_restore', 'Dry Run', false, array('backup_id' => (int)$a['id']));
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

                $sContent .= '</div></details></article>';
            }

            $sContent .= '</div>';
        }

        if (is_array($this->_aPostResult))
            $sContent .= $this->_jsonResult($this->_aPostResult);

        $sContent .= '</div>';

        return BxDolStudioTemplate::getInstance()->parseHtmlByName('module.html', array(
            'content' => $sContent,
        ));
    }

    protected function _backupPageStyles()
    {
        return '<style>
        .muw-backups-header,.muw-backup-card-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}
        .muw-backups-header{margin-bottom:22px;flex-wrap:wrap}.muw-backups-header h2{margin:0 0 8px}.muw-backups-header p{margin:0;opacity:.75}
        .muw-backup-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:18px}
        .muw-backup-card{min-width:0;padding:20px;border:1px solid rgba(128,128,128,.3);border-radius:14px;background:rgba(128,128,128,.035)}
        .muw-backup-file{font-size:.8em;opacity:.7;overflow-wrap:anywhere;margin-top:6px}.muw-backup-status{font-size:.8em;padding:5px 10px;border-radius:20px;white-space:nowrap}
        .muw-complete{background:rgba(28,155,105,.13);color:#15946c}.muw-failed{background:rgba(205,78,70,.12);color:#cf625b}
        .muw-backup-times{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:22px 0}.muw-backup-times div:last-child{grid-column:1/-1}
        .muw-backup-times dt{font-size:.8em;opacity:.65;margin-bottom:5px}.muw-backup-times dd{margin:0;font-variant-numeric:tabular-nums;font-weight:600;font-size:.93em}
        .muw-backup-metrics{display:flex;gap:9px 15px;flex-wrap:wrap;padding:13px 0;border-top:1px solid rgba(128,128,128,.2);border-bottom:1px solid rgba(128,128,128,.2);font-size:.82em}
        .muw-backup-metrics span{white-space:nowrap}.muw-backup-note{font-size:.85em;opacity:.75;overflow-wrap:anywhere}.muw-backup-protected{font-size:.85em;color:#b08831}
        .muw-backup-primary-actions{display:flex;gap:5px;align-items:center;flex-wrap:wrap;margin-top:16px}
        .muw-backup-recovery,.muw-backup-breakdown{margin-top:16px;border-top:1px solid rgba(128,128,128,.2);padding-top:12px;font-size:.88em}
        .muw-backup-recovery summary,.muw-backup-breakdown summary{cursor:pointer}.muw-backup-recovery>div{margin-top:12px}
        .muw-phase-list{display:grid;grid-template-columns:1fr auto;gap:7px 16px}.muw-phase-list dt,.muw-phase-list dd{margin:0}.muw-phase-list dd{font-variant-numeric:tabular-nums}
        .muw-slow-tables{width:100%;font-size:.88em;table-layout:fixed}.muw-slow-tables caption{text-align:left;margin:12px 0 7px;font-weight:600}.muw-slow-tables th,.muw-slow-tables td{text-align:left;padding:5px 3px;overflow-wrap:anywhere}
        @media(max-width:800px){.muw-backup-grid{grid-template-columns:1fr}.muw-backup-times{grid-template-columns:1fr}.muw-backup-card{padding:16px}}
        </style>';
    }

    protected function _backupTimingDetails(array $aTiming)
    {
        if (empty($aTiming['phases']))
            return '';
        $aLabels = array('preparing'=>'Preparation','inventory'=>'Inventory','database'=>'Database export',
            'module_files'=>'Module files','storage'=>'Storage files','archive'=>'ZIP finalization','validation'=>'ZIP validation','checksum'=>'Checksum');
        $s = '<details class="muw-backup-breakdown"><summary>Timing breakdown</summary><dl class="muw-phase-list">';
        foreach ($aTiming['phases'] as $sPhase=>$fSeconds) {
            if (isset($aLabels[$sPhase]))
                $s .= '<dt>' . $aLabels[$sPhase] . '</dt><dd>' . $this->_h(MyUnaWorldBackupTiming::duration($fSeconds)) . '</dd>';
        }
        $s .= '</dl>';
        if (!empty($aTiming['slowest_tables'])) {
            $s .= '<table class="muw-slow-tables"><caption>Slowest database tables</caption><thead><tr><th>Table</th><th>Rows</th><th>Time</th></tr></thead><tbody>';
            foreach ($aTiming['slowest_tables'] as $aTable)
                $s .= '<tr><td>' . $this->_h($aTable['table']) . '</td><td>' . number_format($aTable['rows']) . '</td><td>' . $this->_h(MyUnaWorldBackupTiming::duration($aTable['seconds'])) . '</td></tr>';
            $s .= '</tbody></table>';
        }
        return $s . '</details>';
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
            'visitor_gate_enabled',
            _t('_my_una_world_visitor_gate_enabled'),
            !empty($aSettings['visitor_gate_enabled']),
            _t('_my_una_world_visitor_gate_enabled_desc')
        );

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
        $sContent .= '<h3>Maintenance Page</h3>';
        $sContent .= '<p class="bx-def-font-small">Use the same maintenance-page controls as My UNA Doctor for My UNA World workflows.</p>';

        $sContent .= $this->_checkbox(
            'maintenance_show_header',
            'Show maintenance page header',
            !empty($aSettings['maintenance_show_header']),
            'Show the My UNA World branding and trust header on the public maintenance page.'
        );

        $sContent .= $this->_checkbox(
            'maintenance_show_footer',
            'Show maintenance page footer',
            !empty($aSettings['maintenance_show_footer']),
            'Show the closing My UNA World maintenance footer.'
        );

        $sContent .= $this->_textSetting(
            'maintenance_heading',
            'Maintenance heading',
            $aSettings['maintenance_heading'],
            'Personalize the main heading visitors see while maintenance is active.'
        );
        $sContent .= $this->_textSetting(
            'maintenance_message',
            'Main maintenance message',
            $aSettings['maintenance_message'],
            'Personalize the main message explaining the temporary maintenance period.'
        );
        $sContent .= $this->_textSetting(
            'maintenance_patience',
            'Patience message',
            $aSettings['maintenance_patience'],
            'Message displayed beneath the maintenance countdown.'
        );
        $sContent .= $this->_textSetting(
            'maintenance_return_message',
            'Return/status message',
            $aSettings['maintenance_return_message'],
            'Message shown beneath the countdown while the estimated return time is active.'
        );
        $sContent .= $this->_textSetting(
            'maintenance_footer_message',
            'Footer message',
            $aSettings['maintenance_footer_message'],
            'Personalize the closing message shown in the maintenance page footer.'
        );

        $sContent .= $this->_checkbox(
            'use_maintenance_page',
            'Use My UNA World Page',
            !empty($aSettings['use_maintenance_page']),
            "When enabled, My UNA World uses its branded maintenance page. When disabled, it preserves and uses the existing UNA maintenance page for the site."
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
        $iStarted = isset($aJob['started_at']) ? (int)$aJob['started_at'] : 0;
        $iFinished = isset($aJob['finished_at']) ? (int)$aJob['finished_at'] : 0;
        $sContent .= '<div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:12px;font-size:.85em">' .
            '<span>Started: <strong id="muw-job-started">' . $this->_h($iStarted > 0 ? MyUnaWorldBackupTiming::timestamp($iStarted) : 'Waiting for worker') . '</strong></span>' .
            '<span>Finished: <strong id="muw-job-finished">' . $this->_h($iFinished > 0 ? MyUnaWorldBackupTiming::timestamp($iFinished) : 'Pending') . '</strong></span>' .
            '<span>Elapsed: <strong id="muw-job-duration">' . $this->_h($iStarted > 0 ? MyUnaWorldBackupTiming::duration(max(0,($iFinished ?: time())-$iStarted)) : 'Waiting for worker') . '</strong></span></div>';

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
                'function stamp(n){return n>0?new Date(n*1000).toISOString().slice(0,19).replace("T"," ")+" UTC":"";}' .
                'function elapsed(n){n=Math.max(0,Math.floor(n));return Math.floor(n/3600)+"h "+String(Math.floor(n%3600/60)).padStart(2,"0")+"m "+String(n%60).padStart(2,"0")+"s";}' .
                'function poll(){fetch(url,{credentials:"same-origin",cache:"no-store"}).then(function(r){return r.json();}).then(function(d){' .
                'if(!d||!d.ok||!d.job)return;' .
                'var j=d.job,p=document.getElementById("muw-job-progress"),pc=document.getElementById("muw-job-percent"),st=document.getElementById("muw-job-status"),sg=document.getElementById("muw-job-stage");' .
                'if(p)p.value=j.progress||0;if(pc)pc.textContent=(j.progress||0)+"%";if(st)st.textContent=j.status||"";if(sg)sg.textContent=(j.stage||"")+" · "+(j.message||"");' .
                'var a=document.getElementById("muw-job-started"),b=document.getElementById("muw-job-finished"),c=document.getElementById("muw-job-duration");' .
                'if(a)a.textContent=stamp(j.started_at)||"Waiting for worker";if(b)b.textContent=stamp(j.finished_at)||"Pending";' .
                'if(c)c.textContent=j.started_at>0?elapsed((j.finished_at||d.server_time||Math.floor(Date.now()/1000))-j.started_at):"Waiting for worker";' .
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
                        'visitor_gate_enabled' => isset($_POST['visitor_gate_enabled']),
                        'backup_before_install' => isset($_POST['backup_before_install']),
                        'backup_before_uninstall' => isset($_POST['backup_before_uninstall']),
                        'block_on_failure' => isset($_POST['block_on_failure']),
                        'maintenance_show_header' => isset($_POST['maintenance_show_header']),
                        'maintenance_show_footer' => isset($_POST['maintenance_show_footer']),
                        'maintenance_heading' => isset($_POST['maintenance_heading']) ? $_POST['maintenance_heading'] : '',
                        'maintenance_message' => isset($_POST['maintenance_message']) ? $_POST['maintenance_message'] : '',
                        'maintenance_patience' => isset($_POST['maintenance_patience']) ? $_POST['maintenance_patience'] : '',
                        'maintenance_return_message' => isset($_POST['maintenance_return_message']) ? $_POST['maintenance_return_message'] : '',
                        'maintenance_footer_message' => isset($_POST['maintenance_footer_message']) ? $_POST['maintenance_footer_message'] : '',
                        'use_maintenance_page' => isset($_POST['use_maintenance_page']),
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

    protected function _textSetting($sName, $sCaption, $sValue, $sDescription)
    {
        return '<div class="bx-def-margin-bottom"><label><strong>' .
            $this->_h($sCaption) . '</strong><br><input type="text" name="' .
            $this->_h($sName) . '" value="' . $this->_h($sValue) .
            '" style="width:100%;max-width:900px"></label><br><span class="bx-def-font-small">' .
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
