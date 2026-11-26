<?php defined('BX_DOL') or die('hack attempt');

/** Recurring full backups use the existing native worker and private job queue. */
class MyUnaWorldSchedule
{
    protected $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    protected function path()
    {
        return rtrim($this->module->getBackupRoot(), DIRECTORY_SEPARATOR) . '/.backup-schedule.json';
    }

    protected function defaults()
    {
        return array('enabled' => false, 'frequency' => 'daily', 'hours' => 48,
            'start_date' => date('Y-m-d'), 'start_time' => '03:00',
            'timezone' => date_default_timezone_get(), 'next_run' => 0,
            'last_job_id' => 0, 'last_queued_at' => 0, 'last_error' => '');
    }

    public function getSettings()
    {
        if (!is_file($this->path()))
            return $this->defaults();
        $data = json_decode((string)file_get_contents($this->path()), true);
        if (!is_array($data))
            return array_merge($this->defaults(), array('last_error' => 'Backup schedule is unreadable and disabled. Save a new schedule to repair it.'));
        return array_merge($this->defaults(), $data);
    }

    protected function locked($callback)
    {
        $lock = fopen($this->path() . '.lock', 'c');
        if (!$lock)
            throw new RuntimeException('Unable to open backup schedule lock.');
        try {
            if (!flock($lock, LOCK_EX))
                throw new RuntimeException('Unable to lock backup schedule.');
            return $callback();
        }
        finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected function write(array $data)
    {
        $temp = $this->path() . '.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || file_put_contents($temp, $json . PHP_EOL, LOCK_EX) === false)
            throw new RuntimeException('Unable to save backup schedule.');
        chmod($temp, 0600);
        if (!rename($temp, $this->path()))
            throw new RuntimeException('Unable to replace backup schedule.');
    }

    public function save(array $input, $now = null)
    {
        $now = $now === null ? time() : (int)$now;
        $data = array_merge($this->defaults(), array_intersect_key($input, array_flip(array(
            'enabled', 'frequency', 'hours', 'start_date', 'start_time', 'timezone'))));
        $data['enabled'] = !empty($data['enabled']);
        if (!in_array($data['frequency'], array('daily', 'weekly', '48hours', 'custom'), true))
            throw new InvalidArgumentException('Choose a valid backup frequency.');
        if (filter_var($data['hours'], FILTER_VALIDATE_INT) === false || $data['hours'] < 1 || $data['hours'] > 8760)
            throw new InvalidArgumentException('Custom hours must be a whole number from 1 to 8760.');
        $data['hours'] = (int)$data['hours'];
        self::anchor($data);
        return $this->locked(function () use ($data, $now) {
            try { $current = $this->getSettings(); }
            catch (RuntimeException $e) { $current = $this->defaults(); }
            $changed = false;
            foreach (array('enabled', 'frequency', 'hours', 'start_date', 'start_time', 'timezone') as $key)
                if ($current[$key] !== $data[$key])
                    $changed = true;
            $saved = array_merge($current, array_intersect_key($data, array_flip(array(
                'enabled', 'frequency', 'hours', 'start_date', 'start_time', 'timezone'))));
            $saved['last_error'] = '';
            if ($changed || ($saved['enabled'] && !$saved['next_run'])) {
                $saved['next_run'] = $saved['enabled'] ? self::nextRun($saved, $now) : 0;
                $saved['last_error'] = '';
            }
            $this->write($saved);
            return $saved;
        });
    }

    protected static function anchor(array $settings)
    {
        if (!is_string($settings['timezone']) || !in_array($settings['timezone'], DateTimeZone::listIdentifiers(), true))
            throw new InvalidArgumentException('Choose a valid timezone.');
        $text = $settings['start_date'] . ' ' . $settings['start_time'];
        $anchor = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $text, new DateTimeZone($settings['timezone']));
        if (!$anchor || $anchor->format('Y-m-d H:i') !== $text)
            throw new InvalidArgumentException('Choose a valid start date and time in that timezone.');
        return $anchor;
    }

    /** First slot strictly after now; calendar days preserve local time across DST. */
    public static function nextRun(array $settings, $now)
    {
        $anchor = self::anchor($settings);
        if ($anchor->getTimestamp() > $now)
            return $anchor->getTimestamp();
        if (in_array($settings['frequency'], array('48hours', 'custom'), true)) {
            $seconds = ($settings['frequency'] === '48hours' ? 48 : (int)$settings['hours']) * 3600;
            if ($seconds < 3600)
                throw new InvalidArgumentException('Invalid backup interval.');
            return $anchor->getTimestamp() + ((int)floor(($now - $anchor->getTimestamp()) / $seconds) + 1) * $seconds;
        }
        $localNow = (new DateTimeImmutable('@' . $now))->setTimezone($anchor->getTimezone());
        $days = (int)$anchor->setTime(0, 0)->diff($localNow->setTime(0, 0))->format('%a');
        $step = $settings['frequency'] === 'weekly' ? 7 : 1;
        $days = (int)floor($days / $step) * $step;
        do {
            $candidate = $anchor->modify('+' . $days . ' days');
            $days += $step;
        } while ($candidate->getTimestamp() <= $now);
        return $candidate->getTimestamp();
    }

    public function process($now = null)
    {
        $now = $now === null ? time() : (int)$now;
        return $this->locked(function () use ($now) {
            $settings = $this->getSettings();
            if (!$settings['enabled'] || !$settings['next_run'] || $settings['next_run'] > $now)
                return false;
            try {
                $slot = hash('sha256', json_encode(array($settings['frequency'], $settings['hours'],
                    $settings['start_date'], $settings['start_time'], $settings['timezone'], $settings['next_run'])));
                $job = $this->module->queueScheduledBackup($slot);
                if (empty($job['id']))
                    throw new RuntimeException('Scheduled backup was not queued.');
                $settings['last_job_id'] = (int)$job['id'];
                $settings['last_queued_at'] = $now;
                // After downtime, queue one catch-up backup and skip older missed slots.
                $settings['next_run'] = self::nextRun($settings, $now);
                $settings['last_error'] = '';
                $this->write($settings);
                return $job;
            }
            catch (Throwable $e) {
                $settings['last_error'] = $e->getMessage();
                $this->write($settings);
                return false; // Busy queues retain the due slot and retry next minute.
            }
        });
    }
}
