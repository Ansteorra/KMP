---
layout: default
title: "Managed Platform Legal and Security Governance Template"
description: "Governance planning template for residency, retention, incidents, evidence, and external controls."
---

# Managed Platform Legal and Security Governance Template

This document is an operational drafting template for KMP's managed multi-tenant platform. It is not legal advice and is not evidence that a control has been deployed or tested. Qualified counsel, privacy and security leadership, and the relevant business owner must approve customer commitments, contracts, DPAs, public statements, and regulator communications.

Use this page to prepare runbooks, evidence packages, and decision records. Keep tenant-specific obligations and notification SLAs in the approved agreement or DPA. For related drafting aids, see the [Managed Platform Data Protection Templates](data-protection-agreement-template.md).

[← Back to Deployment Guide](README.md)

## Control-status rules

Every control cited in a contract or public statement must be classified from current evidence:

- **Implemented:** enforced by the deployed application or infrastructure and verified in the named environment.
- **Policy target:** an approved operating objective that still requires environment-specific configuration and evidence.
- **External prerequisite:** a control supplied outside this repository, such as immutable cloud storage, regional recovery infrastructure, legal hold, or a production KEK escrow ceremony.
- **Roadmap:** planned behavior that must not be represented as available.

Current implementation facts are summarized below. Deployment evidence remains authoritative for a particular environment.

| Area | Current implementation | Qualification |
|------|------------------------|---------------|
| Database isolation | Each tenant uses a separate PostgreSQL database; the platform database stores control-plane data. | Implemented application architecture. Verify provisioned databases and grants per environment. |
| Azure regions | The current Azure Bicep deploys one region. | Multi-region recovery is an external prerequisite/roadmap, not a deployed control. |
| Document storage | Tenant objects use logical tenant containers or prefixes. | The current managed identity has storage-account-wide Storage Blob Data Contributor access; it is not per-tenant cloud RBAC isolation. |
| Backups | One global policy supports `daily` or `weekly` cadence and defaults to daily with 30-day retention. Tenant fleet execution is managed; a platform backup can also be explicitly requested. | Tenant artifacts are `.json.gz.enc`; platform artifacts are `.pgdump.enc`. Retention policy and successful execution both require evidence. |
| Audit evidence | Platform audit records have a database hash chain. | The Azure WORM sink is not implemented and the default WORM sink is disabled. Immutable storage and retention locks are external prerequisites. |
| Backup key escrow | Application backup-key wrapping exists. | The repository's Shamir helper is a non-production placeholder; a reviewed escrow ceremony and custody process are external prerequisites. |
| Platform Admin | Platform Admin runs in the same web application on reserved hosts. It uses in-app password plus TOTP, lockout, account status, and a host-bound session. | It has mutating operations. It is not a separate app, an external identity gate, or a read-only surface. |
| Tenant trust pages | Internal platform operations pages exist. | Tenant-facing trust and public status routes are roadmap and must not be promised as available. |

## Data residency model

The managed architecture uses database-per-tenant isolation. The platform database contains tenant routing and operational control-plane records; each tenant PostgreSQL database contains that tenant's application data. The current Azure template is single-region, so a value in `tenants.region` records intended residency but does not itself provision or prove a second region.

| Boundary | Contains | Current boundary | Operator rule |
|----------|----------|------------------|---------------|
| Tenant database | Tenant members, branches, warrants, authorizations, waivers, notes, plugin data, and other tenant-local records | Separate PostgreSQL database per tenant | Do not move, restore, copy, or query outside an approved workflow. Verify database identity before every destructive or export action. |
| Tenant document container/prefix | Uploaded documents and generated files | Logical container/prefix selected from tenant metadata | Do not describe this as per-tenant Azure RBAC. The current workload identity's Blob Contributor role is storage-account-wide. |
| Platform database | Tenant registry, host mappings, platform users, jobs, schedules, backup metadata, audit chain, and secret indexes | Separate control-plane PostgreSQL database | Do not store plaintext keys, tenant database passwords, or tenant bulk exports in platform records. |
| Audit mirror | Optional external append-only copy of audit evidence | Not implemented by the Azure application sink | Do not claim WORM protection until cloud immutability, retention lock, delivery, and retrieval have been independently verified. |
| Logs and telemetry | Health, job summaries, redacted errors, and alerts | Environment log workspace | Exclude secrets, wrapped keys, connection strings, raw exports, and unnecessary personal data. |
| Backup objects | Encrypted tenant and platform backup artifacts plus metadata | Tenant `.json.gz.enc` and platform `.pgdump.enc` objects | Verify object hash, encryption metadata, retention, region, and access control before relying on an artifact. |

