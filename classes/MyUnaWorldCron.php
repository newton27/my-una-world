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

        if (method_exists($oModule, 'processPendingBackupJob'))
            $oModule->processPendingBackupJob();
    }
}
