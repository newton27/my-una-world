<?php defined('BX_DOL') or die('hack attempt');

/**
 * Automatic module lifecycle safety backups.
 *
 * UNA Studio emits system/before_install and system/before_uninstall before
 * changing a module. My UNA World subscribes to those official alerts.
 */
class MyUnaWorldAlertsResponse extends BxDolAlertsResponse
{
    public function response($oAlert)
    {
        if (!$oAlert || $oAlert->sUnit !== 'system')
            return;

        if ($oAlert->sAction !== 'before_install' && $oAlert->sAction !== 'before_uninstall')
            return;

        $oModule = BxDolModule::getInstance('my_una_world');
        if (!$oModule || !method_exists($oModule, 'handleModuleLifecycleAlert'))
            return;

        $oModule->handleModuleLifecycleAlert($oAlert);
    }
}
