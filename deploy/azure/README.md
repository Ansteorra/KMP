# KMP Azure Deployment

For the current runtime/admin identity boundary, new secure inputs, role reconciliation, and rollout order, read [Security infrastructure rollout](security-rollout.md).

The POC KMP environment runs on **Azure Container Apps + Jobs**, backed by
**Azure Database for PostgreSQL Flexible Server**. Each green `dev` image is
resolved by digest and imported from GHCR into Azure Container Registry before
deployment. Scheduled `main` builds still publish the `nightly` channel but do
not deploy it automatically.

[`main.bicep`](./main.bicep) defines the core Azure resource shape. GitHub
environment approval, OIDC setup, DNS, custom-domain validation, and external
security/recovery controls remain explicit operator work.

PostgreSQL migrations use the `CITEXT` extension for selected human-facing
columns that historically inherited case-insensitive behavior from MySQL and
the `UNACCENT` extension for diacritic-insensitive SCA-name search.
Fresh and existing environments run `ensure-postgres-extension.sh` after Azure
login and before the migration job. The helper updates the server-level
`azure.extensions` configuration while preserving any other allowlisted
extensions. Do not run migrations that create or use `citext` or `unaccent`
before this allowlist step succeeds.

POC seed data lives in [`seed/nightly-seed.kmpbackup`](./seed/) — an
engine-agnostic, AES-256-GCM-encrypted logical backup produced by
`seed/bake-seed.sh` from an approved local dev environment. The destructive
restore job (`docker/reset-and-seed.sh`) rebuilds the default rehearsal
database, restores this archive, resets member passwords, and clears caches. It
is functionally aligned with the curated development seed, not byte-identical
database state, and it does not restore the platform database or a tenant
fleet.

## Architecture

```
 GitHub Actions (nightly.yml)                         ┌────────────────────────┐
 └── builds dev → ghcr.io/ansteorra/kmp:dev-SHA ───┐ │  Azure resource group  │
                                                    │  │  kmp-nightly-rg        │
 GitHub Actions (nightly-deploy-azure.yml)         │  │                        │
  1. OIDC → Azure                                  │  │  ACR <prefix>acr<hash> │
  2. az acr import ghcr→ACR                        └─▶│  └─ kmp:poc-*          │
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
- `<prefix>-migrate` — dedicated migration job. Its explicit command runs
  application migrations and `updateDatabase`, platform migrations, legacy
  secret import, backup-key reconciliation, active/suspended tenant fleet
  migration with fail-fast recovery markers, then the isolated model-cache
  clear. The production entrypoint may perform its historical application
  startup pass first, but deployment success is decided by the explicit ordered
  command.
- `<prefix>-restore` — manual restore-from-seed operation using
  `/opt/kmp/reset-and-seed.sh`.
- `<prefix>-provision` — manual tenant provision operation shape. The safe
  default prints command help; operators override args for a specific tenant.
- `<prefix>-queue` — the single three-minute background authority. It runs
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

Infrastructure reconciliation is intended to be rerunnable. The bootstrap also
starts the destructive seed-reset job and currently has no seed-skip option, so
use it only for a disposable POC and confirm the target before rerunning it.

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
1. Select the configured subscription and ensure the resource group exists.
2. Provision the ACR and `az acr import` the current
   `ghcr.io/ansteorra/kmp:nightly`.
3. Deploy all infrastructure from `main.bicep`.
4. Create an AAD app `kmp-poc-github-oidc` with a federated credential scoped
   to the `Ansteorra/KMP` `poc` environment.
5. Assign the AAD app **Contributor** on the resource group.
   When PostgreSQL is hosted elsewhere, assign a custom configuration-only role
   on that specific Flexible Server so deployment can preserve and update
   `azure.extensions` without broader access to its resource group.
6. Push the OIDC, infrastructure names, and PostgreSQL resource group/server
   names as non-secret `poc` environment variables via `gh`. The PostgreSQL
   resource group may differ from the Container Apps resource group when an
   existing server hosts isolated POC databases.
7. Ensure `CITEXT` and `UNACCENT` are present in the PostgreSQL extension allowlist.
8. Start the `kmp-migrate` job to apply application, platform, and tenant-fleet
   migrations. Active and suspended tenants with pending app or plugin versions
   receive their normal pre-migration recovery marker and backup; current
   tenants are verified without another backup.

Skip `gh` integration with `./bootstrap.sh --skip-gh-secrets`.

### 3. Seed / reset the default POC database

Seeding runs **inside the container** through the destructive
`<prefix>-restore` Container Apps Job (the Bicep output retains
`resetJobName` as a compatibility alias). `docker/reset-and-seed.sh` performs:

1. `bin/cake resetDatabase` — recreate the default application schema;
2. `bin/cake updateDatabase` — bring core/plugin tables current;
3. `bin/cake backup restore nightly-seed.kmpbackup` — restore the bundled
   encrypted logical archive through local backup storage;
4. reset every member password to `TestPassword`; and
5. clear application caches.

This job does not restore platform metadata or managed tenant databases.
`bootstrap.sh` starts it for initial POC setup. Re-run it only when intentionally
discarding the current default POC data:

```bash
RG="$AZURE_RESOURCE_GROUP"
az containerapp job start -g "$RG" -n "${AZURE_NAME_PREFIX}-restore"

