<div>
  <h2>Time &amp; Temperature Display</h2>
  <p>Shows a large clock over a smaller outdoor temperature on an LED panel matrix, or on any Pixel Overlay Model.</p>
  <h3>Setting it up</h3>
  <ol>
    <li>Open <strong>Content Setup → Time &amp; Temperature</strong>.</li>
    <li>Pick the panel, colors and clock format, then save.</li>
    <li>For a Tempest weather station, enter the station ID and a personal access token
        (tempestwx.com → Settings → Data Authorizations → Create Token). Without one, the temperature comes from
        Open-Meteo using the latitude and longitude saved in FPP's settings.</li>
    <li>Press <strong>Turn display on</strong>, or schedule it.</li>
  </ol>
  <h3>Scheduling</h3>
  <p>The plugin adds two FPP commands, <strong>Time &amp; Temp Start</strong> and <strong>Time &amp; Temp Stop</strong>.
     Use them from the Scheduler, a playlist, or anywhere else FPP runs commands.</p>
  <h3>Playlists</h3>
  <p>By default the display steps aside whenever a playlist is playing and returns when it ends.</p>
  <h3>Troubleshooting</h3>
  <p>The plugin writes to one log, <code>plugin-fpp-time-temp.log</code>, under <strong>Help → File Manager → Logs</strong>.
     After installing or updating, restart FPPD so the commands and the display service load.</p>
  <p><a href="https://github.com/rwolfe0/fpp-time-temp" target="_blank" rel="noopener">Source and issue tracker on GitHub</a></p>
</div>
