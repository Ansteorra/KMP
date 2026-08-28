---
layout: default
title: "Managed Platform DR Drill Execution Checklist"
description: "Evidence-driven checklist for rehearsing platform and tenant disaster recovery safely."
---

# Managed Platform DR Drill Execution Checklist

This checklist records an approved non-production disaster-recovery exercise.
It is not evidence that a drill has happened. Use it with the
[region failover planning runbook](region-failover-runbook.md) and
[backup/restore procedures](backup-restore.md).

[← Deployment and operations](README.md) |
[Launch readiness](launch-readiness-gate.md)

## Current capability boundary

The repository deploys one Azure region. Geo-redundant PostgreSQL backups and
GRS storage do not provision a recovery environment, restore databases, validate
DNS/TLS, or meet an RTO/RPO by themselves. The database audit chain exists;
Azure WORM storage does not. The bundled Shamir splitter is a non-production
placeholder and cannot be used as an escrow ceremony.

A regional exercise is blocked until operators separately provision an isolated
target, networking/DNS/TLS, secret/key access, storage access, and an approved
platform-database `pg_restore` procedure.

## Candidate drill types

Cadences below are readiness proposals and become requirements only after the
Platform Owner, Security Lead, and recovery owners approve them.

| Type | Destructive? | Evidence |
| --- | --- | --- |
| Tabletop | No | Roles, assumptions, decision log, blockers |
| Tenant restore plan | No by default | `tenant restore_drill` result and job row |
| Isolated tenant restore | Yes, drill target only | Archive/key IDs, transcript, data/host smoke |
| Platform restore | Yes, drill target only | Decrypt, `pg_restore`, platform health, metadata checks |
| Regional rehearsal | Yes, isolated region | Infrastructure record, platform-first restore, tenants, TLS/DNS, worker cutover |
| Escrow rehearsal | Only approved non-production material | Approved implementation, custodians, fingerprint, reseal/rotation |

## Pre-drill safety gate

- [ ] Ticket names source and target subscriptions/resource groups, regions,
      databases, storage, tenants, and scenario.
- [ ] Incident Commander, Platform, Database, Storage/Security, Validation,
      Comms, and Scribe roles are assigned.
- [ ] Exact destructive targets are independently resolved and disposable.
- [ ] Customer production data use, if any, has explicit legal/security/customer
      approval.
- [ ] Selected backups are not the only copies and recovery keys are available
      without exposing them in tickets/chat.
- [ ] The deployed image digest and migration compatibility are recorded.
- [ ] Duplicate web/worker/schedule authority is prevented.
- [ ] Alerts and observation dashboards are ready.
- [ ] External immutable storage and escrow are marked present with proof or
      absent—not inferred from application settings.

## Execution

From `app/`, collect the read-only snapshot:

```bash
bin/cake platform_health --json
bin/cake dr_preflight --freshness-hours 24 --json
bin/cake tenant restore_drill --tenant <test-tenant> --lookback-hours 36
```

`dr_preflight` checks platform health, backup-row freshness, and
queued/running jobs. It reports WORM configuration but does not include it in
the pass result and does not prove archive decryptability, object retention,
storage immutability, recovery infrastructure, DNS, or RTO/RPO.

Then:

1. Record platform and tenant backup IDs, types, completion times, hashes,
   retention metadata, and separately controlled recovery-key references.
2. Restore platform metadata first using the external decrypt/`pg_restore`
   process in [backup/restore](backup-restore.md#platform-metadata-recovery).
3. Restore selected tenant databases only after platform metadata is healthy.
4. Validate at least two tenant hosts plus an unknown host with correct TLS/SNI.
5. Validate login, lookup, authorization workflows, documents, queue processing,
   and safe email behavior.
6. Validate database audit continuity and, only if provisioned, the external
   immutable/WORM control.
7. Enable the unified worker/schedules in exactly one region.
8. Exercise rollback/failback decision points and capture elapsed time and
   backup age.
9. Close with a retro, owners, dates, and a fresh Go/no-go recommendation.

## Evidence

| Item | Required state |
| --- | --- |
| Scenario, exact targets, roles, approvals | Complete before destructive work |
| Preflight output | Restricted and redacted; exceptions owned |
| Image digest and migration state | Exact values recorded |
| Platform `.pgdump.enc` and key reference | Hash/age/retention recorded |
| Tenant `.json.gz.enc` and key reference | Hash/age/retention recorded |
| Restore transcript | Timestamped, redacted, target-specific |
| Tenant/TLS/host smoke | Known hosts correct; unknown host denied |
| Worker/schedule proof | Exactly one authority |
| Audit/WORM evidence | Database chain verified; external immutability separately verified if claimed |
| RTO/RPO measurement | Actual elapsed time and recovery-point age, not estimates |
| Actions | Owner, severity, due date, retest |

## Launch interpretation

- **Go:** approved targets are met with actual evidence and no unresolved P1/P2.
- **Conditional go:** non-critical gaps have time-bound, approved risk records.
- **No-go:** tenant isolation failure, unavailable/restoration-failed platform or
  tenant backup, duplicate workers, unowned critical gap, or any advertised
  recovery/WORM/escrow control without evidence.
