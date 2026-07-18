#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  rollback-unified-worker.sh \
    --resource-group RG \
    --web-app APP \
    --migrate-job JOB \
    --snapshot-dir DIR \
    [--dry-run]

Rolls platform migrations back to 20260715173000 so legacy queue schedules are
re-enabled, then restores the captured web template and Job runtime definitions.
Tenant application data is not destructively rolled back.
EOF
}

resource_group=''
web_app=''
migrate_job=''
snapshot_dir=''
dry_run=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --resource-group) resource_group="$2"; shift 2 ;;
        --web-app) web_app="$2"; shift 2 ;;
        --migrate-job) migrate_job="$2"; shift 2 ;;
        --snapshot-dir) snapshot_dir="$2"; shift 2 ;;
        --dry-run) dry_run=true; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown argument: $1" >&2; usage >&2; exit 64 ;;
    esac
done

for value in resource_group web_app migrate_job snapshot_dir; do
    if [[ -z "${!value}" ]]; then
        echo "Missing required argument: $value" >&2
        exit 64
    fi
done

run() {
    printf '+'
    printf ' %q' "$@"
    printf '\n'
    if ! "$dry_run"; then
        "$@"
    fi
}

patch_job_command() {
    local job="$1"
    local command_json="$2"
    local args_json="$3"
    local current_file patch_file resource_id

    current_file="$(mktemp)"
    patch_file="$(mktemp)"
    az containerapp job show \
        --resource-group "$resource_group" \
        --name "$job" \
        --output json > "$current_file"
    resource_id="$(jq -r '.id' "$current_file")"
    jq --argjson command "$command_json" --argjson args "$args_json" '
        del(.properties.template.containers[].imageType?)
        | .properties.template.containers[0].command = $command
        | .properties.template.containers[0].args = $args
        | .properties.template.containers[0].env = (
            (.properties.template.containers[0].env // [] | map(select(
                .name != "KMP_SKIP_CRON"
                and .name != "KMP_SKIP_MIGRATIONS"
            )))
            + [{"name": "KMP_SKIP_CRON", "value": "true"}]
        )
        | {properties: {template: .properties.template}}
    ' "$current_file" > "$patch_file"
    az rest \
        --method patch \
        --uri "https://management.azure.com${resource_id}?api-version=2024-03-01" \
        --body "@$patch_file" \
        --output none
    rm -f "$current_file" "$patch_file"
}

restore_job() {
    local file="$1"
    local name resource_id patch_file

    name="$(jq -r '.name' "$file")"
    resource_id="$(jq -r '.id' "$file")"
    patch_file="$(mktemp)"
    jq '
        {
            properties: {
                configuration: (
                    {
                        replicaTimeout: .properties.configuration.replicaTimeout,
                        replicaRetryLimit: .properties.configuration.replicaRetryLimit
                    }
                    + if .properties.configuration.triggerType == "Schedule" then {
                        scheduleTriggerConfig: .properties.configuration.scheduleTriggerConfig
                      } else {
                        manualTriggerConfig: .properties.configuration.manualTriggerConfig
                      }
                      end
                ),
                template: (
                    .properties.template
                    | del(.containers[].imageType?)
                )
            }
        }
    ' "$file" > "$patch_file"
    run az rest \
        --method patch \
        --uri "https://management.azure.com${resource_id}?api-version=2024-03-01" \
        --body "@$patch_file" \
        --output none
    rm -f "$patch_file"
}

if "$dry_run"; then
    echo "Would restore ACA definitions from $snapshot_dir."
    exit 0
fi

for command in az jq; do
    command -v "$command" >/dev/null || {
        echo "Required command not found: $command" >&2
        exit 69
    }
done

patch_job_command \
    "$migrate_job" \
    '["/usr/local/bin/docker-entrypoint.sh"]' \
    '["/bin/sh","-lc","bin/cake platform_migrate rollback -t 20260715173000"]'
execution="$(az containerapp job start \
    --resource-group "$resource_group" \
    --name "$migrate_job" \
    --query name \
    --output tsv)"
echo "Started platform migration rollback: $execution"
for attempt in $(seq 1 180); do
    status="$(az containerapp job execution show \
        --resource-group "$resource_group" \
        --job-name "$migrate_job" \
        --name "$execution" \
        --query properties.status \
        --output tsv 2>/dev/null || echo Unknown)"
    case "$status" in
        Succeeded) break ;;
        Failed|Cancelled|Degraded)
            echo "Platform migration rollback failed with $status." >&2
            exit 1
            ;;
    esac
    if [[ "$attempt" -eq 180 ]]; then
        echo "Platform migration rollback timed out." >&2
        exit 1
    fi
    sleep 10
done
restore_job "$snapshot_dir/migrate-job.json"

for file in "$snapshot_dir/worker-job.json" "$snapshot_dir"/legacy-*.json; do
    [[ -f "$file" ]] || continue
    restore_job "$file"
done

web_id="$(jq -r '.id' "$snapshot_dir/web.json")"
web_patch="$(mktemp)"
trap 'rm -f "$web_patch"' EXIT
jq '{
    properties: {
        template: (
            .properties.template
            | del(.containers[].imageType?)
        )
    }
}' "$snapshot_dir/web.json" > "$web_patch"
run az rest \
    --method patch \
    --uri "https://management.azure.com${web_id}?api-version=2024-03-01" \
    --body "@$web_patch" \
    --output none

echo "ACA runtime definitions restored from $snapshot_dir."
