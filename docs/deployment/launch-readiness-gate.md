---
layout: default
title: "Managed Platform Launch Readiness Gate"
description: "Go or no-go evidence gate for security, recovery, operations, and multi-tenant launch readiness."
---

# Managed Platform Launch Readiness Gate

Use this Go/no-go gate before the first production tenant and before expanding a
pilot ring. A checked box is not evidence: every required item needs a current,
restricted evidence link or an explicit risk acceptance with owner and expiry.

[← Deployment and operations](README.md) |
[Trust documentation index](trust-docs-index.md)

## Current gaps that cannot be implied away

The repository currently deploys one Azure region and one web application.
Platform Admin is a privileged reserved-host surface in that app. Azure WORM
audit storage, a provisioned recovery region, a production-grade KEK escrow
ceremony, and tenant/public trust pages are not implemented by the current
template. A launch claim that depends on one of those controls requires separate
deployment evidence and approval.

## Decision record

- Release tag / commit / immutable image digest: `[link]`
- Target environment, tenant(s), and ring: `[value]`
- Review date/timezone: `[value]`
- Decision: `[go | conditional go | no-go]`
- Decision owner: `[name]`
- Restricted evidence package: `[link]`

## Required launch evidence

| Gate | Evidence | Go/no-go condition |
| --- | --- | --- |
| Architecture and tenant isolation | Two-tenant host tests, separate DB evidence, cross-tenant negative tests, logical storage/cache/session review | No cross-tenant path; unknown hosts fail closed |
| Release and migrations | POC-validated digest; same-digest promotion; exact application/platform/secret/key/tenant-fleet migration result | No pending/failed tenant migration; no rebuild after POC |
| Secrets and keys | Database secret-store configuration, master-key availability, backup-key readiness, redaction/rotation evidence | No plaintext exposure; missing required key is no-go |
| Backups and restore | Fresh tenant `.json.gz.enc` and platform `.pgdump.enc` metadata, hashes, recovery-key custody, restore rehearsal | Platform-first recovery is plausible and selected archives are available |
| Platform Admin | Reserved-host enforcement, password/TOTP, lockout/status, host-bound session, policy/CSRF/confirmation tests for mutating actions | Tenant-host access or session-boundary bypass is no-go |
| Worker and health | Unified three-minute worker evidence, one active scheduling authority, `/livez` and `/health` probes | No duplicate worker authority; readiness dependencies healthy |
| Security assessment | Approved penetration-test package, findings/retests, [security regression](security-regression-checklist.md) | Critical closed; High fixed or time-bound accepted |
| Accessibility | WCAG 2.2 AA evidence for changed customer/operator UI | Untriaged keyboard, focus, label, status, or contrast defect is no-go |
| Legal/DPA | Counsel-approved residency, retention, subprocessor, breach, and customer language | No unapproved commitment |
| Pilot migration | Approved importer/cutover procedure, rehearsal, rollback deadline, customer acceptance | All environment-specific placeholders resolved |
| Operations | Staffed on-call, tested alert path, support/comms ownership | P1/P2 ownership and escalation confirmed |
| External controls claimed | Immutable audit storage, recovery region, escrow ceremony, private network, or public trust evidence as applicable | Unproven claimed control is no-go |
| Published trust material | Customer-safe packet matches actual implementation and names gaps | No overclaim or restricted data |

Optional release-manifest, migration-canary, and nightly-drill tooling may support
the evidence package when explicitly configured. They are not part of the
active Azure deployment and their absence must not be disguised as completed
evidence.

## Automatic no-go

- cross-tenant exposure, host misrouting, or authorization bypass;
- missing current platform or affected-tenant backup/recovery key;
- a deployment digest different from the POC-validated digest;
- failed application, platform, or tenant-fleet migration;
- tenant-host reachability of Platform Admin or cross-host admin-session reuse;
- unclosed Critical security finding;
- an advertised WORM, region-failover, escrow, or trust-page control without
  verified deployment evidence;
- unapproved customer legal/residency/retention language; or
- untriaged WCAG 2.2 AA regression in changed UI.

## Conditional-go record

Every accepted risk must identify:

- the exact gap and affected tenants/data;
- owner, mitigation, due date, and expiry;
- customer and operational impact;
- rollback/containment trigger; and
- required Security, Platform, Legal, and customer approvals.

## Approvals

| Role | Name | Decision | Timestamp | Notes |
| --- | --- | --- | --- | --- |
| Platform Owner | `[name]` | `[go/no-go]` | `[time]` | |
| Security Lead | `[name]` | `[go/no-go]` | `[time]` | |
| Database/Recovery Lead | `[name]` | `[go/no-go]` | `[time]` | |
| Operations/Incident Lead | `[name]` | `[go/no-go]` | `[time]` | |
| Counsel/Data Protection | `[name]` | `[go/no-go]` | `[time]` | |
| QA/Accessibility | `[name]` | `[go/no-go]` | `[time]` | |
| Pilot customer representative | `[name]` | `[go/no-go]` | `[time]` | |

Run from `app/` before review:

```bash
bash bin/trust_readiness_check.sh
```

The script checks document presence and keywords only. It does not validate
infrastructure, execute a drill, approve legal text, or prove a control.
