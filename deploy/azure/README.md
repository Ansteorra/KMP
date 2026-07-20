# KMP Azure Deployment

The POC KMP environment runs on **Azure Container Apps + Jobs**, backed by
**Azure Database for PostgreSQL Flexible Server**, with the Docker image
mirrored nightly from GHCR into an **Azure Container Registry**. Every
resource is defined in [`main.bicep`](./main.bicep); nothing is clicked in the
portal.

Seed data lives in [`seed/nightly-seed.kmpbackup`](./seed/) — an
engine-agnostic, AES-256-GCM-encrypted backup produced by
`seed/bake-seed.sh` from a known-good local dev environment. The in-container
reset job (`docker/reset-and-seed.sh`) restores this file via
`bin/cake backup restore`, so the nightly env state is byte-identical to what
any developer sees after running `reset_dev_database.sh`.

## Architecture

```
 GitHub Actions (nightly.yml)                         ┌────────────────────────┐
 └── builds dev → ghcr.io/ansteorra/kmp:dev-SHA ───┐ │  Azure resource group  │
                                                    │  │  kmp-nightly-rg        │
 GitHub Actions (nightly-deploy-azure.yml)         │  │                        │
  1. OIDC → Azure                                  │  │  ACR <prefix>acr<hash> │
  2. az acr import ghcr→ACR                        └─▶│  └─ kmp:nightly-DATE   │
  3. configure + canary unified worker                │                        │
  4. repair + run migrate job (wait)                  │  Key Vault             │
  5. update request-only web + probes                 │  ├─ security-salt      │
  6. smoke /livez and /health                         │  ├─ database-url       │
                                                       │  ├─ platform-db-url    │
                                                       │  ├─ platform-secret-key│
                                                       │  ├─ postgres-admin-pwd │
                                                       │  ├─ backup-enc-key     │
                                                       │  └─ email-smtp-pwd     │
                                                       │                        │
                                                       │  StorageV2 docs        │
                                                       │  └─ per-tenant blobs   │
                                                       │                        │
                                                       │  Postgres Flex (B1ms)  │
                                                       │  ├─ kmp_nightly db     │
                                                       │  └─ kmp_platform db    │
                                                       │                        │
                                                       │  Container Apps env    │
                                                       │  ├─ <prefix>-web       │
                                                       │  └─ jobs:              │
                                                       │     migrate, restore,  │
                                                       │     provision, unified │
                                                       │     background worker  │
                                                       └────────────────────────┘
```

All Container Apps Jobs reuse the exact same image, managed identity, Key
Vault-backed secrets, platform database URL, and storage RBAC as the web app.
Jobs are **fixed schedule shapes**, not tenant-specific infrastructure. Adding
a tenant updates platform metadata and queues tenant-aware work internally; it
does not add Azure resources.

Default job shapes:
- `<prefix>-migrate` — manual migration/canary job; entrypoint applies app
  migrations, then `bin/cake platform_migrate migrate` applies platform metadata
  migrations.
- `<prefix>-restore` — manual restore-from-seed operation using
  `/opt/kmp/reset-and-seed.sh`.
- `<prefix>-provision` — manual tenant provision operation shape. The safe
  default prints command help; operators override args for a specific tenant.
- `<prefix>-queue` — the single one-minute background authority. It runs
  `bin/cake platform worker run`, dispatching due schedules, draining the
  default and active-tenant Queue datasources, and claiming one bounded
  platform job.
- `<prefix>-sched-hourly`, `<prefix>-sched-daily`, `<prefix>-sched-weekly`,
  `<prefix>-sched-nightly` — disabled compatibility shapes. Existing instances
  are parked on an annual no-op schedule after a successful cutover. Stored
  platform schedule rows own cron expressions, tenant scope (`platform`, all
  active tenants, or one tenant), and command payloads.

The Bicep parameters under **Fixed schedule-shape job controls** tune the unified
Job without embedding secrets. Keep parallelism and replica completion count at
`1`. PostgreSQL advisory locks prevent overlapping executions from claiming the
same stored schedule. Queue and platform-job claims provide the equivalent
duplicate protection for those lanes. The worker rotates its tenant starting
point each cycle, enforces a 240-second queue budget, and skips a tenant registry
entry when it points to the already-processed default physical database.
The plain `queue run` command must not be used as the fleet worker because it
only sees the current default datasource. The consolidation migration disables
the legacy `tenant-queue-drain` and `platform-admin-job-runner` rows, so the
unified worker must be live before that migration reaches an environment.

