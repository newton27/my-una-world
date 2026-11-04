<?php

define('BX_DOL', true);
define('BX_DOL_URL_ROOT', '/');

class BxDolModule
{
    public static $testInstance;

    public static function getInstance($sName)
    {
        return $sName === 'my_una_world' ? self::$testInstance : null;
    }
}

class BxTemplStudioModule
{
    public $sManageUrl = '/studio/module.php?name=my_una_world';
    public $sPageDefault = '';
    public $aMenuItems = array();

    public function __construct($sModule, $mixedPageName, $sPage = '')
    {
    }
}

class BxDolStudioTemplate
{
    public static function getInstance()
    {
        static $oInstance;
        if (!$oInstance)
            $oInstance = new self();
        return $oInstance;
    }

    public function parseHtmlByName($sName, array $aVars)
    {
        return isset($aVars['content']) ? $aVars['content'] : '';
    }
}

class BxDolForm
{
    public static function getCsrfToken()
    {
        return 'test-csrf';
    }

    public static function isCsrfTokenValid($sToken)
    {
        return $sToken === 'test-csrf';
    }
}

function getParam($sName)
{
    return 'off';
}

function bx_append_url_params($sUrl, array $aParams)
{
    return $sUrl . (strpos($sUrl, '?') === false ? '?' : '&') . http_build_query($aParams);
}

$GLOBALS['muw_switch_translations'] = array();
$oLanguage = simplexml_load_file(__DIR__ . '/../install/langs/en.xml', 'SimpleXMLElement', LIBXML_NOCDATA);
if ($oLanguage !== false) {
    foreach ($oLanguage->string as $oString)
        $GLOBALS['muw_switch_translations'][(string)$oString['name']] = (string)$oString;
}

function _t($sKey)
{
    return isset($GLOBALS['muw_switch_translations'][$sKey])
        ? $GLOBALS['muw_switch_translations'][$sKey]
        : $sKey;
}

require_once __DIR__ . '/../classes/MyUnaWorldModule.php';
require_once __DIR__ . '/../classes/MyUnaWorldStudioPage.php';

class VisitorGateSettingsBackupStub
{
    private $root;

    public function __construct($sRoot)
    {
        $this->root = $sRoot;
    }

    public function getBackupRoot()
    {
        return $this->root;
    }
}

class VisitorGateSettingsModule extends MyUnaWorldModule
{
    private $settingsRoot;

    public function __construct($sRoot)
    {
        $this->settingsRoot = $sRoot;
    }

    protected function _getBackupEngine()
    {
        return new VisitorGateSettingsBackupStub($this->settingsRoot);
    }

    public function getLifecycleStatus()
    {
        return array(
            'settings' => $this->getLifecycleSettings(),
            'settings_file_exists' => is_file($this->settingsRoot . '/.module_lifecycle_settings.json'),
            'alerts_handler_registered' => true,
            'before_install_hook_registered' => true,
            'before_uninstall_hook_registered' => true,
        );
    }

    public function getBackgroundWorkerStatus()
    {
        return array('registered' => true, 'last_run_iso' => '', 'healthy' => true);
    }
}

class VisitorGateSettingsStudioPage extends MyUnaWorldStudioPage
{
    public function renderSettings()
    {
        return $this->getSettings();
    }
}

$iAssertions = 0;
$aFailures = array();

function muw_switch_assert_same($mExpected, $mActual, $sLabel)
{
    global $iAssertions, $aFailures;
    $iAssertions++;
    if ($mExpected === $mActual)
        return;

    $aFailures[] = $sLabel . ': expected ' . var_export($mExpected, true) .
        ', got ' . var_export($mActual, true);
}

function muw_switch_checkbox_checked($sHtml, $sName)
{
    $oDom = new DOMDocument();
    $bPrevious = libxml_use_internal_errors(true);
    $oDom->loadHTML($sHtml);
    libxml_clear_errors();
    libxml_use_internal_errors($bPrevious);

    $oXpath = new DOMXPath($oDom);
    $oInputs = $oXpath->query('//input[@type="checkbox" and @name="' . $sName . '"]');
    if (!$oInputs || $oInputs->length !== 1)
        return null;

    return $oInputs->item(0)->hasAttribute('checked');
}

