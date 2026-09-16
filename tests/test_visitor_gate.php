<?php

define('BX_DOL', true);

class BxDolModule
{
    public static $testInstance;

    public static function getInstance($sName)
    {
        return $sName === 'my_una_world' ? self::$testInstance : null;
    }
}

class BxDolAlertsResponse
{
}

$GLOBALS['muw_test_logged'] = false;

function isLogged()
{
    return !empty($GLOBALS['muw_test_logged']);
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

require_once __DIR__ . '/../classes/MyUnaWorldModule.php';
require_once __DIR__ . '/../classes/MyUnaWorldAlertsResponse.php';

class VisitorGateCliTestModule extends MyUnaWorldModule
{
    public $redirects = array();

    protected function _redirectGuestToHome()
    {
        $this->redirects[] = '/';
    }
}

class VisitorGateDispatchSpy
{
    public $lifecycleCalls = 0;
    public $visitorGateCalls = 0;

    public function handleModuleLifecycleAlert($oAlert)
    {
        $this->lifecycleCalls++;
    }

    public function handleVisitorGate()
    {
        $this->visitorGateCalls++;
    }
}

$iAssertions = 0;
$aFailures = array();

function muw_assert_same($mExpected, $mActual, $sLabel)
{
    global $iAssertions, $aFailures;
    $iAssertions++;
    if ($mExpected === $mActual)
        return;

    $aFailures[] = $sLabel . ': expected ' . var_export($mExpected, true) .
        ', got ' . var_export($mActual, true);
}

function muw_alert($sUnit, $sAction)
{
    return (object)array('sUnit' => $sUnit, 'sAction' => $sAction);
}

function muw_find_free_port()
{
    $hSocket = @stream_socket_server('tcp://127.0.0.1:0', $iError, $sError);
    if (!$hSocket)
        throw new RuntimeException('Unable to reserve loopback port: ' . $sError);

    $sAddress = stream_socket_get_name($hSocket, false);
    fclose($hSocket);
    return (int)substr(strrchr($sAddress, ':'), 1);
}

function muw_http_request($iPort, $sMethod, $sUri, array $aHeaders = array())
{
    $hSocket = @stream_socket_client(
        'tcp://127.0.0.1:' . $iPort,
        $iError,
        $sError,
        2
    );
    if (!$hSocket)
        throw new RuntimeException('Unable to reach visitor-gate fixture: ' . $sError);

    $aRequestHeaders = array_merge(array(
        'Host' => '127.0.0.1:' . $iPort,
        'Connection' => 'close',
    ), $aHeaders);

    $sRequest = $sMethod . ' ' . $sUri . " HTTP/1.1\r\n";
    foreach ($aRequestHeaders as $sName => $sValue)
        $sRequest .= $sName . ': ' . $sValue . "\r\n";
    $sRequest .= "\r\n";

    fwrite($hSocket, $sRequest);
    stream_set_timeout($hSocket, 3);
    $sResponse = stream_get_contents($hSocket);
    fclose($hSocket);

    list($sRawHeaders, $sBody) = array_pad(explode("\r\n\r\n", $sResponse, 2), 2, '');
    $aLines = explode("\r\n", $sRawHeaders);
    $sStatus = array_shift($aLines);
    $aResponseHeaders = array();
    foreach ($aLines as $sLine) {
        if (strpos($sLine, ':') === false)
            continue;
        list($sName, $sValue) = explode(':', $sLine, 2);
        $aResponseHeaders[strtolower(trim($sName))] = trim($sValue);
    }

    return array(
        'status' => $sStatus,
        'headers' => $aResponseHeaders,
        'body' => $sBody,
    );
}

function muw_wait_for_server($iPort)
{
    for ($iAttempt = 0; $iAttempt < 50; $iAttempt++) {
        $hSocket = @stream_socket_client('tcp://127.0.0.1:' . $iPort, $iError, $sError, 0.1);
        if ($hSocket) {
            fclose($hSocket);
            return;
        }
        usleep(20000);
    }
    throw new RuntimeException('PHP fixture server did not start.');
}

function muw_assert_http_redirect($iPort, $sMethod, $sUri, $sExpected, $sLabel, array $aHeaders = array())
{
    $aResponse = muw_http_request($iPort, $sMethod, $sUri, $aHeaders);
    $sError = isset($aResponse['headers']['x-muw-test-error'])
        ? rawurldecode($aResponse['headers']['x-muw-test-error'])
        : '';
    $sActual = isset($aResponse['headers']['x-muw-test-target'])
        ? rawurldecode($aResponse['headers']['x-muw-test-target'])
        : null;

    if ($sError !== '')
        $sActual = '[fixture error] ' . $sError;
    muw_assert_same($sExpected, $sActual, $sLabel);
}

// A broken or absent CLI guard would redirect this blocked path in the test process.
$bGateAvailable = method_exists('MyUnaWorldModule', 'handleVisitorGate');
if ($bGateAvailable) {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/groups-home';
    $_SERVER['HTTP_SEC_FETCH_MODE'] = 'navigate';
    $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
    $oCliModule = new VisitorGateCliTestModule();
    $oCliModule->handleVisitorGate();
    muw_assert_same(array(), $oCliModule->redirects, 'CLI execution bypasses the visitor gate');
}
else {
    muw_assert_same(true, false, 'MyUnaWorldModule exposes handleVisitorGate');
}

if ($bGateAvailable) {
    $iPort = muw_find_free_port();
    $sRouter = __DIR__ . '/fixtures/visitor_gate_http.php';
    $aCommand = array(PHP_BINARY, '-d', 'display_errors=1', '-S', '127.0.0.1:' . $iPort, $sRouter);
    $aPipes = array();
    $hServer = proc_open(
        $aCommand,
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
        $aPipes,
        dirname(__DIR__)
    );

    if (!is_resource($hServer)) {
        $aFailures[] = 'Unable to start PHP visitor-gate fixture server.';
    }
    else {
        fclose($aPipes[0]);
        stream_set_blocking($aPipes[1], false);
        stream_set_blocking($aPipes[2], false);

        try {
            muw_wait_for_server($iPort);

        $aNavigationHeaders = array(
            'Accept' => 'text/html,application/xhtml+xml',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Dest' => 'document',
            'X-MUW-Test-Auth' => 'guest',
        );

        $aGuestCases = array(
            array('/', ''),
            array('/login', ''),
            array('/login/', ''),
            array('/member.php', ''),
            array('/member.php/', '/'),
            array('/create-account', ''),
            array('/create-account/', ''),
            array('/forgot-password', ''),
            array('/forgot-password/', ''),
            array('/terms', ''),
            array('/terms/', ''),
            array('/terms//', '/'),
            array('/privacy', ''),
            array('/privacy/', ''),
            array('/groups-home', '/'),
            array('/m/artificer/', '/'),
            array('/terms-extra', '/'),
            array('/privacy/archive', '/'),
            array('/this-page-should-block', '/'),
            array('/terms?lang=ru', ''),
            array('/groups-home?next=/terms', '/'),
        );
        foreach ($aGuestCases as $aCase) {
            muw_assert_http_redirect(
                $iPort,
                'GET',
                $aCase[0],
                $aCase[1],
                'guest GET ' . $aCase[0],
                $aNavigationHeaders
            );
        }

        muw_assert_http_redirect(
            $iPort,
            'HEAD',
            '/groups-home',
            '/',
            'guest HEAD navigation is gated',
            $aNavigationHeaders
        );

        muw_assert_http_redirect(
            $iPort,
            'GET',
            '/groups-home',
            '/',
            'legacy text/html navigation is gated',
            array('Accept' => 'text/html', 'X-MUW-Test-Auth' => 'guest')
        );

        $aIframeHeaders = $aNavigationHeaders;
        $aIframeHeaders['Sec-Fetch-Dest'] = 'iframe';
        muw_assert_http_redirect(
            $iPort,
            'GET',
            '/groups-home',
            '/',
            'iframe navigation is gated',
            $aIframeHeaders
        );

        foreach (array('/member.php', '/create-account', '/forgot-password') as $sPath) {
            muw_assert_http_redirect(
                $iPort,
                'POST',
                $sPath,
                '',
                'public form POST remains available: ' . $sPath,
                $aNavigationHeaders
            );
        }

        foreach (array('/groups-home', '/m/artificer/', '/studio/', '/account') as $sPath) {
            $aMemberHeaders = $aNavigationHeaders;
            $aMemberHeaders['X-MUW-Test-Auth'] = 'member';
            muw_assert_http_redirect(
                $iPort,
                'GET',
                $sPath,
                '',
                'authenticated member bypasses gate: ' . $sPath,
                $aMemberHeaders
            );
        }

        $aBypassCases = array(
            array('GET', '/groups-home', array('X-Requested-With' => 'XMLHttpRequest'), 'standard AJAX'),
            array('GET', '/groups-home?dynamic=1', array(), 'UNA dynamic request'),
            array('GET', '/groups-home?api=1', array(), 'UNA API request'),
            array('GET', '/groups-home', array('HX-Request' => 'true'), 'HTMX request'),
            array('POST', '/groups-home', array(), 'non-public POST'),
            array('PUT', '/groups-home', array(), 'PUT request'),
            array('GET', '/groups-home', array('Accept' => 'application/json', 'Sec-Fetch-Mode' => 'cors', 'Sec-Fetch-Dest' => 'empty'), 'JSON fetch'),
            array('GET', '/logo.png', array('Accept' => 'image/avif,image/webp', 'Sec-Fetch-Mode' => 'no-cors', 'Sec-Fetch-Dest' => 'image'), 'image fetch'),
            array('GET', '/groups-home', array('Accept' => '*/*', 'Sec-Fetch-Mode' => '', 'Sec-Fetch-Dest' => ''), 'ambiguous browser request'),
        );
        foreach ($aBypassCases as $aCase) {
            $aHeaders = $aNavigationHeaders;
            foreach ($aCase[2] as $sName => $sValue)
                $aHeaders[$sName] = $sValue;
            muw_assert_http_redirect($iPort, $aCase[0], $aCase[1], '', $aCase[3] . ' bypasses gate', $aHeaders);
        }

        $aMissingAuthHeaders = $aNavigationHeaders;
        $aMissingAuthHeaders['X-MUW-Test-Auth'] = 'missing';
        muw_assert_http_redirect($iPort, 'GET', '/groups-home', '', 'missing authentication helper fails open', $aMissingAuthHeaders);

        $aInvalidAuthHeaders = $aNavigationHeaders;
        $aInvalidAuthHeaders['X-MUW-Test-Auth'] = 'invalid';
        muw_assert_http_redirect($iPort, 'GET', '/groups-home', '', 'invalid authentication result fails open', $aInvalidAuthHeaders);

        foreach (array('__MISSING__', 'http://[') as $sUriOverride) {
            $aUriHeaders = $aNavigationHeaders;
            $aUriHeaders['X-MUW-Test-Request-URI'] = $sUriOverride;
            muw_assert_http_redirect(
                $iPort,
                'GET',
                '/groups-home',
                '',
                ($sUriOverride === '__MISSING__' ? 'missing' : 'invalid') . ' request URI fails open',
                $aUriHeaders
            );
        }
        }
        catch (Throwable $e) {
            $aFailures[] = 'HTTP fixture failure: ' . $e->getMessage();
        }
        finally {
            proc_terminate($hServer);
            foreach ($aPipes as $hPipe) {
                if (is_resource($hPipe))
                    fclose($hPipe);
            }
            proc_close($hServer);
        }
    }
}

$oSpy = new VisitorGateDispatchSpy();
BxDolModule::$testInstance = $oSpy;
$oResponse = new MyUnaWorldAlertsResponse();

$oResponse->response(muw_alert('system', 'before_install'));
muw_assert_same(1, $oSpy->lifecycleCalls, 'before_install dispatches lifecycle handler once');
muw_assert_same(0, $oSpy->visitorGateCalls, 'before_install does not dispatch visitor gate');

$oResponse->response(muw_alert('system', 'before_uninstall'));
muw_assert_same(2, $oSpy->lifecycleCalls, 'before_uninstall dispatches lifecycle handler once');
muw_assert_same(0, $oSpy->visitorGateCalls, 'before_uninstall does not dispatch visitor gate');

$oResponse->response(muw_alert('system', 'design_included'));
muw_assert_same(2, $oSpy->lifecycleCalls, 'design_included does not dispatch lifecycle handler');
muw_assert_same(1, $oSpy->visitorGateCalls, 'design_included dispatches visitor gate once');

$iLifecycleBeforeUnknown = $oSpy->lifecycleCalls;
$iVisitorBeforeUnknown = $oSpy->visitorGateCalls;
$oResponse->response(muw_alert('system', 'unknown_action'));
$oResponse->response(muw_alert('profile', 'design_included'));
muw_assert_same($iLifecycleBeforeUnknown, $oSpy->lifecycleCalls, 'unknown alerts do not dispatch lifecycle handler');
muw_assert_same($iVisitorBeforeUnknown, $oSpy->visitorGateCalls, 'unknown alerts do not dispatch visitor gate');

if ($aFailures) {
    fwrite(STDERR, "Visitor gate regression failures (" . count($aFailures) . '/' . $iAssertions . "):\n");
    foreach ($aFailures as $sFailure)
        fwrite(STDERR, ' - ' . $sFailure . "\n");
    exit(1);
}

fwrite(STDOUT, 'Visitor gate regression assertions passed: ' . $iAssertions . "\n");
