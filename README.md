# fpp-time-temp

A [Falcon Player (FPP)](https://github.com/FalconChristmas/fpp) plugin that shows a large clock over a smaller
outdoor temperature on an LED panel matrix, or on any Pixel Overlay Model.

- Big, hard-edged digits tuned on a 128x96 P5 matrix; the layout scales to other sizes
- Temperature from a WeatherFlow Tempest station, or from Open-Meteo using FPP's saved latitude/longitude
- **Time & Temp Start** / **Time & Temp Stop** commands for the FPP scheduler
- Steps aside automatically while a playlist is playing
- Settings page: panel, colors, 12/24-hour, °F/°C, top margin

Requires FPP 10.0 or later.

## Install

In FPP: **Content Setup → Plugin Manager**, paste this address into the box at the top and press *Get Plugin Info*, then *Install*:

    https://raw.githubusercontent.com/rwolfe0/fpp-time-temp/main/pluginInfo.json

Restart FPPD when prompted, then open **Content Setup → Time & Temperature**.

## How it works

A small PHP program (`bin/tt_daemon.php`) is started when FPPD starts. Once a minute, and whenever the temperature or
a setting changes, it draws a picture and sends it to FPP's overlay API (`PUT /api/overlays/model/<name>/data`).
It uses only FPP's HTTP API and does not touch the panel or channel output configuration.

## Privacy

The plugin contacts `swd.weatherflow.com` (with your station ID and token) or `api.open-meteo.com` (with the latitude and
longitude saved in FPP) to read the temperature. The Tempest token is stored on the device in
`plugindata/fpp-time-temp/` with owner-only permissions; it is never written to the log or to FPP's config backups.

## Files outside the plugin folder

| Path | Purpose |
|---|---|
| `config/plugin.fpp-time-temp` | Settings |
| `plugindata/fpp-time-temp/` | On/off state, status, Tempest token |
| `logs/plugin-fpp-time-temp.log` | Log |

Uninstalling removes the first two. Package installed as a dependency: `php-gd`. The DejaVu Sans Bold font is bundled in `fonts/` under its own license (`fonts/DejaVu-LICENSE.txt`).