# Watch the restore-job execution from the Azure CLI/portal.
az containerapp job execution list \
  -g "$RG" \
  -n "${AZURE_NAME_PREFIX}-restore" \
  -o table
```

The restore job is not part of automatic POC release deployment and must never
run against customer or production data.

### 4. Verify

```bash
set -a
source nightly.env
set +a

WEB=$(az containerapp show \
      -g "$AZURE_RESOURCE_GROUP" \
      -n "${AZURE_NAME_PREFIX}-web" \
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
4. Repairs and manually canaries the three-minute unified worker
5. Repairs `kmp-migrate`, runs application and platform migrations, imports
   missing legacy `KMP_SECRET_*` values into the database-backed store,
   reconciles backup keys, then migrates every active or suspended tenant with
   pending versions and fail-fast recovery markers/backups, clears schema
   caches, and requires success
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

## Ad-hoc POC deployments from your workstation

Use [`nightly-deploy.sh`](./nightly-deploy.sh) from the repository root for POC
operations and rehearsals. It talks directly to Azure; it is not the official
release path and must not be used for production.

Prerequisites:

```bash
export AZURE_TENANT_ID="your-tenant-id"
export AZURE_SUBSCRIPTION_ID="your-subscription-id"
az login --tenant "$AZURE_TENANT_ID"
az account set --subscription "$AZURE_SUBSCRIPTION_ID"
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

# Run app + platform + tenant fleet migrations against the configured image.
bash deploy/azure/nightly-deploy.sh migrate

# Run app + platform + tenant fleet migrations plus the recommendation migration.
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

The helper temporarily patches the `<prefix>-migrate` Container Apps Job when
it needs to run specific commands (`bin/cake migrations migrate`,
`bin/cake schema_cache clear`, `bin/cake updateDatabase`,
`bin/cake platform_migrate migrate`,
`bin/cake schema_cache clear --connection platform`,
`bin/cake platform secrets import-env`,
`bin/cake platform backup-keys ensure --allow-read-only`,
`bin/cake tenant migrate --all --include-suspended --fail-fast`,
`bin/cake cache clear _cake_model_`,
and optionally
`bin/cake awards migrate_award_recommendations --apply --allow-open-manual-review`).
It restores the Job to the standard
schema-safe migration contract afterward, so every deployment repairs migration
drift in the default, platform, and every active or suspended tenant database
before a new web revision starts. Current tenants are skipped without a backup;
pending tenants use the standard pre-migration recovery marker and backup after
backup-key reconciliation. One tenant failure stops the deployment before web
cutover, and rerunning resumes by reinspecting each tenant's migration history.

### Legacy environment secret-store transition

Existing environments can move from `KMP_SECRETS_DRIVER=env` to the encrypted
database store without a release deadlock. The migration job imports only
missing secrets after platform migrations create the destination tables. Exact
tenant and platform-admin references are resolved from platform metadata, and
the database master wrapping key is never copied into the database. Existing
database values always win, so a stale environment value cannot reverse a
rotation. Deleted database rows remain tombstones and cannot be revived by a
legacy value. Distinct portable references are preserved even when the env
backend normalizes their punctuation to the same variable name. The command is
idempotent and safe to repeat.

Stage the database master key in the migration job while the legacy
`KMP_SECRET_*` references are still present, run one successful deployment, and
only then remove those legacy references. If an environment-only deployment has
not received its database master key yet, import is explicitly deferred and
backup-key reconciliation reports missing read-only entries. Tenant migrations
still fail closed when their required database password or tenant backup key is
absent. When the database driver is active, a missing master key remains a hard
deployment failure.

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

For a release rehearsal, deploy an approved immutable POC image digest (or a
tag pinned to that digest); do not rebuild the release candidate. Use the
encrypted nightly seed only for a disposable rehearsal default database, then
migrate or restore the platform database and verify `/health`,
`/platform-admin/health`, tenant host routing, scheduled jobs, Redis-backed
sessions, and backup storage. At cutover, replace rehearsal data through the
platform and tenant backup/restore workflow; never run the destructive seed
reset against live data.

Production deployment is automated from published, non-prerelease GitHub
releases. `release.yml` first verifies the POC evidence, resolves the already
built and validated image by immutable digest, and applies release tags without
rebuilding it. After the protected `production` environment approval, the
deployment imports that same digest into the production ACR and runs the ordered
worker canary, application/platform/tenant-fleet migrations, web cutover,
health probes, and retained-job alignment used by POC. Pending active or
suspended tenant migrations fail fast after creating their standard recovery
markers and backups. Production is never deployed from a mutable channel tag.

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

These examples assume `AZURE_RESOURCE_GROUP` and `AZURE_NAME_PREFIX` are
exported from the intended environment profile.

| Task | Command |
|------|---------|
| Open site | `az containerapp show -g "$AZURE_RESOURCE_GROUP" -n "${AZURE_NAME_PREFIX}-web" --query properties.configuration.ingress.fqdn -o tsv` |
| Tail web logs | `az containerapp logs show -g "$AZURE_RESOURCE_GROUP" -n "${AZURE_NAME_PREFIX}-web" --tail 200 --follow` |
| Run migrations on-demand | `az containerapp job start -g "$AZURE_RESOURCE_GROUP" -n "${AZURE_NAME_PREFIX}-migrate"` |
| See recent worker executions | `az containerapp job execution list -g "$AZURE_RESOURCE_GROUP" -n "${AZURE_NAME_PREFIX}-queue" -o table` |
| Run one stored schedule now | From an operator-controlled container with the normal runtime configuration, run `bin/cake platform schedule run <schedule-name>`; do not start a parked `sched-*` compatibility job |
| Reset the default POC seed database | Start `<prefix>-restore` only after confirming the target is disposable POC data; never use it for production or customer data |
| Rotate a secret | Follow [environment rotation and verification](../../docs/8.1-environment-setup.md#rotation-and-verification), then refresh every affected web and job revision |
| Inspect document storage | `az storage container list --account-name <documentStorageAccountName> --auth-mode login -o table` |
| Retire a disposable POC environment | Obtain explicit approval, confirm the exact resource group, preserve required evidence/backups, and use the organization's Azure teardown procedure |

## Cost planning

Prices and free-tier allowances change, so this repository does not promise a
monthly total. Estimate each environment from its parameter file and the current
[Azure Pricing Calculator](https://azure.microsoft.com/pricing/calculator/).
Include PostgreSQL compute, storage and backup retention; Container Apps minimum
replicas and job executions; managed Redis; ACR; Key Vault; Log Analytics and
Application Insights ingestion; document/backup storage; and network egress.
Production parameters deliberately cost more than the POC profile.

## Security notes

Managed-platform residency boundaries, retention defaults, breach-notification operations, and security escalation templates are maintained in [`../../docs/deployment/legal-governance.md`](../../docs/deployment/legal-governance.md). Review that template with counsel before making customer commitments.


- **Public ingress, HTTPS-only.** All traffic enters through the Container Apps
  auto-issued TLS cert.
- **Postgres currently uses a public endpoint with TLS required.** The
  `AllowAzureServices` firewall setting is broader than this Container Apps
  environment; database credentials and host-level application controls remain
  necessary. The current Bicep does not provide private networking. Runtime
  secrets live in Key Vault and are referenced through the user-assigned
  managed identity rather than stored in GitHub.
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
- `ensure-postgres-extension.sh` — preserves the PostgreSQL extension allowlist
  while adding an extension required by migrations
- `seed/` — encrypted seed backup + bake helper; see `seed/README.md`
- `nightly.env.example` — settings template (copy to `nightly.env`)
- `../../docker/reset-and-seed.sh` — in-container reset script invoked by
  the restore job (engine-agnostic, restores from `seed/nightly-seed.kmpbackup`)
- `../../.github/workflows/azure-deploy.yml` — reusable ordered ACA deployment
- `../../.github/workflows/nightly-deploy-azure.yml` — automated POC deployment
  for each green `dev` image

## Known limitations and operator-owned controls

- The template deploys one Azure region; geo-redundant database backups and GRS
  storage do not create or validate a ready recovery region.
- The application has no Azure WORM evidence sink. Immutable evidence retention
  must be provided and tested externally before claiming that control.
- Platform Admin is a reserved-host surface in the same web application, not a
  separately deployed administration application.
- The managed identity currently receives Blob Data Contributor at the storage
  account scope. Tenant containers are logical isolation, not per-tenant Azure
  identities or RBAC boundaries.
- PostgreSQL uses a public endpoint with `AllowAzureServices` and TLS; the
  template does not configure a VNet/private endpoint.
- The encrypted seed reset restores only the default disposable POC database.
  It does not restore the platform database or a tenant fleet.
- DNS validation, protected-environment approval, KEK escrow ceremonies,
  immutable evidence export, penetration testing, and recovery-region
  orchestration remain operator-owned prerequisites.
