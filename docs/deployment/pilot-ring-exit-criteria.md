---
layout: default
title: "Pilot Ring Exit Criteria and Rollback Plan"
description: "Candidate reliability, recovery, security, and rollback criteria for leaving the pilot ring."
---

# Pilot Ring Exit Criteria and Rollback Plan

This template defines proposed evidence gates for moving managed KMP from
internal rehearsal to a small tenant pilot. It does not prove that an SLO,
penetration test, restore, WORM control, or recovery drill has passed. Adopt
thresholds only after the named owners approve how they are measured.

[← Deployment and operations](README.md) |
[Pilot migration](pilot-migration-runbook.md) |
[Go/no-go template](pilot-go-no-go-checklist.md)

## Implementation facts behind the gates

- Managed Azure is currently single-region.
- Tenant application data uses separate PostgreSQL databases.
- Tenant document containers/prefixes are logical boundaries; the managed
  identity currently has storage-account-wide Blob Data Contributor.
- Platform Admin is a mutating reserved-host surface in the same web app with
  password/TOTP, account status/lockout, authorization, and host-bound sessions.
- The unified worker is one bounded job every three minutes.
- Tenant backups are `.json.gz.enc`; platform backups are `.pgdump.enc`.
- The global backup policy supports daily or weekly tenant-fleet cadence and
  defaults to daily with 30-day retention.
- The database audit chain exists, but the Azure WORM sink and immutable storage
  are not implemented by the template.
- The Shamir splitter is a non-production placeholder, and tenant/public trust
  pages are roadmap.
- Release-manifest, canary, and nightly-migration-drill commands are optional
  tools, not active Azure deployment steps.

A gate depending on an external control must be `not implemented` until
separate evidence exists.

## Evidence gates before a real tenant

| Gate | Required evidence | Proposed threshold |
| --- | --- | --- |
| Image/release | POC evidence, release tag/commit, immutable digest, production import result | Production digest exactly matches POC-validated digest |
| Tenant isolation | Two known tenant hosts plus unknown-host and cross-tenant ID/API/storage tests | No misrouting or cross-tenant access |
| Migrations | Exact managed application/platform/secret/key/tenant-fleet chain; suspended tenants included | No failed/pending tenant and no skipped required stage |
| Platform Admin | Allowed/disallowed hosts, password/TOTP, lockout/status, session host binding, privileged mutation auth/audit | No tenant-host access or session/policy bypass |
| Worker/jobs | Three-minute trigger, bounded args, one scheduling authority, queue/job error handling | No duplicate authority or unowned P1/P2 |
| Backups | Fresh platform and affected-tenant artifacts, hashes, retention metadata, separate key custody | Both scopes available and decrypt/restore plan approved |
| Restore | Actual isolated platform `pg_restore` and tenant restore evidence, plus current non-destructive plans | At least one measured end-to-end rehearsal before a real pilot |
| Audit | Database hash-chain verification | No unexplained chain discontinuity |
| External WORM | Immutable storage/retention/continuity evidence, only if required or advertised | Verified externally; application config alone never passes |
| Security/accessibility | Penetration-test/finding status, regression checks, WCAG 2.2 AA evidence for changed UI | Critical closed; other risks owned/approved |
| Legal/customer | Residency, retention, DPA, support, window, rollback, communications | Counsel and customer approvals recorded |
| Recovery | Single-region risk acceptance and DR tabletop/rehearsal evidence | No implied multi-region guarantee; actual RTO/RPO recorded |

## Ring 0 — internal rehearsal

Use only synthetic, scrubbed, or explicitly approved non-production data.

Entry:

- target digest deployed to rehearsal;
- two tenant databases/hosts plus an unknown host available;
- unified worker and alert paths configured; and
- backup/key inputs and exact migration chain ready.

Exit:

- every applicable evidence gate is green or explicitly marked as an
  unimplemented blocker;
- four **weekly release/migration rehearsals** pass if that cadence is adopted;
  do not call an optional weekly exercise a “nightly production drill”;
- one isolated platform-first recovery exercise completes with actual time and
  recovery-point age;
- Platform Admin host/auth/session/mutating-action tests pass;
- no unresolved tenant-isolation, P1, or P2 blocker remains; and
- external WORM/escrow/recovery controls are proven or excluded from the
  approved offering and customer language.

## Ring 1 — first real tenant

Limit Ring 1 to one approved low-risk tenant with a named customer contact and
rollback deadline.

Entry:

- Ring 0 exit is approved;
- an environment-specific importer and document-transfer plan passed rehearsal;
- the final source freeze/export/checksum and target restore inputs are defined;
- customer/counsel approve region, retention, support, downtime, validation, and
  rollback; and
- a release-specific Go/no-go record is signed.

Proposed progression evidence:

- 14 consecutive days without P1, tenant-isolation, or unrecovered backup
  incident;
- login and host resolution measurements meet the approved pilot targets;
- tenant-fleet backups run at the configured policy cadence;
- a post-import backup and non-destructive restore plan complete immediately;
- an actual isolated restore remains within its approved freshness window; and
- the customer signs off on counts/samples, documents, roles, and core
  workflows.

