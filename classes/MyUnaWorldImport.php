<?php defined('BX_DOL') or die('hack attempt');

class MyUnaWorldImport
{
    protected $_oModule;
    protected $_oBackup;
    protected $_sBackupRoot;

    public function __construct($oModule, $oBackup)
    {
        $this->_oModule = $oModule;
        $this->_oBackup = $oBackup;
        $this->_sBackupRoot = rtrim($oBackup->getBackupRoot(), DIRECTORY_SEPARATOR);
    }

    public function scan()
    {
        $aResult = array();
        $aFiles = glob($this->_sBackupRoot . DIRECTORY_SEPARATOR . 'my_una_world_*.zip');

        if (!is_array($aFiles))
            return $aResult;

        sort($aFiles, SORT_STRING);

        foreach (array_reverse($aFiles) as $sPath) {
            if (!is_file($sPath))
                continue;

            $sName = basename($sPath);
            if (!$this->_isArchiveName($sName))
                continue;

            $aExisting = $this->_oModule->getBackupByArchiveName($sName);

            $aResult[] = array(
                'archive_name' => $sName,
                'archive_size' => (int)filesize($sPath),
                'registered' => (bool)$aExisting,
                'backup_id' => $aExisting ? (int)$aExisting['id'] : 0,
                'verify_sidecar' => is_file($sPath . '.verify.json'),
                'checksum_sidecar' => is_file($sPath . '.sha256'),
            );
        }

        return $aResult;
    }

    public function registerExisting($sArchiveName)
    {
        @set_time_limit(0);

        $sArchiveName = basename((string)$sArchiveName);
        if (!$this->_isArchiveName($sArchiveName))
            throw new RuntimeException('Backup filename is not a valid My UNA World archive name.');

        $aExisting = $this->_oModule->getBackupByArchiveName($sArchiveName);
        if ($aExisting)
            return array(
                'registered' => false,
                'already_registered' => true,
                'backup' => $aExisting,
            );

        $sArchivePath = $this->_safeArchivePath($sArchiveName);
        $aMeta = $this->_inspectArchive($sArchivePath);

        $sSha = hash_file('sha256', $sArchivePath);
        if (!is_string($sSha) || strlen($sSha) !== 64)
            throw new RuntimeException('Unable to calculate SHA-256 for the imported backup.');

        $sVerifyPath = $sArchivePath . '.verify.json';
        $aVerify = array();

        if (is_file($sVerifyPath)) {
            $aVerify = $this->_readJsonFile($sVerifyPath);
            if (!empty($aVerify['sha256']) && !hash_equals(strtolower((string)$aVerify['sha256']), strtolower($sSha)))
                throw new RuntimeException('The uploaded/imported ZIP does not match its Verify JSON SHA-256.');
            if (!empty($aVerify['archive_name']) && basename((string)$aVerify['archive_name']) !== $sArchiveName)
                throw new RuntimeException('Verify JSON archive name does not match the ZIP filename.');
        }

        $sShaPath = $sArchivePath . '.sha256';
        if (is_file($sShaPath)) {
            $sLine = trim((string)file_get_contents($sShaPath));
            if ($sLine !== '') {
                $sExpected = strtolower((string)preg_split('/\s+/', $sLine)[0]);
                if (preg_match('/^[a-f0-9]{64}$/', $sExpected) && !hash_equals($sExpected, strtolower($sSha)))
                    throw new RuntimeException('The imported ZIP does not match its .sha256 sidecar.');
            }
        }
        else {
            file_put_contents($sShaPath, $sSha . '  ' . $sArchiveName . PHP_EOL, LOCK_EX);
            @chmod($sShaPath, 0600);
        }

        $iCreated = $this->_timestampFromArchiveName($sArchiveName);
        if (!empty($aVerify['created_at'])) {
            $iParsed = strtotime((string)$aVerify['created_at']);
            if ($iParsed !== false)
                $iCreated = $iParsed;
        }
        elseif (!empty($aMeta['system']['created_at'])) {
            $iParsed = strtotime((string)$aMeta['system']['created_at']);
            if ($iParsed !== false)
                $iCreated = $iParsed;
        }

        $aManifest = $aMeta['manifest'];
        $sMode = !empty($aManifest['mode']) && $aManifest['mode'] === 'inventory' ? 'inventory' : 'full';

        $aHistory = array(
            'created_at' => $iCreated > 0 ? $iCreated : time(),
            'mode' => $sMode,
            'status' => 'complete',
            'archive_path' => $sArchivePath,
            'archive_name' => $sArchiveName,
            'archive_size' => (int)filesize($sArchivePath),
            'sha256' => $sSha,
            'modules_count' => isset($aManifest['modules_count']) ? (int)$aManifest['modules_count'] : 0,
            'database_tables' => isset($aManifest['database_tables']) ? (int)$aManifest['database_tables'] : 0,
            'files_count' => isset($aManifest['files_count']) ? (int)$aManifest['files_count'] : 0,
            'bytes_source' => isset($aManifest['bytes_source']) ? (int)$aManifest['bytes_source'] : 0,
            'message' => 'Imported existing My UNA World backup for disaster recovery.',
        );

        $this->_oModule->addBackupHistory($aHistory);
        $aRegistered = $this->_oModule->getBackupByArchiveName($sArchiveName);

        if (!is_file($sVerifyPath)) {
            $aNewVerify = array(
                'product' => 'My UNA World',
                'module_version' => isset($aMeta['system']['module_version']) ? (string)$aMeta['system']['module_version'] : '',
                'archive_name' => $sArchiveName,
                'archive_size' => (int)filesize($sArchivePath),
                'sha256' => $sSha,
                'integrity' => 'passed',
                'mode' => $sMode,
                'created_at' => date('c', $aHistory['created_at']),
                'site_url' => isset($aMeta['system']['site_url']) ? (string)$aMeta['system']['site_url'] : '',
                'una_version' => isset($aMeta['system']['una_version']) ? (string)$aMeta['system']['una_version'] : '',
                'php_version' => isset($aMeta['system']['php_version']) ? (string)$aMeta['system']['php_version'] : '',
                'imported' => true,
                'imported_at' => date('c'),
                'stats' => $aManifest,
            );
            $this->_writeJson($sVerifyPath, $aNewVerify);
            @chmod($sVerifyPath, 0600);
        }

        return array(
            'registered' => true,
            'already_registered' => false,
            'backup' => $aRegistered ?: $aHistory,
            'archive_metadata' => array(
                'product' => isset($aMeta['system']['product']) ? $aMeta['system']['product'] : '',
                'module_version' => isset($aMeta['system']['module_version']) ? $aMeta['system']['module_version'] : '',
                'site_url' => isset($aMeta['system']['site_url']) ? $aMeta['system']['site_url'] : '',
                'una_version' => isset($aMeta['system']['una_version']) ? $aMeta['system']['una_version'] : '',
                'php_version' => isset($aMeta['system']['php_version']) ? $aMeta['system']['php_version'] : '',
                'mode' => $sMode,
            ),
        );
    }

