#!/bin/sh
# Safety net: make sure the daemon is gone after fppd stops.
"$(dirname "$0")/../bin/daemon_ctl.sh" stop
