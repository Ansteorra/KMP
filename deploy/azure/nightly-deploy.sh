#!/usr/bin/env bash
# =============================================================================
# KMP — Nightly deploy helper (direct az CLI)
# -----------------------------------------------------------------------------
# Drives the Azure nightly environment without needing the GitHub Actions
# `nightly-deploy-azure.yml` workflow to be registered on the default branch.
#
# What it does mirrors the workflow:
#   1. (optional) trigger a fresh GHCR build via `gh workflow run nightly.yml`
#   2. `az acr import` ghcr.io/jhandel/kmp:<tag> into the nightly ACR
#   3. run the migrate Container Apps Job and wait for Succeeded
#   4. (optional, --reset) run the reset job to reseed the database
#   5. update the web Container App image → forces a new revision
#   6. update the fixed schedule-shape job images
#   7. poll /health until it returns 200
#
# Usage:
#   deploy/azure/nightly-deploy.sh deploy            # deploy current :nightly from GHCR
#   deploy/azure/nightly-deploy.sh deploy-local      # build local checkout, push to ACR, deploy
#   deploy/azure/nightly-deploy.sh deploy --reset    # deploy + wipe+reseed DB
#   deploy/azure/nightly-deploy.sh build             # rebuild image via GH, then deploy
#   deploy/azure/nightly-deploy.sh migrate           # run app + platform migrations on current image
#   deploy/azure/nightly-deploy.sh reset-passwords   # reset tenant member passwords to TestPassword
#   deploy/azure/nightly-deploy.sh verify-tenants    # smoke custom tenant/platform hosts
#   deploy/azure/nightly-deploy.sh reset             # alias: deploy --reset
#   deploy/azure/nightly-deploy.sh status            # recent GH build runs
#   deploy/azure/nightly-deploy.sh health            # curl /health
#   deploy/azure/nightly-deploy.sh url               # print nightly URL
#   deploy/azure/nightly-deploy.sh logs [--tail N]   # tail web container logs
#   deploy/azure/nightly-deploy.sh revisions         # list current revisions
#   deploy/azure/nightly-deploy.sh help
#
# Environment overrides (defaults shown):
#   AZURE_SUBSCRIPTION_ID   0df874b5-82eb-455c-8575-b1f9b589a735
#   AZURE_RESOURCE_GROUP    kmp-nightly-rg
#   AZURE_ACR_NAME          kmpnightlyacrd346d2
#   AZURE_WEB_APP_NAME      kmpnightly-web
#   AZURE_MIGRATE_JOB_NAME  kmpnightly-migrate
#   AZURE_QUEUE_JOB_NAME          kmpnightly-queue
#   AZURE_RESET_JOB_NAME          kmpnightly-reset
#   AZURE_SYNC_JOB_NAME           kmpnightly-sync
#   AZURE_SCHED_HOURLY_JOB_NAME   <unset; skipped if absent>
#   AZURE_SCHED_DAILY_JOB_NAME    <unset; skipped if absent>
#   AZURE_SCHED_WEEKLY_JOB_NAME   <unset; skipped if absent>
#   AZURE_SCHED_NIGHTLY_JOB_NAME  <unset; skipped if absent>
#   IMAGE_TAG               nightly
#   BASE_IMAGE              ghcr.io/ansteorra/kmp-base:php84 (used by `deploy-local`)
#   SKIP_BACKUP_KEY_RECONCILIATION  0 (set to 1 for read-only secret stores)
#   NIGHTLY_BRANCH          <current git branch>   (used by `build`)
#   GH_REPO                 jhandel/KMP            (used by `build` / `status`)
# =============================================================================
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/../.." && pwd)"

# ---- Config (source nightly.env if present for sub/region overrides) --------
if [[ -f "$(dirname "${BASH_SOURCE[0]}")/nightly.env" ]]; then
    set -a
    # shellcheck disable=SC1091
    source "$(dirname "${BASH_SOURCE[0]}")/nightly.env"
    set +a
fi

