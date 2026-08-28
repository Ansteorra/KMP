---
layout: default
title: "Pilot Go/No-Go Checklist Template"
description: "Evidence checklist for deciding whether a managed multi-tenant pilot may proceed."
---

# Pilot Go/No-Go Checklist Template

Copy this template into the restricted migration ticket for each pilot tenant.
Replace every placeholder with evidence; never paste secrets, keys, raw exports,
customer records, or credential-bearing object URLs.

[← Pilot ring criteria](pilot-ring-exit-criteria.md) |
[Pilot migration runbook](pilot-migration-runbook.md)

The current deployment is single-region. Azure WORM storage and tenant/public
trust pages are not implemented. Mark those controls `not implemented` or link
separately verified external evidence—never mark them green from application
configuration alone.

## Migration summary

- Tenant slug and approved hosts: `[value]`
- Ring: `[0 | 1 | 2]`
- Window and timezone: `[value]`
- Source freeze / rollback deadline: `[value]`
- Release tag, commit, POC evidence, and immutable digest: `[links]`
- Incident/change ticket and support channel: `[links]`
- Platform owner, migration operator, validator, communicator: `[names]`
- Customer representative: `[name]`

## Readiness gates

| Gate | Status | Evidence | Owner/notes |
| --- | --- | --- | --- |
| POC-validated digest deployed to rehearsal | `[green/yellow/red]` | `[link]` | |
| Tenant and unknown-host resolution tests pass | `[green/yellow/red]` | `[link]` | |
| Exact managed migration chain passes | `[green/yellow/red]` | `[link]` | |
| Tenant import rehearsal and validation pass | `[green/yellow/red]` | `[link]` | |
| Logical pre-migration marker/rollback rehearsal passes | `[green/yellow/red]` | `[link]` | |
| Tenant backup is fresh (`.json.gz.enc`) | `[green/yellow/red]` | `[link]` | |
| Platform backup is fresh (`.pgdump.enc`) | `[green/yellow/red]` | `[link]` | |
| Recovery-key custody and restore rehearsal pass | `[green/yellow/red]` | `[link]` | |
| Platform Admin host/auth/session tests pass | `[green/yellow/red]` | `[link]` | |
| Database audit chain is continuous | `[green/yellow/red]` | `[link]` | |
| External WORM control, if required, is verified | `[not required/not implemented/green/red]` | `[link]` | |
| Worker, queues, alerts, and on-call are ready | `[green/yellow/red]` | `[link]` | |
| Security/accessibility findings reviewed | `[green/yellow/red]` | `[link]` | |
| Customer/legal communications approved | `[green/yellow/red]` | `[link]` | |

Optional manifest/canary/nightly-drill evidence is listed separately when an
environment deliberately enables it; it is not a substitute for the active
migration result.

## Live-window checks

- [ ] Source write freeze and final export/checksum recorded.
- [ ] Exact target tenant, database, hosts, region, and storage scope confirmed.
- [ ] Final source backup/export is independently readable.
- [ ] Import version and redacted command reference recorded.
- [ ] Critical counts, relationships, documents, and samples validate.
- [ ] Tenant admin login and authorization/workflow smoke pass.
- [ ] Platform Admin login and one authorized privileged-action smoke pass on an
      allowed admin host.
- [ ] Tenant and unknown-host resolution tests pass with valid TLS/SNI.
- [ ] Database audit event and hash chain validate; external WORM smoke runs only
      if that control exists.
- [ ] Unified worker, queue, platform jobs, and alert routes are healthy.
- [ ] Customer accepts cutover.
- [ ] Sixty-minute post-cutover observation completes.

## Rollback decision

- Trigger/deadline: `[value]`
- Decision: `[go | no-go | rollback | approved extension]`
- Decision owner and timestamp: `[value]`
- Customer acknowledgement: `[link]`
- Source/target write status and reconciliation plan: `[value]`
- Backups/evidence preserved: `[links]`

## Approvals

| Role | Approver | Decision | Timestamp |
| --- | --- | --- | --- |
| Platform Owner | `[name]` | `[go/no-go]` | `[time]` |
| Migration/Database Lead | `[name]` | `[go/no-go]` | `[time]` |
| Operations/Incident Lead | `[name]` | `[go/no-go]` | `[time]` |
| Security/Audit Lead | `[name]` | `[go/no-go]` | `[time]` |
| Customer representative | `[name]` | `[go/no-go]` | `[time]` |

Afterward, attach the customer notice, evidence index, incidents/defects, retro,
and ring-progression decision.
