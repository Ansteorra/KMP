# Two-Tenant Staging POC

Use `bin/cake tenant_poc` to prove that one app revision can serve two tenants with distinct hosts and databases. The harness is repo-local, idempotent, and does not require Azure credentials; it only needs the app configured for a staging-like PostgreSQL platform datasource.

## Safety gates

The command is disabled unless both are true:

```bash
export KMP_ENABLE_TENANT_POC=true
bin/cake tenant_poc --yes ...
```

If `APP_ENV=production` or `CAKE_ENV=production`, it also requires `--allow-production` and `KMP_ALLOW_PRODUCTION_TENANT_POC=true`. Do not use production customer hosts for the POC.

## Local PostgreSQL run

From `app/`, point `platform` and `default` at a local PostgreSQL instance, use the local file secret store, then run:

```bash
export KMP_DB_DRIVER=postgres
export PLATFORM_DB_HOST=127.0.0.1
export PLATFORM_DB_USERNAME=kmp_platform_admin
export PLATFORM_DB_PASSWORD='<from local secret manager>'
export PLATFORM_DB_DATABASE=kmp_platform_staging
export KMP_ENABLE_TENANT_POC=true

bin/cake platform_migrate migrate
bin/cake tenant_poc --yes --create-database \
  --tenant-a poc-alpha --host-a poc-alpha.staging.example.test \
  --tenant-b poc-beta --host-b poc-beta.staging.example.test \
  --db-prefix kmp_poc --smoke-table members
```

The command provisions or updates tenant metadata, creates tenant roles/databases when `--create-database` is supplied, runs tenant migrations, resolves both hosts through the platform registry, and performs `SELECT COUNT(*) FROM members` in each tenant database.

For staging environments where the databases and roles are pre-created by infrastructure, replace `--create-database` with `--skip-create-database`.

## Staging runbook

1. Deploy the app revision with tenant resolution still disabled for normal traffic unless the staging POC window explicitly enables it.
2. Configure PostgreSQL platform metadata and tenant DB admin credentials using environment variables or the staging secret store. Do not commit secrets.
3. Choose two DNS names, for example:
   - `poc-alpha.staging.kmp.example.org`
   - `poc-beta.staging.kmp.example.org`
4. Point both hosts at the staging app ingress.
5. Run platform migrations: `bin/cake platform_migrate migrate`.
6. Run the POC command with `KMP_ENABLE_TENANT_POC=true` and `--yes`, using `--skip-create-database` if infrastructure pre-created the tenant DBs.
7. Re-run `bin/cake tenant_poc --yes --verify-only ...` after any ingress or app setting change to verify the same hosts still resolve and smoke successfully.

## Tenant migration canary gate

Use `bin/tenant_migration_canary.sh` before promoting a release that contains tenant migrations. The script provisions a disposable `canary-*` tenant database, runs tenant migration status, dry-run, and idempotent migrate checks, verifies the smoke table exists, then drops the canary tenant metadata, database role, database, jobs, and writable secret-store entry. It never uses `--all`, so real tenants are not selected.

Safety behavior:

- Fails closed unless `KMP_ENABLE_TENANT_CANARY=true` is set.
- Refuses `APP_ENV=production` or `CAKE_ENV=production` unless `KMP_ALLOW_PRODUCTION_TENANT_CANARY=true` is also set.
- Requires both `platform` and `default` datasources to be PostgreSQL with non-empty host, database, username, and password configuration.
- Requires a writable secret store for the disposable tenant DB password.
- Does not print database passwords or connection URLs. Keep shell tracing disabled when running it in CI.

Local/staging run from `app/`:

```bash
export KMP_DB_DRIVER=postgres
export PLATFORM_DB_HOST=127.0.0.1
export PLATFORM_DB_USERNAME=kmp_platform_admin
export PLATFORM_DB_PASSWORD='<from local secret manager>'
export PLATFORM_DB_DATABASE=kmp_platform_staging
export KMP_ENABLE_TENANT_CANARY=true

bash bin/tenant_migration_canary.sh
```

Optional controls:

