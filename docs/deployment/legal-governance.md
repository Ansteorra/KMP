# Managed Platform Legal and Security Governance Template

This document is an operational template for KMP managed multi-tenant platform governance. It is not legal advice and must be reviewed and approved by qualified counsel, security leadership, and the relevant business owner before it is used for customer commitments, contracts, DPAs, public statements, or regulator communications.

Use this template to prepare production runbooks, evidence packages, and decision records. Keep tenant-specific contractual terms, jurisdictional obligations, and customer notification SLAs in the approved legal agreement or DPA; do not treat this page as the source of those obligations. For DPA, privacy, subprocessor, data-handling, responsibility-matrix, and breach-SLA drafting aids, see the [Managed Platform Data Protection Templates](data-protection-agreement-template.md).

[← Back to Deployment Guide](README.md)

## Data residency model

KMP's managed platform uses database-per-tenant isolation. The platform database stores tenant metadata and operational control-plane records; each tenant database stores that tenant's application data. Tenant document storage is resolved from tenant metadata and should use per-tenant containers or prefixes with managed identity/RBAC.

| Boundary | Contains | Residency expectation | Operator rule |
|----------|----------|----------------------|---------------|
| Tenant database | Tenant members, branches, warrants, authorizations, waivers, notes, plugin data, and tenant-local app records | Located in the tenant's assigned platform region as recorded by `tenants.region` | Do not move, restore, copy, or query outside the approved region without an incident/legal change record. |
| Tenant document container/prefix | Uploaded tenant documents and generated files | Same region as the tenant database unless counsel/customer agreement approves otherwise | Use tenant-scoped storage naming and RBAC; never use shared account keys for routine access. |
| Platform database | Tenant registry, host mappings, platform users, job state, backup metadata, audit index, secret indexes, schedules | Hosted in the platform control region for the environment; metadata may reference multiple tenant regions | Treat as control-plane data. It must not contain tenant DB passwords, plaintext keys, or tenant bulk exports. |
| WORM audit mirror | Append-only copy of platform audit events and hash-chain evidence | Region and immutability tier approved for audit evidence retention | Do not edit or delete. Verify retention lock/legal hold in the storage control plane. |
| Logs/telemetry | App logs, health checks, job summaries, redacted errors, security alerts | Platform operations region/log workspace | Logs must be redacted; do not include secrets, wrapped DEKs, connection strings, raw exports, or minor-sensitive details beyond what is needed for operations. |
| Backup objects | Encrypted tenant and platform backup artifacts, object hashes, retention metadata | Same region as source database by default; cross-region copies only through approved DR/residency policy | Confirm `retention_until`, object hash, encryption metadata, and immutable storage settings before relying on a backup for restore or evidence. |

### Residency operating rules

1. Record each tenant's primary residency in `tenants.region` before activation.
2. Use separate tenant databases and separate document containers/prefixes; platform shared services may route requests but must not co-mingle tenant data tables.
3. Keep platform administration, backup, restore, and migration actions in `audit_events` and the WORM mirror.
4. If the platform cannot honor a requested residency region, flag the tenant as not eligible for production activation until legal/customer approval is recorded.
5. Cross-region restores, support exports, or forensic copies require an incident/change ticket with IC, Security Lead, Data Protection/Privacy Lead, and counsel approval.
6. Customer-facing residency language must come from counsel-approved contract/DPA text, not this operational template.

## Retention policy defaults

These defaults are production starting points for platform operations. Shorter or longer periods may be required by jurisdiction, customer agreement, insurance, litigation hold, or security policy; counsel and security leadership must approve exceptions before activation.

