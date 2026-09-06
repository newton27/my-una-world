<?php defined('BX_DOL') or die('hack attempt');

class MyUnaWorldBackup
{
    protected $_oModule;
    protected $_oDb;
    protected $_sSiteRoot;
    protected $_sBackupRoot;
    protected $_iJobId = 0;

    public function __construct($oModule)
    {
        $this->_oModule = $oModule;
        $this->_oDb = BxDolDb::getInstance();
        $this->_sSiteRoot = rtrim(BX_DIRECTORY_PATH_ROOT, DIRECTORY_SEPARATOR);
        $this->_sBackupRoot = $this->_resolveBackupRoot();
    }

    public function getBackupRoot()
    {
        return $this->_sBackupRoot;
    }

    public function run($sMode = 'inventory', array $aContext = array())
    {
        $sMode = $sMode === 'full' ? 'full' : 'inventory';
        $this->_iJobId = isset($aContext['job_id']) ? (int)$aContext['job_id'] : 0;

        $this->_progress(3, 'Preparing', 'Preparing the private work directory and backup metadata.');

        if (!class_exists('ZipArchive'))
            throw new RuntimeException('PHP ZipArchive is required. Install/enable the PHP zip extension and try again.');

        @set_time_limit(0);
        @ignore_user_abort(true);

        $iStarted = time();
        $sStamp = date('Ymd_His', $iStarted);
        $sArchiveName = 'my_una_world_' . $sMode . '_' . $sStamp . '.zip';
        $sArchivePath = $this->_sBackupRoot . DIRECTORY_SEPARATOR . $sArchiveName;
        $sWork = $this->_sBackupRoot . DIRECTORY_SEPARATOR . '.work_' . $sStamp . '_' . bin2hex(random_bytes(3));

        $this->_mkdir($sWork);

        $aStats = array(
            'mode' => $sMode,
            'trigger' => isset($aContext['trigger']) ? (string)$aContext['trigger'] : 'manual',
            'lifecycle_action' => isset($aContext['lifecycle_action']) ? (string)$aContext['lifecycle_action'] : '',
            'module_name' => isset($aContext['module_name']) ? (string)$aContext['module_name'] : '',
            'module_title' => isset($aContext['module_title']) ? (string)$aContext['module_title'] : '',
            'module_version' => isset($aContext['module_version']) ? (string)$aContext['module_version'] : '',
            'started_at' => date('c', $iStarted),
            'modules_count' => 0,
            'module_files' => 0,
            'module_bytes' => 0,
            'storage_files' => 0,
            'storage_bytes' => 0,
            'database_dump_bytes' => 0,
            'files_count' => 0,
            'bytes_source' => 0,
            'database_tables' => 0,
            'database_rows' => 0,
            'skipped_symlinks' => 0,
            'unmatched_installed_modules' => array(),
        );

        try {
            $this->_progress(8, 'Inventory', 'Scanning installed UNA modules and database objects.');

            $aModules = $this->_getInstalledModules();
            $aModulePaths = $this->_discoverInstalledModulePaths($aModules);
            $aStats['modules_count'] = count($aModules);
            $aUnmatched = array_diff(array_keys($aModules), array_keys($aModulePaths));
            $aUnmatched = array_values(array_filter($aUnmatched, function($sName) {
                return $sName !== 'system';
            }));
            $aStats['unmatched_installed_modules'] = $aUnmatched;

            $sModuleVersion = isset($aModules['my_una_world']['version'])
                ? (string)$aModules['my_una_world']['version']
                : '';

            $aSystem = array(
                'product' => 'My UNA World',
                'module_version' => $sModuleVersion,
                'backup_mode' => $sMode,
                'backup_trigger' => isset($aContext['trigger']) ? (string)$aContext['trigger'] : 'manual',
                'backup_reason' => isset($aContext['reason']) ? (string)$aContext['reason'] : '',
                'lifecycle_action' => isset($aContext['lifecycle_action']) ? (string)$aContext['lifecycle_action'] : '',
                'lifecycle_module' => array(
                    'name' => isset($aContext['module_name']) ? (string)$aContext['module_name'] : '',
                    'uri' => isset($aContext['module_uri']) ? (string)$aContext['module_uri'] : '',
                    'title' => isset($aContext['module_title']) ? (string)$aContext['module_title'] : '',
                    'version' => isset($aContext['module_version']) ? (string)$aContext['module_version'] : '',
                ),
                'created_at' => date('c'),
                'site_url' => defined('BX_DOL_URL_ROOT') ? BX_DOL_URL_ROOT : '',
                'una_version' => function_exists('bx_get_ver') ? bx_get_ver() : '',
                'php_version' => PHP_VERSION,
                'site_root' => $this->_sSiteRoot,
                'backup_root' => $this->_sBackupRoot,
                'installed_modules' => array_values($aModules),
                'module_paths' => $aModulePaths,
            );

            $this->_writeJson($sWork . '/system.json', $aSystem);
            $this->_writeJson($sWork . '/modules.json', array_values($aModules));

            $sDbDump = $sWork . '/database.sql';
            if ($sMode === 'full') {
                $this->_progress(15, 'Database', 'Exporting the UNA database to SQL.');
                $aDbStats = $this->_dumpDatabase($sDbDump);
                $aStats['database_tables'] = $aDbStats['tables'];
                $aStats['database_rows'] = $aDbStats['rows'];
                $aStats['database_dump_bytes'] = is_file($sDbDump) ? (int)filesize($sDbDump) : 0;
                $this->_progress(32, 'Database', 'Database export completed. Building the ZIP archive.');
            } else {
                file_put_contents(
                    $sDbDump,
                    "-- My UNA World inventory test\n-- Database data intentionally not included in inventory mode.\n"
                );
                $aStats['database_tables'] = count($this->_getDatabaseObjects());
            }

            $oZip = new ZipArchive();
            $iOpen = $oZip->open($sArchivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($iOpen !== true)
                throw new RuntimeException('Unable to create ZIP archive. ZipArchive error code: ' . $iOpen);

            $oZip->addFile($sWork . '/system.json', 'report/system.json');
            $oZip->addFile($sWork . '/modules.json', 'report/modules.json');
            $oZip->addFile($sDbDump, 'database/database.sql');

            if ($sMode === 'full') {
                $iModuleTotal = max(1, count($aModulePaths));
                $iModuleDone = 0;

                foreach ($aModulePaths as $sModuleName => $sRelativeDir) {
                    $this->_progress(
                        35 + (int)floor(($iModuleDone / $iModuleTotal) * 35),
                        'Module files',
                        'Adding module source: ' . $sModuleName
                    );

                    $sAbsolute = $this->_sSiteRoot . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $sRelativeDir;
                    if (is_dir($sAbsolute))
                        $this->_addDirectory(
                            $oZip,
                            $sAbsolute,
                            'modules/' . str_replace(DIRECTORY_SEPARATOR, '/', $sRelativeDir),
                            $aStats,
                            'module_files',
                            'module_bytes'
                        );

                    $iModuleDone++;
                }

                $this->_progress(72, 'Storage', 'Adding UNA storage and original media files.');
                $sStorage = $this->_sSiteRoot . DIRECTORY_SEPARATOR . 'storage';
                if (is_dir($sStorage))
                    $this->_addDirectory($oZip, $sStorage, 'storage', $aStats, 'storage_files', 'storage_bytes');
            }

            $this->_progress(85, 'Finalizing', 'Writing the backup manifest and finalizing the ZIP archive.');

            $aStats['finished_at'] = date('c');
            $aStats['duration_seconds'] = time() - $iStarted;
            $this->_writeJson($sWork . '/manifest.json', $aStats);
            $oZip->addFile($sWork . '/manifest.json', 'manifest.json');

            if (!$oZip->close())
                throw new RuntimeException('ZIP archive could not be finalized.');

            $this->_progress(92, 'Integrity check', 'Running ZIP consistency validation.');

            $oCheck = new ZipArchive();
            $iCheck = $oCheck->open($sArchivePath, ZipArchive::CHECKCONS);
            if ($iCheck !== true)
                throw new RuntimeException('ZIP integrity validation failed with error code: ' . $iCheck);
            $oCheck->close();

            $this->_progress(96, 'Checksum', 'Calculating SHA-256 and writing verification sidecars.');

            $sSha = hash_file('sha256', $sArchivePath);
            file_put_contents($sArchivePath . '.sha256', $sSha . '  ' . $sArchiveName . PHP_EOL);

            $aVerification = array(
                'product' => 'My UNA World',
                'module_version' => $sModuleVersion,
                'archive_name' => $sArchiveName,
                'archive_size' => (int)filesize($sArchivePath),
                'sha256' => $sSha,
                'integrity' => 'passed',
                'mode' => $sMode,
                'trigger' => isset($aContext['trigger']) ? (string)$aContext['trigger'] : 'manual',
                'reason' => isset($aContext['reason']) ? (string)$aContext['reason'] : '',
                'lifecycle_action' => isset($aContext['lifecycle_action']) ? (string)$aContext['lifecycle_action'] : '',
                'lifecycle_module' => array(
                    'name' => isset($aContext['module_name']) ? (string)$aContext['module_name'] : '',
                    'uri' => isset($aContext['module_uri']) ? (string)$aContext['module_uri'] : '',
                    'title' => isset($aContext['module_title']) ? (string)$aContext['module_title'] : '',
                    'version' => isset($aContext['module_version']) ? (string)$aContext['module_version'] : '',
                ),
                'created_at' => date('c', $iStarted),
                'site_url' => defined('BX_DOL_URL_ROOT') ? BX_DOL_URL_ROOT : '',
                'una_version' => function_exists('bx_get_ver') ? bx_get_ver() : '',
                'php_version' => PHP_VERSION,
                'stats' => $aStats,
            );
            $this->_writeJson($sArchivePath . '.verify.json', $aVerification);

            $aHistory = array(
                'created_at' => $iStarted,
                'mode' => $sMode,
                'status' => 'complete',
                'archive_path' => $sArchivePath,
                'archive_name' => $sArchiveName,
                'archive_size' => filesize($sArchivePath),
                'sha256' => $sSha,
                'modules_count' => $aStats['modules_count'],
                'database_tables' => $aStats['database_tables'],
                'files_count' => $aStats['files_count'],
                'bytes_source' => $aStats['bytes_source'],
                'message' => !empty($aContext['reason'])
                    ? (string)$aContext['reason']
                    : ($sMode === 'full'
                        ? 'Full backup completed and ZIP integrity check passed.'
                        : 'Inventory test completed and ZIP integrity check passed.'),
            );

            $this->_progress(99, 'Recording', 'Saving Backup History and completing the job.');

            $this->_oModule->addBackupHistory($aHistory);
            $this->_removeTree($sWork);

            return array_merge($aHistory, array('stats' => $aStats));
        }
        catch (Throwable $e) {
            if (is_file($sArchivePath))
                @unlink($sArchivePath);
            if (is_file($sArchivePath . '.sha256'))
                @unlink($sArchivePath . '.sha256');
            if (is_file($sArchivePath . '.verify.json'))
                @unlink($sArchivePath . '.verify.json');

            $this->_removeTree($sWork);

            try {
                $this->_oModule->addBackupHistory(array(
                    'created_at' => $iStarted,
                    'mode' => $sMode,
                    'status' => 'failed',
                    'archive_path' => '',
                    'archive_name' => '',
                    'archive_size' => 0,
                    'sha256' => '',
                    'modules_count' => isset($aStats['modules_count']) ? $aStats['modules_count'] : 0,
                    'database_tables' => isset($aStats['database_tables']) ? $aStats['database_tables'] : 0,
                    'files_count' => isset($aStats['files_count']) ? $aStats['files_count'] : 0,
                    'bytes_source' => isset($aStats['bytes_source']) ? $aStats['bytes_source'] : 0,
                    'message' => $e->getMessage(),
                ));
            }
            catch (Throwable $ignored) {
            }

            throw $e;
        }
    }

    protected function _progress($iProgress, $sStage, $sMessage = '')
    {
        if ($this->_iJobId <= 0)
            return;

        if (method_exists($this->_oModule, 'updateBackupJobProgress')) {
            $this->_oModule->updateBackupJobProgress(
                $this->_iJobId,
                max(0, min(99, (int)$iProgress)),
                (string)$sStage,
                (string)$sMessage
            );
        }
    }

    protected function _resolveBackupRoot()
    {
        $sPreferred = dirname($this->_sSiteRoot) . DIRECTORY_SEPARATOR . 'my_una_world_backups';

        if ($this->_canUseDirectory($sPreferred))
            return $sPreferred;

        $sFallback = $this->_sSiteRoot . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'my_una_world_backups';
        $this->_mkdir($sFallback);

        @file_put_contents(
            $sFallback . DIRECTORY_SEPARATOR . '.htaccess',
            "Require all denied\nDeny from all\n"
        );
        @file_put_contents($sFallback . DIRECTORY_SEPARATOR . 'index.html', '');

        return $sFallback;
    }

    protected function _canUseDirectory($sDir)
    {
        try {
            $this->_mkdir($sDir);
            return is_dir($sDir) && is_writable($sDir);
        }
        catch (Throwable $e) {
            return false;
        }
    }

    protected function _mkdir($sDir)
    {
        if (!is_dir($sDir) && !@mkdir($sDir, 0750, true) && !is_dir($sDir))
            throw new RuntimeException('Unable to create backup directory: ' . $sDir);
    }

    protected function _getInstalledModules()
    {
        $aRows = $this->_oDb->getAll("SELECT * FROM `sys_modules` ORDER BY `title`, `name`");
        $aResult = array();

        foreach ($aRows as $aRow) {
            if (!isset($aRow['name']))
                continue;
            $aResult[(string)$aRow['name']] = $aRow;
        }

        return $aResult;
    }

    protected function _discoverInstalledModulePaths(array $aInstalled)
    {
        $aFound = array();
        $sModulesRoot = $this->_sSiteRoot . DIRECTORY_SEPARATOR . 'modules';

        if (!is_dir($sModulesRoot))
            return $aFound;

        foreach ($aInstalled as $sName => $aRow) {
            if ($sName === 'system')
                continue;

            $sPath = isset($aRow['path']) ? trim((string)$aRow['path'], "/\\") : '';
            if ($sPath === '')
                continue;

            $sRelative = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $sPath);
            $sAbsolute = $sModulesRoot . DIRECTORY_SEPARATOR . $sRelative;

            if (is_dir($sAbsolute))
                $aFound[$sName] = $sRelative;
        }

        $aVendors = scandir($sModulesRoot);
        foreach ($aVendors as $sVendor) {
            if ($sVendor === '.' || $sVendor === '..')
                continue;

            $sVendorDir = $sModulesRoot . DIRECTORY_SEPARATOR . $sVendor;
            if (!is_dir($sVendorDir))
                continue;

            $aModuleDirs = scandir($sVendorDir);
            foreach ($aModuleDirs as $sDir) {
                if ($sDir === '.' || $sDir === '..')
                    continue;

                $sModuleDir = $sVendorDir . DIRECTORY_SEPARATOR . $sDir;
                $sConfig = $sModuleDir . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'config.php';
                if (!is_file($sConfig))
                    continue;

                $sText = @file_get_contents($sConfig);
                if (!is_string($sText) ||
                    !preg_match("/['\"]name['\"]\s*=>\s*['\"]([^'\"]+)['\"]/", $sText, $aMatch))
                    continue;

                $sName = $aMatch[1];
                if (isset($aInstalled[$sName]) && !isset($aFound[$sName]))
                    $aFound[$sName] = $sVendor . DIRECTORY_SEPARATOR . $sDir;
            }
        }

        return $aFound;
    }