function muw_switch_post_settings(VisitorGateSettingsModule $oModule, array $aPost)
{
    BxDolModule::$testInstance = $oModule;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = array_merge(array(
        'csrf' => 'test-csrf',
        'muw_action' => 'save_settings',
    ), $aPost);

    $oPage = new VisitorGateSettingsStudioPage('my_una_world', 'settings', 'settings');
    return $oPage->renderSettings();
}

$sTempRoot = sys_get_temp_dir() . '/muw-visitor-gate-switch-' . getmypid() . '-' . bin2hex(random_bytes(4));
if (!mkdir($sTempRoot, 0700, true)) {
    fwrite(STDERR, "Unable to create Visitor Gate switch test directory.\n");
    exit(1);
}

try {
    $oModule = new VisitorGateSettingsModule($sTempRoot);

    $aDefaults = $oModule->getLifecycleSettings();
    muw_switch_assert_same(true, isset($aDefaults['visitor_gate_enabled']) ? $aDefaults['visitor_gate_enabled'] : null, 'missing setting defaults Visitor Gate ON');

    file_put_contents($sTempRoot . '/.module_lifecycle_settings.json', json_encode(array(
        'backup_before_install' => false,
        'backup_before_uninstall' => true,
        'block_on_failure' => false,
        'upgrade_dedupe_seconds' => 900,
        'retention_max_archives' => 17,
        'retention_max_age_days' => 45,
    )));
    $aLegacy = $oModule->getLifecycleSettings();
    muw_switch_assert_same(true, isset($aLegacy['visitor_gate_enabled']) ? $aLegacy['visitor_gate_enabled'] : null, 'legacy settings JSON without switch defaults Visitor Gate ON');
    muw_switch_assert_same(900, $aLegacy['upgrade_dedupe_seconds'], 'legacy settings preserve upgrade dedupe');

    $aOriginal = $oModule->saveLifecycleSettings(array(
        'visitor_gate_enabled' => false,
        'backup_before_install' => false,
        'backup_before_uninstall' => true,
        'block_on_failure' => false,
        'retention_max_archives' => 17,
        'retention_max_age_days' => 45,
    ));
    $aReloaded = $oModule->getLifecycleSettings();
    muw_switch_assert_same(false, isset($aReloaded['visitor_gate_enabled']) ? $aReloaded['visitor_gate_enabled'] : null, 'explicit OFF survives real JSON save and load');
    muw_switch_assert_same(false, $aReloaded['backup_before_install'], 'saving OFF preserves backup-before-install setting');
    muw_switch_assert_same(true, $aReloaded['backup_before_uninstall'], 'saving OFF preserves backup-before-uninstall setting');
    muw_switch_assert_same(false, $aReloaded['block_on_failure'], 'saving OFF preserves block-on-failure setting');
    muw_switch_assert_same(17, $aReloaded['retention_max_archives'], 'saving OFF preserves archive retention setting');
    muw_switch_assert_same(45, $aReloaded['retention_max_age_days'], 'saving OFF preserves age retention setting');
    muw_switch_assert_same(900, $aReloaded['upgrade_dedupe_seconds'], 'saving OFF preserves upgrade dedupe');

    BxDolModule::$testInstance = $oModule;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = array();
    $oOffPage = new VisitorGateSettingsStudioPage('my_una_world', 'settings', 'settings');
    $sOffHtml = $oOffPage->renderSettings();
    $contrastDom = new DOMDocument();
    @$contrastDom->loadHTML($sOffHtml);
    $contrastInputs = (new DOMXPath($contrastDom))->query('//input[@type="text" or @type="number"]');
    muw_switch_assert_same(7, $contrastInputs->length, 'all seven Settings entry fields are covered');
    foreach ($contrastInputs as $contrastInput) {
        $classes = explode(' ', $contrastInput->getAttribute('class'));
        muw_switch_assert_same(true, in_array('bx-form-input-text', $classes) && in_array('bx-def-font-inputs', $classes), 'Settings field uses native theme colors: ' . $contrastInput->getAttribute('name'));
    }

    muw_switch_assert_same(false, muw_switch_checkbox_checked($sOffHtml, 'visitor_gate_enabled'), 'Studio renders Visitor Gate unchecked when OFF');
    muw_switch_assert_same(true, strpos($sOffHtml, 'Enable Visitor Gate') !== false, 'Studio renders exact Visitor Gate label from language key');
    muw_switch_assert_same(true, strpos($sOffHtml, 'Restricts logged-out visitors to Home, Login, Create Account, Forgot Password, Terms, and Privacy. Logged-in members and administrators remain unrestricted.') !== false, 'Studio renders exact Visitor Gate description from language key');

    file_put_contents($sTempRoot . '/.module_lifecycle_settings.json', json_encode(array(
        'visitor_gate_enabled' => true,
        'backup_before_install' => false,
        'backup_before_uninstall' => true,
        'block_on_failure' => false,
        'upgrade_dedupe_seconds' => 600,
        'retention_max_archives' => 17,
        'retention_max_age_days' => 45,
    )));

    muw_switch_post_settings($oModule, array(
        'backup_before_uninstall' => '1',
        'retention_max_archives' => '17',
        'retention_max_age_days' => '45',
    ));
    $aAfterOffPost = $oModule->getLifecycleSettings();
    muw_switch_assert_same(false, isset($aAfterOffPost['visitor_gate_enabled']) ? $aAfterOffPost['visitor_gate_enabled'] : null, 'unchecked Studio checkbox saves Visitor Gate OFF');
    muw_switch_assert_same(false, $aAfterOffPost['backup_before_install'], 'Studio OFF save preserves unchecked backup-before-install');
    muw_switch_assert_same(true, $aAfterOffPost['backup_before_uninstall'], 'Studio OFF save preserves checked backup-before-uninstall');
    muw_switch_assert_same(false, $aAfterOffPost['block_on_failure'], 'Studio OFF save preserves unchecked block-on-failure');
    muw_switch_assert_same(17, $aAfterOffPost['retention_max_archives'], 'Studio OFF save preserves archive retention');
    muw_switch_assert_same(45, $aAfterOffPost['retention_max_age_days'], 'Studio OFF save preserves age retention');
    muw_switch_assert_same(600, $aAfterOffPost['upgrade_dedupe_seconds'], 'Studio OFF save preserves custom upgrade dedupe');

    muw_switch_post_settings($oModule, array(
        'visitor_gate_enabled' => '1',
        'backup_before_uninstall' => '1',
        'retention_max_archives' => '17',
        'retention_max_age_days' => '45',
    ));
    $aAfterOnPost = $oModule->getLifecycleSettings();
    muw_switch_assert_same(true, isset($aAfterOnPost['visitor_gate_enabled']) ? $aAfterOnPost['visitor_gate_enabled'] : null, 'checked Studio checkbox restores Visitor Gate ON');
    muw_switch_assert_same(600, $aAfterOnPost['upgrade_dedupe_seconds'], 'Studio ON save preserves custom upgrade dedupe');

    BxDolModule::$testInstance = $oModule;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = array();
    $oOnPage = new VisitorGateSettingsStudioPage('my_una_world', 'settings', 'settings');
    muw_switch_assert_same(true, muw_switch_checkbox_checked($oOnPage->renderSettings(), 'visitor_gate_enabled'), 'Studio renders Visitor Gate checked when ON');
}
finally {
    $sSettingsPath = $sTempRoot . '/.module_lifecycle_settings.json';
    if (is_file($sSettingsPath))
        unlink($sSettingsPath);
    rmdir($sTempRoot);
}

if ($aFailures) {
    fwrite(STDERR, "Visitor Gate switch assertions failed (" . count($aFailures) . '/' . $iAssertions . "):\n- " . implode("\n- ", $aFailures) . "\n");
    exit(1);
}

fwrite(STDOUT, 'Visitor Gate switch assertions passed: ' . $iAssertions . "\n");