| Data class | Default retention | Storage/control | Deletion/expiry owner | Notes |
|------------|-------------------|-----------------|-----------------------|-------|
| Tenant DB backups | 35 days rolling; at least one weekly restore point retained for 90 days during pilot/ringed rollout | `tenant_backups.retention_until`, encrypted backup object, immutable bucket/container lifecycle | Platform Lead with Database Lead evidence | Aligns with daily backup and restore-drill operations. Preserve longer when incident, litigation hold, or customer agreement requires it. |
| Platform DB backups | 35 days rolling; at least one monthly restore point retained for 13 months | `platform_database_backups.retention_until`, encrypted backup object, immutable lifecycle | Platform Lead | Platform metadata is needed before tenant restore. Keep enough history to investigate tenant registry, job, schedule, and backup metadata changes. |
| Platform audit DB rows | 400 days online/searchable | `audit_events` in platform DB plus hash chain | Security Lead/Data Protection Lead | Prune only after confirming WORM mirror continuity and legal hold status. |
| WORM audit mirrors | 7 years immutable by default | Cloud immutable storage, versioning, retention lock/legal hold | Security Lead with Storage/Audit Lead | Do not shorten without counsel approval. Store retention proof with quarterly audit evidence. |
| Application/job logs | 90 days hot/searchable, then 1 year archived if required for security operations | Log workspace/archive lifecycle | Platform Lead/Security Lead | Redacted operational logs only. Secrets and raw personal data are not acceptable log content. |
| Incident evidence packages | 7 years or legal-hold duration, whichever is longer | Restricted evidence repository with immutable/versioned storage | Incident Commander with counsel | Includes timeline, decisions, affected tenant list, hashes, command outputs, screenshots, and communications. |
| Support exports/customer extracts | 30 days maximum unless customer agreement or legal hold requires otherwise | Encrypted restricted storage, access ticket, deletion proof | Support Lead/Data Protection Lead | Export creation, access, transfer, and deletion must be audited. |

### Retention implementation checklist

- [ ] Backup commands write `retention_until` and `retention_policy` for tenant and platform backup rows.
- [ ] Storage lifecycle rules match or exceed the approved retention windows.
- [ ] Immutable retention/legal hold is enabled for WORM audit mirrors and incident evidence packages.
- [ ] Restore drills verify at least one in-retention backup per selected tenant.
- [ ] Exceptions are tracked by tenant, data class, approver, start/end date, and reason.
- [ ] Deletion jobs are dry-run reviewed before first production execution and after any policy change.

## Breach notification operational process

This section provides an operations workflow for potential security or privacy incidents. It does not define whether an event is legally reportable or set contractual notification commitments. Counsel, privacy, and security leadership determine notification obligations and approve all external language.

### Trigger examples

Open a security incident when any of the following are suspected:

- Unauthorized access to tenant database, document storage, platform metadata, WORM audit storage, backup object, secret store, or admin portal.
- Loss, corruption, or exfiltration of backup objects, support exports, or incident evidence.
- Credential, managed identity, KEK/DEK, Shamir share, or platform-admin account compromise.
- Cross-tenant data exposure, incorrect tenant host routing, or tenant isolation bypass.
- WORM audit gap, retention lock removal, or suspicious audit hash-chain discontinuity.
- Unapproved cross-region data movement or residency boundary violation.

### First-hour process

| Time from detection | Owner | Action | Evidence to capture |
|---------------------|-------|--------|---------------------|
| 0-15 minutes | Detector/On-call | Page Incident Commander and Security Lead; open incident ticket/channel; preserve alerts | Alert ID, detector, timestamp, environment, affected service, initial tenant scope |
| 15-30 minutes | IC | Assign roles, severity, scribe, and initial tenant-impact hypothesis | Role roster, severity rationale, affected/unknown tenant list |
| 30-45 minutes | Platform/Database/Storage Leads | Contain active threat without destroying evidence; freeze risky jobs if needed | Commands run, approvals, snapshots, object hashes, access logs |
| 45-60 minutes | Counsel/Privacy/Comms Leads | Decide whether legal assessment is required immediately and set next-update cadence | Counsel contact time, notification-decision owner, holding statement draft |

### Evidence collection checklist

Collect evidence in a restricted, immutable or versioned evidence repository. Redact secrets before posting to tickets or chat.

- Incident timeline with UTC timestamps, source time zones, and scribe notes.
- Tenant scope: tenant IDs/slugs, hosts, `tenants.region`, database names, document containers, and status.
- Affected data classes and estimated record/file counts, if known.
- Current app image digest, release manifest, schema versions, migrations, and deploy timestamps.
- Platform audit rows and WORM mirror records around the event window, including hash-chain continuity notes.
- Cloud identity/RBAC changes, admin portal access logs, managed identity/service principal activity, and secret-store audit logs.
- Database logs, backup metadata, object URIs, object hashes, retention/legal-hold screenshots, restore drill evidence.
- Document storage access logs, version history, immutable policy changes, and suspected object lists.
- Containment and eradication commands, approvals, rollback/failover decisions, and validation results.
- Communications: internal updates, customer drafts, approved sent messages, recipient lists, and timestamps.

