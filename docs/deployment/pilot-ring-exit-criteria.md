# Pilot Ring Exit Criteria and Rollback Plan

Use this runbook to decide when the managed multi-tenant platform is ready for the first real kingdom, when each pilot ring may expand, and when operators must pause or roll back a tenant. Keep the evidence package with the release record for the app revision being promoted.

[← Back to Deployment Guide](README.md)

## Scope and related runbooks

This document covers pilot readiness, ring progression, migration rollback, customer communications, and the go/no-go evidence package. It references, but does not duplicate, the disaster recovery and restore procedures:

- [Two-Tenant Staging POC](multi-tenant-poc.md)
- [Backup & Restore](backup-restore.md)
- [Managed Platform Region Failover Runbook](region-failover-runbook.md)
- [Pilot Go/No-Go Checklist Template](pilot-go-no-go-checklist.md)
- [Pilot Kingdom Migration Rehearsal Runbook](pilot-migration-runbook.md)

## Pilot readiness gates

Do not invite the first real kingdom until every gate is green and attached to the evidence package.

| Gate | Required evidence | Exit threshold |
|------|-------------------|----------------|
| Two-tenant staging POC | Latest `bin/cake tenant_poc --verify-only` output for two distinct hosts and databases | Passes on the release candidate revision |
| Platform migrations | Nightly migration drill history and rollback rehearsal notes | 4 consecutive weekly green drills; zero unresolved migration blockers |
| Tenant resolution | Host-resolution smoke tests and logs for canonical and alternate hosts | 99.9% success during a 7-day staging soak; no cross-tenant resolution defects |
| Authentication | Login smoke tests for platform admin and tenant users, including TOTP bootstrap path | 99.9% success during staging soak; no P1/P2 auth defects open |
| Backups/restores | Per-pilot-tenant backup completion and restore drill records | Backup age under 24 hours; restore drill for every pilot tenant within 7 days |
| WORM audit | Audit-write continuity check and immutable storage policy verification | No audit ingestion gaps over 7 days; retention lock verified |
| Alert handling | Alert queue, failed platform jobs, and on-call drill tickets | All P1 alerts cleared; P2 alerts have owner and due date; page acknowledged under 15 minutes in drill |
| Security and secrets | KEK escrow verification, storage RBAC review, and high-severity finding list | Escrow verified; no Critical findings; High findings remediated or explicitly accepted by platform owner |
| Customer support | Named support owner, escalation path, and communication templates | Pilot kingdom knows maintenance window, fallback plan, and support channel |

## Ring model

### Ring 0 — internal platform rehearsal

Ring 0 uses non-production or internal tenants only. Its purpose is to prove platform mechanics before any real kingdom data enters the hosted service.

Entry criteria:
- Current release candidate deployed to staging.
- Platform migrations, tenant provisioning, tenant resolution, backup, restore drill, WORM audit, and platform health commands have successful staging evidence.
- Rollback rehearsal has been completed with non-production data.

Exit criteria:
- All readiness gates are green.
- At least 4 consecutive weekly migration drills pass without manual data repair.
- Backup/restore drills are fresh for each rehearsal tenant.
- No unresolved P1/P2 alerts, failed platform jobs, or tenant-isolation defects remain.

### Ring 1 — first real kingdom pilot

Ring 1 is limited to one low-risk kingdom with a named customer contact and an agreed rollback window.

Entry criteria:
- Ring 0 exit criteria are met.
- Kingdom has approved the migration window, expected downtime, validation steps, and fallback process.
- A final source-system backup and export checksum are recorded before migration starts.

Progression criteria:
- 14 consecutive days without P1 incidents or tenant-isolation defects.
- Login and tenant resolution meet SLOs for the pilot tenant.
- Backups complete daily and the first post-migration restore drill completes within 7 days.
- Customer sign-off confirms core workflows, historical data sampling, documents, and authorization state are acceptable.

### Ring 2 — expanded pilot cohort