    public function uploadAndRegister(array $aZip, array $aVerify = array())
    {
        if (empty($aZip) || !isset($aZip['error']))
            throw new RuntimeException('Choose a My UNA World ZIP backup to upload.');

        if ((int)$aZip['error'] !== UPLOAD_ERR_OK)
            throw new RuntimeException('Backup upload failed with PHP upload error code ' . (int)$aZip['error'] . '.');

        $sName = basename(isset($aZip['name']) ? (string)$aZip['name'] : '');
        if (!$this->_isArchiveName($sName))
            throw new RuntimeException('Uploaded ZIP must keep its original My UNA World backup filename.');

        $sTarget = $this->_sBackupRoot . DIRECTORY_SEPARATOR . $sName;
        if (file_exists($sTarget))
            throw new RuntimeException('A backup with this filename already exists in the private backup folder.');

        $sTmp = isset($aZip['tmp_name']) ? (string)$aZip['tmp_name'] : '';
        if ($sTmp === '' || !is_uploaded_file($sTmp))
            throw new RuntimeException('PHP did not recognize the selected ZIP as a valid HTTP upload.');

        if (!@move_uploaded_file($sTmp, $sTarget))
            throw new RuntimeException('Unable to move the uploaded ZIP into the private My UNA World backup folder.');

        @chmod($sTarget, 0600);

        $sVerifyTarget = $sTarget . '.verify.json';

        try {
            if (!empty($aVerify) && isset($aVerify['error']) && (int)$aVerify['error'] !== UPLOAD_ERR_NO_FILE) {
                if ((int)$aVerify['error'] !== UPLOAD_ERR_OK)
                    throw new RuntimeException('Verify JSON upload failed with PHP upload error code ' . (int)$aVerify['error'] . '.');

                $sVerifyName = basename(isset($aVerify['name']) ? (string)$aVerify['name'] : '');
                if ($sVerifyName !== $sName . '.verify.json')
                    throw new RuntimeException('Verify JSON filename must be ' . $sName . '.verify.json.');

                $sVerifyTmp = isset($aVerify['tmp_name']) ? (string)$aVerify['tmp_name'] : '';
                if ($sVerifyTmp === '' || !is_uploaded_file($sVerifyTmp) ||
                    !@move_uploaded_file($sVerifyTmp, $sVerifyTarget))
                    throw new RuntimeException('Unable to move the Verify JSON into the private backup folder.');

                @chmod($sVerifyTarget, 0600);
            }

            return $this->registerExisting($sName);
        }
        catch (Throwable $e) {
            if (is_file($sTarget))
                @unlink($sTarget);
            if (is_file($sVerifyTarget))
                @unlink($sVerifyTarget);
            if (is_file($sTarget . '.sha256'))
                @unlink($sTarget . '.sha256');
            throw $e;
        }
    }

