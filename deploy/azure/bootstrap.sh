#!/usr/bin/env bash
# =============================================================================
# KMP Nightly — One-time Azure bootstrap
#
# Creates (idempotent):
#   - Resource group
#   - Azure AD app + federated credential for GitHub Actions OIDC
#   - Imports ghcr.io/ansteorra/kmp:nightly into a freshly-provisioned ACR
#   - Runs `az deployment group create` with deploy/azure/main.bicep
#   - Writes the AAD client ID + all Azure context back into nightly.env
#   - (optionally) configures GitHub POC environment variables with `gh`
#
# Usage:
#   cd deploy/azure
#   ./bootstrap.sh                          # full bootstrap
#   ./bootstrap.sh --skip-gh-secrets        # don't configure GitHub
#   ./bootstrap.sh --github-repo owner/repo # override the GitHub repository
#
# Requirements:
#   - `az` logged in (`az login`) as an account with Owner/Contributor + User
#     Access Administrator on the target subscription (role assignments)
#   - `gh` authenticated (unless --skip-gh-secrets)
# =============================================================================
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$HERE/nightly.env"
REPO_ROOT="$(cd "$HERE/../.." && pwd)"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Error: $ENV_FILE not found. Copy nightly.env.example and fill it in." >&2
    exit 1
fi

# shellcheck disable=SC1090
set -a; source "$ENV_FILE"; set +a

# --- args
SKIP_GH=0
GITHUB_REPO="Ansteorra/KMP"
while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-gh-secrets) SKIP_GH=1; shift ;;
        --github-repo) GITHUB_REPO="$2"; shift 2 ;;
        -h|--help) sed -n '3,25p' "$0"; exit 0 ;;
        *) echo "Unknown arg: $1" >&2; exit 64 ;;
    esac
done

# --- validate required env
require() {
    local name="$1"
    if [[ -z "${!name:-}" ]]; then
        echo "Error: required var $name not set in nightly.env" >&2
        exit 2
    fi
}
for v in AZURE_SUBSCRIPTION_ID AZURE_TENANT_ID AZURE_REGION AZURE_RESOURCE_GROUP \
         AZURE_NAME_PREFIX SECURITY_SALT POSTGRES_ADMIN_PASSWORD BACKUP_ENCRYPTION_KEY \
         PLATFORM_SECRETS_MASTER_KEY EMAIL_SMTP_HOST EMAIL_SMTP_PORT EMAIL_FROM; do
    require "$v"
done

echo "=== KMP Azure Bootstrap ==="
echo "Subscription : $AZURE_SUBSCRIPTION_ID"
echo "Tenant       : $AZURE_TENANT_ID"
echo "Region       : $AZURE_REGION"
echo "RG           : $AZURE_RESOURCE_GROUP"
echo "Name prefix  : $AZURE_NAME_PREFIX"
echo "GitHub repo  : $GITHUB_REPO"
echo

az account set --subscription "$AZURE_SUBSCRIPTION_ID"

# --- 1. Resource group
echo "--- Ensuring resource group exists..."
az group create -n "$AZURE_RESOURCE_GROUP" -l "$AZURE_REGION" -o none

# --- 2. Resolve deployer principal ID (so bicep can grant KV Secrets Officer)
DEPLOYER_PRINCIPAL_ID="$(az ad signed-in-user show --query id -o tsv 2>/dev/null || echo '')"
if [[ -z "$DEPLOYER_PRINCIPAL_ID" ]]; then
    echo "  (signed-in user is a service principal — skipping deployer KV role assignment)"
fi

# --- 3. Pre-create ACR so we can import the image *before* Container Apps try to pull it
#     We let Bicep own the ACR, so we pick the same name it will compute.
UNIQUE="$(az group show -n "$AZURE_RESOURCE_GROUP" --query id -o tsv | sha256sum | cut -c1-6)"
ACR_NAME="$(echo "${AZURE_NAME_PREFIX}acr${UNIQUE}" | tr '[:upper:]' '[:lower:]')"
echo "  (chosen ACR name: $ACR_NAME)"

echo "--- Deploying bicep (takes 5-15 min first run)..."
DEPLOY_NAME="kmp-nightly-$(date -u +%Y%m%d-%H%M%S)"
IMAGE_REPO="${ACR_NAME}.azurecr.io/kmp"

