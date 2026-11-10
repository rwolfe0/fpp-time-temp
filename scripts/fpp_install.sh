#!/bin/bash
set -e

# fpp-time-temp install script

. ${FPPDIR}/scripts/common

# FPP 10+ installs the packages declared in pluginInfo.json before this runs.
# This guarded fallback only does something if they are still missing.
NEED=""
php -r 'exit(function_exists("imagettftext") ? 0 : 1);' 2>/dev/null || NEED="$NEED php-gd"
[ -f /usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf ] || NEED="$NEED fonts-dejavu-core"
if [ -n "$NEED" ]; then
    apt-get update
    apt-get install -y $NEED
fi

mkdir -p "${MEDIADIR}/plugindata/fpp-time-temp"
chown fpp:fpp "${MEDIADIR}/plugindata/fpp-time-temp" 2>/dev/null || true
chmod +x "${MEDIADIR}/plugins/fpp-time-temp/bin/tt_daemon.php" "${MEDIADIR}/plugins/fpp-time-temp/bin/daemon_ctl.sh" "${MEDIADIR}/plugins/fpp-time-temp/commands/"*.sh "${MEDIADIR}/plugins/fpp-time-temp/scripts/"*.sh 2>/dev/null || true

# fppd reads commands/descriptions.json only at startup, and the daemon is
# launched from postStart.sh, so ask FPP to show its "Restart Required" banner.
setSetting restartFlag 1