    protected function _inspectArchive($sArchivePath)
    {
        if (!class_exists('ZipArchive'))
            throw new RuntimeException('PHP ZipArchive is required to import a backup.');

        $oZip = new ZipArchive();
        $iOpen = $oZip->open($sArchivePath, ZipArchive::CHECKCONS);
        if ($iOpen !== true)
            throw new RuntimeException('Backup ZIP failed integrity validation. ZipArchive code: ' . $iOpen);

        try {
            $sManifest = $oZip->getFromName('manifest.json');
            $sSystem = $oZip->getFromName('report/system.json');

            if ($sManifest === false || $sSystem === false)
                throw new RuntimeException('ZIP is missing manifest.json or report/system.json.');

            $aManifest = json_decode($sManifest, true);
            $aSystem = json_decode($sSystem, true);

            if (!is_array($aManifest) || !is_array($aSystem))
                throw new RuntimeException('Backup manifest/system report contains invalid JSON.');

            if (!isset($aSystem['product']) || (string)$aSystem['product'] !== 'My UNA World')
                throw new RuntimeException('ZIP is not identified as a My UNA World backup.');

            $sMode = isset($aManifest['mode']) ? (string)$aManifest['mode'] : '';
            if (!in_array($sMode, array('full', 'inventory'), true))
                throw new RuntimeException('Backup manifest contains an unsupported mode.');

            if ($sMode === 'full' && $oZip->locateName('database/database.sql') === false)
                throw new RuntimeException('Full backup is missing database/database.sql.');

            return array(
                'manifest' => $aManifest,
                'system' => $aSystem,
            );
        }
        finally {
            $oZip->close();
        }
    }

    protected function _safeArchivePath($sArchiveName)
    {
        $sRoot = realpath($this->_sBackupRoot);
        if ($sRoot === false || !is_dir($sRoot))
            throw new RuntimeException('Private My UNA World backup folder cannot be resolved.');

        $sPath = $sRoot . DIRECTORY_SEPARATOR . $sArchiveName;
        if (!is_file($sPath))
            throw new RuntimeException('Backup ZIP is not present in the private backup folder: ' . $sArchiveName);

        if (realpath(dirname($sPath)) !== $sRoot)
            throw new RuntimeException('Refusing to import a ZIP outside the private backup folder.');

        return $sPath;
    }

    protected function _isArchiveName($sName)
    {
        return basename((string)$sName) === (string)$sName &&
            (bool)preg_match('/^my_una_world_(?:inventory|full)_\d{8}_\d{6}\.zip$/D', (string)$sName);
    }

    protected function _timestampFromArchiveName($sName)
    {
        if (!preg_match('/_(\d{8})_(\d{6})\.zip$/D', (string)$sName, $aMatch))
            return 0;

        $oDate = DateTime::createFromFormat('!Ymd_His', $aMatch[1] . '_' . $aMatch[2], new DateTimeZone('UTC'));
        return $oDate ? $oDate->getTimestamp() : 0;
    }

    protected function _readJsonFile($sPath)
    {
        $sJson = file_get_contents($sPath);
        $aData = json_decode($sJson, true);
        if (!is_array($aData))
            throw new RuntimeException('JSON sidecar is invalid: ' . basename($sPath));
        return $aData;
    }

    protected function _writeJson($sPath, array $aData)
    {
        $sJson = json_encode(
            $aData,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if (!is_string($sJson) || file_put_contents($sPath, $sJson . PHP_EOL, LOCK_EX) === false)
            throw new RuntimeException('Unable to write ' . basename($sPath) . '.');
    }
}