The web container sets `KMP_SKIP_CRON=true` and `KMP_SKIP_MIGRATIONS=true`.
`/livez` is a static liveness file checked every 60 seconds; `/health` is a
60-second readiness check for PostgreSQL and the required shared Redis
cache/session backend. Successful probe requests and their SQL are excluded from
request and OTLP telemetry. ACA image cache files are replica-local ephemeral
data and are reclaimed with replica/revision replacement; persistent self-hosted
image caches must retain the documented `image_cache_gc` schedule.

## One-time bootstrap

Everything below is idempotent; rerun safely.

### Prerequisites
- `az` CLI logged in as an account with **Owner** (or Contributor + User
  Access Administrator) on the subscription
- `gh` CLI authenticated (for configuring the GitHub POC environment)
- You are in the repo root.
- `deploy/azure/seed/nightly-seed.kmpbackup` exists in the repo (bake one
  via `deploy/azure/seed/bake-seed.sh` if this is the first time — see
  [`seed/README.md`](./seed/README.md)).

### 1. Fill in settings

```bash
cp deploy/azure/nightly.env.example deploy/azure/nightly.env
# edit deploy/azure/nightly.env — generate strong secrets with:
#   openssl rand -hex 32                       # for SECURITY_SALT and BACKUP_ENCRYPTION_KEY
#   openssl rand -base64 24 | tr -d '/+='      # for POSTGRES_ADMIN_PASSWORD
```

`BACKUP_ENCRYPTION_KEY` must match the key you used (or will use) when
running `bake-seed.sh`. Save both values in a password manager.

`nightly.env` is gitignored — never commit real values.

### 2. Run bootstrap

```bash
cd deploy/azure
./bootstrap.sh
```

This will:
1. Register required Azure resource providers (already done in your sub).
2. Create the resource group.
3. Provision the ACR and `az acr import` the current
   `ghcr.io/ansteorra/kmp:nightly`.
4. Deploy all infrastructure from `main.bicep`.
5. Create an AAD app `kmp-poc-github-oidc` with a federated credential scoped
   to the `Ansteorra/KMP` `poc` environment.
6. Assign the AAD app **Contributor** on the resource group.
7. Push the OIDC and infrastructure names as non-secret `poc` environment
   variables via `gh`.
8. Start the `kmp-migrate` job to apply base migrations.

Skip `gh` integration with `./bootstrap.sh --skip-gh-secrets`.

### 3. Seed / reset the database

Seeding runs **inside the container** via the `kmp-reset` Container Apps Job,
which mirrors `reset_dev_database.sh`:

1. `bin/cake resetDatabase` — drop + recreate schema
2. Load `/opt/kmp/seed.sql` (the dev seed, baked into the image)
3. `bin/cake migrations migrate` + `bin/cake updateDatabase`
4. Reset every member password to `TestPassword`
5. Clear caches

`bootstrap.sh` kicks this job automatically at the end. To re-run it any time:

```bash
RG="$AZURE_RESOURCE_GROUP"
az containerapp job start -g "$RG" -n "${AZURE_NAME_PREFIX}-reset"

# watch progress
az containerapp logs show -g "$RG" -n "${AZURE_NAME_PREFIX}-reset" --container reset --tail 200 --follow
```

The reset job remains an explicit operator action and is not part of the
automatic POC deployment workflow.

### 4. Verify

```bash
WEB=$(az containerapp show -g kmp-nightly-rg -n kmpnightly-web \
      --query properties.configuration.ingress.fqdn -o tsv)
curl -sv "https://$WEB/health"
```

## POC deployments

Every successful `dev` branch image build triggers
`nightly-deploy-azure.yml` via `workflow_run`. Scheduled `main` builds still
publish the `nightly` channel but do not deploy it automatically. The POC
workflow:

1. Logs in to Azure via OIDC — **no long-lived secrets**
2. Imports the immutable `dev-SHA` image from the official Ansteorra GHCR
   package into the POC ACR
3. Captures the current web and Job definitions as a rollback artifact
4. Repairs and manually canaries the one-minute unified worker
5. Repairs `kmp-migrate`, runs it, and requires success
6. Runs a post-migration worker verification
7. Atomically updates the web image, skip flags, and split probes
8. Requires both `/livez` and `/health` to return 200
9. Parks existing legacy scheduler Jobs on an annual no-op schedule