- `KMP_CANARY_SMOKE_TABLE=members` changes the schema table check.
- `KMP_CANARY_DB_PREFIX=kmp_canary` changes generated DB/role names.
- `KMP_CANARY_KEEP=true` leaves disposable resources behind for debugging; clean them manually before reusing names.

Workflow-ready hook:

```yaml
- name: Tenant migration canary
  working-directory: app
  env:
    KMP_ENABLE_TENANT_CANARY: "true"
    KMP_DB_DRIVER: postgres
    PLATFORM_DB_HOST: ${{ secrets.PLATFORM_DB_HOST }}
    PLATFORM_DB_USERNAME: ${{ secrets.PLATFORM_DB_USERNAME }}
    PLATFORM_DB_PASSWORD: ${{ secrets.PLATFORM_DB_PASSWORD }}
    PLATFORM_DB_DATABASE: ${{ secrets.PLATFORM_DB_DATABASE }}
  run: bash bin/tenant_migration_canary.sh
```

## Platform admin Container App v1

KMP includes an essential `/platform-admin` surface for platform operators.
Deploy it on a reserved platform hostname separate from tenant traffic. Keep
tenant-facing hosts from resolving to the portal unless the host is listed in
`KMP_PLATFORM_ADMIN_HOSTS`.

Minimum admin app settings:

```bash
export KMP_PLATFORM_ADMIN_PORTAL_ENABLED=true
export KMP_PLATFORM_ADMIN_HOSTS='platform.kmp.localhost'
export KMP_PLATFORM_ADMIN_ALLOWED_STATUSES='active'
```

Operators sign in at `/platform-admin/login` with their platform user password
and TOTP code. Operators must exist in `platform_users` with an allowed status.
The v1 UI is intentionally small:

- Dashboard: tenant counts, failed/stuck operations, backup issues, and release
  compatibility drift.
- Tenants: create tenants, edit safe registry fields, configure storage, email,
  integration endpoints, and secret reference names.
- Backups: queue platform database backups and open tenant backup workflows.
- Health: platform metadata datasource diagnostics.

The portal does not expose DB hosts, DB roles, object URIs, wrapped keys, secret
values, raw job errors, or plaintext secret values. Data Console routes remain
controller-gated by `Platform.adminPortal.dataConsole.enabled`; hiding the nav
link is not a security control.

### Backup and restore guardrails

Tenant and platform database backup requests are queued as audited
`platform_jobs`; web requests do not run long backup or restore work inline.
Successful minute-level scheduler and queue polls are retained only when they
process or dispatch work. Failures are always retained. The daily
`platform-job-retention` schedule removes completed scheduler runs after 14
days, other completed operational jobs after 90 days, and failed jobs after
180 days; related `platform_job_events` are removed by cascade. Operators can
run the same bounded cleanup manually with `bin/cake platform jobs prune`.
Platform Admin uses the shared encrypted JSON `.kmpbackup` archive model:

- Create backup requests enqueue a `tenant_backup_json` or
  `platform_backup_json` job with an idempotency key.
- Download and restore actions are available only for completed
  `kmpbackup_json` records whose object name is a safe `.kmpbackup` archive.
- Download requires typed confirmation (`DOWNLOAD <tenant>` or
  `DOWNLOAD platform`), reason text, TOTP step-up, and an audit record before
  bytes are read.
- Restore requires typed confirmation (`RESTORE <tenant>` or
  `RESTORE platform`), reason text, TOTP step-up, and an audit record before a
  restore job is queued.
- Tenant restores require the tenant to be suspended first so no live traffic is
  writing to the database during the destructive operation.

Platform Admin v2 and the tenant-visible trust dashboard roadmap are tracked in [Platform Admin v2 and Tenant Trust Surface](platform-admin-v2-trust-surface.md). Keep new admin capabilities feature-flagged or guarded until their audit and tenant-visible evidence paths are implemented.

## Acceptance criteria

- Two tenant rows exist with distinct slugs, hosts, database names, and blob container names.
- `tenant_hosts.host_normalized` maps each POC host to the expected active tenant.
- The command prints smoke success for both tenants and `Two-tenant POC verification passed.`
- Requests using each Host header route to the same app revision and resolve to the corresponding tenant.
- No Azure credentials are required for the local reproduction; staging uses existing managed identity/secret configuration.
