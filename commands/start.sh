#!/bin/sh
# Turns the time & temperature display on. The daemon notices within a second.
D="$(cd "$(dirname "$0")/../../.." && pwd)/plugindata/fpp-time-temp"
mkdir -p "$D"
echo 1 > "$D/enabled"
chown fpp:fpp "$D" "$D/enabled" 2>/dev/null || true