### Notification decision record template

Use this template inside the incident ticket/evidence repository:

```text
Incident ID:
Decision timestamp (UTC):
Decision owner:
Counsel/privacy reviewer:
Security reviewer:
Affected tenants/customers:
Affected regions/residency commitments:
Data classes involved:
Known facts:
Unknowns and assumptions:
Containment status:
Potential notification obligations under review:
Customer/regulator/partner communications approved? yes/no
Approved message/version link:
Next decision review time:
```

## Security/cyber incident escalation roles

| Role | Primary responsibilities | Backup |
|------|--------------------------|--------|
| Incident Commander | Owns severity, timeline, containment authorization, customer-impact decisions, and closure criteria | Platform Lead |
| Security Lead | Leads technical investigation, threat assessment, indicators of compromise, eradication, and security evidence | Designated security engineer |
| Platform Lead | Owns app, jobs, schedules, tenant registry, platform admin portal, deployment rollback/fix path | Senior platform engineer |
| Database Lead | Owns tenant/platform DB containment, backup/restore evidence, integrity checks, and data-scope estimates | DBA/on-call engineer |
| Storage/Audit Lead | Owns document storage, backup objects, WORM mirrors, retention/legal-hold proof, and RBAC evidence | Cloud storage owner |
| Data Protection/Privacy Lead | Owns personal-data impact analysis, residency assessment, and right-to-deletion/export implications | Privacy delegate |
| Counsel | Determines legal obligations, privilege handling, regulator/customer notification requirements, and approved external language | Outside counsel/escalation contact |
| Communications Lead | Drafts and sends approved internal/customer updates, keeps cadence, records recipients and timestamps | Customer success lead |
| Scribe | Maintains timeline, decision log, evidence index, and post-incident action list | Any trained responder |

### Communication timeline template

| Window | Audience | Message goal | Approver | Required content |
|--------|----------|--------------|----------|------------------|
| T+30 minutes | Internal incident team | Confirm severity, scope hypothesis, containment owner, and next update | IC | What happened, known/unknown tenant impact, active risks, bridge/channel links |
| T+1 hour | Executive/security leadership | Escalate impact, resources needed, potential notification review | IC + Security Lead | Severity, affected systems, containment status, customer-impact hypothesis, legal review status |
| T+2 hours | Counsel/privacy/comms review | Decide notification posture and holding statement readiness | Counsel + Privacy Lead | Facts, affected data classes, regions, obligations under review, proposed cadence |
| T+4 hours or approved cadence | Affected customers if approved/required | Provide accurate operational update without speculation | Counsel + Comms Lead + IC | Impact summary, protective actions, what KMP is doing, customer actions if any, next update time |
| Daily until resolved | Leadership and affected customers as approved | Maintain trust and document progress | IC + Counsel/Comms | Current status, material changes, remaining risk, next milestone |
| Closure | Internal and affected customers as approved | State remediation complete and follow-up path | IC + Counsel/Comms | Root cause summary approved for release, remediation, monitoring, support contact |

## External communication guardrails

- Do not confirm a breach, root cause, data category, record count, legal obligation, or notification deadline externally until counsel approves the statement.
- Do not promise residency, deletion, retention, or notification terms beyond signed customer agreements and approved policy.
- Do not include secrets, object SAS URLs, database names, raw logs, personal data samples, or detailed exploit paths in customer communications.
- Preserve attorney-client privilege where counsel directs it; separate privileged legal analysis from operational facts when requested.
- Keep all customer-facing updates versioned, approved, and linked from the incident evidence index.

## Governance review cadence

- Quarterly: review retention defaults, WORM evidence, restore-drill results, and incident escalation roster.
- Before each pilot/ring expansion: confirm tenant residency support, retention lifecycle policies, backup/restore evidence, and counsel-reviewed customer terms.
- After each security incident/tabletop: update this template, the [Managed Platform Region Failover Runbook](region-failover-runbook.md), and the [Backup & Restore](backup-restore.md) evidence checklist as needed.