The same ordered cutover can be run manually with
`deploy/azure/cutover-unified-worker.sh`. Pass the captured snapshot directory
to `deploy/azure/rollback-unified-worker.sh` to re-enable the legacy queue
schedules and restore the prior ACA runtime definitions. It does not
destructively roll back tenant application data.

You can also trigger it manually from the **Actions** tab → "POC / Deploy to
Azure" → **Run workflow**, optionally overriding the image tag.

## Ad-hoc nightly deploys from your workstation

Use [`nightly-deploy.sh`](./nightly-deploy.sh) from the repository root for
operator-driven releases. It talks directly to Azure and is safe to run outside
GitHub Actions.

Prerequisites:

```bash
az login --tenant 77070ec3-247c-40ce-9a4f-df875ffe914f
az account set --subscription 0df874b5-82eb-455c-8575-b1f9b589a735
```

Common flows:

```bash
# Build the current local checkout, push it to ACR, run migrations, update web/jobs,
# and verify the custom tenant/platform hosts.
bash deploy/azure/nightly-deploy.sh deploy-local

# Same, but also run the Awards recommendation migration command.
bash deploy/azure/nightly-deploy.sh deploy-local --recommendations

# Deploy the already-published GHCR :nightly image instead of building locally.
bash deploy/azure/nightly-deploy.sh deploy

# Run app + platform migrations against the currently configured image.
bash deploy/azure/nightly-deploy.sh migrate

# Run app + platform migrations plus the recommendation migration.
bash deploy/azure/nightly-deploy.sh migrate --recommendations

# Reset all active tenant member passwords to TestPassword.
bash deploy/azure/nightly-deploy.sh reset-passwords

# Smoke-check the current custom host routing.
bash deploy/azure/nightly-deploy.sh verify-tenants
```

`deploy-local` intentionally builds from your current working tree, including
uncommitted changes. It temporarily copies `docker/.dockerignore.prod` into the
repository root for the Docker build and restores the previous `.dockerignore`
afterward. The default local image tag is
`nightly-local-YYYYMMDDHHMMSS`; override with `LOCAL_IMAGE_TAG=...` when you
want a stable tag.

The helper temporarily patches the `kmpnightly-migrate` Container Apps Job when
it needs to run specific commands (`bin/cake migrations migrate`,
`bin/cake updateDatabase`, `bin/cake platform_migrate migrate`,
`bin/cake platform backup-keys ensure`, and optionally
`bin/cake awards migrate_award_recommendations --apply --allow-open-manual-review`).
It restores the Job to the standard
`/usr/local/bin/docker-entrypoint.sh /bin/sh -lc "bin/cake platform_migrate
migrate"` contract afterward, so every deployment repairs migration drift.

Current custom-host smoke checks expect:

- `https://poc-alpha.kmpdev.ansteorra.org/members/login`
- `https://poc-beta.kmpdev.ansteorra.org/members/login`
- `https://plat.kmpdev.ansteorra.org/platform-admin/login`

## Managed identity document storage

`main.bicep` provisions a StorageV2 account for uploaded documents and grants
the shared Container Apps user-assigned managed identity **Storage Blob Data
Contributor** on that account. The app receives only non-secret settings:
`DOCUMENT_STORAGE_ADAPTER=azure`, `AZURE_STORAGE_AUTH_MODE=managedIdentity`,
`AZURE_STORAGE_ACCOUNT_NAME`, and `AZURE_STORAGE_CONTAINER_PREFIX`.

Tenant provision stores the exact container under
`tenant_config.documents.blob_container` (for example `documents-tenant-a`).
If tenant metadata is missing that value, runtime resolution falls back to
`<AZURE_STORAGE_CONTAINER_PREFIX>-<tenant-slug>`. Do not add per-tenant storage
account keys or connection strings; create narrower container-scoped RBAC
assignments later only when containers are pre-provisioned outside the app.

## Phase 0 staging preparation

`main.bicep` is also parameterized for a non-destructive Phase 0 staging
environment. Staging uses the same baseline shape as nightly — Postgres Flex,
Key Vault, Container Apps environment, web app, and fixed schedule-shape jobs — and enables an Azure
Front Door Standard profile in front of the Container App to mirror the intended
production edge topology.

Use [`staging.bicepparam`](./staging.bicepparam) as the starting point. It keeps
secrets out of git by reading secure values from environment variables:

