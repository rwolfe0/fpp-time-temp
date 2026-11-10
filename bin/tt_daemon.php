#!/usr/bin/php
<?php
/*
 * fpp-time-temp daemon
 *
 * Draws a time + temperature picture once a minute and hands it to FPP's
 * Pixel Overlay Model API. Started by scripts/postStart.sh, stopped by
 * scripts/preStop.sh. Talks to FPP only through its HTTP API.
 */

const PLUGIN = 'fpp-time-temp';

$pluginDir = dirname(__DIR__);
$mediaDir  = getenv('TT_MEDIADIR') ?: (getenv('MEDIADIR') ?: dirname(dirname($pluginDir)));
$cfgFile   = "$mediaDir/config/plugin." . PLUGIN;
$dataDir   = "$mediaDir/plugindata/" . PLUGIN;
$logFile   = (getenv('LOGDIR') ?: "$mediaDir/logs") . '/plugin-' . PLUGIN . '.log';
$FPP       = getenv('TT_FPP_HOST') ?: 'http://127.0.0.1';
$FONT      = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

$DEFAULTS = [
    'Model'            => 'LED Panels',
    'Use24Hour'        => '0',
    'TimeColor'        => 'FFFFFF',
    'TempColor'        => '00FFFF',
    'Units'            => 'F',
    'YieldToPlaylists' => '1',
    'Source'           => 'auto',      // auto | tempest | openmeteo | none
    'StationId'        => '',
    'TopMargin'        => '9',
];

function logMsg($msg) {
    global $logFile;
    // opened per write so FPP's log rotation keeps working
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

function logOnce($key, $msg) {
    static $last = [];
    if (($last[$key] ?? null) !== $msg) { $last[$key] = $msg; if ($msg !== '') logMsg($msg); }
}

function http($method, $url, $body = null, $headers = [], $timeout = 8) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 4]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return [$code, $r === false ? '' : $r];
}

function loadCfg() {
    global $cfgFile, $DEFAULTS;
    $cfg = $DEFAULTS;
    if (is_readable($cfgFile)) {
        foreach (file($cfgFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^\s*([A-Za-z0-9_]+)\s*=\s*"?(.*?)"?\s*$/', $line, $m) && array_key_exists($m[1], $cfg)) {
                $cfg[$m[1]] = $m[2];
            }
        }
    }
    return $cfg;
}

function isEnabled() {
    global $dataDir;
    return trim((string)@file_get_contents("$dataDir/enabled")) === '1';
}

function readToken() {
    global $dataDir;
    return trim((string)@file_get_contents("$dataDir/tempest_token"));
}

function writeStatus($arr) {
    global $dataDir;
    static $lastJson = '';
    $json = json_encode($arr);
    if ($json === $lastJson) return;
    $lastJson = $json;
    $f = "$dataDir/status.json";
    @file_put_contents("$f.tmp", $json);
    @chmod("$f.tmp", 0644);
    @rename("$f.tmp", $f);
}

function setTimezone() {
    global $FPP;
    $tz = getenv('TT_TZ') ?: trim((string)@file_get_contents('/etc/timezone'));
    if (!$tz && is_link('/etc/localtime') && preg_match('#zoneinfo/(.+)$#', (string)readlink('/etc/localtime'), $m)) $tz = $m[1];
    if (!$tz) {
        [$c, $r] = http('GET', "$FPP/api/settings/TimeZone");
        $tz = json_decode($r, true)['value'] ?? '';
    }
    if ($tz && @date_default_timezone_set($tz)) return;
    logMsg('Could not determine the time zone; using the PHP default (' . date_default_timezone_get() . ')');
}

/* ---------- temperature ---------- */

