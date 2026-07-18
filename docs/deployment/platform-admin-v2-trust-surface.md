# Platform Operations and Tenant Trust Surface

The isolated Platform Admin portal is the multi-tenant operations console for
fleet support, onboarding, resilience, and tenant health. Tenant-visible trust
evidence remains a separate, read-only surface so kingdoms never receive
cross-tenant operational data.

[← Back to Deployment Guide](README.md)

## Goals

- Give platform operators an actionable fleet risk queue across kingdoms.
- Persist privacy-safe request volume, error, and latency aggregates for support.
- Make onboarding, backup, restore, lifecycle recovery, retention, and scheduled
  maintenance executable and auditable.
- Give tenant administrators a future read-only trust dashboard scoped only to
  their own kingdom.

## Boundaries

- Tenant users have no self-service restore, migration, export, or secret
  rotation actions.
- Platform pages never render database passwords, KEKs, wrapped DEKs, raw
  request paths, request bodies, user identities, exception text, or signed
  object URLs.
- Platform database restore is an external disaster-recovery operation, not a
  web action.
- This operational surface does not create contract, DPA, or
  breach-notification commitments beyond counsel-approved governance material.

## Surfaces

| Surface | Audience | Route shape | Data scope | Initial behavior |
|---------|----------|-------------|------------|------------------|
| Platform Operations | Platform operators | `/platform-admin` | All tenants, redacted | Fleet analytics, onboarding, jobs, schedules, backups, and guarded lifecycle controls. |
| Tenant Trust Dashboard | Tenant admins | `/admin/trust` or tenant-scoped equivalent | Current tenant only | Planned read-only trust status cards and evidence links. |
| Public status summary | Public or authenticated tenants | `/status` | Platform-wide aggregates, no tenant-private detail | Availability and maintenance notices only. |

Deploy the platform-operator route only in the isolated platform-admin Container App. The tenant route must run inside normal tenant authentication and must resolve tenant context from the current request, never from request parameters alone.

## Current Platform Operations console

The dashboard prioritizes tenants by lifecycle state, backup freshness, request
error rate, server errors, average latency, and stuck jobs. Request telemetry is
stored as hourly aggregates keyed by tenant and routed controller/action. It
does not store raw URLs, query strings, request bodies, member identities, or
exception messages. Metrics older than 90 days are pruned by the
`tenant-metrics-retention` schedule.

Starting fleet thresholds are:

| Signal | Attention condition |
|--------|---------------------|
| Backup | Missing, failed, expired, or older than 24 hours |
| Request errors | At least 20 requests and error rate at or above 5% |
| Latency | At least 10 requests and average duration at or above 1000 ms |
| Lifecycle job | Queued or running for more than 15 minutes |
| Tenant state | Provisioning, suspended, failed, or another non-active state |

Operators can inspect a sanitized job timeline and retry only failed job types
implemented by the worker. A retry creates a new audited job and preserves the
source record.

## Tenant onboarding

The Platform Admin tenant create flow is an asynchronous control-plane operation. The portal creates the tenant registry row in `provisioning`, stores only secret references and safe tenant configuration, then queues a `platform_jobs` row with `job_type = tenant_provision`. It does not create databases or run migrations inside the HTTP request.

Provisioning is completed by the unified `platform worker run` worker. The
worker claims queued provisioning jobs, creates or updates the tenant database
role/database when permitted, runs tenant migrations, smoke-tests the tenant
database, and only then marks the tenant `active`.

The create form requires an initial tenant super-user email address. Provisioning creates one verified tenant member, a system `Super User` role, a super-user permission that does not require a warrant, and assigns that member to the `Super User` role. No known password, password token, or reset link is stored in the platform job payload; the tenant super user claims the account through the normal tenant Forgot Password flow after the tenant becomes active.

Operators must ensure:

- The due-schedule dispatcher runs every minute in the platform-admin runtime
  (`bin/cake platform schedule due`).
- The configured secret store is writable by the platform-admin runtime.
- `bin/cake platform backup-keys ensure` has reconciled the platform backup KEK
  and every non-archived tenant backup KEK.
- Platform workers provide PHP JSON and zlib support for tenant logical
  archives, and `pg_dump` remains available for platform metadata disaster
  recovery backups.
- The platform database principal has the PostgreSQL privileges required to create tenant roles/databases when portal onboarding is enabled.
- `KMP_AUTO_CREATE_DATABASES=true` is set only in environments where automatic tenant DB creation is approved, or provisioning jobs are submitted with explicit database creation enabled.
- Job parameters and audit metadata contain only tenant identifiers, configuration references, and secret names; plaintext passwords, API keys, tokens, and connection strings must remain out of `platform_jobs`.

### Tenant queue ownership

Queue tables live in each tenant database, so a worker bound only to the
application's default datasource cannot deliver another kingdom's mail or run
its deferred workflows. The unified queue worker enumerates active tenants,
binds each tenant connection and `TenantContext`, and runs the queue processor
with the application DI container. Service-aware tasks therefore resolve the
same dependencies as normal application commands.

Each tenant target is isolated and bounded to 25 attempted jobs or 45 seconds
per schedule run. A tenant failure is recorded on that tenant's platform job
without preventing later tenants from running. Suspended and archived tenants
are not drained; queued work remains in their database until an operator
reactivates or explicitly recovers the tenant.

## Guarded lifecycle and recovery

Status is not editable as an ordinary tenant registry field. Operators use
typed confirmation, a reason, and TOTP step-up to:

