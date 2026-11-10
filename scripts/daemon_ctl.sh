#!/bin/sh
# Shared start/stop logic for the fpp-time-temp daemon.
PLUGINDIR="$(cd "$(dirname "$0")/.." && pwd)"
MEDIA="$(cd "$PLUGINDIR/../.." && pwd)"
DATA="$MEDIA/plugindata/fpp-time-temp"
PIDFILE="$DATA/daemon.pid"
LOG="${LOGDIR:-$MEDIA/logs}/plugin-fpp-time-temp.log"

stop_daemon() {
    if [ -f "$PIDFILE" ]; then
        PID="$(cat "$PIDFILE" 2>/dev/null)"
        if [ -n "$PID" ] && kill -0 "$PID" 2>/dev/null && grep -q tt_daemon "/proc/$PID/cmdline" 2>/dev/null; then
            kill "$PID" 2>/dev/null
            i=0
            while kill -0 "$PID" 2>/dev/null && [ $i -lt 30 ]; do sleep 0.1; i=$((i + 1)); done
            kill -0 "$PID" 2>/dev/null && kill -9 "$PID" 2>/dev/null
        fi
        rm -f "$PIDFILE"
    fi
}

start_daemon() {
    stop_daemon
    mkdir -p "$DATA"
    chown fpp:fpp "$DATA" 2>/dev/null || true
    nohup /usr/bin/php "$PLUGINDIR/bin/tt_daemon.php" >> "$LOG" 2>&1 < /dev/null &
    echo $! > "$PIDFILE"
    chown fpp:fpp "$PIDFILE" "$LOG" 2>/dev/null || true
}

case "$1" in
    start) start_daemon ;;
    stop) stop_daemon ;;
esac
