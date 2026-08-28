---
layout: default
title: "Platform Operations and Tenant Trust Surface"
description: "Implemented Platform Admin controls and tenant-visible trust features that remain roadmap work."
---

# Platform Operations and Tenant Trust Surface

This page separates the implemented Platform Admin control plane from the
tenant-visible trust features that remain roadmap work. The filename is retained
for stable links.

[← Back to Deployment and Operations](README.md)

## Implementation status

| Area | Status |
| --- | --- |
| Reserved-host Platform Admin routes | Implemented in the main web application |
| Platform-user password + TOTP, lockout, recovery, allowed status | Implemented |
| Host-bound Platform Admin session | Implemented |
| Tenant provisioning/lifecycle, jobs, schedules, backup/restore controls | Implemented |
| Fleet health summaries | Implemented |
| Separate Platform Admin Container App | Not implemented |
| External identity header/allowed-email gate | Not implemented |
| Tenant `/admin/trust` dashboard | Not implemented |
| Public `/status` page | Not implemented |
| Azure Blob application WORM sink | Not implemented; external control required |

Do not use roadmap controls as evidence for a current release.

## Current security boundary

Platform Admin is a privileged, mutating prefix in the same Container App as
tenant traffic. Requests are admitted only when:

1. `KMP_PLATFORM_ADMIN_PORTAL_ENABLED=true`;
2. the normalized request host is in `KMP_PLATFORM_ADMIN_HOSTS`;
3. the operator authenticates with a `platform_users` email/password and TOTP;
4. the account has an allowed status;
5. lockout/recovery checks pass; and
6. the session was established for the same admin host.

A configured admin host redirects non-admin paths to the portal. Tenant hosts
must not be listed as admin hosts and cannot use an admin session issued for
another host. The current app does not consume a trusted external identity
header. Penetration and regression tests should test host confusion, forwarded
host handling, session replay across hosts, password/TOTP lockout, recovery
paths, CSRF, and action authorization.

The data console is separately gated by
`KMP_PLATFORM_DATA_CONSOLE_ENABLED` and should remain disabled in production
unless reviewed.

## Current operator capabilities

The implemented console is not read-only. Depending on controller policy and
step-up requirements, it can:

- view platform and fleet health summaries;
- create tenants and queue asynchronous provisioning;
- edit safe tenant registry, host, storage, email, integration, and secret
  reference settings;
- suspend/reactivate/archive tenants through guarded lifecycle controls;
- inspect platform job and schedule state;
- queue tenant and platform backups;
- download encrypted archives and portable per-backup recovery keys;
- queue same-tenant or cross-tenant restores to suspended targets; and
- import a legacy tenant `.kmpbackup` into the managed backup pipeline.

Views and audit metadata must not expose database passwords, plaintext secret
values, reusable KEKs, wrapped DEKs, TOTP/recovery material, credential-bearing
object URLs, or raw job errors.

## Tenant onboarding and job ownership

Tenant creation is asynchronous. The HTTP request validates safe metadata,
creates/updates the registry row in `provisioning`, stores only secret reference
names and safe configuration, and queues a `tenant_provision` platform job. The
worker—not the web request—creates or validates the role/database, writes the
database password to the configured writable secret store, runs tenant
migrations, creates backup-key material, and activates the tenant only after
verification.

The current Azure background authority runs every three minutes:

    bin/cake platform worker run \
      --schedule-limit 100 \
      --max-jobs 100 \
      --max-runtime 45 \
      --cycle-budget 240 \
      --platform-limit 1 \
      --json

It dispatches due schedules, drains default/active tenant queues, and claims a
bounded platform job. The old Azure `sched-hourly`, `sched-daily`,
`sched-weekly`, and `sched-nightly` jobs are parked compatibility shapes.

Job parameters may contain tenant identifiers, safe configuration, and secret
reference names. They must never contain plaintext passwords, tokens, API keys,
KEKs, or connection URLs.

## Backup and restore controls

Current formats are:

- tenant `backup_type=json` → encrypted `.json.gz.enc`;
- platform `backup_type=pg_dump` → encrypted `.pgdump.enc`; and
- historical tenant archives imported and re-encrypted as current JSON backups.

