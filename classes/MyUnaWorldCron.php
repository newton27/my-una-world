<?php defined('BX_DOL') or die('hack attempt');

class MyUnaWorldCron extends BxDolCron
{
    public function processing()
    {
        $oModule = BxDolModule::getInstance('my_una_world');
        if (!$oModule)
            return;

        if (method_exists($oModule, 'touchBackgroundWorker'))
            $oModule->touchBackgroundWorker();

        try {
            $oModule->getBackupSchedule()->process();
        }
        catch (Throwable $e) {
            // A damaged schedule must not stop already queued manual backups.
            error_log('My UNA World schedule: ' . $e->getMessage());
        }

        if (method_exists($oModule, 'processPendingBackupJob'))
            $oModule->processPendingBackupJob();
    }
}
