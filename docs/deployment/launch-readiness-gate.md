# Managed Platform Launch Readiness Gate

Use this gate before activating the first production tenant and before expanding pilot rings. The decision is no-go unless required evidence is present, current, and approved, or an explicit risk acceptance is recorded with owner and expiry.

[← Back to Deployment Guide](README.md) | [Trust Documentation Index](trust-docs-index.md)

## Decision record

- Release version / commit / image digest: `[link]`
- Target tenant(s) / pilot ring: `[name]`
- Review date and timezone: `[date]`
- Decision: `[go | conditional go | no-go]`
- Decision owner: `[Platform Owner]`
- Evidence package: `[restricted link]`

## Required launch evidence

| Gate | Required evidence | Owner | Go/no-go criteria |
|------|-------------------|-------|-------------------|
| Architecture and tenant isolation | Two-tenant POC, tenant host-resolution tests, isolation negative tests, tenant-scoped storage/cache/session review | Platform Lead | No confirmed cross-tenant data path; unknown hosts denied |
| Platform database and migrations | Platform migrations applied in staging, release manifest compatibility, tenant migration canary, nightly migration drill history | Platform Lead | No unresolved migration blocker; rollback marker path rehearsed |
| Secrets and encryption | Secret-store configuration, KEK escrow verification, no plaintext key/log exposure, approved Shamir implementation plan | Security Lead | No plaintext secrets in code/logs/evidence; escrow drill prerequisites met |
| Backups and restore drills | Fresh platform and tenant backups, non-destructive restore drill records, retention/immutability proof | Database Lead | Backup age within threshold; restore drill current; object retention verified |
| Disaster recovery | Tabletop or drill evidence tied to [DR checklist](dr-drill-execution-checklist.md), RTO/RPO measurement, failback/rollback notes | Incident Commander | Critical DR steps rehearsed; RTO/RPO risk accepted or met |
| WORM audit | Audit write smoke, hash-chain continuity, immutable mirror retention proof, fail-closed decision | Storage/Audit Lead | No unowned gap; immutable retention enabled or risk accepted |
| Platform admin and trust surface | Isolated platform admin ingress, platform-admin auth gate, read-only routes, tenant trust redaction tests | Platform Lead | Tenant traffic cannot reach platform admin; no secret/other-tenant leakage |
| Security assessment | Approved penetration test scope, final report when completed, findings disposition, regression checklist | Security Lead | Critical findings closed; High findings closed or formally accepted |
| Accessibility and UI security | [Security Regression Checklist](security-regression-checklist.md), WCAG 2.2 AA evidence for changed UI | QA Lead | Blocking accessibility/security regression fixed or release held |
| Legal and DPA | Counsel-approved governance, DPA/privacy, breach escalation, subprocessor/residency terms as applicable | Counsel + Data Protection Lead | No customer commitment uses unapproved language |
| Pilot migration | Rehearsal runbook evidence, rollback rehearsal, go/no-go checklist, communication templates | Platform Owner | Customer-impacting migration has named approvers and rollback deadline |
| Operations and support | On-call roster, alert routing, runbooks, incident roles, status/customer communication path | Operations Owner | P1/P2 coverage staffed; alerts tested or accepted |
| Published trust docs | [Trust Docs Index](trust-docs-index.md) packet reviewed and customer-safe | Platform Owner | Published material does not overclaim or expose restricted data |

## Go/no-go rules

### Automatic no-go

- Confirmed cross-tenant data exposure, host misrouting, or authorization boundary bypass.
- Missing current restorable backup for the tenant or platform metadata needed for launch.
- Failed WORM audit continuity without explicit incident/risk acceptance.
- Critical penetration test finding not fixed and retested.
- Platform admin reachable from normal tenant traffic or accepts spoofed identity headers.
- Counsel has not approved customer-facing legal, DPA, breach, retention, or residency language.
- Customer-facing UI changes have untriaged keyboard, focus, label, contrast, or non-color-status accessibility regressions.

### Conditional go requirements

- Each accepted risk has an owner, due date, expiry, mitigation, customer-impact assessment, and approving roles.
- Security Lead approves any High finding deferral.
- Platform Owner and affected customer representative approve any pilot migration risk that can affect downtime, data quality, or rollback.

## Approvals

| Role | Name | Decision | Timestamp | Notes |
|------|------|----------|-----------|-------|
| Platform Owner | `[name]` | `[go/no-go]` | `[time]` | |
| Security Lead | `[name]` | `[go/no-go]` | `[time]` | |
| Database Lead | `[name]` | `[go/no-go]` | `[time]` | |
| Storage/Audit Lead | `[name]` | `[go/no-go]` | `[time]` | |
| Incident Commander / Ops | `[name]` | `[go/no-go]` | `[time]` | |
| Counsel / Data Protection | `[name]` | `[go/no-go]` | `[time]` | |
| QA / Accessibility | `[name]` | `[go/no-go]` | `[time]` | |
| Customer representative, if pilot | `[name]` | `[go/no-go]` | `[time]` | |

## Readiness script

Run the local read-only documentation check from `app/` before review:

```bash
bash bin/trust_readiness_check.sh
```

The script verifies that required trust documents exist and contain key launch topics. Passing script output is not a substitute for human evidence review or a completed penetration test/DR drill.
