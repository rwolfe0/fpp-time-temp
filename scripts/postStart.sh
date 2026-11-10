#!/bin/sh
# Start the display daemon once fppd is up. Returns immediately.
"$(dirname "$0")/../bin/daemon_ctl.sh" start
