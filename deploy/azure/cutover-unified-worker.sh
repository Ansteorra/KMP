#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  cutover-unified-worker.sh \
    --resource-group RG \
    --web-app APP \
    --migrate-job JOB \
    --worker-job JOB \
    --image IMAGE \
    [--legacy-job JOB ...] \
    [--snapshot-dir DIR] \
    [--dry-run]

Ordered, idempotent ACA cutover:
  1. Capture current web and Job definitions.
  2. Configure and manually canary the unified worker.
  3. Repair and run the migration Job.
  4. Update the web revision with request-only flags and split probes.
  5. Verify /livez and /health.
  6. Park legacy scheduler Jobs on an annual no-op schedule.
EOF
}

resource_group=''
web_app=''
migrate_job=''
worker_job=''
image=''
snapshot_dir=''
dry_run=false
legacy_jobs=()
existing_legacy_jobs=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --resource-group) resource_group="$2"; shift 2 ;;
        --web-app) web_app="$2"; shift 2 ;;
        --migrate-job) migrate_job="$2"; shift 2 ;;
        --worker-job) worker_job="$2"; shift 2 ;;
        --image) image="$2"; shift 2 ;;
        --legacy-job) legacy_jobs+=("$2"); shift 2 ;;
        --snapshot-dir) snapshot_dir="$2"; shift 2 ;;
        --dry-run) dry_run=true; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown argument: $1" >&2; usage >&2; exit 64 ;;
    esac
done

for value in resource_group web_app migrate_job worker_job image; do
    if [[ -z "${!value}" ]]; then
        echo "Missing required argument: $value" >&2
        exit 64
    fi
done

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
snapshot_dir="${snapshot_dir:-./kmp-aca-snapshot-$(date -u +%Y%m%dT%H%M%SZ)}"

run() {
    printf '+'
    printf ' %q' "$@"
    printf '\n'
    if ! "$dry_run"; then
        "$@"
    fi
}

patch_job_runtime() {
    local job="$1"
    local image_ref="$2"
    local cron="$3"
    local timeout="$4"
    local retries="$5"
    local parallelism="$6"
    local completions="$7"
    local env_mode="$8"
    local command_json="$9"
    local args_json="${10}"

    if "$dry_run"; then
        echo "Would patch $job image=$image_ref cron=${cron:-manual} command=$command_json args=$args_json."
        return
    fi

    local current_file patch_file resource_id
    current_file="$(mktemp)"
    patch_file="$(mktemp)"
    az containerapp job show \
        --resource-group "$resource_group" \
        --name "$job" \
        --output json > "$current_file"
    resource_id="$(jq -r '.id' "$current_file")"
    jq \
        --arg image "$image_ref" \
        --arg cron "$cron" \
        --arg envMode "$env_mode" \
        --argjson timeout "$timeout" \
        --argjson retries "$retries" \
        --argjson parallelism "$parallelism" \
        --argjson completions "$completions" \
        --argjson command "$command_json" \
        --argjson args "$args_json" '
        (
            .properties.template
            | del(.containers[].imageType?)
            | .containers[0].image = $image
            | .containers[0].command = $command
            | .containers[0].args = $args
            | .containers[0].env = (
                (.containers[0].env // [] | map(select(
                    .name != "KMP_SKIP_CRON"
                    and .name != "KMP_SKIP_MIGRATIONS"
                )))
                + if $envMode == "migrate" then
                    [{"name": "KMP_SKIP_CRON", "value": "true"}]
                  else
                    [
                        {"name": "KMP_SKIP_CRON", "value": "true"},
                        {"name": "KMP_SKIP_MIGRATIONS", "value": "true"}
                    ]
                  end
            )
        ) as $template
        | {
            properties: {
                configuration: (
                    {
                        replicaTimeout: $timeout,
                        replicaRetryLimit: $retries
                    }
                    + if $cron == "" then {}
                      else {
                        scheduleTriggerConfig: (
                            (.properties.configuration.scheduleTriggerConfig // {})
                            + {
                                cronExpression: $cron,
                                parallelism: $parallelism,
                                replicaCompletionCount: $completions
                            }
                        )
                      }
                      end
                ),
                template: $template
            }
        }
    ' "$current_file" > "$patch_file"
    az rest \
        --method patch \
        --uri "https://management.azure.com${resource_id}?api-version=2024-03-01" \
        --body "@$patch_file" \
        --output none
    rm -f "$current_file" "$patch_file"
}

wait_for_execution() {
    local job="$1"
    local execution="$2"
    local label="$3"
    local attempts="${4:-180}"

    if "$dry_run"; then
        echo "Would wait for $label execution $execution."
        return
    fi

    local status
    for ((attempt = 1; attempt <= attempts; attempt++)); do
        status="$(az containerapp job execution show \
            --resource-group "$resource_group" \
            --name "$job" \
            --job-execution-name "$execution" \
            --query properties.status \
            --output tsv 2>/dev/null || echo Unknown)"
        echo "[$attempt/$attempts] $label status: $status"
        case "$status" in
            Succeeded) return ;;
            Failed|Cancelled|Degraded)
                az containerapp job execution show \
                    --resource-group "$resource_group" \
                    --name "$job" \
                    --job-execution-name "$execution" \
                    --output yaml || true
                return 1
                ;;
        esac
        sleep 10
    done

    echo "$label execution timed out." >&2
    return 1
}

