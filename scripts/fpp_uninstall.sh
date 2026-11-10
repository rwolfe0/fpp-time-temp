#!/bin/bash

# fpp-time-temp uninstall script (safe to run twice)

. ${FPPDIR}/scripts/common

"${MEDIADIR}/plugins/fpp-time-temp/bin/daemon_ctl.sh" stop 2>/dev/null || true

# The commands stay in fppd's memory until it restarts.
setSetting restartFlag 1

# Keep settings and the Tempest token across a Reinstall; remove them otherwise.
if [ "${FPP_PLUGIN_REINSTALL:-0}" != "1" ]; then
    rm -rf "${MEDIADIR}/plugindata/fpp-time-temp"
    rm -f "${MEDIADIR}/config/plugin.fpp-time-temp"
fi
