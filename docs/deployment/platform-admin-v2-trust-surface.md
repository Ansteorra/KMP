# Platform Admin v2 and Tenant Trust Surface

This roadmap defines the next safe slice after the read-only Platform Admin v1 portal. It prioritizes tenant-visible trust evidence without exposing other tenants, platform secrets, or privileged control-plane actions.

[← Back to Deployment Guide](README.md)

## Goals

- Give tenant administrators a read-only trust dashboard for their own kingdom.
- Show operational freshness signals: backups, restore drills, status, incidents, security posture, and WORM audit continuity.
- Reuse platform evidence already produced by backup, restore-drill, health, job, legal-governance, and WORM audit workflows.
- Keep v2 deployable incrementally: documentation first, then read-only APIs/pages behind explicit feature flags.

## Non-goals for this slice

- No tenant self-service restore, migration, export, or secret rotation actions.
- No cross-tenant dashboards, global incident lists, platform user lists, DB names, host credentials, object URIs, wrapped keys, or raw job errors.
- No contract, DPA, or breach-notification commitments beyond links to counsel-approved governance material.

## Surfaces

| Surface | Audience | Route shape | Data scope | Initial behavior |
|---------|----------|-------------|------------|------------------|
| Platform Admin v2 | Platform operators | `/platform-admin/trust` | All tenants, redacted | Read-only evidence index for ops triage. |
| Tenant Trust Dashboard | Tenant admins | `/admin/trust` or tenant-scoped equivalent | Current tenant only | Read-only trust status cards and evidence links. |
| Public status summary | Public or authenticated tenants | `/status` | Platform-wide aggregates, no tenant-private detail | Availability and maintenance notices only. |

Deploy the platform-operator route only in the isolated platform-admin Container App. The tenant route must run inside normal tenant authentication and must resolve tenant context from the current request, never from request parameters alone.

## Platform Admin tenant onboarding

The Platform Admin tenant create flow is an asynchronous control-plane operation. The portal creates the tenant registry row in `provisioning`, stores only secret references and safe tenant configuration, then queues a `platform_jobs` row with `job_type = tenant_provision`. It does not create databases or run migrations inside the HTTP request.

Provisioning is completed by the platform schedule `platform-admin-job-runner`, which dispatches the allowlisted `platform:run-platform-jobs` command. The runner claims queued provisioning jobs, creates or updates the tenant database role/database when permitted, runs tenant migrations, smoke-tests the tenant database, and only then marks the tenant `active`.

The create form requires an initial tenant super-user email address. Provisioning creates one verified tenant member, a system `Super User` role, a super-user permission that does not require a warrant, and assigns that member to the `Super User` role. No known password, password token, or reset link is stored in the platform job payload; the tenant super user claims the account through the normal tenant Forgot Password flow after the tenant becomes active.

Operators must ensure:

- The platform schedule runner is active in the platform-admin runtime.
- The configured secret store is writable by the platform-admin runtime.
- The platform database principal has the PostgreSQL privileges required to create tenant roles/databases when portal onboarding is enabled.
- `KMP_AUTO_CREATE_DATABASES=true` is set only in environments where automatic tenant DB creation is approved, or provisioning jobs are submitted with explicit database creation enabled.
- Job parameters and audit metadata contain only tenant identifiers, configuration references, and secret names; plaintext passwords, API keys, tokens, and connection strings must remain out of `platform_jobs`.

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

## Read-only gate requirements

Any code added for this roadmap must be fail-closed until explicitly enabled:

```bash
export KMP_TENANT_TRUST_DASHBOARD_ENABLED=false
export KMP_PLATFORM_ADMIN_TRUST_V2_ENABLED=false
```

Implementation requirements:

- Use GET-only routes for the first slice.
- Reuse existing authentication and authorization layers.
- Skip destructive commands, replay endpoints, direct object downloads, and raw SQL consoles.
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

## Phased implementation plan

1. **Docs and evidence contract**: publish this roadmap, align terminology with [Backup & Restore](backup-restore.md), [Two-Tenant Staging POC](multi-tenant-poc.md), and [Pilot Go/No-Go Checklist Template](pilot-go-no-go-checklist.md).
2. **Read-only service layer**: create a tenant-scoped trust summary service returning redacted DTOs and explicit `green/yellow/red/unknown` statuses.
3. **Tenant route placeholder**: add a feature-flagged tenant-admin page that renders static disabled-state copy until the service is wired.
4. **Tenant evidence cards**: wire backup, restore drill, WORM continuity, platform status, security posture, and incident communication summaries.
5. **Platform Admin v2 evidence index**: add an operator-only read-only view that aggregates per-tenant status without raw secrets.
6. **Public status summary**: expose only broad service availability and maintenance messages approved for public display.

## Test checklist for code slices

- Unauthenticated callers are redirected or denied according to the existing tenant admin behavior.
- Authenticated non-admin members cannot access the tenant trust dashboard.
- Tenant admins see only current-tenant evidence even if they pass another tenant slug or ID in the URL/query.
- Responses do not contain secret markers, raw object URIs, raw job errors, or other tenant names.
- Disabled feature flags return `404` without querying trust evidence.
- Platform-admin v2 routes require the isolated platform admin gate and are not reachable from normal tenant traffic.
- Templates meet WCAG 2.2 AA basics: semantic landmarks/headings, keyboard navigation, visible focus, labels, and non-color status text.

## Operational links

- Backup and restore evidence: [Backup & Restore](backup-restore.md)
- Pilot readiness evidence: [Pilot Ring Exit Criteria and Rollback Plan](pilot-ring-exit-criteria.md)
- Go/no-go checklist: [Pilot Go/No-Go Checklist Template](pilot-go-no-go-checklist.md)
- Region failover evidence: [Managed Platform Region Failover Runbook](region-failover-runbook.md)
- Governance, retention, and communication guardrails: [Managed Platform Legal and Security Governance Template](legal-governance.md)
