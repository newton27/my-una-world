<?php defined('BX_DOL') or die('hack attempt');

/** Resolve recorded evidence only; legacy manifest times precede verification. */
class MyUnaWorldBackupTiming
{
    public static function resolve(array $record, array $verification = array())
    {
        $start = isset($record['created_at']) ? max(0, (int)$record['created_at']) : 0;
        $result = array('started_at'=>$start ?: null, 'completed_at'=>null, 'duration_seconds'=>null, 'phases'=>array(), 'slowest_tables'=>array());
        if ($start <= 0 || !isset($record['status']) || $record['status'] !== 'complete')
            return $result;

        $workerEnd = isset($record['worker_completed_at']) ? (int)$record['worker_completed_at'] : 0;
        if ($workerEnd >= $start) {
            $result['completed_at'] = $workerEnd;
            $result['duration_seconds'] = $workerEnd - $start;
        }

        $digest = isset($record['sha256']) ? strtolower((string)$record['sha256']) : '';
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $digest) ||
            !isset($verification['archive_name'], $verification['sha256'], $verification['integrity'], $verification['timing']) ||
            $verification['archive_name'] !== ($record['archive_name'] ?? '') ||
            !is_string($verification['sha256']) || !hash_equals($digest, strtolower($verification['sha256'])) ||
            $verification['integrity'] !== 'passed' || !is_array($verification['timing']))
            return $result;

        $timing = $verification['timing'];
        if (($timing['version'] ?? null) !== 1 || !is_int($timing['started_at'] ?? null) ||
            !is_int($timing['completed_at'] ?? null) || $timing['started_at'] !== $start || $timing['completed_at'] < $start)
            return $result;

        $result['completed_at'] = $timing['completed_at'];
        $result['duration_seconds'] = $timing['completed_at'] - $start;
        $allowed = array('preparing','inventory','database','module_files','storage','archive','validation','checksum');
        foreach (isset($timing['phases']) && is_array($timing['phases']) ? $timing['phases'] : array() as $phase=>$seconds) {
            if (in_array($phase,$allowed,true) && (is_int($seconds) || is_float($seconds)) && is_finite((float)$seconds) && $seconds >= 0)
                $result['phases'][$phase] = (float)$seconds;
        }
        foreach (isset($timing['slowest_tables']) && is_array($timing['slowest_tables']) ? array_slice($timing['slowest_tables'],0,10) : array() as $table) {
            if (is_array($table) && is_string($table['table'] ?? null) && strlen($table['table']) <= 255 &&
                is_int($table['rows'] ?? null) && $table['rows'] >= 0 &&
                (is_int($table['seconds'] ?? null) || is_float($table['seconds'] ?? null)) && is_finite((float)$table['seconds']) && $table['seconds'] >= 0)
                $result['slowest_tables'][] = $table;
        }
        return $result;
    }

    public static function timestamp($value)
    {
        return is_int($value) && $value > 0 ? gmdate('Y-m-d H:i:s', $value) . ' UTC' : 'Not recorded';
    }

    public static function duration($value)
    {
        if (!is_numeric($value) || !is_finite((float)$value) || $value < 0)
            return 'Not recorded';
        $seconds = (int)$value;
        return intdiv($seconds,3600) . 'h ' . sprintf('%02dm %02ds',intdiv($seconds % 3600,60),$seconds % 60);
    }
}