    protected function _getDatabaseObjects()
    {
        return $this->_oDb->getAll("SHOW FULL TABLES");
    }

    protected function _dumpDatabase($sPath)
    {
        $h = @fopen($sPath, 'wb');
        if (!$h)
            throw new RuntimeException('Unable to create database dump file.');

        fwrite($h, "-- My UNA World database backup\n");
        fwrite($h, "-- Created: " . date('c') . "\n\n");
        fwrite($h, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $aObjects = $this->_getDatabaseObjects();
        $iTables = 0;
        $iRowsTotal = 0;
        $aViews = array();

        foreach ($aObjects as $aObject) {
            $aValues = array_values($aObject);
            if (empty($aValues))
                continue;

            $sName = (string)$aValues[0];
            $sType = isset($aValues[1]) ? strtoupper((string)$aValues[1]) : 'BASE TABLE';

            if ($sType === 'VIEW') {
                $aViews[] = $sName;
                continue;
            }

            $iTables++;
            $sIdent = $this->_quoteIdentifier($sName);
            $aCreate = $this->_oDb->getRow("SHOW CREATE TABLE " . $sIdent);
            $aCreateValues = is_array($aCreate) ? array_values($aCreate) : array();

            fwrite($h, "\n-- --------------------------------------------------------\n");
            fwrite($h, "-- Table: " . $sName . "\n");
            fwrite($h, "DROP TABLE IF EXISTS " . $sIdent . ";\n");

            if (isset($aCreateValues[1]))
                fwrite($h, $aCreateValues[1] . ";\n\n");

            $iOffset = 0;
            $iChunk = 250;

            while (true) {
                $aRows = $this->_oDb->getAll(
                    "SELECT * FROM " . $sIdent . " LIMIT " . (int)$iOffset . "," . (int)$iChunk
                );

                if (!$aRows)
                    break;

                foreach ($aRows as $aRow) {
                    $aColumns = array();
                    $aValuesSql = array();

                    foreach ($aRow as $sColumn => $mixedValue) {
                        $aColumns[] = $this->_quoteIdentifier($sColumn);
                        $aValuesSql[] = $this->_sqlValue($mixedValue);
                    }

                    fwrite(
                        $h,
                        "INSERT INTO " . $sIdent .
                        " (" . implode(',', $aColumns) . ") VALUES (" .
                        implode(',', $aValuesSql) . ");\n"
                    );
                    $iRowsTotal++;
                }

                $iOffset += count($aRows);
                if (count($aRows) < $iChunk)
                    break;
            }

            fwrite($h, "\n");
        }

        foreach ($aViews as $sName) {
            $sIdent = $this->_quoteIdentifier($sName);
            $aCreate = $this->_oDb->getRow("SHOW CREATE VIEW " . $sIdent);
            $aCreateValues = is_array($aCreate) ? array_values($aCreate) : array();

            fwrite($h, "\n-- View: " . $sName . "\n");
            fwrite($h, "DROP VIEW IF EXISTS " . $sIdent . ";\n");
            if (isset($aCreateValues[1]))
                fwrite($h, $aCreateValues[1] . ";\n\n");
        }

        fwrite($h, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        fclose($h);

        return array('tables' => $iTables, 'rows' => $iRowsTotal);
    }

    protected function _quoteIdentifier($s)
    {
        return '`' . str_replace('`', '``', (string)$s) . '`';
    }

    protected function _sqlValue($mixed)
    {
        if ($mixed === null)
            return 'NULL';

        if (is_bool($mixed))
            return $mixed ? '1' : '0';

        $s = (string)$mixed;
        $s = strtr($s, array(
            "\\" => "\\\\",
            "\0" => "\\0",
            "\n" => "\\n",
            "\r" => "\\r",
            "'" => "\\'",
            "\x1a" => "\\Z",
        ));

        return "'" . $s . "'";
    }

    protected function _addDirectory(ZipArchive $oZip, $sSource, $sZipPrefix, array &$aStats, $sCounter, $sBytesCounter = '')
    {
        $sSource = rtrim($sSource, DIRECTORY_SEPARATOR);
        $iBaseLen = strlen($sSource) + 1;

        $oIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sSource, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($oIterator as $oFile) {
            if ($oFile->isLink()) {
                $aStats['skipped_symlinks']++;
                continue;
            }

            if (!$oFile->isFile())
                continue;

            $sPath = $oFile->getPathname();
            $sRel = substr($sPath, $iBaseLen);
            $sZipName = trim($sZipPrefix, '/') . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $sRel);

            if (!$oZip->addFile($sPath, $sZipName))
                throw new RuntimeException('Unable to add file to ZIP: ' . $sPath);

            $iSize = (int)$oFile->getSize();
            $aStats[$sCounter]++;
            if ($sBytesCounter !== '') {
                if (!isset($aStats[$sBytesCounter]))
                    $aStats[$sBytesCounter] = 0;
                $aStats[$sBytesCounter] += $iSize;
            }
            $aStats['files_count']++;
            $aStats['bytes_source'] += $iSize;
        }
    }

    protected function _writeJson($sPath, $aData)
    {
        $sJson = json_encode(
            $aData,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if (!is_string($sJson) || file_put_contents($sPath, $sJson . PHP_EOL) === false)
            throw new RuntimeException('Unable to write report file: ' . $sPath);
    }

    protected function _removeTree($sDir)
    {
        if (!is_dir($sDir))
            return;

        $oIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($oIterator as $oFile) {
            if ($oFile->isDir() && !$oFile->isLink())
                @rmdir($oFile->getPathname());
            else
                @unlink($oFile->getPathname());
        }

        @rmdir($sDir);
    }
}