start_and_wait() {
    local job="$1"
    local label="$2"
    local execution

    if "$dry_run"; then
        execution='dry-run'
        echo "Would start $label Job $job."
    else
        execution="$(az containerapp job start \
            --resource-group "$resource_group" \
            --name "$job" \
            --query name \
            --output tsv)"
    fi
    wait_for_execution "$job" "$execution" "$label"
}

probe() {
    local url="$1"
    local label="$2"

    if "$dry_run"; then
        echo "Would probe $url."
        return
    fi

    local code
    for attempt in $(seq 1 40); do
        code="$(curl --silent --show-error --output "/tmp/${label}.txt" \
            --write-out '%{http_code}' "$url" || echo 000)"
        if [[ "$code" == '200' ]]; then
            echo "$label returned 200."
            return
        fi
        echo "[$attempt/40] $label returned $code."
        sleep 15
    done

    echo "$label did not become healthy." >&2
    return 1
}

if ! "$dry_run"; then
    for command in az curl jq; do
        command -v "$command" >/dev/null || {
            echo "Required command not found: $command" >&2
            exit 69
        }
    done

    umask 077
    mkdir -p "$snapshot_dir"
    az containerapp show -g "$resource_group" -n "$web_app" -o json > "$snapshot_dir/web.json"
    az containerapp job show -g "$resource_group" -n "$migrate_job" -o json > "$snapshot_dir/migrate-job.json"
    az containerapp job show -g "$resource_group" -n "$worker_job" -o json > "$snapshot_dir/worker-job.json"
    for job in "${legacy_jobs[@]}"; do
        [[ -n "$job" ]] || continue
        if az containerapp job show -g "$resource_group" -n "$job" -o json \
            > "$snapshot_dir/legacy-${job}.json" 2>/dev/null; then
            existing_legacy_jobs+=("$job")
        else
            rm -f "$snapshot_dir/legacy-${job}.json"
            echo "Legacy Job $job does not exist; skipping it."
        fi
    done
    echo "Captured rollback definitions in $snapshot_dir."
fi
if "$dry_run"; then
    existing_legacy_jobs=("${legacy_jobs[@]}")
fi

# The pre-migration canary may see legacy queue schedule rows. Queue/platform
# claims keep that one cycle idempotent until the consolidation migration lands.
patch_job_runtime \
    "$worker_job" \
    "$image" \
    '0 0 1 1 *' \
    3600 \
    1 \
    1 \
    1 \
    worker \
    '["/usr/local/bin/docker-entrypoint.sh"]' \
    '["/bin/sh","-lc","bin/cake platform worker run --schedule-limit 100 --max-jobs 100 --max-runtime 45 --cycle-budget 240 --platform-limit 1 --json --fail-on-overlap"]'

start_and_wait "$worker_job" 'unified worker canary'

patch_job_runtime \
    "$migrate_job" \
    "$image" \
    '' \
    1800 \
    1 \
    1 \
    1 \
    migrate \
    '["/usr/local/bin/docker-entrypoint.sh"]' \
    '["/bin/sh","-lc","bin/cake migrations migrate && bin/cake schema_cache clear && bin/cake updateDatabase && bin/cake platform_migrate migrate && bin/cake schema_cache clear --connection platform"]'

start_and_wait "$migrate_job" 'migration'

start_and_wait "$worker_job" 'post-migration worker verification'

patch_job_runtime \
    "$worker_job" \
    "$image" \
    '*/3 * * * *' \
    3600 \
    1 \
    1 \
    1 \
    worker \
    '["/usr/local/bin/docker-entrypoint.sh"]' \
    '["/bin/sh","-lc","bin/cake platform worker run --schedule-limit 100 --max-jobs 100 --max-runtime 45 --cycle-budget 240 --platform-limit 1 --json"]'

run "$script_dir/update-web-runtime.sh" \
    --resource-group "$resource_group" \
    --web-app "$web_app" \
    --image "$image"

if "$dry_run"; then
    fqdn="${web_app}.example.invalid"
else
    fqdn="$(az containerapp show \
        --resource-group "$resource_group" \
        --name "$web_app" \
        --query properties.configuration.ingress.fqdn \
        --output tsv)"
fi
probe "https://${fqdn}/livez" livez
probe "https://${fqdn}/health" health

for job in "${existing_legacy_jobs[@]}"; do
    [[ -n "$job" ]] || continue
    patch_job_runtime \
        "$job" \
        "$image" \
        '0 0 1 1 *' \
        60 \
        0 \
        1 \
        1 \
        worker \
        '["/usr/local/bin/docker-entrypoint.sh"]' \
        '["/bin/true"]'
done

echo "Unified ACA worker cutover completed."