```bash
export AZURE_REGION=centralus
export AZURE_ACR_NAME=<precreated-or-planned-acr-name>
export KMP_STAGING_IMAGE_REPOSITORY=<acr-login-server>/kmp
export KMP_STAGING_IMAGE_TAG=staging
export POSTGRES_ADMIN_PASSWORD=<from-password-manager>
export SECURITY_SALT=<from-password-manager>
export BACKUP_ENCRYPTION_KEY=<from-password-manager>
export PLATFORM_SECRETS_MASTER_KEY=<from-password-manager>
export EMAIL_SMTP_HOST=<smtp-host>
export EMAIL_FROM=staging-noreply@example.org
export AZURE_DEPLOYER_PRINCIPAL_ID=<your-entra-object-id>
```

Safe local/static validation:

```bash
az bicep build --file deploy/azure/main.bicep
```

Safe Azure validation (requires credentials but does not create/update
resources):

```bash
az deployment group validate \
  --resource-group <staging-rg> \
  --template-file deploy/azure/main.bicep \
  --parameters deploy/azure/staging.bicepparam
```

Do not run `az deployment group create` for staging until the subscription,
resource group, DNS/custom-domain plan, and secret values are explicitly
approved. If custom domains are needed for staging, add entries to
`frontDoorCustomDomains` as objects with `name` and `hostName`; DNS validation
and certificate issuance remain operational follow-up steps.

## Production release environment

[`production.bicepparam`](./production.bicepparam) defines the initial
cost-optimized North Central US release stack. It adds the smallest Azure
Managed Redis tier (`Balanced_B0`) with high availability disabled and a
`NoCluster` database, 35-day PostgreSQL backup retention with geo-redundant
backups, GRS document storage, 90-day Key Vault soft delete with purge
protection, and the platform administration portal on the Container App's
default hostname. KMP requires `NoCluster` because CakePHP's Redis cache engine
uses a single-node client and cannot follow Redis Cluster slot redirections.
Production enables persistent Redis connections so each Apache PHP worker can
reuse its TLS connections across requests. These connections do not keep a
Container Apps replica active or change scaling; the production minimum replica
count remains the cost-controlling setting.
The rehearsal tenant hostname `poc-production.kmpdev.ansteorra.org` is bound
directly to the Container App with a managed certificate. Production telemetry
uses a dedicated workspace-based Application Insights component backed by
`kmpprod-law`, with request, performance, error, and sampled SQL telemetry
enabled. Application logs are batched in-process and sent over local OTLP/gRPC
to the Container Apps managed OpenTelemetry agent; the agent performs the
remote Application Insights delivery, retry, and buffering outside the web
request. Set `applicationInsightsTransport = 'direct'` only as a rollback path
for environments that do not have the managed agent enabled. A production copy
of the KMP Telemetry Dashboard workbook is deployed against that component. The
profile intentionally does not configure Front Door or PgBouncer.

The initial PostgreSQL B1ms server connects on port `5432`. Azure's built-in
PgBouncer is not available on Burstable compute; move to General Purpose before
switching application URLs to port `6432`.

The parameter file contains no secrets. Export the required values in the
operator shell, then validate and deploy manually:

```bash
az group create -n kmp-production-rg -l northcentralus

az deployment group validate \
  --resource-group kmp-production-rg \
  --template-file deploy/azure/main.bicep \
  --parameters deploy/azure/production.bicepparam

az deployment group create \
  --resource-group kmp-production-rg \
  --name "kmp-production-$(date -u +%Y%m%d-%H%M%S)" \
  --template-file deploy/azure/main.bicep \
  --parameters deploy/azure/production.bicepparam
```

For a release rehearsal, build an immutable image tag, restore the encrypted
nightly application seed with the restore job, migrate or restore the platform
database, and verify `/health`, `/platform-admin/health`, tenant host routing,
scheduled jobs, Redis-backed sessions, and backup storage. At cutover, replace
the rehearsal data through the platform and tenant backup/restore workflow; do
not rerun the destructive nightly-seed restore job against live data.

Production deployment is automated from published, non-prerelease GitHub
releases. `release.yml` builds and smoke-tests the image, addresses it by digest,
then pauses at the protected `production` environment for approval. Approval
promotes that exact digest into the production ACR and runs the same ordered
worker canary, migrations, web cutover, health probes, and retained-job alignment
used by POC. Production is never deployed from a mutable channel tag.

Configure the repository environments and their resource-group-scoped OIDC
identities idempotently:

