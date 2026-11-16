#!/bin/bash
set -eu

# Runs as www-data. The socket, authentication file and route manifest stay private.
pool_pid=''
stop_pool() {
    if [ -n "$pool_pid" ]; then
        kill -TERM "$pool_pid" 2>/dev/null || true
        wait "$pool_pid" 2>/dev/null || true
    fi
    exit 0
}
trap stop_pool TERM INT
while true; do
    /usr/sbin/pgbouncer /run/kmp-db-pool/pgbouncer.ini &
    pool_pid=$!
    while kill -0 "$pool_pid" 2>/dev/null; do
        if pool_change="$(php /opt/docker/db-pool.php render)"; then
            if [ "$pool_change" = 'changed' ]; then
                kill -HUP "$pool_pid" 2>/dev/null || true
            fi
        fi
        sleep 15 &
        wait $! || true
    done
    wait "$pool_pid" || true
    echo 'PostgreSQL pool exited; restarting.' >&2
    sleep 1
done
