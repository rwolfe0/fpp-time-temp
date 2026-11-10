#!/bin/sh
# Stop the display daemon (it blanks the panel on the way out).
"$(dirname "$0")/../bin/daemon_ctl.sh" stop