SUB="${AZURE_SUBSCRIPTION_ID:-0df874b5-82eb-455c-8575-b1f9b589a735}"
RG="${AZURE_RESOURCE_GROUP:-kmp-nightly-rg}"
ACR="${AZURE_ACR_NAME:-kmpnightlyacrd346d2}"
WEB="${AZURE_WEB_APP_NAME:-kmpnightly-web}"
MIGRATE_JOB="${AZURE_MIGRATE_JOB_NAME:-kmpnightly-migrate}"
QUEUE_JOB="${AZURE_QUEUE_JOB_NAME:-kmpnightly-queue}"
RESET_JOB="${AZURE_RESET_JOB_NAME:-kmpnightly-reset}"
SYNC_JOB="${AZURE_SYNC_JOB_NAME:-kmpnightly-sync}"
SCHED_HOURLY_JOB="${AZURE_SCHED_HOURLY_JOB_NAME:-}"
SCHED_DAILY_JOB="${AZURE_SCHED_DAILY_JOB_NAME:-}"
SCHED_WEEKLY_JOB="${AZURE_SCHED_WEEKLY_JOB_NAME:-}"
SCHED_NIGHTLY_JOB="${AZURE_SCHED_NIGHTLY_JOB_NAME:-}"
IMAGE_TAG="${IMAGE_TAG:-nightly}"
LOCAL_IMAGE_TAG="${LOCAL_IMAGE_TAG:-nightly-local-$(date -u +%Y%m%d%H%M%S)}"
TEST_PASSWORD="${TEST_PASSWORD:-TestPassword}"

REPO="${GH_REPO:-jhandel/KMP}"
BRANCH="${NIGHTLY_BRANCH:-$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo feature/workflow-engine)}"
BUILD_WF="nightly.yml"
BUILD_NAME="Nightly / Dev Docker Image"
NIGHTLY_URL="https://kmpnightly-web.lemonstone-62ccb06f.centralus.azurecontainerapps.io"

# ---- Helpers ----------------------------------------------------------------
need() { command -v "$1" >/dev/null 2>&1 || { echo "❌ required: $1" >&2; exit 1; }; }
log()  { printf '\033[36m▶\033[0m %s\n' "$*"; }
ok()   { printf '\033[32m✅\033[0m %s\n' "$*"; }
warn() { printf '\033[33m⚠️ \033[0m %s\n' "$*"; }
die()  { printf '\033[31m❌\033[0m %s\n' "$*" >&2; exit 1; }

ensure_az() {
    need az
    # Ensure we're in the right subscription; az account set is idempotent.
    az account set --subscription "$SUB" >/dev/null 2>&1 \
        || die "az not logged in. Run: az login --tenant 77070ec3-247c-40ce-9a4f-df875ffe914f"
}

acr_login_server() {
    az acr show -n "$ACR" --query loginServer -o tsv
}

job_exists() {
    local job="$1"
    az containerapp job show -g "$RG" -n "$job" >/dev/null 2>&1
}

wait_job() {
    local job="$1" exec_name="$2" label="${3:-$job}" max="${4:-180}"
    for i in $(seq 1 "$max"); do
        local status
        status=$(az containerapp job execution show \
                    -g "$RG" -n "$job" --job-execution-name "$exec_name" \
                    --query properties.status -o tsv 2>/dev/null || echo Unknown)
        printf '  [%d/%d] %s: %s\n' "$i" "$max" "$label" "$status"
        case "$status" in
            Succeeded)
                ok "$label succeeded"
                return 0 ;;
            Failed|Cancelled|Degraded)
                warn "$label finished with $status — log tail:"
                az containerapp job execution show -g "$RG" -n "$job" --job-execution-name "$exec_name" -o yaml 2>&1 | tail -40 || true
                return 1 ;;
        esac
        sleep 10
    done
    die "$label timed out after $((max*10))s"
}

run_job() {
    local job="$1" image="$2" label="${3:-$job}"
    log "Updating $job image → $image"
    az containerapp job update -g "$RG" -n "$job" --image "$image" -o none
    log "Starting $job"
    local exec_name
    exec_name=$(az containerapp job start -g "$RG" -n "$job" --query name -o tsv)
    log "$job execution: $exec_name"
    wait_job "$job" "$exec_name" "$label"
}

update_job_image_if_exists() {
    local job="$1" image="$2"
    [[ -n "$job" ]] || return 0
    if job_exists "$job"; then
        log "Updating $job → $image"
        az containerapp job update -g "$RG" -n "$job" --image "$image" -o none
    else
        warn "Skipping missing optional job: $job"
    fi
}

