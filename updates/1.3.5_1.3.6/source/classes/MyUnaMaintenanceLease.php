<?php defined('BX_DOL') or die('hack attempt');

/**
 * Request-scoped native UNA soft maintenance, shared by cooperating modules.
 * The lock and rollback journal live above the public site directory. Recovery
 * requires the same exclusive lock; elapsed time never overrides a live worker.
 */
class MyUnaMaintenanceLease
{
    private $siteRoot;
    private $prefix;
    private $lock = null;
    private $journal = null;
    private $lastResult = array('ok' => true, 'busy' => false, 'preserved' => array(), 'errors' => array());

    public function __construct($siteRoot)
    {
        $real = realpath((string)$siteRoot);
        if ($real === false || !is_dir($real))
            throw new RuntimeException('Maintenance requires an existing site root.');
        $this->siteRoot = rtrim($real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->prefix = dirname(rtrim($this->siteRoot, DIRECTORY_SEPARATOR))
            . DIRECTORY_SEPARATOR . '.my-una-maintenance-' . sha1($this->siteRoot);
    }

    /** Activate maintenance, preserving any maintenance already enabled. */
    public function begin($customHtml = null)
    {
        $this->acquire();
        try {
            $recovery = $this->recoverLocked();
            if (!$recovery['ok'])
                throw new RuntimeException('Previous maintenance could not be recovered: ' . implode('; ', $recovery['errors']));

            $original = array();
            foreach (self::optionNames() as $name)
                $original[$name] = $this->readOption($name);
            $owner = bin2hex(random_bytes(16));
            $expected = $original;
            $expected['sys_template_cache_compress_enable'] = '';
            if ($original['sys_maintenance_mode'] === '') {
                if ($customHtml !== null)
                    $expected['sys_maintenance_mode_html'] = (string)$customHtml . "\n<!-- MyUnaMaintenanceLease:" . $owner . ' -->';
                // UNA's native gate treats any nonempty value as enabled.
                // A unique value distinguishes our switch from a Studio save
                // of the checkbox's ordinary 'on', even after process death.
                $expected['sys_maintenance_mode'] = 'my_una:' . $owner;
            }

            $this->journal = array(
                'schema' => 2, 'site_root' => $this->siteRoot,
                'owner' => $owner, 'applied' => array(), 'pending' => null,
                'original' => $original, 'expected' => $expected,
            );
            $this->writeJournal();
            register_shutdown_function(function () {
                if (is_resource($this->lock))
                    $this->finish();
            });

            // gzip_loader.php is blocked by soft maintenance; use direct assets
            // before changing the public switch so Studio stays usable.
            foreach (array('sys_template_cache_compress_enable', 'sys_maintenance_mode_html', 'sys_maintenance_mode') as $name) {
                if ($expected[$name] !== $original[$name]) {
                    if ($this->readOption($name) !== $original[$name])
                        throw new RuntimeException('Maintenance option changed while arming: ' . $name);
                    $this->journal['pending'] = $name;
                    $this->writeJournal();
                    if (!$this->writeOption($name, $expected[$name], $original[$name]))
                        throw new RuntimeException('Maintenance option changed while arming: ' . $name);
                    $this->journal['applied'][] = $name;
                    $this->journal['pending'] = null;
                    $this->writeJournal();
                }
            }
            return array('ok' => true, 'owner' => $this->journal['owner'], 'original' => $original,
                'maintenance_owned' => $original['sys_maintenance_mode'] === '');
        } catch (Throwable $error) {
            $result = $this->finish();
            if (!$result['ok'])
                throw new RuntimeException($error->getMessage() . ' Rollback requires recovery: ' . implode('; ', $result['errors']), 0, $error);
            throw $error;
        }
    }

    /** Restore only values still matching this operation's expected state. */
    public function finish()
    {
        if (!is_resource($this->lock))
            return $this->lastResult;
        try {
            $this->lastResult = $this->journal === null
                ? array('ok' => true, 'busy' => false, 'preserved' => array(), 'errors' => array())
                : $this->restore($this->journal);
        } catch (Throwable $error) {
            $this->lastResult = array('ok' => false, 'busy' => false, 'preserved' => array(), 'errors' => array($error->getMessage()));
        }
        $this->journal = null;
        $this->release();
        return $this->lastResult;
    }

    /** Recover an interrupted request, only after proving its lock is free. */
    public static function recover($siteRoot)
    {
        $lease = null;
        try {
            $lease = new self($siteRoot);
            $lease->acquire();
            return $lease->recoverLocked();
        } catch (Throwable $error) {
            return array('ok' => false, 'busy' => $error->getCode() === 409, 'preserved' => array(), 'errors' => array($error->getMessage()));
        } finally {
            if ($lease !== null)
                $lease->release();
        }
    }

    private static function optionNames()
    {
        // Restore public access first, then HTML, then compressed assets.
        return array('sys_maintenance_mode', 'sys_maintenance_mode_html', 'sys_template_cache_compress_enable');
    }

    private function readOption($name)
    {
        $row = BxDolDb::getInstance()->getRow(
            'SELECT `value` FROM `sys_options` WHERE `name` = ? LIMIT 1', array($name)
        );
        if (!is_array($row) || !array_key_exists('value', $row))
            throw new RuntimeException('Required native maintenance option is unavailable: ' . $name);
        return (string)$row['value'];
    }

    private function writeOption($name, $value, $expected)
    {
        $db = BxDolDb::getInstance();
        // Studio saves do not take our file lock. Compare the exact stored
        // bytes atomically so a concurrent administrator edit always wins.
        $changed = $db->query(
            'UPDATE `sys_options` SET `value` = ? WHERE `name` = ? AND BINARY `value` = BINARY ? LIMIT 1',
            array($value, $name, $expected)
        );
        if ($changed === false)
            throw new RuntimeException('Native maintenance option could not be written: ' . $name);
        $db->cacheParams(true);
        if ((int)$changed === 0)
            return false;
        if ($this->readOption($name) !== $value)
            throw new RuntimeException('Native maintenance option could not be verified: ' . $name);
        return true;
    }

    private function acquire()
    {
        if (is_resource($this->lock))
            throw new RuntimeException('This maintenance lease is already active.');
        $path = $this->prefix . '.lock';
        if (is_link($path))
            throw new RuntimeException('Maintenance lock must be a regular private file.');
        $handle = @fopen($path, 'c+');
        if ($handle === false)
            throw new RuntimeException('Cannot create the private maintenance lock above the site root.');
        if (!@flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('Another My UNA operation currently owns site maintenance. Wait for it to finish.', 409);
        }
        if (!@chmod($path, 0600)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new RuntimeException('Cannot secure the private maintenance lock.');
        }
        $this->lock = $handle;
    }

    private function release()
    {
        if (is_resource($this->lock)) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
        }
        $this->lock = null;
    }

