# Managed Platform Published Trust Documentation Index

This index is the customer/operator-facing publication map for managed multi-tenant trust material. Documents below must be reviewed for customer-safe language before external publication. Internal evidence links may point to restricted tickets, but published pages must not expose secrets, raw logs, object URIs with credentials, tenant-private data, or other tenants' information.

[← Back to Deployment Guide](README.md) | [Launch Readiness Gate](launch-readiness-gate.md)

## Publication set

| Trust topic | Source document | Publication status | Required reviewer |
|-------------|-----------------|--------------------|-------------------|
| Architecture and tenant isolation | [Two-Tenant Staging POC](multi-tenant-poc.md), [Platform Admin v2 and Tenant Trust Surface](platform-admin-v2-trust-surface.md), [Legal and Security Governance](legal-governance.md#data-residency-model) | Prepare customer-safe summary | Platform Lead + Security Lead |
| Backup and restore | [Backup & Restore](backup-restore.md) | Prepare operational summary and evidence freshness language | Database Lead |
| Disaster recovery | [Managed Platform Region Failover Runbook](region-failover-runbook.md), [DR Drill Execution Checklist](dr-drill-execution-checklist.md) | Publish RTO/RPO targets only after approval | Platform Owner + IC |
| Legal governance and DPA | [Legal and Security Governance](legal-governance.md), [Data Protection Templates](data-protection-agreement-template.md) | Counsel-approved only | Counsel + Data Protection Lead |
| Pilot migration and rollback | [Pilot Ring Exit Criteria and Rollback Plan](pilot-ring-exit-criteria.md), [Pilot Migration Rehearsal Runbook](pilot-migration-runbook.md), [Pilot Go/No-Go Checklist](pilot-go-no-go-checklist.md) | Prepare pilot onboarding packet | Platform Owner + Customer Success |
| Admin trust surface | [Platform Admin v2 and Tenant Trust Surface](platform-admin-v2-trust-surface.md) | Publish feature availability and redaction rules | Platform Lead + Security Lead |
| Security controls and testing | [Penetration Test Scope and Evidence Checklist](penetration-test-scope-checklist.md), [Security Regression Checklist](security-regression-checklist.md) | Publish control summary after assessment evidence is ready | Security Lead |
| Launch readiness evidence | [Launch Readiness Gate](launch-readiness-gate.md) | Internal gate; publish only high-level readiness status | Platform Owner |

## Customer-safe publication rules

- Describe controls and outcomes; do not publish exploit details, internal severities, privileged routes, raw command output, or vendor-sensitive configurations.
- Use aggregate or tenant-scoped evidence. Never include another tenant's slug, hostname, database name, object URI, support ticket, or incident detail.
- Link to counsel-approved DPA/privacy language for contractual commitments. Operational runbooks are not contract terms by themselves.
- Mark unperformed activities as required/planned. Do not state that a penetration test, DR drill, or external audit has completed until the evidence package exists and is approved.
- Include accessibility commitments for customer-facing UI changes, including WCAG 2.2 AA regression checks.

## Required trust packet before first production tenant

- [ ] Customer-safe architecture/isolation summary approved.
- [ ] Backup/restore summary includes retention defaults, restore drill cadence, and evidence freshness thresholds.
- [ ] DR summary includes approved RTO/RPO targets and links to latest tabletop or drill evidence.
- [ ] DPA/privacy/governance packet reviewed by counsel.
- [ ] Pilot migration packet includes rehearsal, rollback, go/no-go, and customer communication templates.
- [ ] Platform admin and tenant trust surface documentation describes read-only status and redactions.
- [ ] Security controls packet includes penetration test scope, final report link when available, remediation status, and security regression checklist.
- [ ] Published pages have link, spelling, whitespace, and customer-safe redaction review.

## Maintenance cadence

| Cadence | Action |
|---------|--------|
| Every release candidate | Re-run [Launch Readiness Gate](launch-readiness-gate.md) evidence review and `bin/trust_readiness_check.sh`. |
| Monthly during pilot | Refresh backup/restore, DR, WORM audit, incident/contact, and security posture summaries. |
| Quarterly | Review legal governance, DPA templates, retention defaults, access rosters, KEK escrow, and DR tabletop outcomes. |
| After any incident or major control change | Update affected published trust pages and record reviewer approval before republishing. |