patch_job_command() {
    need jq
    local job="$1" command_json="$2" args_json="$3"
    local tmp
    tmp="$(mktemp)"
    az containerapp job show -g "$RG" -n "$job" -o json > "$tmp"
    jq --argjson command "$command_json" --argjson args "$args_json" '
        .properties.template.containers[0].command = $command
        | .properties.template.containers[0].args = $args
        | del(.properties.template.containers[].imageType?)
        | {properties:{template:.properties.template}}
    ' "$tmp" > "$tmp.patch"
    az rest --method patch \
        --uri "https://management.azure.com/subscriptions/$SUB/resourceGroups/$RG/providers/Microsoft.App/jobs/$job?api-version=2024-03-01" \
        --body @"$tmp.patch" \
        --only-show-errors >/dev/null
    rm -f "$tmp" "$tmp.patch"
}

restore_migrate_job_noop() {
    patch_job_command "$MIGRATE_JOB" '["/usr/local/bin/docker-entrypoint.sh"]' '["/bin/true"]'
}

run_migrate_command() {
    local label="$1"
    shift
    local args_json
    args_json="$(printf '%s\n' "$@" | jq -R . | jq -s .)"
    log "Running $label via $MIGRATE_JOB: $*"
    patch_job_command "$MIGRATE_JOB" '["/usr/local/bin/docker-entrypoint.sh"]' "$args_json"
    local exec_name
    exec_name=$(az containerapp job start -g "$RG" -n "$MIGRATE_JOB" --query name -o tsv)
    wait_job "$MIGRATE_JOB" "$exec_name" "$label"
}

run_migrations() {
    ensure_az
    trap restore_migrate_job_noop EXIT
    run_migrate_command "app migrations" bin/cake migrations migrate
    run_migrate_command "app settings update" bin/cake updateDatabase
    run_migrate_command "platform migrations" bin/cake platform_migrate migrate
    if [[ "${SKIP_BACKUP_KEY_RECONCILIATION:-0}" == "1" ]]; then
        warn "Skipping platform backup key reconciliation"
    else
        run_migrate_command "platform backup key reconciliation" bin/cake platform backup-keys ensure
    fi
    if [[ "${RUN_RECOMMENDATION_MIGRATION:-0}" == "1" ]]; then
        run_migrate_command "award recommendation migration" \
            bin/cake awards migrate_award_recommendations --apply --allow-open-manual-review
    fi
    restore_migrate_job_noop
    trap - EXIT
}

# ---- Subcommands ------------------------------------------------------------
cmd_url()    { echo "$NIGHTLY_URL"; }

cmd_health() {
    log "GET $NIGHTLY_URL/health"
    curl -fsS "$NIGHTLY_URL/health"
    echo
}

cmd_status() {
    need gh
    echo "── last 5 build runs ($BUILD_NAME) ──"
    gh run list --repo "$REPO" --workflow="$BUILD_WF" --limit 5 2>/dev/null \
        || warn "could not list runs (check gh auth)"
}

cmd_build() {
    need gh
    log "Triggering $BUILD_NAME on $BRANCH"
    gh workflow run "$BUILD_WF" --repo "$REPO" --ref "$BRANCH"
    sleep 4
    local id
    id=$(gh run list --repo "$REPO" --workflow="$BUILD_WF" --limit 1 --json databaseId -q '.[0].databaseId')
    log "Watching build run $id (typically 6–9 min)"
    gh run watch "$id" --repo "$REPO" --exit-status
    ok "build done — proceeding to deploy"
    cmd_deploy "$@"
}

cmd_revisions() {
    ensure_az
    az containerapp revision list -g "$RG" -n "$WEB" \
        --query '[].{name:name,active:properties.active,image:properties.template.containers[0].image,createdTime:properties.createdTime,replicas:properties.replicas}' \
        -o table
}

cmd_logs() {
    ensure_az
    local tail=200
    if [[ "${1:-}" == "--tail" && -n "${2:-}" ]]; then tail="$2"; fi
    az containerapp logs show -g "$RG" -n "$WEB" --tail "$tail" --type console --follow false
}

cmd_migrate() {
    local run_recommendations=0
    for arg in "$@"; do
        case "$arg" in
            --recommendations) run_recommendations=1 ;;
            *) die "unknown migrate option: $arg" ;;
        esac
    done
    RUN_RECOMMENDATION_MIGRATION="$run_recommendations" run_migrations
    ok "migrations complete"
}

