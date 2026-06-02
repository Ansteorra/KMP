#!/usr/bin/env sh
set -u

cd "$(dirname "$0")/.." || exit 1

restart_delay="${KMP_QUEUE_RESTART_DELAY:-5}"
stopping=0
child_pid=""

stop_worker() {
    stopping=1
    if [ -n "$child_pid" ] && kill -0 "$child_pid" 2>/dev/null; then
        kill -TERM "$child_pid" 2>/dev/null || true
    fi
}

trap stop_worker TERM INT

echo "KMP queue worker loop started."
while [ "$stopping" -eq 0 ]; do
    bin/cake queue run -q &
    child_pid="$!"
    wait "$child_pid"
    status="$?"
    child_pid=""

    if [ "$stopping" -ne 0 ]; then
        break
    fi

    if [ "$status" -ne 0 ]; then
        echo "WARNING: queue worker exited with status ${status}; restarting after ${restart_delay}s." >&2
    else
        echo "Queue worker reached configured runtime; restarting after ${restart_delay}s." >&2
    fi

    sleep "$restart_delay" &
    child_pid="$!"
    wait "$child_pid"
    child_pid=""
done

echo "KMP queue worker loop stopped."
