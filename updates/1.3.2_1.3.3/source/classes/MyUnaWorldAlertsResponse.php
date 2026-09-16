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

        $oModule = BxDolModule::getInstance('my_una_world');
        if (!$oModule)
            return;

        if ($oAlert->sAction === 'before_install' || $oAlert->sAction === 'before_uninstall') {
            if (method_exists($oModule, 'handleModuleLifecycleAlert'))
                $oModule->handleModuleLifecycleAlert($oAlert);
            return;
        }

        if ($oAlert->sAction === 'design_included' && method_exists($oModule, 'handleVisitorGate'))
            $oModule->handleVisitorGate();
    }
}