Requests enqueue `tenant_backup`, `platform_database_backup`, or
`tenant_restore` jobs. Download, portable recovery-key export, manual deletion,
and restore require typed confirmation, reason, current TOTP, and a platform
audit record. Tenant targets must be suspended before restore queueing and are
checked again under the operation lock.

The web application does not restore the platform database. Operators decrypt an
exported platform backup on a secured recovery host and use the external
PostgreSQL recovery process.

## Fleet health

Current Platform Admin health derives from platform data and telemetry:

- platform datasource state and retry result;
- tenant lifecycle/schema drift and failed or stuck platform jobs;
- backup freshness based on the configured daily (24-hour) or weekly (168-hour)
  policy window, with critical status after three missed windows;
- recent request/error telemetry when enough samples exist; and
- release compatibility data that the platform database actually records.

Treat absent evidence as unknown, not healthy. A default/disabled WORM sink or an
unexecuted restore drill is not proof of continuity or recovery.

## Data isolation rules

- Platform metadata contains tenant registry and operational control-plane data;
  tenant application records stay in tenant databases.
- Tenant request resolution uses normalized host mappings and an active tenant
  record before switching the datasource.
- Tenant database passwords are resolved from secret references and never stored
  in tenant/job configuration.
- Document and backup object paths are scoped by tenant slug. In current Azure,
  one managed identity has account-wide Blob Data Contributor access, so this is
  logical application isolation rather than per-tenant Azure identity/RBAC.
- Platform Admin summaries must avoid cross-tenant record samples unless an
  authorized operator deliberately enters a separately gated workflow.
- Customer-facing evidence must use summarized/redacted records rather than raw
  platform jobs, audits, URLs, or database identifiers.

## Audit and WORM status

Platform audit rows include a hash chain. The local file sink appends redacted,
hash-chained JSONL records but is not storage-enforced WORM. The `azure_blob`
sink is not implemented, the default is `disabled`, fail-closed defaults to
`false`, and `deploy/azure/main.bicep` does not create immutable audit storage.

A production trust packet can require WORM continuity, retention lock/legal
hold, and monitoring, but those must be supplied and verified as external
controls until the application integration exists.

## Tenant-visible trust roadmap

The proposed tenant `/admin/trust` surface and public status page are not
current routes. If implemented, tenant evidence should be read-only, scoped to
the resolved tenant, and summarized around:

- tenant isolation and host mapping;
- backup freshness and latest restore-drill result;
- incident/contact and maintenance status;
- relevant security posture and release identity; and
- externally verified audit continuity.

It must not expose another tenant's name or metrics, raw audit/job rows, object
URIs, database names/roles, secret reference names, wrapped keys, or operator
identity beyond an approved display.

Recommended freshness semantics for a future surface should be derived from the
actual backup policy and evidence timestamps rather than a fixed “24 hours”
label. No trust card should claim WORM, restore success, or incident clearance
from configuration alone.

## Regression checklist

For each Platform Admin change, verify:

- tenant hosts cannot reach the prefix and the reserved host cannot be confused
  with `X-Forwarded-Host` input;
- password, TOTP, allowed-status, lockout, recovery, and host-bound session
  behavior;
- controller authorization and CSRF on every mutating action;
- typed confirmation, reason, TOTP step-up, audit ordering, and suspended-target
  checks on destructive operations;
- queued work contains references, not secret values;
- sensitive fields and raw errors are absent from HTML, JSON, logs, and flash
  messages;
- one tenant's host/session cannot resolve or render another tenant's data;
- keyboard operation, focus order/visibility, labels, status announcements,
  contrast, and non-color-only cues remain WCAG 2.2 AA; and
- roadmap routes remain absent or feature-gated until their data contract and
  redaction tests exist.

## Operational links

- [Azure deployment](https://github.com/Ansteorra/KMP/blob/main/deploy/azure/README.md)
- [Environment setup](../8.1-environment-setup.md)
- [Managed backup and restore](backup-restore.md)
- [Region failover](region-failover-runbook.md)
- [Security regression checklist](security-regression-checklist.md)
- [Trust documentation index](trust-docs-index.md)
