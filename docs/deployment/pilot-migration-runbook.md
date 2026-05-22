# Pilot Kingdom Migration Rehearsal Runbook

Use this runbook for the production-focused rehearsal that must pass before a real kingdom enters Ring 1 or Ring 2. It is intentionally split into a non-production rehearsal path and a production cutover path. Do not run a live production cutover from this document unless the go/no-go checklist has explicit written approvals.

[← Back to Deployment Guide](README.md)

Related evidence:

- [Pilot Ring Exit Criteria and Rollback Plan](pilot-ring-exit-criteria.md)
- [Pilot Go/No-Go Checklist Template](pilot-go-no-go-checklist.md)
- [Two-Tenant Staging POC](multi-tenant-poc.md)
- [Backup & Restore](backup-restore.md)
- [Platform Admin v2 and Tenant Trust Surface](platform-admin-v2-trust-surface.md)

## Scope and safety principles

- Rehearsal validates production-like mechanics with scrubbed or otherwise approved source exports.
- Live production cutover is a separate change event with a freeze window, named approvers, and a customer rollback deadline.
- No command in this runbook should include passwords, tokens, raw exports, KEKs, wrapped keys, or customer-private records in arguments, logs, screenshots, or evidence notes.
- Prefer tenant-scoped commands. Avoid `--all` for pilot onboarding unless the platform owner records a specific exception.
- Keep source systems available until the pilot representative signs off or the rollback deadline passes.

## Roles

| Role | Required for rehearsal | Required for cutover | Responsibilities |
|------|------------------------|----------------------|------------------|
| Platform owner | Yes | Yes | Owns go/no-go, risk acceptance, ring progression. |
| Migration operator | Yes | Yes | Runs commands, captures timestamps, stops on validation failure. |
| Validation owner | Yes | Yes | Verifies row counts, workflows, login, documents, audit writes. |
| On-call/operations owner | Yes | Yes | Monitors alerts, backup jobs, queue health, and rollback readiness. |
| Security/audit owner | Yes | Yes | Confirms WORM audit continuity, evidence redaction, and secret handling. |
| Customer communicator | Recommended | Yes | Sends approved messages and records customer acknowledgement. |
| Customer representative | Recommended | Yes | Confirms business validation and accepts or rejects cutover. |

## Phase A: Intake and preflight

1. Create a migration ticket and copy the [Pilot Go/No-Go Checklist Template](pilot-go-no-go-checklist.md) into it.
2. Record the target ring, release version, commit SHA, image digest, migration window, rollback deadline, and support channel.
3. Confirm Ring 0 or prior-ring entry criteria from [Pilot Ring Exit Criteria and Rollback Plan](pilot-ring-exit-criteria.md) are green.
4. Confirm the target release passed:
   - two-tenant POC or host-resolution smoke;
   - tenant migration canary;
   - nightly migration drill;
   - backup freshness and restore drill checks;
   - WORM audit continuity checks.
5. Receive the source export through an approved secure channel. Store only an evidence link and checksums in the ticket.
6. Verify export metadata without opening or copying sensitive records into logs:
   - source application version;
   - export timestamp and timezone;
   - checksum or signed manifest;
   - expected row counts for critical tables;
   - document/object inventory summary.
7. Confirm data handling approval for rehearsal data. Use scrubbed data unless production data has explicit approval and storage controls.

## Phase B: Rehearsal execution

Run rehearsal against staging or an isolated rehearsal environment, not the production tenant endpoint. Example placeholders below must be replaced with environment-specific values kept in the secret store or deployment system.

```bash
cd app

# 1. Confirm release/schema compatibility for the selected rehearsal tenant.
bin/cake platform release_check \
  --manifest config/release_manifest.json \
  --tenant <tenant-slug>

# 2. Provision or update the tenant metadata. Use --skip-create-database when
# infrastructure pre-created the database and role.
bin/cake tenant provision <tenant-slug> \
  --display-name '<kingdom display name>' \
  --host <tenant-hostname> \
  --db-name <tenant-database-name> \
  --db-role <tenant-database-role> \
  --blob-container <tenant-blob-container> \
  --skip-create-database \
  --skip-migrations \
  --status provisioning

# 3. Verify current migration state without changing schema.
bin/cake tenant migrate --tenant <tenant-slug> --status

# 4. Create a pre-migration marker/backup and stop before applying migrations.
bin/cake tenant migrate \
  --tenant <tenant-slug> \
  --marker-only \
  --manifest config/release_manifest.json

# 5. Dry-run migrations for SQL visibility without applying changes.
bin/cake tenant migrate \
  --tenant <tenant-slug> \
  --dry-run \
  --manifest config/release_manifest.json

# 6. Apply tenant migrations only after marker-only and dry-run evidence is green.
bin/cake tenant migrate \
  --tenant <tenant-slug> \
  --manifest config/release_manifest.json

# 7. Create an encrypted rehearsal backup for rollback evidence.
bin/cake tenant backup --tenant <tenant-slug> --retention-days 30

# 8. Plan a non-destructive restore drill from a recent backup.
bin/cake tenant restore_drill --tenant <tenant-slug> --lookback-hours 36
```

Import of kingdom data and documents must use the approved migration importer for the source format. If the importer is outside this repository, record the exact version, arguments after redaction, duration, and checksum report in the evidence package. Do not paste raw import payloads into KMP logs or tickets.

## Phase C: Rehearsal validation

Validation must be tenant-scoped and evidence-safe.