Ring 2 may include 3-5 kingdoms with varied size and workflow complexity. Add one kingdom at a time unless the platform owner explicitly approves batching.

Entry criteria:
- Ring 1 progression criteria are met.
- Support, alerting, migration runbook, and rollback templates have been updated from the Ring 1 retro.
- Capacity review confirms headroom for the cohort.

Exit criteria for general availability:
- 30 consecutive days across Ring 2 without P1 platform incidents, unrecovered backup failures, or WORM audit gaps.
- 95th percentile migration duration is within the published maintenance window.
- Migration failure rate is below 5%, and every failed rehearsal or live migration has a documented root cause and successful retry or rollback.
- Restore drill evidence is fresh for every pilot tenant.
- Pilot retro findings are closed or assigned to the GA backlog with an accepted risk owner.

## Operational SLOs and SLIs

Measure these per tenant and platform-wide during pilot. Treat missing telemetry as a failed measurement until corrected.

| Area | SLI | Pilot SLO | Alert / review threshold |
|------|-----|-----------|--------------------------|
| Login | Successful interactive logins divided by total login attempts | 99.9% over rolling 7 days | Page on 5-minute error spike above 2%; review any tenant-specific failure cluster |
| Tenant resolution | Requests with a resolved active tenant and no cross-tenant mismatch | 99.99% over rolling 7 days | Page on any cross-tenant mismatch; investigate 5xx/unknown-host spike above 1% |
| Migration duration | Time from migration start to tenant validation complete | p95 within approved maintenance window; p50 under 50% of window | Pause expansion when p95 exceeds window or a manual repair is needed |
| Migration failure rate | Failed migrations divided by attempted rehearsals/live migrations | Under 5% in Ring 2; 0 unresolved live failures | Page on live failure; block next tenant until RCA and retry/rollback evidence exists |
| Backups | Completed tenant backup age | Latest successful backup under 24 hours | Page when backup age exceeds 36 hours or a backup job fails twice |
| Restore drills | Age of last successful or planned non-destructive drill | Every pilot tenant within 7 days before migration and weekly during pilot | Block ring progression when any pilot tenant drill is stale |
| Alert handling | Time to acknowledge and time to resolve/mitigate P1/P2 alerts | P1 ack under 15 minutes, mitigate under 4 hours; P2 owner under 1 business day | Escalate missed ack; pause onboarding on unresolved P1 |
| WORM audit continuity | Audit events accepted and immutable storage policy intact | No ingestion gaps over 7 days; retention lock continuously enabled | Page on any write failure, retention-policy change, or gap exceeding 15 minutes |

## Data migration rehearsal

Run at least one full rehearsal per pilot tenant before the live window. Use production-like data volumes, scrubbed when needed, and the same app revision planned for go-live.

1. Confirm source system is stable and take a pre-rehearsal backup/export.
2. Provision the target tenant and verify host resolution without sending customer traffic.
3. Run platform and tenant migrations.
4. Import data and documents using the live migration procedure.
5. Validate row counts, key checksums, member search, authorization workflows, document access, login, and audit writes.
6. Capture duration, failures, manual interventions, and customer-specific defects.
7. Rehearse fallback by restoring the target tenant to the pre-import state or deleting/reprovisioning the rehearsal tenant.
8. Update the go/no-go checklist with evidence links and owner sign-off.

## Live migration rollback and fallback plan

Rollback must be simple, rehearsed, and biased toward preserving customer access. Do not continue a live migration after a rollback trigger unless the incident commander explicitly records a go decision.

### Before the window

- Freeze source writes or announce the write cutoff time.
- Take and verify a final source backup/export and document checksum.
- Confirm the target tenant backup/restore plan and latest restore drill evidence.
- Keep old DNS/ingress configuration available until customer sign-off is complete.
- Pre-stage rollback communications for start, pause, rollback, successful completion, and post-incident follow-up.

### During the window

