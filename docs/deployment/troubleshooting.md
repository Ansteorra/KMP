---
layout: default
title: "Managed Deployment Troubleshooting"
description: "Diagnostics for managed Azure health, migrations, workers, tenant routing, secrets, and backups."
---

# Managed Deployment Troubleshooting

Use this page for the current Azure multi-tenant runtime. Historical
Docker/VPC, Fly.io, and Railway diagnostics are summarized at the end.

[← Back to Deployment and Operations](README.md)

## Start with role and digest

Record the environment, active web revision, image digest, and recent migration
and worker executions. Do not copy secret values or full connection URLs into
tickets.

Typical Azure checks:

    az containerapp show \
      --resource-group <resource-group> \
      --name <prefix>-web \
      --query '{fqdn:properties.configuration.ingress.fqdn,revision:properties.latestReadyRevisionName}' \
      --output json

    az containerapp job execution list \
      --resource-group <resource-group> \
      --name <prefix>-migrate \
      --output table

    az containerapp job execution list \
      --resource-group <resource-group> \
      --name <prefix>-queue \
      --output table

    az containerapp logs show \
      --resource-group <resource-group> \
      --name <prefix>-web \
      --tail 200

Use the actual names from the Bicep outputs/environment variables rather than
assuming `kmpnightly`.

## Liveness and readiness

`/livez` is a static liveness probe:

    curl -fsS https://<approved-host>/livez

`/health` checks the current default database and cache/session backend:

    curl -fsS https://<approved-host>/health | jq .

A healthy response includes more than version:

    {
      "status": "ok",
      "version": "…",
      "image_tag": "…",
      "channel": "…",
      "db": true,
      "cache": true,
      "profile": "…",
      "timestamp": "…"
    }

The endpoint returns HTTP 503 with `status=degraded` when database or cache
readiness fails. In a production Redis profile, KMP also reports degraded when
`CACHE_ENGINE=redis` or cache-backed sessions were requested but a local
fallback is active.

Interpretation:

| Result | Likely scope |
| --- | --- |
| `/livez` fails | Revision/container/ingress is unavailable |
| `/livez` passes, `/health` fails DB | Default database URL, network, TLS, role, or schema issue |
| `/livez` passes, `/health` fails cache | Redis URL/TLS/extension/session configuration or managed Redis availability |
| Tenant host fails but default host health passes | Host mapping, tenant status, tenant DB secret/connection, or custom-domain routing |
| `platform_health` fails | Platform database URL/schema/connectivity rather than default datasource |

Check platform metadata separately:

    cd app
    bin/cake platform_health --json

## Deployment or migration failure

The web app deliberately sets `KMP_SKIP_MIGRATIONS=true`. Inspect the dedicated
migration job; do not “fix” the problem by enabling migration on web replicas or
running individual plugin commands in an arbitrary order.

The managed chain is:

    bin/cake migrations migrate &&
    bin/cake schema_cache clear &&
    bin/cake updateDatabase &&
    bin/cake platform_migrate migrate &&
    bin/cake schema_cache clear --connection platform &&
    bin/cake platform secrets import-env &&
    bin/cake platform backup-keys ensure --allow-read-only &&
    bin/cake tenant migrate --all --include-suspended --fail-fast &&
    bin/cake cache clear _cake_model_

Triage in order:

1. Confirm PostgreSQL `CITEXT` and `UNACCENT` are allowlisted by
   `deploy/azure/ensure-postgres-extension.sh`.
2. Find the first failing command in migration-job logs.
3. If platform migrations failed, do not attempt tenant work.
4. If secret import/key reconciliation failed, confirm the database master key
   and required tenant password/KEK references exist without printing them.
5. If one tenant failed, record the tenant slug, migration job/backup IDs, and
   redacted error. The fleet is resumable; fix the cause and rerun the full job.
6. Do not cut web to the new image until the migration job and post-migration
   worker verification succeed.

The optional release manifest and nightly drill are not part of the active Azure
workflow. A missing `config/release_manifest.json` is relevant only when an
operator deliberately runs those rehearsal tools.