    private function writeJournal()
    {
        $path = $this->prefix . '.json';
        $temporary = $path . '.' . $this->journal['owner'] . '.tmp';
        $handle = @fopen($temporary, 'x+b');
        if ($handle === false)
            throw new RuntimeException('Cannot create private maintenance rollback journal.');
        try {
            if (!@chmod($temporary, 0600))
                throw new RuntimeException('Cannot secure maintenance rollback journal.');
            $json = json_encode($this->journal, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (fwrite($handle, $json) !== strlen($json) || !fflush($handle))
                throw new RuntimeException('Cannot persist maintenance rollback journal.');
            if (function_exists('fsync') && !fsync($handle))
                throw new RuntimeException('Cannot synchronize maintenance rollback journal.');
            fclose($handle);
            $handle = null;
            if (!@rename($temporary, $path))
                throw new RuntimeException('Cannot install maintenance rollback journal.');
            // Persist the rename itself before the database can be changed.
            $directory = @fopen(dirname($path), 'r');
            if ($directory === false)
                throw new RuntimeException('Cannot open the maintenance journal directory for synchronization.');
            try {
                if (function_exists('fsync') && !fsync($directory))
                    throw new RuntimeException('Cannot synchronize the maintenance journal directory.');
            } finally {
                fclose($directory);
            }
        } finally {
            if (is_resource($handle)) fclose($handle);
            if (is_file($temporary)) @unlink($temporary);
        }
    }

    private function recoverLocked()
    {
        $path = $this->prefix . '.json';
        if (!file_exists($path))
            return array('ok' => true, 'busy' => false, 'preserved' => array(), 'errors' => array());
        if (is_link($path))
            throw new RuntimeException('Maintenance rollback journal must be a private regular file.');
        $journal = json_decode((string)file_get_contents($path), true);
        if (!is_array($journal) || ($journal['schema'] ?? null) !== 2
            || ($journal['site_root'] ?? null) !== $this->siteRoot
            || !is_string($journal['owner'] ?? null) || $journal['owner'] === ''
            || !is_array($journal['applied'] ?? null)
            || array_diff($journal['applied'], self::optionNames())
            || !array_key_exists('pending', $journal)
            || ($journal['pending'] !== null && !in_array($journal['pending'], self::optionNames(), true)))
            throw new RuntimeException('Maintenance rollback journal is invalid; manual review is required.');
        foreach (array('original', 'expected') as $section) {
            foreach (self::optionNames() as $name) {
                if (!isset($journal[$section][$name]) || !is_string($journal[$section][$name]))
                    throw new RuntimeException('Maintenance rollback options are invalid; manual review is required.');
            }
        }
        return $this->restore($journal);
    }

    private function restore(array $journal)
    {
        $result = array('ok' => true, 'busy' => false, 'preserved' => array(), 'errors' => array());
        foreach (self::optionNames() as $name) {
            $original = $journal['original'][$name];
            $expected = $journal['expected'][$name];
            if ($original === $expected) continue;
            try {
                if (!in_array($name, $journal['applied'], true)) {
                    if (($journal['pending'] ?? null) !== $name)
                        continue;
                    // A process can die after a successful CAS but before its
                    // applied checkpoint. Unique mode/page values still prove
                    // ownership; an empty compression value cannot, so leave
                    // ambiguous direct-asset configuration safely in place.
                    if ($name === 'sys_template_cache_compress_enable') {
                        if ($this->readOption($name) !== $original)
                            $result['preserved'][] = $name;
                        continue;
                    }
                }
                // If reopening an originally public site failed, keep direct
                // Studio assets until a later recovery can restore the switch.
                if ($name === 'sys_template_cache_compress_enable'
                    && $journal['original']['sys_maintenance_mode'] === ''
                    && !$result['ok']
                    && $this->readOption('sys_maintenance_mode') !== '')
                    continue;
                $current = $this->readOption($name);
                if ($current === $original) continue;
                if ($current !== $expected) {
                    $result['preserved'][] = $name;
                    continue;
                }
                if (!$this->writeOption($name, $original, $expected))
                    $result['preserved'][] = $name;
            } catch (Throwable $error) {
                $result['ok'] = false;
                $result['errors'][] = $error->getMessage();
            }
        }
        if ($result['ok'] && file_exists($this->prefix . '.json') && !@unlink($this->prefix . '.json')) {
            $result['ok'] = false;
            $result['errors'][] = 'Cannot remove the restored maintenance rollback journal.';
        }
        return $result;
    }
}