### Residency operating rules

1. Record the approved tenant region before activation, and verify that deployed database and storage resources actually satisfy it.
2. Preserve separate tenant databases and logical tenant document boundaries. Shared platform services may route requests, but must not co-mingle tenant tables.
3. Record platform administration, backup, restore, and migration activity in the database audit chain. If a contract requires immutable evidence, separately deploy and validate an external WORM destination.
4. Treat cross-region recovery as unavailable until recovery infrastructure, data replication, secrets, DNS, and a completed drill prove it.
5. Cross-region restores, support exports, and forensic copies require a change or incident record and the approvals required by the applicable agreement.
6. Customer-facing residency language must come from counsel-approved terms and deployment evidence, not from this template or `tenants.region` alone.

## Retention policy placeholders (not application defaults)

The following values are governance starting points for counsel and security review. They are not KMP application defaults and must not be represented as configured merely because they appear here.

The implemented backup policy has one global cadence (`daily` or `weekly`) and one retention value; it defaults to daily and 30 days. The managed scheduler performs tenant fleet backups. Platform backups can be explicitly requested. Any longer weekly, monthly, annual, audit, log, incident, or support-export retention must be implemented in external lifecycle policy or operational automation and verified.

| Data class | Policy placeholder | Evidence required before commitment |
|------------|--------------------|-------------------------------------|
| Tenant DB backups | 35 rolling days; consider a weekly point for 90 days during pilot | Applied KMP policy, successful tenant fleet jobs, `.json.gz.enc` object inventory, restore test, and external lifecycle evidence if tiers differ |
| Platform DB backups | 35 rolling days; consider a monthly point for 13 months | Explicit/scheduled platform backup evidence, `.pgdump.enc` inventory, decrypt/restore test, and external lifecycle evidence |
| Platform audit rows | 400 days online | Database retention job/configuration, hash-chain verification, legal-hold handling, and approved deletion evidence |
| Immutable audit evidence | 7 years if required | External WORM destination, locked retention policy, delivery monitoring, retrieval test, and counsel approval |
| Application/job logs | 90 days searchable; one-year archive if required | Log workspace settings, redaction test, access review, and archive lifecycle |
| Incident evidence | 7 years or legal-hold duration | Approved restricted repository, immutability/versioning proof, access review, and hold/release workflow |
| Support exports | 30 days maximum unless an approved exception applies | Export ticket, encrypted location, access log, expiry configuration, and deletion proof |

### Retention implementation checklist

- [ ] Counsel and security approved each applicable retention period.
- [ ] The deployed global backup cadence and retention match the approved KMP policy.
- [ ] Tenant fleet jobs and any explicitly requested platform backups completed successfully.
- [ ] Object inventories distinguish `.json.gz.enc` tenant backups from `.pgdump.enc` platform backups.
- [ ] External storage lifecycle, immutability, and legal-hold controls are verified where required.
- [ ] At least one in-retention artifact has passed an appropriate restore exercise.
- [ ] Exceptions identify the tenant/data class, reason, approver, effective dates, and evidence.
- [ ] Deletion or pruning is dry-run reviewed before initial use and after policy changes.

## Privileged administration governance

Platform Admin is a privileged, mutating surface in the same web application. It is selected by reserved host routing and protected by the application's password-plus-TOTP authentication, lockout, account-status checks, and host-bound session behavior.

Required operating controls:

- Restrict Platform Admin accounts to named operators with an approved role and current access review.
- Treat password, TOTP recovery, account unlock, tenant state changes, backup requests, restore actions, secret changes, and impersonation as privileged events.
- Do not rely on a separate admin deployment, an upstream external identity provider, or read-only routes unless those controls are separately added and evidenced.
- Verify reserved-host routing, session host binding, CSRF protection, reauthentication where required, and audit records after each relevant release.
- Do not publish tenant trust or public status URLs until those roadmap routes exist and have passed security, privacy, and accessibility review.

## Breach notification operational process