1. Start the incident channel and name roles: incident commander, migration operator, validation owner, customer communicator, and scribe.
2. Record timestamps for source freeze, final backup, import start, validation start, DNS cutover, and customer acceptance.
3. If validation passes, cut traffic to the managed platform and monitor login, tenant resolution, audit writes, and queue jobs for at least 60 minutes.
4. If validation fails before cutover, keep customers on the source deployment, discard or quarantine the target tenant, and schedule a retry after RCA.
5. If validation fails after cutover, choose the least-risk rollback path below.

### Rollback paths

| Scenario | Preferred action | Notes |
|----------|------------------|-------|
| Failure before DNS cutover | Leave kingdom on source deployment; delete or quarantine target tenant | No customer data should be accepted by target tenant |
| Failure after cutover with no target writes | Repoint DNS/ingress to source deployment | Verify source still has the final pre-cutover state |
| Failure after limited target writes | Stop writes, export accepted changes, restore source or target according to data-owner decision | Customer communicator must explain expected data reconciliation |
| Tenant data corruption or isolation defect | Disable tenant traffic, preserve forensic copy, restore from last known-good backup | Page platform owner and security owner; do not onboard more tenants |
| Regional outage | Follow the [Managed Platform Region Failover Runbook](region-failover-runbook.md) | Keep tenant-specific communications aligned with platform incident updates |

## Rollback triggers

Any of these triggers require pausing onboarding. P1 triggers require immediate rollback or an incident commander decision to continue.

- Cross-tenant data exposure, host misrouting, or authorization boundary failure.
- Login failure rate above SLO for 15 minutes during the pilot window, or any inability for kingdom admins to sign in after cutover.
- Migration validation mismatch in critical tables, missing documents, broken authorization workflow state, or unreconciled checksum failure.
- Migration duration exceeds the approved maintenance window with no customer-approved extension.
- Backup for the tenant fails or latest restorable backup is older than the threshold before cutover.
- Restore drill is stale or failed for the tenant being migrated.
- WORM audit writes fail, immutable retention is disabled, or audit continuity has a gap exceeding 15 minutes.
- P1 alert is not acknowledged within 15 minutes or there is no staffed incident commander.
- Customer requests rollback during the agreed acceptance window.

## Customer communications outline

Use plain language and keep time estimates conservative.

1. **Pilot invitation**: explain pilot scope, benefits, known limitations, expected timeline, support channel, and opt-out path.
2. **Readiness confirmation**: confirm date/time, expected downtime, validation responsibilities, and rollback decision deadline.
3. **Window start**: announce source freeze and expected next update time.
4. **Progress updates**: send at least every 30 minutes during the window, even when there is no change.
5. **Go-live confirmation**: share new URL, validation summary, known issues, support path, and monitoring window.
6. **Rollback notice**: state that service is returning to the previous deployment, expected restoration time, whether any changes need re-entry, and when RCA will be shared.
7. **Pilot retro**: summarize what worked, incidents, follow-up actions, and whether the kingdom remains in pilot.

## Go/no-go decision

Hold the go/no-go review no earlier than 1 business day before migration and repeat it if any release, infrastructure, DNS, or migration input changes.

Required approvers:
- Platform owner
- Migration operator
- On-call/operations owner
- Security or audit owner
- Customer representative for the pilot kingdom

The decision is **no-go** unless every required checklist item is green or has an explicit written risk acceptance by the platform owner and customer representative.

## Evidence package

Store links or artifacts with the release/migration ticket. Do not paste secrets, raw KEKs, wrapped DEKs, tokens, customer-private records, or full database exports into the package.

Required evidence:
- Release candidate version, commit SHA, image digest, and deployment timestamp.
- Successful Ring 0 readiness evidence, including two-tenant POC output.
- Migration rehearsal logs, validation results, duration, failure rate, and rollback rehearsal result.
- Latest platform and tenant migration drill history.
- Latest backup, restore drill, and KEK escrow verification evidence.
- WORM audit continuity check and immutable retention proof.
- Alert drill or recent incident evidence showing acknowledgement and escalation performance.
- Customer communication approvals and go/no-go checklist.
- Post-pilot retro and action-item closure plan.
