<?php

define('BX_DOL', true);
define('BX_DOL_URL_ROOT', '/');

class BxDolModule
{
}

class BxDolAlertsResponse
{
}

function bx_get($sKey)
{
    return isset($_REQUEST[$sKey]) ? $_REQUEST[$sKey] : null;
}

function bx_is_dynamic_request()
{
    return bx_get('dynamic') ||
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strcasecmp((string)$_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0);
}

function bx_is_htmx_request()
{
    return !empty($_SERVER['HTTP_HX_REQUEST']);
}

function bx_is_api()
{
    return defined('BX_API') || bx_get('api');
}

$sAuthMode = isset($_SERVER['HTTP_X_MUW_TEST_AUTH'])
    ? (string)$_SERVER['HTTP_X_MUW_TEST_AUTH']
    : 'guest';

if ($sAuthMode !== 'missing') {
    $GLOBALS['muw_test_auth_mode'] = $sAuthMode;

    function isLogged()
    {
        if ($GLOBALS['muw_test_auth_mode'] === 'member')
            return true;
        if ($GLOBALS['muw_test_auth_mode'] === 'invalid')
            return 'unknown';
        return false;
    }
}

if (isset($_SERVER['HTTP_X_MUW_TEST_REQUEST_URI'])) {
    if ($_SERVER['HTTP_X_MUW_TEST_REQUEST_URI'] === '__MISSING__')
        unset($_SERVER['REQUEST_URI']);
    else
        $_SERVER['REQUEST_URI'] = (string)$_SERVER['HTTP_X_MUW_TEST_REQUEST_URI'];
}

require_once __DIR__ . '/../../classes/MyUnaWorldModule.php';

class VisitorGateHttpTestModule extends MyUnaWorldModule
{
    public $redirects = array();

    protected function _redirectGuestToHome()
    {
        $this->redirects[] = '/';
    }
}

$bBaseMode = isset($_SERVER['HTTP_X_MUW_TEST_MODE']) &&
    $_SERVER['HTTP_X_MUW_TEST_MODE'] === 'base';
$oModule = $bBaseMode ? new MyUnaWorldModule() : new VisitorGateHttpTestModule();
$sError = '';

try {
    $oModule->handleVisitorGate();
}
catch (Throwable $e) {
    http_response_code(500);
    $sError = get_class($e) . ': ' . $e->getMessage();
}

if ($bBaseMode) {
    header('Content-Type: text/plain');
    echo 'visitor-gate-continued';
    return;
}

$sTarget = count($oModule->redirects) > 0 ? (string)$oModule->redirects[0] : '';
header('Content-Type: application/json');
header('X-MUW-Test-Redirect: ' . ($sTarget !== '' ? '1' : '0'));
header('X-MUW-Test-Target: ' . rawurlencode($sTarget));
if ($sError !== '')
    header('X-MUW-Test-Error: ' . rawurlencode($sError));

echo json_encode(array(
    'redirect' => $sTarget,
    'error' => $sError,
), JSON_UNESCAPED_SLASHES);
