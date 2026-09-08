#!/bin/sh
set -u
cd /var/www/html || exit 1
while true; do
    if ! bin/cake platform jobs run --limit 1; then
        echo 'Administrative job cycle failed; queued work will be inspected on the next cycle.' >&2
    fi
    sleep "${KMP_ADMIN_JOB_POLL_INTERVAL:-10}"
done
