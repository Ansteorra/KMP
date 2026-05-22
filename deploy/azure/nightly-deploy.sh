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
#   4. (optional, --reset) run the restore job to reseed the database
#   5. update the web Container App image → forces a new revision
#   6. update the fixed schedule-shape job images
#   7. poll /health until it returns 200
#
# Usage:
#   deploy/azure/nightly-deploy.sh deploy            # deploy current :nightly
#   deploy/azure/nightly-deploy.sh deploy --reset    # deploy + wipe+reseed DB
#   deploy/azure/nightly-deploy.sh build             # rebuild image via GH, then deploy
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
#   AZURE_RESTORE_JOB_NAME        kmpnightly-restore
#   AZURE_SCHED_HOURLY_JOB_NAME   kmpnightly-sched-hourly
#   AZURE_SCHED_DAILY_JOB_NAME    kmpnightly-sched-daily
#   AZURE_SCHED_WEEKLY_JOB_NAME   kmpnightly-sched-weekly
#   AZURE_SCHED_NIGHTLY_JOB_NAME  kmpnightly-sched-nightly
#   IMAGE_TAG               nightly
#   NIGHTLY_BRANCH          <current git branch>   (used by `build`)
#   GH_REPO                 jhandel/KMP            (used by `build` / `status`)
# =============================================================================
set -euo pipefail

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
RESTORE_JOB="${AZURE_RESTORE_JOB_NAME:-${AZURE_RESET_JOB_NAME:-kmpnightly-restore}}"
SCHED_HOURLY_JOB="${AZURE_SCHED_HOURLY_JOB_NAME:-kmpnightly-sched-hourly}"
SCHED_DAILY_JOB="${AZURE_SCHED_DAILY_JOB_NAME:-${AZURE_SYNC_JOB_NAME:-kmpnightly-sched-daily}}"
SCHED_WEEKLY_JOB="${AZURE_SCHED_WEEKLY_JOB_NAME:-kmpnightly-sched-weekly}"
SCHED_NIGHTLY_JOB="${AZURE_SCHED_NIGHTLY_JOB_NAME:-kmpnightly-sched-nightly}"
IMAGE_TAG="${IMAGE_TAG:-nightly}"

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

wait_job() {
    local job="$1" exec_name="$2" label="${3:-$job}" max="${4:-180}"
    for i in $(seq 1 "$max"); do
        local status
        status=$(az containerapp job execution show \
                    -g "$RG" --job-name "$job" -n "$exec_name" \
                    --query properties.status -o tsv 2>/dev/null || echo Unknown)
        printf '  [%d/%d] %s: %s\n' "$i" "$max" "$label" "$status"
        case "$status" in
            Succeeded)
                ok "$label succeeded"
                return 0 ;;
            Failed|Cancelled|Degraded)
                warn "$label finished with $status — log tail:"
                az containerapp job execution show -g "$RG" --job-name "$job" -n "$exec_name" -o yaml 2>&1 | tail -40 || true
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

cmd_deploy() {
    local do_reset=0
    for arg in "$@"; do
        case "$arg" in
            --reset|reset) do_reset=1 ;;
        esac
    done

    ensure_az
    local date_tag="nightly-$(date -u +%Y-%m-%d-%H%M%S)"
    local acr_login
    acr_login=$(az acr show -n "$ACR" --query loginServer -o tsv)
    local image_ref="${acr_login}/kmp:${date_tag}"

    log "Importing ghcr.io/jhandel/kmp:${IMAGE_TAG} → ${acr_login}/kmp:{${IMAGE_TAG},${date_tag}}"
    az acr import \
        --name "$ACR" \
        --source "ghcr.io/jhandel/kmp:${IMAGE_TAG}" \
        --image "kmp:${IMAGE_TAG}" \
        --image "kmp:${date_tag}" \
        --force -o none
    ok "image imported as $image_ref"

    # 1. Migrate
    run_job "$MIGRATE_JOB" "$image_ref" "migrate"

    # 2. Optional full reset
    if [[ "$do_reset" == 1 ]]; then
        warn "FULL RESET — dropping & reseeding DB (all passwords reset to TestPassword)"
        run_job "$RESTORE_JOB" "$image_ref" "restore"
    fi

    # 3. Web
    log "Updating web Container App image"
    az containerapp update -g "$RG" -n "$WEB" --image "$image_ref" -o none
    ok "web image updated"

    # 4. Other jobs so cron/manual starts use the new image next run
    for job in "$QUEUE_JOB" "$RESTORE_JOB" "$SCHED_HOURLY_JOB" "$SCHED_DAILY_JOB" "$SCHED_WEEKLY_JOB" "$SCHED_NIGHTLY_JOB"; do
        log "Updating $job → $image_ref"
        az containerapp job update -g "$RG" -n "$job" --image "$image_ref" -o none
    done

    # 5. Smoke-check /health
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
    sed -n '3,36p' "${BASH_SOURCE[0]}" | sed 's/^# \?//'
}

# ---- Dispatch ---------------------------------------------------------------
sub="${1:-help}"
shift || true
case "$sub" in
    deploy|reset|build|status|watch|health|url|logs|revisions|help) "cmd_$sub" "$@" ;;
    -h|--help) cmd_help ;;
    *) die "unknown subcommand: $sub (try: deploy, build, reset, status, health, url, logs, revisions, help)" ;;
esac