# First pass: deploy everything except the Container Apps (they'd fail pulling a
# not-yet-imported image). Technique: deploy full template but using the public
# GHCR image as a stand-in; we'll swap to ACR on pass 2 after `az acr import`.
# Simpler: deploy full template using ghcr.io directly for first pass — but
# Container Apps registry auth requires a username/password for ghcr.io public
# pulls won't work without creds for the registries[] entry.
#
# Cleanest: deploy ACR-only first, import image, then deploy full template.
echo "    (pass 1/2) provisioning ACR so we can mirror the nightly image..."
ACR_BICEP="$HERE/.acr-bootstrap.bicep"
trap 'rm -f "$ACR_BICEP"' EXIT
cat > "$ACR_BICEP" <<'BICEP'
param location string = resourceGroup().location
param acrName string
resource acr 'Microsoft.ContainerRegistry/registries@2023-11-01-preview' = {
  name: acrName
  location: location
  sku: { name: 'Basic' }
  properties: { adminUserEnabled: false }
}
output loginServer string = acr.properties.loginServer
BICEP
az deployment group create \
    -g "$AZURE_RESOURCE_GROUP" \
    -n "${DEPLOY_NAME}-acr" \
    --template-file "$ACR_BICEP" \
    --parameters acrName="$ACR_NAME" location="$AZURE_REGION" \
    -o none

echo "    importing ghcr.io/ansteorra/kmp:nightly into ACR..."
az acr import \
    --name "$ACR_NAME" \
    --source "ghcr.io/ansteorra/kmp:nightly" \
    --image "kmp:nightly" \
    --force

echo "    (pass 2/2) full deployment..."
az deployment group create \
    -g "$AZURE_RESOURCE_GROUP" \
    -n "$DEPLOY_NAME" \
    --template-file "$HERE/main.bicep" \
    --parameters \
        location="$AZURE_REGION" \
        namePrefix="$AZURE_NAME_PREFIX" \
        acrName="$ACR_NAME" \
        imageRepository="$IMAGE_REPO" \
        imageTag="nightly" \
        postgresAdminPassword="$POSTGRES_ADMIN_PASSWORD" \
        securitySalt="$SECURITY_SALT" \
        backupEncryptionKey="$BACKUP_ENCRYPTION_KEY" \
        platformSecretsMasterKey="$PLATFORM_SECRETS_MASTER_KEY" \
        emailSmtpHost="$EMAIL_SMTP_HOST" \
        emailSmtpPort="$EMAIL_SMTP_PORT" \
        emailSmtpUsername="${EMAIL_SMTP_USERNAME:-}" \
        emailSmtpPassword="${EMAIL_SMTP_PASSWORD:-}" \
        emailSmtpTls="${EMAIL_SMTP_TLS:-false}" \
        emailFrom="$EMAIL_FROM" \
        deployerPrincipalId="${DEPLOYER_PRINCIPAL_ID:-}" \
    -o none

# --- 4. Read outputs
OUT_FILE="$HERE/.azure-outputs.json"
az deployment group show \
    -g "$AZURE_RESOURCE_GROUP" -n "$DEPLOY_NAME" \
    --query properties.outputs \
    -o json > "$OUT_FILE"
echo "    outputs saved to $OUT_FILE"

jqget() { python3 -c "import json,sys;print(json.load(open('$OUT_FILE'))['$1']['value'])"; }
WEB_FQDN="$(jqget webAppFqdn)"
ACR_LOGIN_SERVER="$(jqget acrLoginServer)"
POSTGRES_FQDN="$(jqget postgresFqdn)"
POSTGRES_SERVER_NAME="$(jqget postgresServerName)"
KV_NAME="$(jqget keyVaultName)"
MIGRATE_JOB="$(jqget migrateJobName)"
QUEUE_JOB="$(jqget queueJobName)"
RESTORE_JOB="$(jqget restoreJobName)"
PROVISION_JOB="$(jqget provisionJobName)"
SCHED_HOURLY_JOB="$(jqget scheduleHourlyJobName)"
SCHED_DAILY_JOB="$(jqget scheduleDailyJobName)"
SCHED_WEEKLY_JOB="$(jqget scheduleWeeklyJobName)"
SCHED_NIGHTLY_JOB="$(jqget scheduleNightlyJobName)"
WEB_APP="$(jqget webAppName)"

