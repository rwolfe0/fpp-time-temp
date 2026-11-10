#!/bin/sh
# Turns the time & temperature display off. The daemon notices within a second.
D="$(cd "$(dirname "$0")/../../.." && pwd)/plugindata/fpp-time-temp"
mkdir -p "$D"
echo 0 > "$D/enabled"
chown fpp:fpp "$D" "$D/enabled" 2>/dev/null || true
