#!/usr/bin/env bash
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "$here/../.." && pwd)"
bicep="$here/main.bicep"
workflow="$repo_root/.github/workflows/nightly-deploy-azure.yml"
scheduler="$repo_root/docker/scheduler-loop.sh"

assert_contains() {
    local file="$1"
    local expected="$2"

    if ! grep -Fq -- "$expected" "$file"; then
        echo "Missing deployment contract in $file: $expected" >&2
        exit 1
    fi
}

assert_contains "$bicep" "param queueWorkerCron string = '* * * * *'"
assert_contains "$bicep" 'param queueWorkerReplicaTimeoutSeconds int = 3600'
assert_contains "$bicep" 'param enableScheduleHourlyJob bool = false'
assert_contains "$bicep" "{ name: 'KMP_SKIP_CRON', value: 'true' }"
assert_contains "$bicep" "{ name: 'KMP_SKIP_MIGRATIONS', value: 'true' }"
assert_contains "$bicep" "httpGet: { path: '/livez', port: 80 }"
assert_contains "$bicep" "periodSeconds: 60"
assert_contains "$bicep" "'worker'"
assert_contains "$bicep" "'--cycle-budget'"
assert_contains "$bicep" 'bin/cake migrations migrate && bin/cake updateDatabase && bin/cake platform_migrate migrate'
assert_contains "$workflow" 'cutover-unified-worker.sh'
assert_contains "$workflow" 'Preserve pre-cutover ACA definitions'
assert_contains "$here/cutover-unified-worker.sh" '--fail-on-overlap'
assert_contains "$here/cutover-unified-worker.sh" "0 0 1 1 *"
assert_contains "$scheduler" 'bin/cake platform worker run'
if grep -Fq 'bin/cake platform schedule due' "$scheduler" \
    || grep -Fq 'bin/cake platform queues run' "$scheduler"; then
    echo 'Local tenant scheduler still contains a second background authority.' >&2
    exit 1
fi
if grep -Fq -- '--args /bin/sh' "$here/cutover-unified-worker.sh" \
    || grep -Fq -- '--args /bin/sh' "$here/rollback-unified-worker.sh"; then
    echo 'Azure CLI cannot parse shell flags passed through job update --args.' >&2
    exit 1
fi
if grep -Fq 'az containerapp job update' "$here/cutover-unified-worker.sh" \
    || grep -Fq 'az containerapp job update' "$here/rollback-unified-worker.sh"; then
    echo 'Job mutations must use sanitized ARM patches, not the lossy Azure CLI extension.' >&2
    exit 1
fi

bash -n "$here/update-web-runtime.sh"
bash -n "$here/cutover-unified-worker.sh"
bash -n "$here/rollback-unified-worker.sh"

"$here/update-web-runtime.sh" \
    --resource-group test-rg \
    --web-app test-web \
    --image example.azurecr.io/kmp:test \
    --dry-run >/dev/null
"$here/cutover-unified-worker.sh" \
    --resource-group test-rg \
    --web-app test-web \
    --migrate-job test-migrate \
    --worker-job test-worker \
    --image example.azurecr.io/kmp:test \
    --legacy-job test-scheduler \
    --dry-run >/dev/null
"$here/rollback-unified-worker.sh" \
    --resource-group test-rg \
    --web-app test-web \
    --migrate-job test-migrate \
    --snapshot-dir /tmp/not-read-in-dry-run \
    --dry-run >/dev/null

echo 'Azure runtime contract checks passed.'