- Suspend an active tenant before destructive restore or incident containment.
- Reactivate a provisioned, suspended tenant after recovery checks pass.
- Archive a suspended or incomplete tenant.

Lifecycle transitions are rejected while provisioning, backup, or restore work
is queued or running. Archived tenants cannot be reactivated through the
portal.

## Tenant-visible dashboard cards

Each card should be accessible by keyboard, use semantic headings, and present a concise status, timestamp, evidence link, and support contact. Status text must not rely on color alone.

| Card | Source evidence | Tenant-visible fields | Redactions |
|------|-----------------|-----------------------|------------|
| Backup freshness | `tenant_backups` completed rows | Last successful backup time, retention window, evidence ticket/link, freshness status | Object URI, storage account/container if sensitive, encryption metadata internals. |
| Restore drill evidence | `platform_jobs` `tenant_restore_drill` rows | Last successful/planned drill, drill mode, evidence link, next scheduled cadence | Raw command output, DB names, backup object paths. |
| Security posture | security alert summaries and governance review records | MFA/admin-gate status, latest posture review date, open customer-impacting exceptions | Findings details, exploit paths, internal severities unless approved. |
| Incident communication | incident/change communication tracker | Current incident state for this tenant, next update time, approved customer message link | Other tenant names, legal analysis, privileged notes, unapproved drafts. |
| Platform status | health checks and maintenance schedule | Availability state, maintenance windows, degraded features | Internal hostnames, queue internals, infrastructure identifiers. |
| WORM audit continuity | `audit_events` hash-chain/WORM mirror checks | Last continuity verification, retention proof link, gap status | Raw audit rows for other tenants, storage legal-hold internals, operator identity beyond approved display. |

## Data isolation rules

1. Every tenant-visible query must be constrained by server-side tenant context before it reaches a repository, service, or SQL builder.
2. Do not accept `tenant_id`, slug, host, or region from query strings as an authorization boundary. Those values may filter only after the authenticated tenant context is established.
3. Prefer summarized evidence rows over raw operational records. Store or render a redacted customer-safe `evidence_summary` when raw records include secrets or other-tenant data.
4. Include automated tests proving that tenant A cannot see tenant B backup, drill, incident, audit, or status evidence.
5. Include automated tests proving common secret markers are absent from tenant-visible responses: passwords, tokens, connection strings, object URIs with SAS/query signatures, wrapped DEKs, KEKs, and raw job errors.
6. Treat missing evidence as `unknown` or `needs_attention`; never infer green status from an empty result set.

## Tenant trust read-only gate requirements

The future tenant-visible trust surface must fail closed until explicitly enabled:

```bash
export KMP_TENANT_TRUST_DASHBOARD_ENABLED=false
```

Implementation requirements:

- Use GET-only routes for the first slice.
- Reuse existing authentication and authorization layers.
- Skip destructive commands, replay endpoints, direct object downloads, and raw
  SQL consoles on tenant-visible trust routes.
- Log dashboard views to the platform audit stream without logging rendered tenant evidence payloads.
- Return `404` when the feature flag is disabled and `403` when the caller lacks tenant-admin permission.

## Evidence freshness thresholds

| Evidence | Green | Yellow | Red |
|----------|-------|--------|-----|
| Tenant backup | Completed within 24 hours | 24-36 hours | Older than 36 hours or latest failed |
| Restore drill | Successful/planned within 35 days | 35-45 days | Older than 45 days or latest failed |
| WORM continuity | Verified within 24 hours | 24-48 hours | Gap detected or older than 48 hours |
| Platform health | All required checks pass now | Degraded non-critical check | Critical check failed or unknown |
| Incident communications | No active incident, or next update scheduled | Update due within 2 hours | Approved cadence missed |

Thresholds are starting defaults. Production thresholds must be reviewed with the retention and breach-notification processes in the [Managed Platform Legal and Security Governance Template](legal-governance.md).

## Remaining tenant trust roadmap

1. Create a tenant-scoped trust summary service returning redacted DTOs and
   explicit `green/yellow/red/unknown` statuses.
2. Add a feature-flagged tenant-admin route that derives scope only from the
   authenticated tenant context.
3. Wire backup, restore-drill, WORM continuity, platform status, security
   posture, and incident communication summaries.
4. Expose only broad, approved availability and maintenance information through
   any public status surface.

## Test checklist for code slices

- Unauthenticated callers are redirected or denied according to the existing tenant admin behavior.
- Authenticated non-admin members cannot access the tenant trust dashboard.
- Tenant admins see only current-tenant evidence even if they pass another tenant slug or ID in the URL/query.
- Responses do not contain secret markers, raw object URIs, raw job errors, or other tenant names.
- Disabled feature flags return `404` without querying trust evidence.
- Platform operations routes require the isolated platform-admin host and
  session gate and are not reachable from normal tenant traffic.
- Platform lifecycle, restore, download, and retry actions require the expected
  status plus typed confirmation, reason, and TOTP step-up.
- Templates meet WCAG 2.2 AA basics: semantic landmarks/headings, keyboard navigation, visible focus, labels, and non-color status text.

## Operational links

- Backup and restore evidence: [Backup & Restore](backup-restore.md)
- Pilot readiness evidence: [Pilot Ring Exit Criteria and Rollback Plan](pilot-ring-exit-criteria.md)
- Go/no-go checklist: [Pilot Go/No-Go Checklist Template](pilot-go-no-go-checklist.md)
- Region failover evidence: [Managed Platform Region Failover Runbook](region-failover-runbook.md)
- Governance, retention, and communication guardrails: [Managed Platform Legal and Security Governance Template](legal-governance.md)