function fetchTempest($station, $token, $units) {
    [$c, $r] = http('GET', 'https://swd.weatherflow.com/swd/rest/observations/station/' . rawurlencode($station) . '?token=' . rawurlencode($token), null, [], 12);
    if ($c !== 200) return [null, "Tempest request failed (HTTP $c)"];
    $obs = json_decode($r, true)['obs'][0] ?? null;
    if (!is_array($obs) || !isset($obs['air_temperature'])) return [null, 'Tempest returned no current observation'];
    if (isset($obs['timestamp']) && time() - (int)$obs['timestamp'] > 1800) return [null, 'Tempest reading is more than 30 minutes old'];
    $cTemp = (float)$obs['air_temperature'];           // API reports Celsius
    return [$units === 'C' ? $cTemp : $cTemp * 9 / 5 + 32, ''];
}

function fetchOpenMeteo($units) {
    global $FPP;
    [$c1, $la] = http('GET', "$FPP/api/settings/Latitude");
    [$c2, $lo] = http('GET', "$FPP/api/settings/Longitude");
    $lat = json_decode($la, true)['value'] ?? ''; $lon = json_decode($lo, true)['value'] ?? '';
    if (!is_numeric($lat) || !is_numeric($lon)) return [null, 'Latitude/Longitude are not set in FPP settings'];
    $u = $units === 'C' ? 'celsius' : 'fahrenheit';
    [$c, $r] = http('GET', "https://api.open-meteo.com/v1/forecast?latitude=$lat&longitude=$lon&current=temperature_2m&temperature_unit=$u", null, [], 12);
    $t = json_decode($r, true)['current']['temperature_2m'] ?? null;
    if ($c !== 200 || $t === null) return [null, "Open-Meteo request failed (HTTP $c)"];
    return [(float)$t, ''];
}

/* returns [display string or null, source name, error text] ; cached */
function getTemp($cfg) {
    static $cache = ['key' => '', 'at' => 0, 'val' => null, 'src' => '', 'err' => '', 'goodAt' => 0];
    $source = $cfg['Source'];
    $token = readToken(); $station = trim($cfg['StationId']);
    if ($source === 'auto') $source = ($token !== '' && $station !== '') ? 'tempest' : 'openmeteo';
    if ($source === 'none') return [null, 'none', ''];
    $key = $source . '|' . $cfg['Units'] . '|' . $station . '|' . md5($token);
    $interval = $source === 'tempest' ? 60 : 600;
    if ($cache['key'] === $key && time() - $cache['at'] < $interval) {
        return [$cache['val'], $cache['src'], $cache['err']];
    }
    if ($source === 'tempest') {
        if ($token === '' || $station === '') { $t = null; $err = 'Tempest station ID or token is not set'; }
        else [$t, $err] = fetchTempest($station, $token, $cfg['Units']);
    } else {
        [$t, $err] = fetchOpenMeteo($cfg['Units']);
    }
    $val = $cache['key'] === $key ? $cache['val'] : null;
    $goodAt = $cache['key'] === $key ? $cache['goodAt'] : 0;
    if ($t !== null) { $val = round($t) . '°' . ($cfg['Units'] === 'C' ? 'C' : 'F'); $goodAt = time(); }
    elseif (time() - $goodAt > 1800) $val = null;          // keep the last good reading for up to 30 minutes
    $cache = ['key' => $key, 'at' => time(), 'val' => $val, 'src' => $source, 'err' => $err, 'goodAt' => $goodAt];
    logOnce('temp', $err);
    return [$val, $source, $err];
}

/* ---------- drawing ---------- */

function fitSize($font, $text, $maxW, $maxH, $start) {
    for ($s = $start; $s > 5; $s -= 0.5) {
        $b = imagettfbbox($s, 0, $font, $text);
        if (($b[2] - $b[0]) <= $maxW && ($b[1] - $b[7]) <= $maxH) return $s;
    }
    return 5;
}