| Check | Minimum evidence | Stop condition |
|-------|------------------|----------------|
| Critical row counts | Source/export counts compared with target counts for members, branches, roles, permissions, events, and plugin tables used by the kingdom. | Any unexplained mismatch in critical tables. |
| Checksums or samples | Hash/checksum report or approved business sampling notes. | Missing required history or broken relationships. |
| Documents/storage | Sample document inventory and open/read verification. | Missing customer-required objects. |
| Tenant resolution | Host resolves to only the pilot tenant and no alternate host resolves cross-tenant. | Any cross-tenant or unknown-host defect. |
| Login and MFA/TOTP | Customer admin and platform admin smoke results. | Customer admin cannot sign in. |
| Authorization workflows | Role, warrant, award, activity, waiver, and officer flows relevant to the kingdom. | Privilege boundary failure or core workflow break. |
| WORM audit | Audit write smoke plus immutable storage policy evidence. | Audit write failure or retention-policy gap. |
| Backups/restores | Backup command and non-destructive restore drill evidence. | Backup failure, stale backup, or failed restore plan. |
| Platform jobs/alerts | No failed tenant migration, backup, queue, or alert jobs without owner. | Unowned P1/P2 or failed platform job. |
| Trust surface readiness | Tenant-visible evidence summaries are redacted and align with the trust dashboard roadmap. | Evidence exposes secrets, object URIs, raw job errors, or other-tenant data. |

## Phase D: Rehearsal rollback and cleanup

Choose the least destructive cleanup path for the rehearsal environment:

1. If the target was disposable, delete or quarantine the rehearsal tenant after evidence is captured.
2. If it will be reused for the live cutover, restore it to the pre-import state or repeat provisioning from a clean database.
3. Confirm no rehearsal host receives customer traffic.
4. Record rollback duration, manual repairs, and any data reconciliation defects.
5. Update the go/no-go checklist with failures, owners, and retest dates.

## Phase E: Production cutover gate

A production cutover is no-go unless every item below is approved in the migration ticket:

- Ring entry criteria and operational SLOs from [Pilot Ring Exit Criteria and Rollback Plan](pilot-ring-exit-criteria.md) are met.
- Rehearsal evidence is green on the same app revision or on a documented successor revision with repeated affected checks.
- Source export checksum and final backup procedure are approved.
- Tenant backup, PITR/pre-migration marker, restore drill, and rollback path are fresh.
- Customer communication templates are approved for start, progress, success, rollback, and retro messages.
- Platform owner, migration operator, on-call owner, security/audit owner, and customer representative recorded go decisions.
- The customer representative accepted expected downtime, validation responsibilities, and rollback deadline.

## Production cutover command outline

These commands are examples only. Run them during the approved window after the source write freeze and final export verification.

```bash
cd app

# Freeze source writes through the source-system procedure, then record final export checksum.

bin/cake tenant migrate --tenant <tenant-slug> --status
bin/cake tenant migrate \
  --tenant <tenant-slug> \
  --marker-only \
  --manifest config/release_manifest.json

# Import final source export with the approved importer. Keep secrets and payloads out of logs.
# <approved-import-command> --tenant <tenant-slug> --source-manifest <secure-reference>

bin/cake tenant migrate \
  --tenant <tenant-slug> \
  --manifest config/release_manifest.json
bin/cake tenant backup --tenant <tenant-slug> --retention-days 30
bin/cake tenant restore_drill --tenant <tenant-slug> --lookback-hours 36
```

Cut traffic only after validation passes. Monitor login, tenant resolution, WORM audit writes, queues, backups, and platform jobs for at least 60 minutes before closing the window.

## Rollback and fallback

Follow the rollback triggers in [Pilot Ring Exit Criteria and Rollback Plan](pilot-ring-exit-criteria.md#rollback-triggers). Summary:

- Before DNS or ingress cutover, leave customers on the source deployment and quarantine the target tenant.
- After cutover with no target writes, repoint traffic to the source deployment and preserve target evidence for RCA.
- After target writes, stop writes and let the data owner choose reconciliation, source restore, or target restore based on customer impact.
- For isolation, authorization, audit, or data-corruption defects, disable tenant traffic, preserve forensic copies, page the platform and security owners, and pause all further onboarding.

## Evidence package

Attach links or redacted artifacts to the migration ticket:

- release version, commit SHA, image digest, and deployment timestamp;
- source export manifest, checksum, and intake approval;
- tenant provision command transcript with secrets redacted;
- release compatibility, migration status, marker-only, dry-run, migrate, backup, and restore-drill outputs;
- import duration and validation report;
- row count and checksum summaries;
- document sampling evidence;
- tenant resolution and login smoke results;
- WORM audit continuity and immutable retention proof;
- platform admin/trust-surface evidence summaries with redactions verified;
- go/no-go approvals and customer communications;
- rollback rehearsal result, retro notes, and follow-up defects.

## Acceptance criteria

- The rehearsal follows the Ring 0/Ring 1/Ring 2 gates in [Pilot Ring Exit Criteria and Rollback Plan](pilot-ring-exit-criteria.md) and the migration ticket has a completed [Pilot Go/No-Go Checklist Template](pilot-go-no-go-checklist.md).
- The source export is intake-approved, checksum-verified, and referenced without raw customer data in tickets or logs.
- Tenant provisioning, release compatibility, marker-only backup/PITR marker, dry-run migration, applied migration, backup, and non-destructive restore drill have green evidence.
- Validation covers counts, checksums/sampling, documents, login, authorization workflows, tenant resolution, WORM audit, queues/jobs, and customer acceptance.
- Rollback/fallback was rehearsed and has a timed decision deadline for the live window.
- Evidence destined for the trust surface is tenant-scoped and redacted according to [Platform Admin v2 and Tenant Trust Surface](platform-admin-v2-trust-surface.md).
- Production cutover remains blocked until named approvers record go decisions and the customer representative accepts the window and rollback process.
