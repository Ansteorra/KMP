---
layout: default
title: "Deployment and Operations"
description: "Managed multi-tenant deployment, operations, trust controls, and historical deployment references."
---

# Deployment and Operations

This directory documents KMP's managed multi-tenant deployment and the evidence
templates used to operate it. The supported production target is Azure Container
Apps, PostgreSQL Flexible Server, Redis, Azure Storage, and Key Vault as defined
in `deploy/azure/main.bicep`.

The standalone installer and provider-specific self-hosted quick starts are
historical. They remain available for maintainers of existing single-database
systems, but they are not supported for new deployments.

## Documentation map

### Current managed platform

| Page | Purpose | Status |
| --- | --- | --- |
| [Azure deployment](https://github.com/Ansteorra/KMP/blob/main/deploy/azure/README.md) | Infrastructure, jobs, bootstrap, deployment, and operator commands | Current source of truth |
| [Environment setup](../8.1-environment-setup.md) | Managed/local environment variables and runtime roles | Current |
| [Updating and release](updating.md) | POC validation and same-digest production promotion | Current |
| [Backup and restore](backup-restore.md) | Managed tenant/platform formats, retention, recovery keys, and restore guardrails | Current |
| [Two-tenant POC](multi-tenant-poc.md) | Local/staging tenant isolation proof and migration canary | Current |
| [Platform operations and tenant trust](platform-admin-v2-trust-surface.md) | Implemented Platform Admin controls and separately identified roadmap | Current plus roadmap |
| [Pilot migration rehearsal](pilot-migration-runbook.md) | Staged rehearsal/evidence template | Manual template; requires environment-specific importer/cutover steps |
| [Region failover](region-failover-runbook.md) | Recovery ordering and validation | Manual skeleton; recovery-region infrastructure is external |
| [Troubleshooting](troubleshooting.md) | Managed health, migration, worker, and tenant diagnostics | Current |
| [Trust documentation index](trust-docs-index.md) | Launch, security, governance, and customer-safe evidence templates | Current index; templates include external prerequisites |

### Historical self-hosted reference

| Page | Historical scope |
| --- | --- |
| [Docker/VPC quick start](quickstart-vpc.md) | Single MariaDB database with Caddy and Docker Compose |
| [Fly.io quick start](quickstart-fly.md) | Retired single-database provider example |
| [Railway quick start](quickstart-railway.md) | Retired single-database provider example |
| [VPC template reference](https://github.com/Ansteorra/KMP/blob/main/deploy/vpc/README.md) | Legacy Compose scripts, one MariaDB backup |
| [Installer archive](https://github.com/Ansteorra/KMP/blob/main/installer/README.md) | Retired CLI and incomplete provider stubs |
| [Legacy configuration appendix](configuration.md) | Archived variables and management-tool file format |

Historical pages are not a fallback managed-platform design. They omit the
platform database, tenant registry, database-backed secrets, shared worker,
tenant fleet migration, managed backup formats, and current release gates.

## Managed platform invariants

### Runtime ownership

- The web Container App handles HTTP requests only and sets
  `KMP_SKIP_MIGRATIONS=true` and `KMP_SKIP_CRON=true`.
- One Container Apps Job runs the unified `platform worker run` command every
  three minutes.
- A dedicated migration job applies application, platform, secret-transition,
  backup-key, and tenant-fleet steps before web cutover.
- The reset/restore job is a destructive POC seed operation and is never part of
  normal release or production recovery.
- Platform Admin is hosted by the same web application on a reserved host. Its
  boundary is host validation plus in-app password/TOTP authentication, lockout,
  allowed account status, and host-bound sessions—not a separate Container App
  or trusted identity headers.

### Migration order

The Azure runtime contract enforces:

    bin/cake migrations migrate &&
    bin/cake schema_cache clear &&
    bin/cake updateDatabase &&
    bin/cake platform_migrate migrate &&
    bin/cake schema_cache clear --connection platform &&
    bin/cake platform secrets import-env &&
    bin/cake platform backup-keys ensure --allow-read-only &&
    bin/cake tenant migrate --all --include-suspended --fail-fast &&
    bin/cake cache clear _cake_model_

Pending active or suspended tenants receive a recovery marker and encrypted
backup before migration. Current tenants are inspected and skipped. Archived
tenants are not migrated. A failure blocks web cutover and a rerun resumes by
reinspecting state.

### Release promotion

Official releases are built once. A `dev-<sha>` image is deployed to POC by
digest, tagged `poc-validated-<sha>` only after the deployment succeeds, then
given stable release tags without rebuilding. Production imports and deploys
that same digest after protected-environment approval.

Rolling channel tags such as `dev`, `nightly`, and `latest` are mutable
references. Use digests or commit/version-specific tags for evidence.

### Backups

- New tenant backups are logical JSON archives stored as encrypted
  `.json.gz.enc` objects.
- Platform metadata backups are PostgreSQL custom dumps stored as encrypted
  `.pgdump.enc` objects.
- Managed backups use per-backup data keys wrapped by tenant/platform KEKs.
- Tenant restore targets must be suspended.
- Historical VPC SQL dumps cover one MariaDB database only and cannot protect a
  managed tenant fleet.

### Audit immutability

Database audit events have a hash chain. The file mirror is a local/dev,
redacted, hash-chained JSONL sink. The Azure Blob sink is not implemented and
the current Bicep template does not provision immutable audit storage.
Production WORM retention, legal hold, continuity monitoring, and proof remain
external launch prerequisites.

## Optional rehearsal tooling

The repository includes `platform release_check`, tenant canary, release
manifest, and nightly migration drill helpers. They are useful for explicit
staging/pilot rehearsals, but the active Azure workflow does not generate
`config/release_manifest.json` or pass `--manifest` to the deployment migration
job. Do not describe those helpers as automated release gates until the workflow
actually wires them in.

## Safety rules

- Never put database URLs, passwords, SMTP credentials, storage credentials,
  KEKs, recovery keys, TOTP secrets, or customer records in documentation,
  tickets, screenshots, or command arguments.
- Do not run the destructive seed restore against production or customer data.
- Do not start the parked compatibility scheduler jobs as a substitute for the
  unified worker.
- Do not assume image rollback reverses migrations or tenant data.
- Do not claim WORM, per-tenant Azure RBAC, multi-region failover, escrow, or a
  public trust dashboard is implemented unless current evidence proves it.