function hexColor($im, $hex, $fallback) {
    if (!preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) $hex = $fallback;
    return imagecolorallocate($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

/* Layout was tuned on a 128x96 panel; everything scales from that. */
function renderPng($W, $H, $cfg, $temp) {
    global $FONT;
    $sx = $W / 128; $sy = $H / 96; $s = min($sx, $sy);
    $im = imagecreatetruecolor($W, $H);
    imagefill($im, 0, 0, imagecolorallocate($im, 0, 0, 0));

    $use24 = $cfg['Use24Hour'] === '1';
    $time = date($use24 ? 'H:i' : 'g:i');
    [$hh, $mm] = explode(':', $time);

    // big time: hard-edged pixels (negative color = no smoothing), extra space around the colon,
    // sized so the widest case always fits, then stretched taller without getting wider
    $gap = max(1, (int)round(3 * $sx));
    $ts = fitSize($FONT, $use24 ? '00:00' : '12:00', 124 * $sx - 2 * $gap, 60 * $sy, 60 * $s);
    $tb = imagettfbbox($ts, 0, $FONT, $time); $th = $tb[1] - $tb[7];
    $parts = [$hh, ':', $mm]; $widths = []; $tw = 2 * $gap;
    foreach ($parts as $p) { $bb = imagettfbbox($ts, 0, $FONT, $p); $widths[] = [$bb[0], $bb[2] - $bb[0]]; $tw += $bb[2] - $bb[0]; }
    $tmp = imagecreatetruecolor($tw + 2, $th + 2);
    imagefill($tmp, 0, 0, imagecolorallocate($tmp, 0, 0, 0));
    $tc = -hexColor($tmp, $cfg['TimeColor'], 'FFFFFF'); $cx = 0;
    foreach ($parts as $i => $p) {
        imagettftext($tmp, $ts, 0, $cx - $widths[$i][0], -$tb[7] + 1, $tc, $FONT, $p);
        $cx += $widths[$i][1] + ($i < 2 ? $gap : 0);
    }
    $top = max(0, min($H - 10, (int)round((int)$cfg['TopMargin'] * $sy)));
    imagecopyresized($im, $tmp, (int)(($W - $tw) / 2), $top, 0, 0, $tw, (int)round(46 * $sy), $tw, $th + 2);

    // smaller temperature, centered below
    if ($temp !== null) {
        $ps = fitSize($FONT, $temp, 120 * $sx, 30 * $sy, 25 * $s);
        $pb = imagettfbbox($ps, 0, $FONT, $temp); $pw = $pb[2] - $pb[0];
        $base = min($H - 2, $top + (int)round(81 * $sy));
        imagettftext($im, $ps, 0, (int)(($W - $pw) / 2) - $pb[0], $base, -hexColor($im, $cfg['TempColor'], '00FFFF'), $FONT, $temp);
    }
    ob_start(); imagepng($im); $png = ob_get_clean();
    return $png;
}

/* ---------- FPP ---------- */

function modelUrl($cfg) { global $FPP; return "$FPP/api/overlays/model/" . rawurlencode($cfg['Model']); }

function modelSize($cfg) {
    static $cache = ['name' => null, 'at' => 0, 'size' => null];
    if ($cache['name'] === $cfg['Model'] && $cache['size'] && time() - $cache['at'] < 300) return $cache['size'];
    [$c, $r] = http('GET', modelUrl($cfg));
    $j = json_decode($r, true);
    $size = ($c === 200 && !empty($j['width']) && !empty($j['height'])) ? [(int)$j['width'], (int)$j['height']] : null;
    $cache = ['name' => $cfg['Model'], 'at' => time(), 'size' => $size];
    return $size;
}

function fppPlaying() {
    global $FPP;
    [$c, $r] = http('GET', "$FPP/api/fppd/status", null, [], 4);
    $name = json_decode($r, true)['status_name'] ?? null;
    return $name !== null && $name !== 'idle';
}

function pushFrame($cfg, $png) {
    $m = modelUrl($cfg);
    [$c1, $r1] = http('PUT', "$m/state", json_encode(['State' => 1]), ['Content-Type: application/json']);
    [$c2, $r2] = http('PUT', "$m/data", $png, ['Content-Type: image/png']);
    return ($c1 === 200 && $c2 === 200) ? '' : "FPP did not accept the frame (state HTTP $c1, data HTTP $c2)";
}

function clearModel($cfg) {
    $m = modelUrl($cfg);
    http('PUT', "$m/fill", json_encode(['RGB' => [0, 0, 0]]), ['Content-Type: application/json']);
    http('PUT', "$m/state", json_encode(['State' => 0]), ['Content-Type: application/json']);
}

/* ---------- main ---------- */

if (!function_exists('imagettftext')) { logMsg('PHP GD with FreeType is missing (package php-gd); cannot draw. Exiting.'); exit(1); }
if (!is_readable($FONT)) { logMsg("Font not found: $FONT (package fonts-dejavu-core). Exiting."); exit(1); }
if (!function_exists('curl_init')) { logMsg('PHP curl extension is missing (package php-curl). Exiting.'); exit(1); }

$running = true;
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    $stop = function () use (&$running) { $running = false; };
    pcntl_signal(SIGTERM, $stop); pcntl_signal(SIGINT, $stop);
}

if (($argv[1] ?? '') === 'render') {           // offline test: tt_daemon.php render out.png [W H] [temp]
    setTimezone();
    file_put_contents($argv[2] ?? 'preview.png', renderPng((int)($argv[3] ?? 128), (int)($argv[4] ?? 96), loadCfg(), $argv[5] ?? '59°F'));
    exit(0);
}

@mkdir($dataDir, 0755, true);
// Single-instance lock, then our own pid file (written by rename so either root or fpp can replace it).
$lockFh = @fopen("$dataDir/daemon.lock", 'c');
if ($lockFh) { @chmod("$dataDir/daemon.lock", 0666); if (!flock($lockFh, LOCK_EX | LOCK_NB)) exit(0); }
@file_put_contents("$dataDir/daemon.pid.tmp", getmypid() . "\n");
@chmod("$dataDir/daemon.pid.tmp", 0644);
@rename("$dataDir/daemon.pid.tmp", "$dataDir/daemon.pid");
setTimezone();
logMsg('daemon started');

$showing = false; $shownCfg = null; $lastKey = ''; $lastPush = 0; $playing = false; $lastPlayCheck = 0;

while ($running) {
    $cfg = loadCfg();
    $enabled = isEnabled();
    if (time() - $lastPlayCheck >= 3) { $playing = $cfg['YieldToPlaylists'] === '1' ? fppPlaying() : false; $lastPlayCheck = time(); }
    $want = $enabled && !$playing;
    $err = ''; $temp = null; $src = '';

    if ($want) {
        [$temp, $src, $terr] = getTemp($cfg);
        $size = modelSize($cfg);
        if (!$size) {
            $err = 'Overlay model "' . $cfg['Model'] . '" was not found in FPP';
        } else {
            $key = date('YmdHi') . '|' . $temp . '|' . md5(json_encode($cfg)) . '|' . implode('x', $size);
            if ($key !== $lastKey || !$showing || time() - $lastPush >= 30) {
                if ($showing && $shownCfg && $shownCfg['Model'] !== $cfg['Model']) clearModel($shownCfg);
                $err = pushFrame($cfg, renderPng($size[0], $size[1], $cfg, $temp));
                if ($err === '') { $showing = true; $shownCfg = $cfg; $lastKey = $key; $lastPush = time(); }
            }
        }
        logOnce('fpp', $err);
        if ($err === '' && $terr !== '') $err = $terr;
    } elseif ($showing) {
        clearModel($shownCfg ?: $cfg);
        $showing = false; $lastKey = '';
    }

    writeStatus([
        'enabled'    => $enabled,
        'displaying' => $showing,
        'yielding'   => $enabled && $playing,
        'temp'       => $temp,
        'source'     => $src,
        'message'    => $err,
    ]);
    sleep(1);
}

if ($showing) clearModel($shownCfg ?: loadCfg());
writeStatus(['enabled' => isEnabled(), 'displaying' => false, 'yielding' => false, 'temp' => null, 'source' => '', 'message' => 'daemon stopped']);
@unlink("$dataDir/daemon.pid");
logMsg('daemon stopped');
