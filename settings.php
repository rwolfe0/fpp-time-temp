<?php /* fpp-time-temp settings page. Uses FPP's Bootstrap styles; no hardcoded colors or fixed widths. */ ?>
<div id="ttWrap">
  <h2>Time &amp; Temperature Display</h2>

  <div class="row mb-3">
    <div class="col-12">
      <div id="ttStatus" class="alert alert-secondary mb-2" role="status">Loading…</div>
      <button type="button" class="btn btn-success me-2 mb-2" id="ttOn">Turn display on</button>
      <button type="button" class="btn btn-outline-danger mb-2" id="ttOff">Turn display off</button>
    </div>
  </div>

  <h3>Display</h3>
  <div class="row mb-2">
    <label class="col-md-4 col-form-label" for="ttModel">Panel (Pixel Overlay Model)</label>
    <div class="col-md-8"><select class="form-select" id="ttModel"></select></div>
  </div>
  <div class="row mb-2">
    <label class="col-md-4 col-form-label" for="ttUse24">Clock format</label>
    <div class="col-md-8">
      <select class="form-select" id="ttUse24"><option value="0">12-hour (4:05)</option><option value="1">24-hour (16:05)</option></select>
    </div>
  </div>
  <div class="row mb-2">
    <label class="col-md-4 col-form-label" for="ttTimeColor">Time color</label>
    <div class="col-md-8"><input type="color" class="form-control form-control-color" id="ttTimeColor"></div>
  </div>
  <div class="row mb-2">
    <label class="col-md-4 col-form-label" for="ttTempColor">Temperature color</label>
    <div class="col-md-8"><input type="color" class="form-control form-control-color" id="ttTempColor"></div>
  </div>
  <div class="row mb-2">
    <label class="col-md-4 col-form-label" for="ttTopMargin">Blank rows above the time</label>
    <div class="col-md-8">
      <input type="number" class="form-control" id="ttTopMargin" min="0" max="60">
      <div class="form-text">Raise this if the cabinet edge hides the top of the digits. Measured on a 96-row panel.</div>
    </div>
  </div>
  <div class="row mb-3">
    <label class="col-md-4 col-form-label" for="ttYield">While a playlist is playing</label>
    <div class="col-md-8">
      <select class="form-select" id="ttYield"><option value="1">Step aside, come back when it ends</option><option value="0">Keep showing time &amp; temperature</option></select>
    </div>
  </div>

  <h3>Temperature</h3>
  <div class="row mb-2">
    <label class="col-md-4 col-form-label" for="ttSource">Source</label>
    <div class="col-md-8">
      <select class="form-select" id="ttSource">
        <option value="auto">Automatic (Tempest if set up, otherwise Open-Meteo)</option>
        <option value="tempest">Tempest weather station</option>
        <option value="openmeteo">Open-Meteo (uses FPP's latitude/longitude)</option>
        <option value="none">None (time only)</option>
      </select>
    </div>
  </div>
  <div class="row mb-2">
    <label class="col-md-4 col-form-label" for="ttUnits">Units</label>
    <div class="col-md-8"><select class="form-select" id="ttUnits"><option value="F">°F</option><option value="C">°C</option></select></div>
  </div>
  <div class="row mb-2">
    <label class="col-md-4 col-form-label" for="ttStation">Tempest station ID</label>
    <div class="col-md-8">
      <input type="text" class="form-control" id="ttStation" inputmode="numeric" maxlength="12" autocomplete="off">
      <div class="form-text">The number in your station address, tempestwx.com/station/<em>number</em>.</div>
    </div>
  </div>
  <div class="row mb-3">
    <label class="col-md-4 col-form-label" for="ttToken">Tempest access token</label>
    <div class="col-md-8">
      <input type="password" class="form-control" id="ttToken" autocomplete="new-password" placeholder="">
      <div class="form-text" id="ttTokenHelp"></div>
      <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="ttClearToken">Remove saved token</button>
    </div>
  </div>

  <button type="button" class="btn btn-primary mb-3" id="ttSave">Save settings</button>
  <span id="ttSaved" class="ms-2"></span>

  <h3>Scheduling</h3>
  <p>In <strong>Content Setup → Scheduler</strong>, add an entry, choose <strong>Command</strong>, and pick
  <strong>Time &amp; Temp Start</strong> or <strong>Time &amp; Temp Stop</strong>. Use one entry to turn it on and one to turn it off.
  The on/off state is remembered across restarts.</p>
</div>

<script>
(function () {
  var API = 'api/plugin/fpp-time-temp/';
  function el(id) { return document.getElementById(id); }
  function call(method, path, body) {
    return fetch(API + path, {
      method: method,
      headers: body ? { 'Content-Type': 'application/json' } : {},
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
  }
  function showStatus(d) {
    var box = el('ttStatus'), s = d.status || {}, text, cls;
    if (!d.daemonRunning) { text = 'The display service is not running. Restart FPPD to start it.'; cls = 'alert-warning'; }
    else if (!d.enabled) { text = 'Display is off.'; cls = 'alert-secondary'; }
    else if (s.yielding) { text = 'Display is on, stepping aside while a playlist plays.'; cls = 'alert-info'; }
    else if (s.displaying) {
      text = 'Display is on' + (s.temp ? ', showing ' + s.temp : ', showing time only') +
             (s.source === 'tempest' ? ' from your Tempest station.' : s.source === 'openmeteo' ? ' from Open-Meteo.' : '.');
      cls = 'alert-success';
    } else { text = 'Display is on but nothing is being shown yet.'; cls = 'alert-warning'; }
    if (d.enabled && s.message) { text += ' Note: ' + s.message + '.'; if (cls === 'alert-success') cls = 'alert-warning'; }
    box.className = 'alert mb-2 ' + cls;
    box.textContent = text;
  }
  function fill(d, models) {
    var c = d.settings, sel = el('ttModel');
    if (models) {
      sel.innerHTML = '';
      var names = models.map(function (m) { return m.Name; });
      if (names.indexOf(c.Model) < 0) names.unshift(c.Model);
      names.forEach(function (n) { var o = document.createElement('option'); o.value = n; o.textContent = n; sel.appendChild(o); });
    }
    sel.value = c.Model;
    el('ttUse24').value = c.Use24Hour; el('ttYield').value = c.YieldToPlaylists;
    el('ttTimeColor').value = '#' + c.TimeColor; el('ttTempColor').value = '#' + c.TempColor;
    el('ttTopMargin').value = c.TopMargin; el('ttSource').value = c.Source; el('ttUnits').value = c.Units;
    el('ttStation').value = c.StationId; el('ttToken').value = '';
    el('ttTokenHelp').textContent = d.tokenSet
      ? 'A token is saved. Leave this blank to keep it, or type a new one to replace it.'
      : 'No token saved. Create one at tempestwx.com under Settings → Data Authorizations.';
    el('ttClearToken').style.display = d.tokenSet ? '' : 'none';
    showStatus(d);
  }
  function refresh() { call('GET', 'tt-config').then(showStatus).catch(function () {}); }

  Promise.all([call('GET', 'tt-config'), fetch('api/overlays/models').then(function (r) { return r.json(); }).catch(function () { return []; })])
    .then(function (res) { fill(res[0], Array.isArray(res[1]) ? res[1] : []); })
    .catch(function (e) { el('ttStatus').className = 'alert alert-danger mb-2'; el('ttStatus').textContent = 'Could not load settings (' + e.message + ').'; });

  el('ttSave').addEventListener('click', function () {
    var body = {
      Model: el('ttModel').value, Use24Hour: el('ttUse24').value === '1', YieldToPlaylists: el('ttYield').value === '1',
      TimeColor: el('ttTimeColor').value, TempColor: el('ttTempColor').value, TopMargin: el('ttTopMargin').value,
      Source: el('ttSource').value, Units: el('ttUnits').value, StationId: el('ttStation').value
    };
    if (el('ttToken').value.trim() !== '') body.Token = el('ttToken').value.trim();
    call('POST', 'tt-config', body).then(function (d) {
      fill(d, null); el('ttSaved').textContent = 'Saved. The sign updates within a few seconds.';
      setTimeout(function () { el('ttSaved').textContent = ''; }, 5000); setTimeout(refresh, 3000);
    }).catch(function (e) { el('ttSaved').textContent = 'Save failed (' + e.message + ').'; });
  });
  el('ttClearToken').addEventListener('click', function () {
    call('POST', 'tt-config', { ClearToken: true }).then(function (d) { fill(d, null); });
  });
  el('ttOn').addEventListener('click', function () { call('POST', 'tt-enable').then(function (d) { showStatus(d); setTimeout(refresh, 2500); }); });
  el('ttOff').addEventListener('click', function () { call('POST', 'tt-disable').then(function (d) { showStatus(d); setTimeout(refresh, 2500); }); });
  setInterval(refresh, 10000);
})();
</script>