cmd_reset_passwords() {
    ensure_az
    need az
    need php
    need psql

    local kv db_url hash count
    kv=$(az keyvault list -g "$RG" --query "[?contains(name, 'kv')].name | [0]" -o tsv)
    [[ -n "$kv" ]] || die "could not discover Key Vault in $RG"

    db_url=$(az keyvault secret show --vault-name "$kv" --name database-url --query value -o tsv)
    [[ -n "$db_url" ]] || die "database-url secret is empty"

    hash=$(PASSWORD="$TEST_PASSWORD" php -r 'echo password_hash(getenv("PASSWORD"), PASSWORD_DEFAULT);')
    log "Resetting tenant member passwords to configured TEST_PASSWORD value"
    count=$(psql "$db_url" -v ON_ERROR_STOP=1 -v hash="$hash" -At <<'SQL'
UPDATE members SET password = :'hash', modified = CURRENT_TIMESTAMP WHERE deleted IS NULL;
SELECT COUNT(*) FROM members WHERE deleted IS NULL;
SQL
)
    ok "password reset complete for $(echo "$count" | tail -1) active member rows"
}

cmd_verify_tenants() {
    local urls=(
        "https://poc-alpha.kmpdev.ansteorra.org/members/login"
        "https://poc-beta.kmpdev.ansteorra.org/members/login"
        "https://plat.kmpdev.ansteorra.org/platform-admin/login"
    )
    local failed=0
    for url in "${urls[@]}"; do
        local body code title marker
        body="$(mktemp)"
        code=$(curl -k -sS -o "$body" -w '%{http_code}' "$url" || echo 000)
        title=$(grep -Eio '<title>[^<]+' "$body" | head -1 | sed 's/<title>//I' || true)
        marker=$(grep -Eio 'Tenant not found|Missing database password|Platform metadata database unavailable|Tenant maintenance in progress|Internal Server Error|Fatal error' "$body" | head -1 || true)
        rm -f "$body"
        printf '%s -> %s %s %s\n' "$url" "$code" "$title" "$marker"
        [[ "$code" == "200" && -z "$marker" ]] || failed=1
    done
    [[ "$failed" == 0 ]] || die "one or more tenant/platform smoke checks failed"
    ok "tenant/platform smoke checks passed"
}

build_local_image() {
    ensure_az
    need docker
    local acr_login image_ref ignore_backup=""
    acr_login=$(acr_login_server)
    image_ref="${acr_login}/kmp:${LOCAL_IMAGE_TAG}"

    log "Logging Docker into $acr_login"
    az acr login -n "$ACR" -o none

    pushd "$ROOT" >/dev/null
    if [[ -f .dockerignore ]]; then
        ignore_backup=".dockerignore.kmp-nightly-backup.$$"
        cp .dockerignore "$ignore_backup"
    fi
    cp docker/.dockerignore.prod .dockerignore
    trap 'if [[ -n "$ignore_backup" && -f "$ignore_backup" ]]; then mv "$ignore_backup" .dockerignore; else rm -f .dockerignore; fi' RETURN

    local app_version
    app_version="${APP_VERSION:-0.0.$(date -u +%Y%m%d%H%M%S)}"
    log "Building local checkout → $image_ref"
    docker buildx build \
        --platform "${LOCAL_BUILD_PLATFORM:-linux/amd64}" \
        --file docker/Dockerfile.prod \
        --build-arg BASE_IMAGE="${BASE_IMAGE:-ghcr.io/ansteorra/kmp-base:php84}" \
        --build-arg APP_VERSION="$app_version" \
        --build-arg RELEASE_CHANNEL="${RELEASE_CHANNEL:-nightly-local}" \
        --tag "$image_ref" \
        --push \
        .
    if [[ -n "$ignore_backup" && -f "$ignore_backup" ]]; then
        mv "$ignore_backup" .dockerignore
    else
        rm -f .dockerignore
    fi
    trap - RETURN
    popd >/dev/null
    ok "local image pushed: $image_ref"
    echo "$image_ref"
}

deploy_image_ref() {
    local image_ref="$1" do_reset="${2:-0}"
    ensure_az

    update_job_image_if_exists "$MIGRATE_JOB" "$image_ref"
    RUN_RECOMMENDATION_MIGRATION="${RUN_RECOMMENDATION_MIGRATION:-0}" run_migrations

    if [[ "$do_reset" == 1 ]]; then
        warn "FULL RESET — dropping & reseeding DB"
        run_job "$RESET_JOB" "$image_ref" "reset"
        cmd_reset_passwords
    fi

    log "Updating web Container App image"
    az containerapp update -g "$RG" -n "$WEB" --image "$image_ref" -o none
    ok "web image updated"

    for job in "$QUEUE_JOB" "$RESET_JOB" "$SYNC_JOB" "$SCHED_HOURLY_JOB" "$SCHED_DAILY_JOB" "$SCHED_WEEKLY_JOB" "$SCHED_NIGHTLY_JOB"; do
        update_job_image_if_exists "$job" "$image_ref"
    done

    cmd_verify_tenants
}