# --- 5. AAD app + federated credential for GitHub OIDC
AAD_APP_NAME="kmp-poc-github-oidc"
echo "--- Ensuring AAD app '$AAD_APP_NAME' and federated credential..."
APP_ID="$(az ad app list --display-name "$AAD_APP_NAME" --query '[0].appId' -o tsv 2>/dev/null || true)"
if [[ -z "$APP_ID" ]]; then
    APP_ID="$(az ad app create --display-name "$AAD_APP_NAME" --query appId -o tsv)"
    echo "    created AAD app: $APP_ID"
else
    echo "    AAD app exists: $APP_ID"
fi

SP_OBJECT_ID="$(az ad sp list --filter "appId eq '$APP_ID'" --query '[0].id' -o tsv 2>/dev/null || true)"
if [[ -z "$SP_OBJECT_ID" ]]; then
    SP_OBJECT_ID="$(az ad sp create --id "$APP_ID" --query id -o tsv)"
    echo "    created service principal: $SP_OBJECT_ID"
fi

# Grant Contributor on the RG so the workflow can update containerapps + acr import
RG_ID="$(az group show -n "$AZURE_RESOURCE_GROUP" --query id -o tsv)"
az role assignment create \
    --assignee-object-id "$SP_OBJECT_ID" \
    --assignee-principal-type ServicePrincipal \
    --role Contributor \
    --scope "$RG_ID" \
    -o none 2>/dev/null || true

# Federated credential is scoped to the GitHub POC environment.
for subject in "repo:${GITHUB_REPO}:environment:poc"; do
    cred_name="gh-$(echo "$subject" | tr ':/' '--')"
    cred_name="${cred_name:0:120}"
    exists="$(az ad app federated-credential list --id "$APP_ID" --query "[?name=='$cred_name'] | length(@)" -o tsv 2>/dev/null || echo 0)"
    if [[ "$exists" == "0" ]]; then
        az ad app federated-credential create --id "$APP_ID" \
            --parameters "{\"name\":\"$cred_name\",\"issuer\":\"https://token.actions.githubusercontent.com\",\"subject\":\"$subject\",\"audiences\":[\"api://AzureADTokenExchange\"]}" \
            -o none
        echo "    added federated credential: $cred_name"
    fi
done

# --- 6. Persist AZURE_CLIENT_ID and outputs back into nightly.env
if grep -q '^AZURE_CLIENT_ID=' "$ENV_FILE"; then
    sed -i "s|^AZURE_CLIENT_ID=.*|AZURE_CLIENT_ID=$APP_ID|" "$ENV_FILE"
else
    printf '\n# Populated by bootstrap.sh\nAZURE_CLIENT_ID=%s\nACR_LOGIN_SERVER=%s\nWEB_APP_FQDN=%s\n' \
        "$APP_ID" "$ACR_LOGIN_SERVER" "$WEB_FQDN" >> "$ENV_FILE"
fi

# --- 7. Configure the GitHub POC environment (optional)
if [[ $SKIP_GH -eq 0 ]]; then
    if command -v gh >/dev/null; then
        echo "--- Setting GitHub POC environment variables on $GITHUB_REPO..."
        gh api --method PUT "repos/$GITHUB_REPO/environments/poc" >/dev/null
        gh variable set AZURE_CLIENT_ID --body "$APP_ID" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_TENANT_ID --body "$AZURE_TENANT_ID" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_SUBSCRIPTION_ID --body "$AZURE_SUBSCRIPTION_ID" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_RESOURCE_GROUP --body "$AZURE_RESOURCE_GROUP" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_ACR_NAME --body "$ACR_NAME" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_POSTGRES_RESOURCE_GROUP --body "$AZURE_RESOURCE_GROUP" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_POSTGRES_SERVER_NAME --body "$POSTGRES_SERVER_NAME" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_WEB_APP_NAME --body "$WEB_APP" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_MIGRATE_JOB_NAME --body "$MIGRATE_JOB" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_QUEUE_JOB_NAME --body "$QUEUE_JOB" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_RESTORE_JOB_NAME --body "$RESTORE_JOB" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_PROVISION_JOB_NAME --body "$PROVISION_JOB" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_SCHED_HOURLY_JOB_NAME --body "$SCHED_HOURLY_JOB" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_SCHED_DAILY_JOB_NAME --body "$SCHED_DAILY_JOB" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_SCHED_WEEKLY_JOB_NAME --body "$SCHED_WEEKLY_JOB" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_SCHED_NIGHTLY_JOB_NAME --body "$SCHED_NIGHTLY_JOB" --env poc --repo "$GITHUB_REPO"
        # Backward-compatible names for older workflows.
        gh variable set AZURE_SYNC_JOB_NAME --body "$SCHED_DAILY_JOB" --env poc --repo "$GITHUB_REPO"
        gh variable set AZURE_RESET_JOB_NAME --body "$RESTORE_JOB" --env poc --repo "$GITHUB_REPO"
    else
        echo "    gh CLI not found; install it and re-run with secrets unchanged."
    fi
