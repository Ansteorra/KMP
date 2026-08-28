---
layout: default
---
[← Back to Table of Contents](index.md)

# 8. Deployment

KMP's supported production path is the managed, multi-tenant Azure deployment.
It runs one application revision for many tenant hosts while keeping platform
metadata and each tenant's application data in separate PostgreSQL databases.

The older Docker/VPC, Fly.io, Railway, shared-hosting, and standalone installer
material is retained only as historical reference. It is not a supported way to
provision a new managed tenant and does not implement the current platform
database, secret-store, worker, migration, or backup contracts.

## 8.1 Start here

| Need | Current source of truth |
| --- | --- |
| Architecture, Azure resources, and operator commands | [Azure deployment runbook](https://github.com/Ansteorra/KMP/blob/main/deploy/azure/README.md) |
| Environment variables and runtime roles | [Environment setup](8.1-environment-setup.md) |
| Release and same-digest promotion | [Updating and release](deployment/updating.md) |
| Tenant and platform backup/restore | [Backup and restore](deployment/backup-restore.md) |
| Tenant proof and migration rehearsal | [Two-tenant POC](deployment/multi-tenant-poc.md) and [pilot migration runbook](deployment/pilot-migration-runbook.md) |
| Incident and recovery preparation | [Region failover](deployment/region-failover-runbook.md) and [DR drill checklist](deployment/dr-drill-execution-checklist.md) |
| Trust, legal, and launch templates | [Trust documentation index](deployment/trust-docs-index.md) |
| Historical self-hosted notes | [Legacy deployment archive](deployment/README.md#historical-self-hosted-reference) |

## 8.2 Managed runtime shape

The Azure template in `deploy/azure/main.bicep` provisions the current runtime:

| Component | Responsibility |
| --- | --- |
| Azure Container Apps web app | Serves tenant hosts and the reserved Platform Admin host. It does not run migrations, cron, or queue work. |
| PostgreSQL Flexible Server | Holds one default application database, one platform metadata database, and provisioned tenant databases. |
| Azure Managed Redis | Shared cache and sessions for the multi-replica production web tier. |
| Azure Storage | Private document and encrypted backup objects through one managed identity. Tenant object keys/containers provide logical scoping; the current identity has account-wide Blob Data Contributor access. |
| Azure Key Vault | Bootstraps runtime connection strings, the security salt, the database secret-store master key, the seed key, Redis, and SMTP credentials. |
| Container Apps Jobs | Own migrations, the destructive POC seed restore, tenant provisioning shape, and the unified background worker. |

The web revision sets:

    KMP_SKIP_MIGRATIONS=true
    KMP_SKIP_CRON=true

Do not remove those flags from managed web replicas. Startup migration in the
production image exists for historical single-database containers; it is not the
managed multi-tenant rollout mechanism.

### Background work

One scheduled job runs every three minutes and executes:

    bin/cake platform worker run \
      --schedule-limit 100 \
      --max-jobs 100 \
      --max-runtime 45 \
      --cycle-budget 240 \
      --platform-limit 1 \
      --json

That worker dispatches due platform schedules, drains the default and active
tenant queue datasources, and claims a bounded platform job. A plain
`bin/cake queue run` sees only the current datasource and must not be used as
the managed tenant-fleet worker. The old hourly, daily, weekly, and nightly
Container Apps Job shapes are compatibility resources parked on annual no-op
schedules after cutover.

## 8.3 Migration contract

Managed deployments run migrations in one dedicated job before web cutover.
The enforced order is:

    bin/cake migrations migrate &&
    bin/cake schema_cache clear &&
    bin/cake updateDatabase &&
    bin/cake platform_migrate migrate &&
    bin/cake schema_cache clear --connection platform &&
    bin/cake platform secrets import-env &&
    bin/cake platform backup-keys ensure --allow-read-only &&
    bin/cake tenant migrate --all --include-suspended --fail-fast &&
    bin/cake cache clear _cake_model_

The order is important:

1. Core and plugin application schema is brought current.
2. Platform metadata tables are migrated.
3. Missing legacy environment secrets may be imported into the encrypted
   database-backed store. Existing values and tombstones win.
4. The platform and non-archived tenant backup KEKs are reconciled.
5. Active and suspended tenant databases are inspected and migrated.
6. Shared model metadata is cleared only after every required database succeeds.

A tenant with pending versions receives its normal pre-migration recovery marker
and encrypted backup. Current tenants are inspected and skipped without another
backup. A failure stops the deployment before web cutover; rerunning is
resumable because the fleet command reinspects each database.

Do not replace this chain with ad-hoc plugin migration commands, migration on
web startup, or a manual schema rollback. The optional release-manifest,
`platform release_check`, canary, and nightly migration-drill commands are
rehearsal tools; the active Azure workflow does not currently generate a
`config/release_manifest.json` or pass one to the migration job.

## 8.4 Release and rollback

The official release path builds once and promotes by immutable digest:

1. A green commit on official `main` is fast-forwarded to `dev`.
2. `Nightly / Dev Docker Image` builds a multi-architecture
   `ghcr.io/ansteorra/kmp:dev-<sha>` image and smoke-checks it.
3. `POC / Deploy to Azure` imports that digest, canaries the worker, runs the
   migration contract, cuts over web, probes it, and records
   `poc-validated-<sha>` evidence.
4. A stable `v*` GitHub Release for the same commit applies release tags to the
   POC-validated digest without rebuilding.
5. The protected production environment deploys that same digest after approval.

See [Updating and release](deployment/updating.md) for the exact operator
procedure. Rolling back an Azure runtime revision does not reverse tenant data
or schema changes. Use the documented recovery markers and restore process only
after confirming image/schema compatibility.

## 8.5 Backups and recovery

Managed backups are not the historical VPC SQL dumps:

- Tenant backups are logical JSON archives, gzip-compressed and envelope
  encrypted as `.json.gz.enc` objects.
- Platform metadata backups are PostgreSQL custom dumps encrypted as
  `.pgdump.enc` objects.
- Each backup has its own data key wrapped by the tenant or platform backup KEK.
- Backup objects use scoped `backup://tenants/<slug>/...` or
  `backup://platform/...` identifiers and record size, SHA-256, retention, and
  encryption metadata in the platform database.
- Tenant restore targets must be suspended before a destructive restore is
  queued. Platform Admin backup/download/restore actions require confirmation,
  a reason, TOTP step-up, and audit records.

The application currently has one global managed-backup policy: daily or weekly
cadence and 1–365 retention days, defaulting to daily and 30 days. Governance
templates may define stricter future/customer targets; they do not change the
implemented scheduler automatically.

Read [Backup and restore](deployment/backup-restore.md) before operating on
customer data.

## 8.6 Secrets and Platform Admin

Managed Azure sets `KMP_SECRETS_DRIVER=database`. Key Vault supplies the master
key used to wrap encrypted values in the platform database; the master key
itself is never stored there. Tenant database passwords and backup KEKs are
referenced by name from platform metadata and must never be placed in job
parameters, tickets, or logs.

Platform Admin is a privileged, mutating control-plane surface in the same web
application. It is isolated by reserved hosts in `KMP_PLATFORM_ADMIN_HOSTS`,
portal enablement, in-app platform-user password authentication, TOTP, lockout,
allowed account status, and a host-bound session. The current deployment does
not use a separate admin Container App or trusted external identity headers.
Keep the data console disabled in production unless its separate risk review is
complete.

## 8.7 Health and observability

- `/livez` is a static liveness probe. It does not prove database or cache
  readiness.
- `/health` checks the current default database and cache/session runtime and
  returns `status`, `version`, `image_tag`, `channel`, `db`, `cache`,
  `profile`, and `timestamp`. It returns HTTP 503 when database or cache
  readiness fails.
- `bin/cake platform_health --json` checks the platform metadata datasource.
- `bin/cake telemetry_check` validates effective telemetry configuration and
  writable local paths.

Production uses Application Insights/OTLP configuration from the Azure template.
Do not include connection strings, object credentials, secret values, raw job
errors, or customer records in diagnostic evidence.

## 8.8 Audit immutability status

Platform audit rows include a database hash chain. A local/dev file mirror can
append redacted hash-chained JSONL records. The Azure Blob WORM sink is not
implemented in the application and is not provisioned by the current Bicep
template; the default sink is disabled and fail-closed behavior defaults to
false. Cloud immutable storage, retention/legal hold, monitoring, and continuity
evidence therefore remain explicit external launch prerequisites.

Do not describe the current Azure environment as having an application-managed
WORM mirror until that integration is implemented and verified.

## 8.9 Historical self-hosting

The archived VPC/Fly/Railway and installer pages may help maintain an existing
single-database installation. They are unsupported for new deployments and do
not provide managed tenancy. In particular:

- `kmp install` and `bin/cake kmp_install` are retired and return errors.
- Several installer providers are stubs.
- `kmp update` does not create a database backup.
- `kmp rollback` does not restore a database or reverse migrations.
- The VPC scripts back up one MariaDB database, not platform metadata and a
  tenant fleet.

Treat those pages as historical context, not a production runbook.