This workflow supports potential security or privacy incidents. It does not decide whether an event is legally reportable or create a notification deadline. Counsel, privacy, and security leadership determine obligations and approve external language.

### Trigger examples

Open a security incident for suspected:

- unauthorized access to a tenant database, document prefix/container, platform metadata, backup, secret store, or Platform Admin;
- loss, corruption, or disclosure of backup artifacts, support exports, or evidence;
- credential, managed identity, KEK/DEK, TOTP recovery, or Platform Admin account compromise;
- cross-tenant routing or data exposure;
- database audit hash-chain discontinuity, or a gap in an externally configured immutable mirror;
- unapproved data movement or a residency commitment violation.

### First-hour process

| Time | Owner | Action | Evidence |
|------|-------|--------|----------|
| 0–15 minutes | Detector/on-call | Page the Incident Commander and Security Lead; open the incident record; preserve alerts. | Alert ID, timestamp, environment, service, initial tenant scope |
| 15–30 minutes | Incident Commander | Assign roles, severity, scribe, and an initial impact hypothesis. | Role roster, severity rationale, affected/unknown tenants |
| 30–45 minutes | Platform/database/storage leads | Contain active risk without destroying evidence; pause risky work when approved. | Commands, approvals, snapshots, hashes, access logs |
| 45–60 minutes | Counsel/privacy/comms | Decide whether immediate legal assessment is required and establish the update cadence. | Contact time, decision owner, holding-statement status |

### Evidence checklist

Store evidence in an approved restricted repository. Use external immutable/versioned storage only when it is actually configured, and redact secrets before posting to tickets or chat.

- UTC incident timeline, roles, decisions, and approvals.
- Tenant scope, hosts, region commitments, database identities, and document boundaries.
- Affected data classes and estimated records/files when known.
- Running image digest, schema versions, migration evidence, and deploy timestamps.
- Platform audit rows and database hash-chain continuity.
- Cloud identity/RBAC changes, Platform Admin authentication/activity, and secret-store audit evidence.
- Backup metadata, artifact format, object hash, retention, and restore evidence.
- External WORM delivery and retention-lock evidence only if that prerequisite is deployed.
- Containment, remediation, rollback/failover decisions, and validation.
- Approved communications, recipients, timestamps, and later corrections.

### Notification decision record

```text
Incident ID:
Decision timestamp (UTC):
Decision owner:
Counsel/privacy reviewer:
Security reviewer:
Affected tenants/customers:
Affected regions and contractual commitments:
Data classes involved:
Known facts:
Unknowns and assumptions:
Containment status:
Notification obligations under review:
External communications approved? yes/no
Approved message/version:
Next decision review:
```

## Escalation roles

| Role | Responsibility |
|------|----------------|
| Incident Commander | Severity, timeline, containment authority, customer-impact decisions, and closure |
| Security Lead | Investigation, threat assessment, eradication, and security evidence |
| Platform Lead | Web app, jobs, schedules, tenant registry, Platform Admin, and deployment path |
| Database Lead | Tenant/platform database containment, backup/restore, integrity, and data-scope estimates |
| Storage/Audit Lead | Documents, backup objects, external immutable storage when present, lifecycle, and RBAC evidence |
| Data Protection/Privacy Lead | Personal-data impact, residency, deletion/export, and privacy obligations |
| Counsel | Legal obligations, privilege, notification requirements, and approved language |
| Communications Lead | Approved updates, cadence, recipients, and timestamps |
| Scribe | Timeline, decisions, evidence index, and remediation actions |

## External communication guardrails

- Do not confirm a breach, cause, data category, record count, obligation, or deadline externally until counsel approves the statement.
- Do not promise residency, deletion, retention, WORM storage, escrow, regional failover, or notification terms beyond signed agreements and verified controls.
- Do not include credentials, signed object URLs, database names, raw logs, personal-data samples, or exploit detail in customer communications.
- Preserve privilege where directed, and keep each customer-facing update versioned and approved.

## Governance review cadence

- Quarterly: review configured backup policy, restore evidence, database audit-chain checks, any external immutable evidence, access rosters, and incident contacts.
- Before each pilot or production expansion: confirm tenant isolation, actual region, storage access scope, backup/key readiness, and counsel-reviewed terms.
- After an incident or tabletop: update this template and the related [failover](region-failover-runbook.md) and [backup/restore](backup-restore.md) runbooks.
