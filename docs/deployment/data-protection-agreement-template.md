# Managed Platform Data Protection Templates

These templates support KMP managed multi-tenant platform operations. They are
not legal advice and must be reviewed and approved by qualified counsel,
privacy leadership, security leadership, and the relevant business owner before
they are used for customer commitments, contract language, public statements, or
regulator communications.

[← Back to Deployment Guide](README.md)

Related operational references:

- [Managed Platform Legal and Security Governance Template](legal-governance.md)
- [Managed Platform Region Failover Runbook](region-failover-runbook.md)
- [Backup & Restore](backup-restore.md)
- [Pilot Ring Exit Criteria and Rollback Plan](pilot-ring-exit-criteria.md)
- [Pilot Go/No-Go Checklist Template](pilot-go-no-go-checklist.md)

## How to use this document

1. Copy the relevant section into the legal, privacy, or customer-readiness
   ticket.
2. Replace bracketed placeholders with tenant-specific facts and evidence
   links.
3. Have counsel decide what belongs in the signed DPA, order form, privacy
   notice, security addendum, or customer-facing trust page.
4. Do not publish or promise any SLA, subprocessor statement, residency term,
   retention term, or breach-notification deadline until counsel approves it.

## DPA template outline for counsel review

This outline is a drafting aid for a Data Processing Agreement or equivalent
data-protection addendum. It intentionally avoids final legal language.

