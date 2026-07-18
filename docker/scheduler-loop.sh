#!/bin/sh
set -u

cd /var/www/html

poll_interval="${KMP_SCHEDULER_POLL_INTERVAL:-10}"
workflow_interval="${KMP_WORKFLOW_SCHEDULER_INTERVAL:-60}"
active_window_interval="${KMP_ACTIVE_WINDOW_SYNC_INTERVAL:-900}"
warrantable_interval="${KMP_MEMBER_WARRANTABLE_SYNC_INTERVAL:-86400}"
age_up_interval="${KMP_AGE_UP_MEMBERS_INTERVAL:-86400}"
queue_interval="${KMP_QUEUE_DRAIN_INTERVAL:-5}"
tenancy_enabled="${KMP_TENANCY_ENABLED:-false}"

started_at="$(date +%s)"
last_workflow=0
last_active_window=0
last_warrantable="$started_at"
last_age_up="$started_at"
last_queue=0

run_due() {
    name="$1"
    command="$2"
    last_run="$3"
    interval="$4"
    now="$(date +%s)"

    if [ $((now - last_run)) -lt "$interval" ]; then
        echo "$last_run"
        return
    fi

    echo "Running ${name} at $(date -u +"%Y-%m-%dT%H:%M:%SZ")" >&2
    if ! sh -c "$command" >&2 2>&1; then
        echo "WARNING: ${name} exited with a non-zero status; scheduler loop will continue." >&2
    fi

    echo "$now"
}

schedule_command() {
    schedule_name="$1"
    legacy_command="$2"

    if [ "${KMP_TENANCY_ENABLED:-false}" = "true" ]; then
        echo "bin/cake platform schedule run ${schedule_name}"
    else
        echo "$legacy_command"
    fi
}

workflow_command="$(schedule_command "workflow-scheduler" "bin/cake workflow_scheduler")"
active_window_command="$(schedule_command "active-window-sync" "bin/cake sync_active_window_statuses")"
warrantable_command="$(schedule_command "member-warrantable-sync" "bin/cake sync_member_warrantable_statuses")"
age_up_command="$(schedule_command "age-up-members" "bin/cake age_up_members")"

echo "KMP scheduler loop started."
while true; do
    if [ "$tenancy_enabled" = "true" ]; then
        last_queue="$(run_due \
            "platform worker" \
            "bin/cake platform worker run --schedule-limit 100 --max-jobs 100 --max-runtime 45 --cycle-budget 240 --platform-limit 1" \
            "$last_queue" \
            "$queue_interval")"
    else
        last_queue="$(run_due \
            "default queue" \
            "bin/cake queue run -q --max-jobs 25 --max-runtime 45 --exit-when-empty" \
            "$last_queue" \
            "$queue_interval")"
        last_workflow="$(run_due "workflow scheduler" "$workflow_command" "$last_workflow" "$workflow_interval")"
        last_active_window="$(run_due "active-window sync" "$active_window_command" "$last_active_window" "$active_window_interval")"
        last_warrantable="$(run_due "member warrantable sync" "$warrantable_command" "$last_warrantable" "$warrantable_interval")"
        last_age_up="$(run_due "age-up members" "$age_up_command" "$last_age_up" "$age_up_interval")"
    fi
    sleep "$poll_interval"
done
