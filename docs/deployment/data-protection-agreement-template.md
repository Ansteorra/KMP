---
layout: default
title: "Managed Platform Data Protection Templates"
description: "Planning templates for data protection terms, subprocessors, incidents, and tenant evidence."
---

# Managed Platform Data Protection Templates

These templates support legal, privacy, and customer-readiness work for KMP's managed multi-tenant platform. They are not legal advice, customer commitments, or proof that a technical control is deployed. Qualified counsel, privacy and security leadership, and the relevant business owner must approve anything used in a contract, public statement, regulator communication, or customer trust packet.

[← Back to Deployment Guide](README.md)

Related references:

- [Legal and Security Governance Template](legal-governance.md)
- [Managed Platform Region Failover Runbook](region-failover-runbook.md)
- [Backup & Restore](backup-restore.md)
- [Pilot Ring Exit Criteria](pilot-ring-exit-criteria.md)
- [Pilot Go/No-Go Checklist](pilot-go-no-go-checklist.md)

## How to use this document

1. Copy the relevant section into the controlled legal/privacy review record.
2. Replace placeholders with tenant- and environment-specific facts and evidence.
3. Classify every cited safeguard as verified/implemented, an external prerequisite, a policy target, or roadmap.
4. Have counsel decide what belongs in the DPA, order form, privacy notice, security addendum, or public trust material.
5. Do not publish an SLA, residency statement, retention term, subprocessor claim, or breach deadline until it is approved and supported by deployed evidence.

## Current implementation facts to verify

These facts describe the repository's present managed design; they are not a warranty about a particular deployment.

| Topic | Current state | Contract drafting caution |
|-------|---------------|----------------------------|
| Tenant data | Separate PostgreSQL database per tenant; platform records are held in a separate control-plane database. | Verify each environment's provisioned databases and permissions. |
| Region | Current Azure Bicep is single-region. | Do not promise regional failover or multi-region residency without separately deployed and tested infrastructure. |
| Documents | Logical tenant containers/prefixes are selected from tenant metadata. | The current managed identity has storage-account-wide Blob Contributor access, so do not describe this as per-tenant Azure RBAC. |
| Backups | One global policy supports daily/weekly cadence and defaults to daily/30 days. Managed jobs perform tenant fleet backups; platform backups may be explicitly requested. | Tenant files are `.json.gz.enc` and platform files are `.pgdump.enc`. Verify execution, retention, decryptability, and restore evidence. |
| Audit immutability | A database audit hash chain exists. | The Azure WORM sink is not implemented and defaults disabled; immutable evidence storage is an external prerequisite. |
| Key escrow | Backup-key wrapping is implemented. | The Shamir tool is a non-production placeholder. A reviewed production escrow ceremony, custodians, storage, and recovery test are external. |
| Platform Admin | Same web app on reserved hosts, with in-app password plus TOTP, lockout, account status, and host-bound sessions. | It includes mutating operations; do not describe a separate app, external identity gate, or read-only portal. |
| Trust/status UI | Operator-facing platform functions exist. | Tenant trust and public status routes are roadmap. |

## DPA outline for counsel review

This is a drafting aid for a Data Processing Agreement or equivalent addendum.

