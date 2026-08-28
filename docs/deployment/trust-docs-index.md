---
layout: default
title: "Managed Platform Trust Documentation Index"
description: "Status-aware index of implemented controls, evidence templates, external controls, and roadmap items."
---

# Managed Platform Trust Documentation Index

This index maps KMP's managed multi-tenant trust material and its current publication status. It is not itself assurance evidence. Every external statement requires customer-safe language, current environment evidence, and the listed review. Published pages must not expose secrets, signed object URLs, raw logs, privileged implementation details, tenant-private data, or another tenant's identifiers.

[← Back to Deployment Guide](README.md) | [Launch Readiness Gate](launch-readiness-gate.md)

## Status vocabulary

- **Current:** implemented in the repository; verify the deployed environment before publishing.
- **Conditional:** available only when configured and evidenced.
- **External prerequisite:** not supplied by the active Azure application/infrastructure path.
- **Roadmap/template:** planned behavior or an evidence-gathering aid, not a completed control.

## Current platform qualifications

- Architecture and tenant isolation currently use a separate PostgreSQL database per tenant plus a platform database.
- Document isolation is logical by tenant container/prefix. The current managed identity has storage-account-wide Storage Blob Data Contributor access.
- The active Azure Bicep is single-region; regional recovery remains an external prerequisite.
- One global backup policy supports daily/weekly cadence and defaults to daily/30 days. Managed jobs run tenant fleet backups; platform backups may be explicitly requested. Tenant artifacts are `.json.gz.enc` and platform artifacts are `.pgdump.enc`.
- The database audit hash chain exists. The Azure WORM sink is not implemented, defaults disabled, and requires an external immutable destination.
- The Shamir KEK helper is a non-production placeholder; the production escrow ceremony is external.
- Platform Admin is part of the same web app on reserved hosts. It uses in-app password plus TOTP, lockout, account status, and host-bound sessions, and it includes mutating operations.
- Tenant trust and public status routes are roadmap.

## Publication set

| Trust topic | Sources | Status before publication | Reviewer |
|-------------|---------|---------------------------|----------|
| Architecture and tenant isolation | [Two-Tenant Staging POC](multi-tenant-poc.md), [Platform Admin and Trust Surface](platform-admin-v2-trust-surface.md), [Legal governance](legal-governance.md#data-residency-model) | Current database boundary; disclose logical storage boundary, account-wide identity scope, and single-region limitation | Platform + Security |
| Backup and restore | [Backup & Restore](backup-restore.md) | Current capability; publish only configured cadence, retention, artifact freshness, and tested restore evidence | Database + Platform |
| Disaster recovery | [Region Failover Runbook](region-failover-runbook.md), [DR Drill Checklist](dr-drill-execution-checklist.md) | Template/external prerequisite; do not claim recovery-region readiness or RTO/RPO achievement without a completed drill | Platform Owner + Incident Commander |
| Legal governance and DPA | [Governance Template](legal-governance.md), [Data Protection Templates](data-protection-agreement-template.md) | Templates only; counsel-approved terms required | Counsel + Privacy |
| Pilot migration and rollback | [Ring Exit Criteria](pilot-ring-exit-criteria.md), [Migration Rehearsal](pilot-migration-runbook.md), [Go/No-Go Checklist](pilot-go-no-go-checklist.md) | Templates; attach completed evidence for the named tenant/environment | Platform Owner + Customer Success |
| Platform Admin | [Platform Admin and Trust Surface](platform-admin-v2-trust-surface.md) | Current privileged/mutating surface; document actual authentication and redaction behavior | Platform + Security |
| Tenant trust/public status | [Platform Admin and Trust Surface](platform-admin-v2-trust-surface.md) | Roadmap; do not publish routes or availability claims | Product + Security + Accessibility |
| Security controls and testing | [Penetration Test Checklist](penetration-test-scope-checklist.md), [Security Regression Checklist](security-regression-checklist.md) | Assessment templates; publish outcomes only after approved evidence exists | Security |
| Audit immutability and KEK escrow | [Backup & Restore](backup-restore.md), [Governance Template](legal-governance.md) | External prerequisites; distinguish database hash chain from WORM storage and placeholder tooling from a production ceremony | Security + Platform + Counsel |
| Launch readiness | [Launch Readiness Gate](launch-readiness-gate.md) | Internal decision template; publish only an approved high-level status | Platform Owner |

## Customer-safe publication rules

- Describe only controls and outcomes supported by current evidence; mark unperformed work as required, external, or roadmap.
- Never state that a penetration test, DR drill, restore drill, escrow ceremony, external audit, or WORM control is complete until its approved evidence package exists.
- Explain the difference between separate tenant databases, logical document boundaries, and the cloud identity's account-wide storage role.
- State the deployed region and tested recovery posture. A runbook or geo-redundant service option does not prove regional failover.
- Publish the actual configured backup cadence/retention and tested artifacts, not governance placeholders.
- Describe Platform Admin as privileged and mutating; do not call it a separate application, externally authenticated, or read-only.
- Link to counsel-approved terms for contractual commitments.
- Include WCAG 2.2 AA evidence for any customer-facing trust or status interface once those roadmap routes are implemented.

## Required trust packet before a production commitment

- [ ] Customer-safe architecture summary identifies database isolation, storage boundary/RBAC scope, and deployed region.
- [ ] Backup summary identifies the configured global policy, tenant/platform artifact formats, latest successful jobs, and restore evidence.
- [ ] Disaster recovery summary labels unbuilt regional controls and unproven targets; completed drill evidence is attached if a claim is made.
- [ ] Legal governance and DPA material has counsel/privacy approval.
- [ ] Platform Admin documentation reflects same-app reserved-host routing, in-app authentication, and mutating capability.
- [ ] WORM and KEK escrow claims are omitted or backed by external implementation evidence.
- [ ] Tenant trust/public status routes are labeled roadmap until implemented and reviewed.
- [ ] Security controls packet links the approved assessment scope, results when available, remediation, and regression evidence.
- [ ] Links, spelling, whitespace, confidentiality, and customer-safe redaction have been reviewed.

## Maintenance cadence

| Cadence | Action |
|---------|--------|
| Every release candidate | Re-run the [Launch Readiness Gate](launch-readiness-gate.md) review and `bin/trust_readiness_check.sh`; update claims when implementation changes. |
| Monthly during pilot | Refresh backup, restore, incident contact, audit-chain, access, and security evidence. Review external WORM/recovery status without implying deployment. |
| Quarterly | Review legal/DPA templates, configured retention, privileged access, external escrow status, and DR/tabletop evidence. |
| After an incident or material control change | Update affected pages and record reviewer approval before republishing. |
