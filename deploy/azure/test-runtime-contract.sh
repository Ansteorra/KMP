#!/usr/bin/env bash
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "$here/../.." && pwd)"
bicep="$here/main.bicep"
workflow="$repo_root/.github/workflows/azure-deploy.yml"
poc_workflow="$repo_root/.github/workflows/nightly-deploy-azure.yml"
scheduler="$repo_root/docker/scheduler-loop.sh"
extension_helper="$here/ensure-postgres-extension.sh"

assert_contains() {
    local file="$1"
    local expected="$2"

    if ! grep -Fq -- "$expected" "$file"; then
        echo "Missing deployment contract in $file: $expected" >&2
        exit 1
    fi
}

assert_contains "$bicep" "param queueWorkerCron string = '*/3 * * * *'"
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
assert_contains "$workflow" 'Preserve pre-cutover definitions'
assert_contains "$workflow" 'AZURE_POSTGRES_RESOURCE_GROUP'
assert_contains "$workflow" 'AZURE_POSTGRES_SERVER_NAME'
assert_contains "$workflow" 'ensure-postgres-extension.sh'
assert_contains "$poc_workflow" 'uses: ./.github/workflows/azure-deploy.yml'
assert_contains "$here/bootstrap.sh" 'ensure-postgres-extension.sh'
assert_contains "$here/bootstrap.sh" 'AZURE_POSTGRES_RESOURCE_GROUP'
assert_contains "$here/configure-github-cd.sh" 'AZURE_POSTGRES_RESOURCE_GROUP'
assert_contains "$here/configure-github-cd.sh" 'AZURE_POSTGRES_SERVER_NAME'
assert_contains "$here/configure-github-cd.sh" 'Microsoft.DBforPostgreSQL/flexibleServers/configurations/read'
assert_contains "$here/configure-github-cd.sh" 'Microsoft.DBforPostgreSQL/flexibleServers/configurations/write'
assert_contains "$here/update-web-runtime.sh" 'del(.scale.cooldownPeriod?, .scale.pollingInterval?)'
assert_contains "$here/rollback-unified-worker.sh" 'del(.scale.cooldownPeriod?, .scale.pollingInterval?)'
assert_contains "$here/cutover-unified-worker.sh" '--fail-on-overlap'
assert_contains "$here/cutover-unified-worker.sh" "0 0 1 1 *"
assert_contains "$here/cutover-unified-worker.sh" "*/3 * * * *"
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
assert_contains "$here/cutover-unified-worker.sh" '--job-execution-name "$execution"'
if grep -Fq -- '--job-name "$job"' "$here/cutover-unified-worker.sh"; then
    echo 'Execution polling uses the wrong Container Apps extension argument names.' >&2
    exit 1
fi

bash -n "$here/update-web-runtime.sh"
bash -n "$here/cutover-unified-worker.sh"
bash -n "$here/rollback-unified-worker.sh"
bash -n "$extension_helper"

extension_line="$(grep -n 'ensure-postgres-extension.sh' "$workflow" | head -1 | cut -d: -f1)"
cutover_line="$(grep -n 'name: Cut over worker, migrations, and web' "$workflow" | head -1 | cut -d: -f1)"
if [[ "$extension_line" -ge "$cutover_line" ]]; then
    echo 'PostgreSQL extensions must be allowlisted before the migration cutover.' >&2
    exit 1
fi

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

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT
printf '%s\n' \
    '#!/usr/bin/env bash' \
    'printf "%s\n" "PGCRYPTO, uuid-ossp"' > "$tmpdir/az"
chmod +x "$tmpdir/az"
extension_output="$(
    PATH="$tmpdir:$PATH" "$extension_helper" \
        --resource-group test-rg \
        --server-name test-pg \
        --extension CITEXT \
        --dry-run
)"
if [[ "$extension_output" != *'PGCRYPTO,uuid-ossp,CITEXT'* ]]; then
    echo 'PostgreSQL extension helper did not preserve the existing allowlist.' >&2
    exit 1
fi

echo 'Azure runtime contract checks passed.'