| Section | Facts or decisions to supply | Evidence |
|---------|------------------------------|----------|
| Parties and roles | Identify the customer, operator, controller/processor assumptions, and tenant administrators. | Contract and onboarding record |
| Scope of processing | Managed hosting, tenant provisioning, application operations, support, configured backup/restore, incident response, and audit processing. | Architecture record and [governance template](legal-governance.md) |
| Data categories | Member profiles, branches/roles, warrants, authorizations, waivers, notes, documents, audit logs, operational metadata, approved exports, and incident evidence as applicable. | Tenant and plugin inventory |
| Data subjects | Members, guardians/parents where applicable, officers, administrators, event participants, support contacts, and platform operators. | Onboarding questionnaire |
| Minor data | Youth/minor fields, guardian communications, access restrictions, public-display controls, export rules, and disclosure approvals. | Tenant policy and verified app controls |
| Processing instructions | Signed customer instructions, approved runbooks, emergency procedures, and change control. | Contract, change, and incident records |
| Confidentiality | Authorized trained access; restricted evidence locations; no secrets or personal data in ordinary chat/docs/arguments. | Access review and training evidence |
| Security measures | Include only safeguards verified in the contracted environment. Distinguish database isolation and encrypted backups from logical storage boundaries, external WORM, external escrow, regional recovery, policy targets, and roadmap work. | [Backup/restore](backup-restore.md), deployment evidence, security assessment |
| Residency/transfers | Approved deployment region and the workflow for any copy, export, or restore outside it. | Provisioned-resource evidence and [residency model](legal-governance.md#data-residency-model) |
| Subprocessors | Approved register; notice, objection, and emergency replacement process. | Subprocessor register |
| Assistance | Access, correction, deletion, export, audit, DPIA, regulator, and support-request procedure. | Support runbook and tickets |
| Retention/deletion | Configured application defaults, approved policy targets, overrides, legal holds, offboarding, and external lifecycle controls. | [Retention policy placeholders](legal-governance.md#retention-policy-placeholders-not-application-defaults) |
| Security incidents | Operational response plus counsel-approved notification terms. | [Breach process](legal-governance.md#breach-notification-operational-process) |
| Audit/evidence | Reasonable assistance, database audit-chain evidence, external immutability only when deployed, restore evidence, and confidentiality limits. | Approved evidence repository |
| Termination | Export handoff, database/document deletion, backup expiry, required audit retention, and completion evidence. | Offboarding record |
| Liability/law | Counsel-owned language only. | Legal review |

### DPA fact sheet

```text
Tenant/customer:
Tenant slug(s):
Deployed region:
Customer and operator role assumptions:
Data categories:
Minor data present? yes/no/unknown:
Subprocessors approved:
Configured backup cadence/retention:
Policy retention overrides:
External WORM deployed and verified? yes/no:
Production escrow ceremony complete? yes/no:
Regional recovery deployed and drilled? yes/no:
Support/export contacts:
Security evidence:
Counsel reviewer:
Privacy reviewer:
Approved for customer use? yes/no:
Approval timestamp:
```

## Privacy, subprocessor, and data-handling template

Counsel and privacy leadership must approve customer-facing wording. Use conditional language until a deployment is evidenced.

### Draft privacy statement outline

- **Service purpose:** KMP supports tenant kingdom-management workflows, including member, branch, role, warrant, authorization, waiver, event, note, document, notification, and audit records.
- **Tenant control:** The tenant determines its administrators, business records, and member-facing policies.
- **Platform operation:** The operator runs the managed service, applies approved changes, monitors health/security, executes configured backup jobs, and supports authorized restores and incidents. Do not claim a restore-drill cadence unless evidence exists.
- **Isolation:** Tenant application records are in a tenant-specific PostgreSQL database. Documents use logical tenant containers/prefixes; the present workload identity's storage role is account-wide.
- **Access:** Operator access is restricted by approved roles and should be tied to support, change, or incident records where applicable. Platform Admin is a privileged, mutating part of the same web app and uses its in-app password/TOTP controls.
- **Minor data:** Do not export, disclose, or use minor data outside approved workflows without the required tenant, privacy, and legal authorization.
- **Backups:** Describe the configured cadence and retention, not policy aspirations. Distinguish encrypted tenant `.json.gz.enc` artifacts from encrypted platform `.pgdump.enc` artifacts.
- **Audit:** Describe the database hash chain. Mention immutable/WORM retention only if an external sink and retention lock have been deployed and tested.
- **Recovery:** The current Azure template is single-region. Do not claim regional failover before a separate recovery environment and drill exist.
- **Trust UI:** Do not link or promise tenant trust/public status routes while they remain roadmap.
- **Deletion/export:** Route requests through the tenant's authorized contact and track approval, execution, backup expiry, and confirmation.
- **Incidents:** Customer/regulator notification follows counsel-approved contractual and legal requirements.

### Subprocessor register

| Subprocessor | Purpose | Data categories | Region | Verified safeguards | Status |
|--------------|---------|-----------------|--------|---------------------|--------|
| `[Cloud hosting provider]` | App hosting, database, storage, networking, monitoring | Tenant data, documents, backups, logs, platform metadata | `[region]` | `[Only controls verified in this environment]` | `[proposed/approved/retired]` |
| `[Email provider]` | Transactional notifications | Recipient address, message metadata, required content | `[region]` | `[TLS, access, retention evidence]` | `[proposed/approved/retired]` |
| `[Support provider]` | Case management | Contact details and approved redacted evidence | `[region]` | `[Queue restrictions, redaction, retention]` | `[proposed/approved/retired]` |
| `[Monitoring provider]` | Availability/security/error monitoring | Redacted logs, metrics, traces, alerts | `[region]` | `[PII controls, access review, lifecycle]` | `[proposed/approved/retired]` |

Change workflow:

1. Open a privacy/security review before adding or materially changing a subprocessor.
2. Record data categories, regions, retention, access, transfer mechanism, and contractual state.
3. Obtain counsel, privacy, security, and business approval.
4. Notify tenants only through the approved process and timeline.
5. Revoke access and preserve deletion/export evidence on retirement.

### Operator data-handling rules

- Resolve and verify the tenant before access; never copy data between tenants without an approved process.
- Do not place secrets, plaintext KEKs, Shamir shares, connection strings, dumps, raw exports, or personal-data samples in documentation, ordinary chat, command arguments, or unapproved tickets.
- Redact logs and screenshots before attaching them to support or incident records.
- Keep approved exports encrypted and access-controlled, audit their lifecycle, and delete them on schedule unless legal hold applies.
- Link backup and restore evidence to the relevant change or drill.
- Treat cross-tenant exposure, unauthorized Platform Admin activity, unapproved exports, or residency violations as security/privacy incidents.

## Breach SLA operational targets

> These are drafting placeholders for readiness planning, not contractual or legal deadlines. Counsel, privacy leadership, and the applicable agreement control notification decisions and timing.

| Target | Placeholder | Owner | Evidence |
|--------|-------------|-------|----------|
| Detection triage | Page on-call and open an incident record within 15 minutes of credible detection. | Detector/on-call | Alert and ticket timestamps |
| Incident command | Assign incident, security, platform, database/storage, communications, privacy, counsel, and scribe roles within 30 minutes. | Incident Commander | Role roster |
| Containment | Begin approved containment and preservation within 60 minutes. | Incident Commander + technical leads | Timeline, actions, approvals |
| Impact hypothesis | Develop an initial affected/possibly affected tenant and data-class hypothesis within four hours when evidence permits. | Security + Privacy | Scope memo |
| Notification review | Have counsel/privacy/comms determine whether notice is required and set an approved deadline/cadence. | Counsel + Privacy | Decision record |
| Closure | Complete the approved report, remediation tracker, and evidence index. | Incident Commander | Closure package |

## Tenant and platform responsibility matrix

| Area | Tenant responsibility | Platform operator responsibility |
|------|-----------------------|----------------------------------|
| Data accuracy | Maintain tenant records and identify authorized administrators. | Preserve database routing/isolation and support approved correction/export. |
| User access | Assign/review tenant roles and remove departed administrators. | Operate application access controls; review privileged Platform Admin accounts and audit mutating actions. |
| Minor data | Define youth/minor policy, guardian communications, and public display rules. | Provide verified privacy controls and restrict support exports. |
| Residency | Approve a supported deployed region and exceptions. | Evidence actual deployment location; obtain approval for copies/restores elsewhere. |
| Retention/deletion | Request approved overrides, holds, deletion, and export. | Configure the global backup policy, execute approved lifecycle work, and preserve evidence. |
| Subprocessors | Review notices and use the approved objection process. | Maintain and review the subprocessor register. |
| Backups/restores | Participate in validation and approve tenant-impacting restore decisions. | Monitor tenant fleet backups; request platform backups as needed; test restore paths under the [runbook](backup-restore.md). |
| Disaster recovery | Provide contacts and approve customer communications where required. | Maintain an honest single-region posture until external recovery infrastructure is deployed and drilled. |
| Incidents | Report suspected incidents and provide tenant-side facts. | Triage, contain, investigate, preserve evidence, and coordinate approved notices. |
| Public trust material | Review tenant-specific statements. | Publish only counsel-approved claims backed by current evidence; do not publish roadmap routes as live. |

## Review and approval checklist

- [ ] Counsel approved the DPA outline and customer-facing language.
- [ ] Privacy approved data categories, minor-data handling, deletion/export, retention, and subprocessors.
- [ ] Security verified every listed technical safeguard in the contracted environment and identified external/roadmap items.
- [ ] Platform evidence records the deployed region, database isolation, storage role scope, backup configuration/artifacts, Platform Admin controls, and audit-chain status.
- [ ] Any claimed WORM store, escrow ceremony, or recovery region has separate implementation and test evidence.
- [ ] The business owner approved every customer commitment, SLA, and public trust statement.
- [ ] The approved version, reviewers, timestamp, and evidence links are in the controlled review record.
