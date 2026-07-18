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
        .properties.template.containers[0].command = $command
        | .properties.template.containers[0].args = $args
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
    local name container image timeout retries parallelism completions cron
    local command_json args_json
    local -a cron_args remove_env_args set_env_args

    name="$(jq -r '.name' "$file")"
    container="$(jq -r '.properties.template.containers[0].name' "$file")"
    image="$(jq -r '.properties.template.containers[0].image' "$file")"
    timeout="$(jq -r '.properties.configuration.replicaTimeout' "$file")"
    retries="$(jq -r '.properties.configuration.replicaRetryLimit' "$file")"
    parallelism="$(jq -r '.properties.configuration.scheduleTriggerConfig.parallelism // 1' "$file")"
    completions="$(jq -r '.properties.configuration.scheduleTriggerConfig.replicaCompletionCount // 1' "$file")"
    cron="$(jq -r '.properties.configuration.scheduleTriggerConfig.cronExpression // empty' "$file")"
    command_json="$(jq -c '.properties.template.containers[0].command // []' "$file")"
    args_json="$(jq -c '.properties.template.containers[0].args // []' "$file")"

    cron_args=()
    if [[ -n "$cron" ]]; then
        cron_args=(--cron-expression "$cron")
    fi
    remove_env_args=()
    set_env_args=()
    for env_name in KMP_SKIP_CRON KMP_SKIP_MIGRATIONS; do
        env_value="$(jq -r --arg name "$env_name" '
            .properties.template.containers[0].env[]
            | select(.name == $name)
            | .value // empty
        ' "$file")"
        if [[ -n "$env_value" ]]; then
            set_env_args+=("${env_name}=${env_value}")
        else
            remove_env_args+=("$env_name")
        fi
    done

    local -a env_options=()
    if (( ${#set_env_args[@]} > 0 )); then
        env_options+=(--set-env-vars "${set_env_args[@]}")
    fi
    if (( ${#remove_env_args[@]} > 0 )); then
        env_options+=(--remove-env-vars "${remove_env_args[@]}")
    fi

    run az containerapp job update \
        --resource-group "$resource_group" \
        --name "$name" \
        --image "$image" \
        --container-name "$container" \
        --replica-timeout "$timeout" \
        --replica-retry-limit "$retries" \
        --parallelism "$parallelism" \
        --replica-completion-count "$completions" \
        "${cron_args[@]}" \
        "${env_options[@]}" \
        --output none
    patch_job_command "$name" "$command_json" "$args_json"
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

rollback_image="$(az containerapp job show \
    --resource-group "$resource_group" \
    --name "$migrate_job" \
    --query properties.template.containers[0].image \
    --output tsv)"
run az containerapp job update \
    --resource-group "$resource_group" \
    --name "$migrate_job" \
    --image "$rollback_image" \
    --set-env-vars KMP_SKIP_CRON=true \
    --remove-env-vars KMP_SKIP_MIGRATIONS \
    --output none
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
jq '{properties: {template: .properties.template}}' "$snapshot_dir/web.json" > "$web_patch"
run az rest \
    --method patch \
    --uri "https://management.azure.com${web_id}?api-version=2024-03-01" \
    --body "@$web_patch" \
    --output none

echo "ACA runtime definitions restored from $snapshot_dir."