## Unified worker and schedules

The current Azure queue job runs every three minutes. It dispatches due platform
schedules, drains the default and active tenant queues, and claims one bounded
platform job.

If work is stuck:

1. Confirm recent `<prefix>-queue` executions exist and completed.
2. Inspect `platform_jobs` and `platform_job_events` through Platform Admin for
   queued/running/failed state.
3. Confirm the tenant is active for queue draining and its database password
   secret resolves.
4. Check PostgreSQL advisory-lock contention and another still-running worker
   execution.
5. Verify the compatibility `sched-hourly/daily/weekly/nightly` jobs remain
   parked. Do not start them as a substitute worker.

A plain `bin/cake queue run` sees only the current datasource and cannot drain a
tenant fleet.

## Tenant host resolution

For a failing tenant host, verify:

- DNS/custom-domain binding reaches the intended Container App;
- the normalized host exists once in `tenant_hosts`;
- the tenant row is `active` (suspended/archived tenants are intentionally not
  served);
- the tenant database/role and referenced password exist;
- tenant schema matches the running release; and
- shared tenant-host-map cache invalidation reached Redis.

Use a real hostname for TLS/SNI. For a pre-DNS target, prefer:

    curl --resolve tenant.example.org:443:<edge-ip> \
      https://tenant.example.org/health

A `Host` header alone does not set TLS SNI.

## Platform Admin access

The current boundary is the same web app on a reserved host:

- `KMP_PLATFORM_ADMIN_PORTAL_ENABLED=true`;
- normalized host in `KMP_PLATFORM_ADMIN_HOSTS`;
- platform-user email/password and TOTP;
- allowed account status, lockout, and host-bound session.

There is no separate admin Container App or trusted identity-header gate.
Check the reserved host and app authentication rather than debugging an
external-identity allowlist that is not implemented. Keep detailed login errors
off in production.

## Backup and restore failures

Check the platform job, backup row, and redacted event first. Then verify:

- source/target tenant and status;
- tenant DB password and backup KEK references;
- `platform.backup.kek` for platform backups;
- scoped `backup://` object URI, size, and SHA-256;
- Azure managed identity storage access;
- worker memory for large JSON tenants; and
- target suspension for restore.

New tenant backups are `.json.gz.enc`; platform backups are
`.pgdump.enc`. Do not use the historical VPC SQL restore on either format.

A non-destructive restore drill:

    bin/cake tenant restore_drill --tenant <slug> --lookback-hours 36

ends as `planned` after validation. It is evidence of archive/readiness checks,
not proof that a destructive restore completed.

## Telemetry and audit

Validate telemetry without exposing credentials:

    bin/cake telemetry_check
    bin/cake telemetry_check --send

The database audit chain is implemented. The Azure Blob WORM sink is not; the
default mirror is disabled. If a launch/evidence checklist expects immutable
continuity, verify the external storage/retention control rather than treating
`PLATFORM_AUDIT_WORM_SINK` configuration as proof.

## Historical self-hosted diagnostics

The archived VPC stack uses services named by
`deploy/vpc/docker-compose.yml` (including `app` and `db`), one MariaDB
database, Caddy, and a privileged updater sidecar. It is unsupported for new
deployments.

For an existing instance:

    docker compose ps
    docker compose logs --tail 200 app
    docker compose logs --tail 100 db
    curl -fsS https://<legacy-host>/livez
    curl -fsS https://<legacy-host>/health

Do not follow old advice to restore a SQL dump and immediately rerun
`kmp update`. The management tool does not create an automatic backup or restore
a database during rollback, and several providers are incomplete. Define a
deployment-specific recovery plan before changing an old system.

## Evidence for an issue

Include:

- environment and UTC time window;
- commit, image-specific tag, and digest;
- web revision and migration/worker execution IDs;
- affected tenant slug/host only when approved;
- safe health output and redacted error class/message; and
- whether rollback would be runtime-only or requires data recovery.

Exclude secrets, database URLs, object credentials, recovery-key files, KEKs,
TOTP/recovery material, raw customer records, and full backup archives.
