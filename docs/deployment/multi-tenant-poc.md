---
layout: default
title: "Two-Tenant Staging POC"
description: "Operator guide for validating tenant isolation, Platform Admin, jobs, backups, and host routing."
---

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

The optional `bin/tenant_migration_canary.sh` provides an explicit staging rehearsal before promoting a release with tenant migrations. The active Azure workflow does not call this script automatically. When invoked, it provisions a disposable `canary-*` tenant database, runs tenant migration status, dry-run, and idempotent migrate checks, verifies the smoke table, then drops the canary tenant metadata, database role, database, jobs, and writable secret-store entry. It never uses `--all`, so real tenants are not selected.

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

{% raw %}
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
{% endraw %}

## Platform Admin reserved-host surface

KMP includes a privileged `/platform-admin` surface in the same web application
that serves tenant traffic. The current Azure template does not provision a
separate admin Container App and does not trust upstream identity headers.
Isolate the surface with a reserved hostname in `KMP_PLATFORM_ADMIN_HOSTS` and
keep tenant hosts out of that list.

Minimum settings:

```bash
export KMP_PLATFORM_ADMIN_PORTAL_ENABLED=true
export KMP_PLATFORM_ADMIN_HOSTS='platform.kmp.localhost'
export KMP_PLATFORM_ADMIN_ALLOWED_STATUSES='active'
export KMP_PLATFORM_DATA_CONSOLE_ENABLED=false
```

Operators sign in at `/platform-admin/login` with platform-user email/password
and TOTP. The app enforces allowed account status, lockout, and a host-bound
session. The portal is mutating: authorized operators can create/provision
tenants, change lifecycle state, configure safe registry values, queue backups
and restores, and operate platform jobs.

Views intentionally omit secret values, database passwords, reusable KEKs,
wrapped DEKs, credential-bearing object URLs, and raw job errors. Data Console
routes remain separately controller-gated; hiding the navigation link is not a
security control.

### Backup and restore guardrails

Tenant and platform database backup requests are queued as audited
`platform_jobs`; web requests do not run long backup or restore work inline.
The three-minute unified worker claims `tenant_backup`,
`platform_database_backup`, and `tenant_restore` jobs. The daily
`platform-job-retention` schedule prunes completed/failed job history according
to its configured classes; `bin/cake platform jobs prune` runs the same bounded
cleanup manually.

- New tenant backups are logical JSON archives stored as encrypted
  `.json.gz.enc` objects.
- Platform metadata backups are PostgreSQL custom dumps stored as encrypted
  `.pgdump.enc` objects for external disaster recovery.
- Archive download and portable per-backup key export are separate guarded
  actions requiring typed confirmation, reason, TOTP step-up, and audit.
- Tenant restore requires typed `RESTORE <target-slug>` confirmation, reason,
  TOTP step-up, and a suspended target checked again at execution.
- The serving web app does not restore the platform metadata database. Operators
  decrypt the exported `.pgdump.enc` on a secured recovery host and follow the
  external platform restore procedure.

The tenant-visible trust dashboard remains a roadmap item. Keep it distinct from
the implemented Platform Admin surface in
[Platform Operations and Tenant Trust Surface](platform-admin-v2-trust-surface.md).

## Acceptance criteria

- Two tenant rows exist with distinct slugs, hosts, database names, and blob container names.
- `tenant_hosts.host_normalized` maps each POC host to the expected active tenant.
- The command prints smoke success for both tenants and `Two-tenant POC verification passed.`
- Requests using each Host header route to the same app revision and resolve to the corresponding tenant.
- No Azure credentials are required for the local reproduction; staging uses existing managed identity/secret configuration.
