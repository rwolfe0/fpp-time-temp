<?php
/*
 * fpp-time-temp API, served under /api/plugin/fpp-time-temp/
 *
 *   GET  tt-config   current settings, on/off state and daemon status (never returns the token)
 *   POST tt-config   save settings (JSON body)
 *   POST tt-enable   turn the display on
 *   POST tt-disable   turn the display off
 */

function fpptimetempDefaults() {
    return array(
        'Model' => 'LED Panels', 'Use24Hour' => '0', 'TimeColor' => 'FFFFFF', 'TempColor' => '00FFFF',
        'Units' => 'F', 'YieldToPlaylists' => '1', 'Source' => 'auto', 'StationId' => '', 'TopMargin' => '9',
    );
}

function fpptimetempDataDir() {
    global $settings;
    $dir = $settings['mediaDirectory'] . '/plugindata/fpp-time-temp';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function getEndpointsfpptimetemp() {
    return array(
        array('method' => 'GET',  'endpoint' => 'tt-config', 'callback' => 'fpptimetempGetSettings'),
        array('method' => 'POST', 'endpoint' => 'tt-config', 'callback' => 'fpptimetempSaveSettings'),
        array('method' => 'POST', 'endpoint' => 'tt-enable',   'callback' => 'fpptimetempEnable'),
        array('method' => 'POST', 'endpoint' => 'tt-disable',  'callback' => 'fpptimetempDisable'),
    );
}

// The display service normally starts with FPPD; this starts it if it is not running
// (first use after install, or after a soft restart that skips the start hooks).
function fpptimetempEnsureDaemon() {
    @exec('/bin/sh ' . escapeshellarg(__DIR__ . '/bin/daemon_ctl.sh') . ' ensure > /dev/null 2>&1');
    usleep(400000);
}

function fpptimetempState() {
    $dir = fpptimetempDataDir();
    $cfg = fpptimetempDefaults();
    foreach ($cfg as $k => $v) {
        $s = ReadSettingFromFile($k, 'fpp-time-temp');
        if ($s !== false && $s !== null) $cfg[$k] = $s;
    }
    $status = json_decode((string)@file_get_contents("$dir/status.json"), true);
    $pid = trim((string)@file_get_contents("$dir/daemon.pid"));
    return array(
        'settings'      => $cfg,
        'tokenSet'      => trim((string)@file_get_contents("$dir/tempest_token")) !== '',
        'enabled'       => trim((string)@file_get_contents("$dir/enabled")) === '1',
        'daemonRunning' => $pid !== '' && is_dir("/proc/$pid"),
        'status'        => is_array($status) ? $status : null,
    );
}

function fpptimetempGetSettings() {
    return json(fpptimetempState());
}

function fpptimetempSaveSettings() {
    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) { halt(400, 'Expected a JSON body'); }

    $clean = array();
    if (isset($in['Model']))            $clean['Model'] = substr(preg_replace('/[^A-Za-z0-9 _.\-]/', '', (string)$in['Model']), 0, 64);
    if (isset($in['Use24Hour']))        $clean['Use24Hour'] = $in['Use24Hour'] ? '1' : '0';
    if (isset($in['YieldToPlaylists'])) $clean['YieldToPlaylists'] = $in['YieldToPlaylists'] ? '1' : '0';
    foreach (array('TimeColor', 'TempColor') as $k) {
        if (isset($in[$k]) && preg_match('/^#?([0-9A-Fa-f]{6})$/', (string)$in[$k], $m)) $clean[$k] = strtoupper($m[1]);
    }
    if (isset($in['Units']) && in_array($in['Units'], array('F', 'C'), true)) $clean['Units'] = $in['Units'];
    if (isset($in['Source']) && in_array($in['Source'], array('auto', 'tempest', 'openmeteo', 'none'), true)) $clean['Source'] = $in['Source'];
    if (isset($in['StationId']))        $clean['StationId'] = substr(preg_replace('/[^0-9]/', '', (string)$in['StationId']), 0, 12);
    if (isset($in['TopMargin']))        $clean['TopMargin'] = (string)max(0, min(60, (int)$in['TopMargin']));

    foreach ($clean as $k => $v) WriteSettingToFile($k, $v, 'fpp-time-temp');
    fpptimetempEnsureDaemon();

    // The token is a credential: it lives in plugindata/ (mode 0600), never in config/ and never in the log.
    $dir = fpptimetempDataDir();
    if (!empty($in['ClearToken'])) {
        @unlink("$dir/tempest_token");
    } elseif (isset($in['Token']) && trim((string)$in['Token']) !== '') {
        $token = preg_replace('/[^A-Za-z0-9\-_]/', '', trim((string)$in['Token']));
        $f = "$dir/tempest_token";
        @touch($f); @chmod($f, 0600);
        file_put_contents($f, $token);
    }
    return json(fpptimetempState());
}

function fpptimetempSetEnabled($on) {
    $dir = fpptimetempDataDir();
    file_put_contents("$dir/enabled", $on ? "1\n" : "0\n");
    if ($on) fpptimetempEnsureDaemon();
    return json(fpptimetempState());
}

function fpptimetempEnable()  { return fpptimetempSetEnabled(true); }
function fpptimetempDisable() { return fpptimetempSetEnabled(false); }
?>