cmd_deploy_local() {
    local do_reset=0 run_recommendations=0
    for arg in "$@"; do
        case "$arg" in
            --reset|reset) do_reset=1 ;;
            --recommendations) run_recommendations=1 ;;
            *) die "unknown deploy-local option: $arg" ;;
        esac
    done

    local acr_login image_ref
    ensure_az
    acr_login=$(acr_login_server)
    image_ref="${acr_login}/kmp:${LOCAL_IMAGE_TAG}"
    build_local_image
    RUN_RECOMMENDATION_MIGRATION="$run_recommendations" deploy_image_ref "$image_ref" "$do_reset"
}

cmd_deploy() {
    local do_reset=0
    local run_recommendations=0
    for arg in "$@"; do
        case "$arg" in
            --reset|reset) do_reset=1 ;;
            --recommendations) run_recommendations=1 ;;
            *) die "unknown deploy option: $arg" ;;
        esac
    done

    ensure_az
    local date_tag="nightly-$(date -u +%Y-%m-%d-%H%M%S)"
    local acr_login
    acr_login=$(acr_login_server)
    local image_ref="${acr_login}/kmp:${date_tag}"

    log "Importing ghcr.io/jhandel/kmp:${IMAGE_TAG} → ${acr_login}/kmp:{${IMAGE_TAG},${date_tag}}"
    az acr import \
        --name "$ACR" \
        --source "ghcr.io/jhandel/kmp:${IMAGE_TAG}" \
        --image "kmp:${IMAGE_TAG}" \
        --image "kmp:${date_tag}" \
        --force -o none
    ok "image imported as $image_ref"

    RUN_RECOMMENDATION_MIGRATION="$run_recommendations" deploy_image_ref "$image_ref" "$do_reset"

    # Smoke-check /health on default Container App FQDN too.
    local fqdn
    fqdn=$(az containerapp show -g "$RG" -n "$WEB" \
            --query properties.configuration.ingress.fqdn -o tsv)
    log "Probing https://$fqdn/health (up to 10 min for new revision)"
    for i in $(seq 1 40); do
        local code
        code=$(curl -sS -o "$HERE/.kmp-health.txt" -w '%{http_code}' "https://$fqdn/health" || echo 000)
        if [[ "$code" == "200" ]]; then
            ok "/health OK"
            cat "$HERE/.kmp-health.txt"; echo
            echo
            ok "Deploy complete: https://$fqdn"
            return 0
        fi
        printf '  [%d/40] /health → %s\n' "$i" "$code"
        sleep 15
    done
    warn "/health never reached 200 within 10 min — recent logs:"
    az containerapp logs show -g "$RG" -n "$WEB" --tail 200 --type console --follow false || true
    die "deploy failed health check"
}

cmd_reset() { cmd_deploy --reset "$@"; }

cmd_watch() {
    need gh
    local id
    id=$(gh run list --repo "$REPO" --workflow="$BUILD_WF" --limit 1 --json databaseId,status -q '.[0] | select(.status != "completed") | .databaseId')
    if [[ -z "$id" ]]; then
        warn "no running build — showing latest run status instead"
        cmd_status
        return
    fi
    log "Watching build run $id"
    gh run watch "$id" --repo "$REPO" --exit-status
}

cmd_help() {
    sed -n '3,45p' "${BASH_SOURCE[0]}" | sed 's/^# \?//'
}

# ---- Dispatch ---------------------------------------------------------------
sub="${1:-help}"
shift || true
case "$sub" in
    deploy-local) cmd_deploy_local "$@" ;;
    reset-passwords) cmd_reset_passwords "$@" ;;
    verify-tenants) cmd_verify_tenants "$@" ;;
    deploy|reset|build|migrate|status|watch|health|url|logs|revisions|help) "cmd_$sub" "$@" ;;
    -h|--help) cmd_help ;;
    *) die "unknown subcommand: $sub (try: deploy-local, deploy, build, migrate, reset, reset-passwords, verify-tenants, status, health, url, logs, revisions, help)" ;;
esac