| Section | Operational content to provide | Evidence source |
|---------|--------------------------------|-----------------|
| Parties and roles | Identify the tenant/customer, platform operator, data controller/processor role assumptions, and any tenant-specific administrators. | Contract record, tenant onboarding ticket |
| Scope of processing | Describe managed KMP hosting, tenant provisioning, application operations, support, backup, restore, incident response, and audit evidence processing. | Architecture record, [legal governance](legal-governance.md) |
| Data categories | Member profiles, branch/role data, warrants, authorizations, waivers, notes, uploaded documents, audit logs, operational metadata, support exports, and incident evidence as applicable. | Tenant data inventory, plugin inventory |
| Data subjects | Members, guardians/parents where applicable, officers, administrators, event participants, support contacts, and platform operators. | Tenant onboarding questionnaire |
| Minor data handling | Identify youth/minor fields and workflows, guardian contact patterns, access restrictions, public-display controls, support export rules, and approval requirements for any disclosure. | Application privacy controls, tenant policy |
| Processing instructions | State that platform operation follows signed customer instructions, approved runbooks, emergency incident procedures, and documented change control. | Contract, change ticket, incident record |
| Confidentiality | Require trained, authorized operator access only; prohibit secrets or personal data in chat, tickets, docs, or command arguments except approved evidence repositories. | Access review, training records |
| Security measures | Database-per-tenant isolation, per-tenant storage boundaries, managed identity/RBAC, encrypted backups, WORM audit mirrors, secret-store controls, KEK escrow, monitoring, and restore drills. | [backup/restore](backup-restore.md), [failover runbook](region-failover-runbook.md) |
| Residency and transfers | Use `tenants.region` as the operational source of residency; describe approved regions and cross-region restore/export approval workflow. | [legal governance residency model](legal-governance.md#data-residency-model) |
| Subprocessors | Reference the approved subprocessor register template below; define notice, objection, and emergency replacement process for counsel to finalize. | Subprocessor register |
| Assistance requests | Define how the operator helps with access, correction, deletion, export, audit, DPIA, regulator, or customer-support requests. | Support runbook, ticket queue |
| Retention and deletion | State default backup, audit, log, support export, and evidence retention windows; identify tenant override and legal-hold process. | [retention defaults](legal-governance.md#retention-policy-defaults) |
| Breach/security incident | Reference the operational incident process and counsel-approved notification terms; do not treat this template as the commitment. | [breach process](legal-governance.md#breach-notification-operational-process), section below |
| Audit and evidence | Define reasonable audit assistance, evidence package contents, WORM proof, restore drill evidence, and confidentiality limits. | Audit evidence repository |
| Return or deletion on termination | Describe export handoff, tenant database/document deletion, backup expiry, audit retention, and confirmation evidence. | Offboarding ticket |
| Liability, indemnity, governing law | Counsel-owned language only. | Legal review |

### DPA fact sheet template

```text
Tenant/customer:
Tenant slug(s):
Primary region/residency:
Customer controller/processor role assumption:
Platform operator role assumption:
Data categories in scope:
Minor data present? yes/no/unknown:
Subprocessors approved:
Retention overrides:
Support/export contacts:
Security evidence package link:
Counsel reviewer:
Privacy reviewer:
Approved for customer use? yes/no:
Approval timestamp:
```

## Privacy, subprocessor, and data handling template

Use this section as the starting point for a public or customer-facing "How we
handle your data" page. Counsel and privacy leadership must approve wording
before publication.

### Draft privacy statement outline

- **Service purpose:** KMP hosts kingdom management workflows for the tenant,
  including member records, branches, roles, warrants, authorizations, waivers,
  events, notes, documents, notifications, and administrative audit records.
- **Tenant control:** The tenant defines who may administer its kingdom data,
  what records are entered, and what member-facing policies apply.
- **Platform operation:** The platform operator runs the managed service,
  applies approved changes, monitors health/security, performs backups and
  restore drills, and assists with incidents and support requests.
- **Isolation:** Tenant application data is stored in a tenant-specific database.
  Documents are stored in tenant-scoped containers or prefixes. Platform metadata
  is limited to control-plane records needed to route, operate, secure, and audit
  the service.
- **Access:** Routine operator access is restricted to authorized platform
  personnel. Emergency or support access must be tied to an incident, change, or
  support ticket and captured in audit evidence.
- **Minor data:** Minor-related records require extra care. Do not export,
  disclose, or use minor data outside approved tenant workflows unless the
  tenant, privacy lead, and counsel-approved process permits it.
- **Backups and audit:** Backups, WORM audit mirrors, logs, and incident
  evidence are retained according to approved policy and legal holds.
- **Deletion/export:** Tenant requests for deletion, export, or correction are
  routed through the tenant's authorized contact and tracked to completion.
- **Incidents:** Potential security or privacy incidents are handled through the
  incident process. Customer/regulator notices are sent only after counsel and
  privacy approval.

### Subprocessor register template

| Subprocessor | Service purpose | Data categories | Processing location/region | Safeguards | Status |
|--------------|-----------------|-----------------|-----------------------------|------------|--------|
| `[Cloud hosting provider]` | App hosting, managed database, storage, networking, monitoring | Tenant application data, documents, backups, logs, platform metadata | `[region list]` | Encryption, RBAC, private networking where applicable, contractual terms | `[proposed/approved/retired]` |
| `[Email provider]` | Transactional notifications | Recipient email, message metadata, template content needed for delivery | `[region list]` | TLS, account access controls, retention settings | `[proposed/approved/retired]` |
| `[Support/ticketing system]` | Support case management | Contact details, issue summaries, approved screenshots/evidence | `[region list]` | Restricted queues, redaction rules, retention policy | `[proposed/approved/retired]` |
| `[Monitoring/log provider]` | Availability, security, and error monitoring | Redacted logs, metrics, traces, alert metadata | `[region list]` | PII redaction, access review, retention lifecycle | `[proposed/approved/retired]` |

Subprocessor change workflow:

1. Open a privacy/security review ticket before adding or materially changing a
   subprocessor.
2. Record data categories, regions, retention, access controls, and contractual
   status.
3. Obtain counsel, privacy, and security approval.
4. Notify tenants only using the counsel-approved process and timeline.
5. Retire access and confirm deletion/export evidence when a subprocessor is no
   longer used.

### Data handling rules for operators

- Use tenant-scoped tools and identifiers; never copy data between tenants.
- Do not place secrets, plaintext KEKs, Shamir shares, connection strings,
  database dumps, raw exports, or personal data samples in documentation, chat,
  command arguments, or unapproved tickets.
- Redact logs and screenshots before attaching them to support or incident
  tickets.
- Store support exports in encrypted restricted storage, audit creation/access,
  and delete them within the approved retention window unless legal hold applies.
- Keep backup and restore evidence linked to the tenant change or drill ticket.
- Treat any suspected cross-tenant exposure, unapproved export, or residency
  violation as a security/privacy incident.

## Breach SLA operational commitments draft

> **Requires legal approval before customer commitment.** The following is an
> operational target draft for incident readiness. It does not create a
> contractual SLA, legal notification deadline, regulator statement, or customer
> promise until approved by counsel and incorporated into the appropriate
> agreement or customer communication.

| Operational target | Draft commitment for review | Owner | Evidence |
|--------------------|-----------------------------|-------|----------|
| Detection triage | Page on-call and open an incident ticket within 15 minutes of credible detection. | Detector/on-call | Alert ID, ticket timestamp |
| Incident command | Assign Incident Commander, Security Lead, Platform Lead, Database/Storage leads, Comms Lead, Privacy Lead, counsel contact, and scribe within 30 minutes. | IC | Role roster |
| Initial containment | Begin containment and evidence preservation within 60 minutes, with destructive actions approved by IC/security unless emergency break-glass is required. | IC + technical leads | Timeline, commands, approvals |
| Tenant-impact assessment | Produce an initial affected/possibly affected tenant list and data-class hypothesis within 4 hours when evidence allows. | Security Lead + Data Protection/Privacy Lead | Scope memo |
| Customer notification decision | Counsel/privacy/comms review determines whether and when customer notification is required; target first decision review within 24 hours. | Counsel + Privacy Lead | Decision record |
| Customer notice after approval | If counsel approves/requires notice, send to affected tenants without undue delay and no later than the approved contractual/legal deadline. A 72-hour outer target may be used for readiness planning only if counsel approves it for the tenant/region. | Comms Lead + IC | Approved message, recipient list, timestamps |
| Update cadence | Provide follow-up updates at the cadence approved in the incident decision record until closure. | Comms Lead | Versioned updates |
| Closure package | Complete post-incident report, remediation tracker, evidence index, and tenant/customer follow-up within the approved closure window. | IC | Post-incident report |

Suggested customer notice placeholders for counsel:

```text
Subject:
Incident ID:
Date/time detected:
Date/time contained:
Affected tenant(s):
Systems involved:
Data categories involved:
Known impact:
Protective actions taken:
Recommended customer actions:
What KMP is doing next:
Next update time:
Support contact:
Counsel-approved version:
```

## Tenant and platform responsibility matrix

| Area | Tenant responsibilities | Platform operator responsibilities |
|------|-------------------------|------------------------------------|
| Tenant data accuracy | Enter, maintain, classify, and validate member/kingdom records; define tenant administrators. | Preserve tenant isolation, provide application controls, and support authorized correction/export workflows. |
| User access | Assign tenant roles, remove departed admins, review local permissions, and enforce tenant policy. | Operate platform admin access controls, audit privileged actions, and provide role/security features. |
| Minor data | Define tenant policy for youth/minor records, guardian communications, and public display. | Provide privacy-aware controls, restrict support exports, and escalate suspected minor-data incidents. |
| Residency | Select/approve supported residency region and document any exception. | Store tenant DB/documents according to approved region and require approval for cross-region copies/restores. |
| Retention/deletion | Request tenant-specific retention overrides, deletion/export actions, and legal holds through authorized contacts. | Apply approved retention defaults/overrides, maintain backup/audit lifecycle evidence, and execute tracked deletion/export requests. |
| Subprocessors | Review notices and raise objections through approved process. | Maintain subprocessor register, perform security/privacy review, and notify tenants as approved by counsel. |
| Backups/restores | Participate in restore validation when requested and sign off on tenant-impacting restore decisions. | Run backups, restore drills, integrity checks, and evidence capture under [Backup & Restore](backup-restore.md). |
| Disaster recovery | Provide business contacts and approve customer-facing downtime/failover communications where required. | Execute the [region failover runbook](region-failover-runbook.md), protect evidence, and restore platform/tenant services. |
| Incidents | Report suspected incidents promptly and provide tenant-side facts, contacts, and communication approvals. | Triage, contain, investigate, preserve evidence, coordinate counsel/privacy review, and send approved notices. |
| Pilot readiness | Validate migrated workflows, approve migration windows, and accept or reject cutover. | Meet [pilot exit criteria](pilot-ring-exit-criteria.md), complete [go/no-go checklist](pilot-go-no-go-checklist.md), and provide evidence. |

## Review and approval checklist

- [ ] Counsel reviewed DPA outline and any customer-facing language.
- [ ] Privacy lead approved data categories, minor-data handling, deletion/export
      workflow, and subprocessor register.
- [ ] Security lead approved security measures, incident process, audit evidence,
      and operator data-handling rules.
- [ ] Platform lead confirmed backup/restore, DR, WORM audit, and pilot evidence
      links are current.
- [ ] Business owner approved any customer commitment, SLA, or public trust page.
- [ ] Approved version, approvers, timestamp, and evidence links are stored in
      the legal/privacy review ticket.