## Ring 2 — expanded pilot

Add one tenant at a time unless a written risk decision permits a batch. A
candidate cohort is three to five tenants with varied size/workflows.

Entry:

- Ring 1 progression is approved;
- the importer, support, alert, and rollback procedures incorporate the Ring 1
  retrospective; and
- PostgreSQL, Redis, storage, worker, telemetry, and on-call capacity have
  measured headroom.

Proposed GA evidence:

- 30 consecutive days without a P1, tenant-isolation incident, or unrecovered
  backup failure;
- every migration failure has root cause and successful retry/rollback evidence;
- actual migration p95 fits approved customer windows;
- platform and tenant recovery evidence is current;
- security/accessibility/legal gaps are closed or formally accepted; and
- no customer material claims WORM, multi-region recovery, escrow, per-tenant
  Azure RBAC, or trust pages without proof.

## Candidate SLIs/SLOs

These are planning values, not instrumented guarantees. The owner must name the
query/dashboard, sample population, exclusions, alert, and evidence location.
Missing telemetry is a failed measurement.

| Area | Candidate measure | Candidate pilot target / stop threshold |
| --- | --- | --- |
| Login | Successful interactive logins / attempts, per tenant | 99.9% rolling 7 days; investigate tenant cluster; page on severe spike |
| Tenant routing | Known-host successful resolution and cross-tenant mismatch | 99.99% rolling 7 days; any mismatch is immediate stop |
| Migration | Duration/failure by rehearsal and live tenant | p95 inside approved window; no unresolved live failure |
| Worker | Expected versus completed three-minute cycles; queue/job backlog | No prolonged missing cycles, duplicate claims, or unowned failure |
| Tenant backup | Latest completed backup age versus configured daily/weekly policy | Warn after one cadence; critical after three cadence windows |
| Platform backup | Latest explicit completed platform backup and usable key | Threshold set by recovery policy; stale/missing blocks cutover |
| Restore | Last actual isolated restore plus non-destructive plan freshness | Actual rehearsal before Ring 1 and after material format/process change |
| Alert handling | P1 acknowledgement/mitigation and P2 ownership | Candidate P1 ack <15m, mitigation <4h; P2 owner <1 business day |
| Database audit | Hash-chain continuity | Any unexplained break is stop/security review |
| External WORM | Sink acceptance plus storage retention/lock state | Only when deployed; any required-control gap is stop |

## Migration rehearsal requirements

Follow [the pilot migration runbook](pilot-migration-runbook.md). At minimum:

1. approve and checksum the source export and document inventory;
2. provision/resolve an isolated target without customer traffic;
3. run the exact target migration/import sequence;
4. validate counts, relationships, documents, hosts/TLS, login, authorization,
   queues, and database audit;
5. create an encrypted post-import tenant backup and recovery-key reference;
6. run a non-destructive restore plan and an approved isolated real restore;
7. time rollback and document reconciliation; and
8. obtain customer/business validation.

## Live rollback model

Before cutover, keep the source available and record its write state, final
backup/export, DNS target/TTL, and rollback deadline.

| Scenario | Action |
| --- | --- |
| Failure before traffic cutover | Keep users on source; quarantine target; preserve evidence |
| Failure after cutover, no target writes | Return traffic to verified source |
| Failure after target writes | Freeze both sides; data owner selects reconciliation or source/target recovery |
| Corruption/isolation/auth failure | Disable affected traffic, preserve forensics, page Platform/Security, pause onboarding |
| Region outage | Follow [region failover planning](region-failover-runbook.md); do not imply a standby exists |

An image rollback does not reverse schema/data. A tenant logical backup does not
restore documents, platform metadata, or source-system changes.

## Immediate pause/rollback triggers

- any cross-tenant access, host misrouting, or authorization bypass;
- customer admin unable to log in or complete an agreed critical flow;
- unexplained critical count/relationship/document mismatch;
- migration exceeds the approved window without customer extension;
- required platform/tenant backup or key is unavailable;
- an actual recovery rehearsal failed or is outside its approved freshness;
- duplicate worker/schedule authority or unacknowledged P1;
- database audit-chain failure; or
- required/advertised external WORM, escrow, recovery, or residency control
  lacks evidence.

## Decision and evidence package

Required approvers are Platform Owner, Migration/Database Lead,
Operations/Incident Lead, Security/Audit Lead, and the pilot customer
representative; include Counsel/Data Protection where commitments or residency
are involved.

Store links—not secrets or raw customer data—to:

- tag, commit, POC result, and exact image digest;
- migration/provision/import transcripts and validation summaries;
- host/TLS and tenant-isolation tests;
- Platform Admin auth/session/action evidence;
- worker/jobs/alerts;
- platform and tenant backup formats, IDs, hashes, ages, retention, and key
  custody;
- actual recovery timing and non-destructive plan;
- database audit and conditional external WORM evidence;
- security/accessibility/legal approvals;
- customer messages, Go/no-go record, rollback outcome, and retrospective.