fi

# --- 8. Allow extensions required by PostgreSQL migrations
"$HERE/ensure-postgres-extension.sh" \
    --resource-group "$AZURE_RESOURCE_GROUP" \
    --server-name "$POSTGRES_SERVER_NAME" \
    --extension CITEXT

# --- 9. Apply migrations before the request-only web revision serves traffic
echo "--- Starting migration job..."
MIGRATE_EXECUTION="$(az containerapp job start \
    -g "$AZURE_RESOURCE_GROUP" \
    -n "$MIGRATE_JOB" \
    --query name \
    -o tsv)"
for attempt in $(seq 1 180); do
    MIGRATE_STATUS="$(az containerapp job execution show \
        -g "$AZURE_RESOURCE_GROUP" \
        --job-name "$MIGRATE_JOB" \
        -n "$MIGRATE_EXECUTION" \
        --query properties.status \
        -o tsv 2>/dev/null || echo Unknown)"
    echo "    [$attempt/180] migrate status: $MIGRATE_STATUS"
    case "$MIGRATE_STATUS" in
        Succeeded) break ;;
        Failed|Cancelled|Degraded)
            echo "Error: migration job finished with $MIGRATE_STATUS." >&2
            exit 1
            ;;
    esac
    if [[ "$attempt" -eq 180 ]]; then
        echo "Error: migration job timed out after 30 minutes." >&2
        exit 1
    fi
    sleep 10
done

# --- 10. Kick the restore job: full schema rebuild + dev seed + password reset
# NOTE: requires /opt/kmp/reset-and-seed.sh to be present in the image. If you
# bootstrap before the next nightly rebuild, the reset will fail — just run
# the migrate job instead, and re-run reset after the image catches up.
echo "--- Starting restore-from-seed job (non-fatal)..."
RESET_JOB="$RESTORE_JOB"
RESET_KICK_ERR="$HERE/.reset-kick.err"
if ! az containerapp job start -g "$AZURE_RESOURCE_GROUP" -n "$RESET_JOB" -o none 2>"$RESET_KICK_ERR"; then
    echo "    warn: restore job start failed ($(cat "$RESET_KICK_ERR"))."
    echo "    You can retry after the next nightly image build includes reset-and-seed.sh:"
    echo "    az containerapp job start -g $AZURE_RESOURCE_GROUP -n $RESET_JOB"
fi
echo "    Watch progress with:"
echo "    az containerapp job execution list -g $AZURE_RESOURCE_GROUP -n $RESET_JOB -o table"
echo "    az containerapp logs show  -g $AZURE_RESOURCE_GROUP -n $RESET_JOB --container restore --tail 200 --follow"

echo
echo "=== Bootstrap complete ==="
echo "  Web FQDN      : https://$WEB_FQDN"
echo "  Health check  : https://$WEB_FQDN/health"
echo "  Postgres host : $POSTGRES_FQDN"
echo "  Key Vault     : $KV_NAME"
echo "  AAD client ID : $APP_ID"
echo
echo "After the reset job finishes (check logs), every member account has"
echo "password = TestPassword. The nightly image ships the encrypted dev seed"
echo "at /opt/kmp/seed/nightly-seed.kmpbackup — rerun anytime with:"
echo "  az containerapp job start -g $AZURE_RESOURCE_GROUP -n $RESET_JOB"