```bash
AZURE_SUBSCRIPTION_ID=<subscription-id> \
AZURE_TENANT_ID=<tenant-id> \
PRODUCTION_REVIEWER=<github-login> \
env -u GH_TOKEN bash deploy/azure/configure-github-cd.sh
```

The script creates separate `kmp-poc-github-oidc` and
`kmp-production-github-oidc` Entra applications. POC trusts only the
`Ansteorra/KMP` `poc` environment and deploys from `dev`; production trusts only
the `production` environment, accepts `v*` release tags, and requires the
configured reviewer.

## Common operations

| Task | Command |
|------|---------|
| Open site | `az containerapp show -g $RG -n kmpnightly-web --query properties.configuration.ingress.fqdn -o tsv` |
| Tail web logs | `az containerapp logs show -g $RG -n kmpnightly-web --tail 200 --follow` |
| Run migrations on-demand | `az containerapp job start -g $RG -n kmpnightly-migrate` |
| See recent queue executions | `az containerapp job execution list -g $RG -n kmpnightly-queue -o table` |
| Run nightly dispatcher now | `az containerapp job start -g $RG -n kmpnightly-sched-nightly` |
| Run restore-from-seed | `az containerapp job start -g $RG -n kmpnightly-restore` |
| Rotate a secret | `az keyvault secret set --vault-name <kv> --name security-salt --value <new>` then `az containerapp revision restart` on the web app |
| Inspect document storage | `az storage container list --account-name <documentStorageAccountName> --auth-mode login -o table` |
| Nuke and redeploy | `az group delete -n kmp-nightly-rg --yes --no-wait` then rerun `bootstrap.sh` |

## Cost expectations (US central)

| Resource | SKU | ~ Monthly |
|----------|-----|-----------|
| Postgres Flex | B1ms, 32 GB | ~$15 |
| Container Apps (web) | Consumption, 1 always-on | ~$8–15 |
| Container Apps Jobs | Consumption, ~300 min/mo | <$2 |
| ACR | Basic | $5 |
| Log Analytics | first 5 GB free | $0–3 |
| Key Vault | standard | <$1 |
| **Total** | | **~$30–40 / month** |

## Security notes

Managed-platform residency boundaries, retention defaults, breach-notification operations, and security escalation templates are maintained in [`../../docs/deployment/legal-governance.md`](../../docs/deployment/legal-governance.md). Review that template with counsel before making customer commitments.


- **Public ingress, HTTPS-only.** All traffic enters through the Container Apps
  auto-issued TLS cert.
- **Postgres public access with TLS required.** Firewall rule
  `AllowAzureServices` lets Container Apps in; everything else is rejected.
  Secrets never hit GitHub — they live in Key Vault and are referenced via
  user-assigned managed identity.
- **Encrypted seed payload.** `deploy/azure/seed/nightly-seed.kmpbackup` is
  AES-256-GCM encrypted; even if the repo leaks, the committed blob is
  unreadable without the key stored in Key Vault.
- **GitHub → Azure auth is OIDC.** No client secret exists. If the repo is
  deleted/transferred, revoke by deleting the federated credential on the
  AAD app.
- **Blast radius.** Separate POC and production AAD apps are scoped
  **Contributor** only on their respective resource groups.

## File map

- `main.bicep` — full resource graph (ACR, UAMI, KV, Postgres Flex, ACA env,
  web + 8 fixed schedule-shape jobs, optional Front Door, role assignments)
- `staging.bicepparam` — Phase 0 staging parameter file; reads secrets from
  environment variables and enables Front Door
- `bootstrap.sh` — one-time POC provisioning + GitHub environment wiring
- `configure-github-cd.sh` — idempotent Ansteorra GitHub environment, OIDC,
  and Azure RBAC configuration
- `seed/` — encrypted seed backup + bake helper; see `seed/README.md`
- `nightly.env.example` — settings template (copy to `nightly.env`)
- `../../docker/reset-and-seed.sh` — in-container reset script invoked by
  the restore job (engine-agnostic, restores from `seed/nightly-seed.kmpbackup`)
- `../../.github/workflows/azure-deploy.yml` — reusable ordered ACA deployment
- `../../.github/workflows/nightly-deploy-azure.yml` — automated POC deployment
  for each green `dev` image

## Known limitations / future work

- The `dev` branch must exist before automatic POC deployment can begin.
- Custom domain: the Container App has the default
  `*.azurecontainerapps.io` FQDN. To add `nightly.ansteorra.org`, attach a
  managed certificate + CNAME — one day of additional work.
