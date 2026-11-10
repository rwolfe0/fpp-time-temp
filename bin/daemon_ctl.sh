#!/bin/sh
# Start/stop logic for the fpp-time-temp daemon.
#   start   stop any running copy, then start
#   ensure  start only if it is not already running
#   stop    stop it (it blanks the panel on the way out)
# The daemon writes its own pid file and holds a lock, so two copies cannot run.
PLUGINDIR="$(cd "$(dirname "$0")/.." && pwd)"
MEDIA="$(cd "$PLUGINDIR/../.." && pwd)"
DATA="$MEDIA/plugindata/fpp-time-temp"
PIDFILE="$DATA/daemon.pid"
LOG="${LOGDIR:-$MEDIA/logs}/plugin-fpp-time-temp.log"

running_pid() {
    [ -f "$PIDFILE" ] || return 1
    PID="$(cat "$PIDFILE" 2>/dev/null)"
    [ -n "$PID" ] && [ -d "/proc/$PID" ] && grep -q tt_daemon "/proc/$PID/cmdline" 2>/dev/null
}

stop_daemon() {
    if running_pid; then
        kill "$PID" 2>/dev/null
        i=0
        while [ -d "/proc/$PID" ] && [ $i -lt 30 ]; do sleep 0.1; i=$((i + 1)); done
        [ -d "/proc/$PID" ] && kill -9 "$PID" 2>/dev/null
    fi
    rm -f "$PIDFILE" 2>/dev/null
    return 0
}

start_daemon() {
    mkdir -p "$DATA"
    chown fpp:fpp "$DATA" 2>/dev/null || true
    setsid nohup /usr/bin/php "$PLUGINDIR/bin/tt_daemon.php" >> "$LOG" 2>&1 < /dev/null &
    chown fpp:fpp "$LOG" 2>/dev/null || true
}

case "$1" in
    start)  stop_daemon; start_daemon ;;
    ensure) running_pid || start_daemon ;;
    stop)   stop_daemon ;;
esac
exit 0
