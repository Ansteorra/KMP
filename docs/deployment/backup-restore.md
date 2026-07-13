# Backup & Restore

Protect your KMP data with automated and on-demand backups.

[← Back to Deployment Guide](README.md)

## Ownership Model

Backups are a **platform responsibility** in the hosted SaaS model. One system
— the managed backup system — creates, schedules, retains, and restores
backups. The delineation:

| Concern | Owner | Where |
|---------|-------|-------|
| Scheduling (cadence) | Platform | Global backup policy + `tenant-backup-fleet` platform schedule |
| Storage destination | Platform | Configured backup storage (`BackupStorageFactory`) |
| Retention | Platform | Global backup policy → `retention_until` + `backup-retention` schedule |
| Restore (same-tenant and cross-tenant) | Platform admins only | Platform Admin guarded restore flow |
| Legacy `.kmpbackup` import | Platform admins only | Platform Admin **Import Legacy Backup** |
| On-demand backup request | Tenant admins (`Can Manage Backups`) and platform admins | Tenant `/backups` page; Platform Admin tenant backups page |
| Download archive + one-time recovery key | Tenant admins (`Can Manage Backups`) and platform admins | Tenant `/backups` page; Platform Admin |
| Backup status visibility | Tenant admins (read-only) | Tenant `/backups` page |

The **global backup policy** (cadence `daily`/`weekly` + retention days) is
edited on the Platform Admin **Backups** page. It drives the fleet schedule's
cron, the default retention for every new backup, and the fleet-health
staleness thresholds (warning after one cadence window, critical after three).

Tenants no longer schedule their own backups, manage encryption keys, or run
restores. The legacy `Backup.schedule` / `Backup.retentionDays` app settings
and the `backup_check` command are removed.

## Creating Backups

### Scheduled fleet backups

The `tenant-backup-fleet` platform schedule runs `tenant_backups_enqueue`
daily (or weekly, per policy). For each active tenant whose latest completed
backup is older than the policy cadence, it enqueues an audited managed
`tenant_backup` job; the `platform-admin-job-runner` schedule executes it.
Runs are idempotent per tenant per day, and a tenant with another lifecycle
operation in flight is skipped and retried on the next run.

```bash
# Manual sweep (same thing the schedule runs)
cd app
bin/cake tenant_backups_enqueue
```

### On-demand tenant backups

Tenant admins with the **Can Manage Backups** permission can request a backup
from the tenant **Backups** page (`/backups`). The request enqueues the same
managed job (rate-limited to one per hour) — there is no separate tenant
backup format or key. The page also shows read-only backup status: latest
completed backup, effective cadence, and retention.

### Managed Platform Admin

Platform operators use `/platform-admin/backups` for platform database backups
and each tenant's **Backups** page under `/platform-admin/tenants/<slug>/backups`
for tenant-scoped platform operations. These actions enqueue audited
`platform_database_backup`, `tenant_backup`, and `tenant_restore` jobs instead
of running long database work in the web request.

Managed tenant and platform-metadata backups intentionally use different
formats:

- Tenant backups use KMP's versioned JSON logical engine. Each archive contains
  a schema manifest, migration fingerprint, and application table rows while
  excluding transient queue, session, and backup rows. The JSON is
  gzip-compressed and stored as an authenticated `.json.gz.enc` stream.
- Platform metadata backups remain PostgreSQL custom-format dumps stored as
  authenticated `.pgdump.enc` streams for external disaster recovery.

Both formats use a per-backup data key wrapped by the platform or tenant KEK.
Object size and SHA-256 are verified on download and before restore. Legacy
managed tenant `pg_dump` archives remain restorable, but all new tenant backups
use JSON. Secret values, object URIs, wrapped keys, and raw job errors are not
rendered in Platform Admin.

Before enabling backups, reconcile encryption keys without printing their
values:

```bash
cd app
bin/cake platform backup-keys ensure
```

The command creates `platform.backup.kek` and missing `tenant.<slug>.kek`
entries in the configured writable secret store. It is idempotent and skips
archived tenants.

### Portable per-backup recovery keys

Platform Admin provides a separate **Download recovery key** action beside each
current-format tenant or platform archive. This is an intentional two-file
workflow rather than a combined download:

- The `.kmpbackup-key.json` file contains the raw data-encryption key for one
  backup, encoded for portability. It does **not** contain
  `tenant.<slug>.kek`, `platform.backup.kek`, or any other reusable KEK.
- The package is bound to the backup ID, tenant ID and slug when applicable,
  archive type, encryption algorithm, byte size, and SHA-256. KMP rejects a
  different archive or tenant even if both files are otherwise readable.
- Export requires typed confirmation (`DOWNLOAD KEY <tenant>` or
  `DOWNLOAD KEY platform`), an operator reason, current TOTP, and a successful
  immutable audit event. The response is marked `no-store`.
- The original KEK must still be available when the recovery key is exported.
  Export and escrow the pair while the platform is healthy; it cannot be
  reconstructed after both the KEK and wrapped backup key are lost.

Treat the recovery-key file as high-sensitivity secret material. Download the
archive and key separately, place them in separate access-controlled recovery
locations, verify custody, and remove browser/download-folder copies. Never put
the key file in source control, tickets, chat, ordinary document storage, or the
same unprotected location as its archive.

#### Tenant downloads: two-file model with one-time keys

Tenant admins download the encrypted `.json.gz.enc` archive and its
`.kmpbackup-key.json` recovery key as two separate files from `/backups`.
The recovery key can be exported **once per backup** from the tenant surface;
a repeat attempt is refused and directs the tenant to a platform
administrator (Platform Admin exports remain step-up-guarded and audited, and
record who exported first). Store the two files in separate protected
locations.

#### Restores are platform-only

Tenants cannot run restores. All tenant restores — same-tenant, cross-tenant,
and legacy imports — go through Platform Admin:

1. **Same-tenant restore**: suspend the tenant, open its Backups page, and
   queue the guarded destructive restore (`RESTORE <slug>` + reason + TOTP).
2. **Cross-tenant restore**: in the same restore modal, pick a different
   *suspended* target tenant. The typed confirmation becomes
   `RESTORE <target-slug>`. The backup stays bound to its source tenant; the
   service re-validates source/target, archive ownership, and target
   suspension before executing (`tenant restore --mode cross-tenant` under
   the hood).
3. **Legacy `.kmpbackup` import**: use **Import Legacy Backup** on the
   tenant's Backups page (`IMPORT <slug>` + reason + TOTP). Upload the
   passphrase-encrypted archive (from the retired self-service system or an
   upstream ansteorra/KMP install) with its original encryption key. KMP
   decrypts it, re-encrypts it with the tenant's managed envelope keys, and
   records it as a normal managed backup — restorable through the standard
   guarded flow, including cross-tenant. Import is non-destructive and does
   not require the tenant to be suspended; the subsequent restore does.
   Payload-model differences between upstream releases and this platform are
   upgraded automatically at restore time.

#### Decrypt a platform archive for external disaster recovery

Platform database restoration remains outside the serving web process. On a
secured recovery host with the encrypted archive and its separately escrowed
key, create a new plaintext PostgreSQL custom-format dump:

```bash
cd app
bin/cake platform backup decrypt \
  --archive /secure/input/platform-<backup-id>.pgdump.enc \
  --recovery-key /separate/escrow/platform-<backup-id>.kmpbackup-key.json \
  --output /secure/work/platform-<backup-id>.pgdump \
  --confirm WRITE-PLAINTEXT-PLATFORM-BACKUP
```

The command verifies the package/archive pairing, refuses to overwrite an
existing output, and writes the plaintext dump with owner-only permissions.
Inspect it with `pg_restore --list`, restore it through the approved external
platform-database runbook, then dispose of the plaintext copy according to the
incident's evidence-retention requirements.

### Using the VPC Backup Script

If deployed with `deploy/vpc/`, use the included backup script:

```bash
cd /opt/kmp

# Local backup (saved to ./backups/)
./backup.sh

# Backup and upload to S3
./backup.sh --upload s3

# Backup and upload to Azure Blob Storage
./backup.sh --upload azure
```

## Automated Backup Schedule

### Cron (VPC / Self-Hosted)

```bash
# Daily at 3 AM
0 3 * * * /opt/kmp/backup.sh >> /var/log/kmp-backup.log 2>&1

# Daily at 3 AM with S3 upload
0 3 * * * /opt/kmp/backup.sh --upload s3 >> /var/log/kmp-backup.log 2>&1
```

### Fly.io

Use Fly.io's built-in Postgres snapshots, which are taken automatically. You can also create on-demand snapshots:

```bash
fly postgres backup create --app kmp-db
fly postgres backup list --app kmp-db
```

### Railway

Railway provides automatic MySQL backups. Check the Railway dashboard under your MySQL service for backup management.

## Cloud Storage for Backups

### Amazon S3

Set these environment variables before running backup with `--upload s3`:

```bash
export AWS_ACCESS_KEY_ID=your-key
export AWS_SECRET_ACCESS_KEY=your-secret
export AWS_REGION=us-east-1
export AWS_BUCKET=kmp-backups
```

### Azure Blob Storage

Set these environment variables before running backup with `--upload azure`:

```bash
export AZURE_STORAGE_CONNECTION_STRING="DefaultEndpointsProtocol=https;AccountName=..."
export AZURE_BACKUP_CONTAINER=kmp-backups
```

## Restoring from Backup

### Using the Management Tool

```bash
# List available backups
kmp backup --list

# Restore a specific backup
kmp restore 2026-02-19-030000
```

### Using the VPC Restore Script

```bash
cd /opt/kmp
./restore.sh backups/2026-02-19-030000.sql.gz
```

### Manual Restore

```bash
# Decompress and import
gunzip -c backup.sql.gz | docker compose exec -T db mysql -u root -p"$MYSQL_ROOT_PASSWORD" kmp
```

> **Warning**: Restoring a backup will overwrite all current data. Always confirm you're restoring to the correct environment.

### Managed Platform restore/download guardrails

Platform Admin destructive and sensitive backup actions are guarded before they run:

- Tenant actions accept completed, retained JSON records with scoped
  `.json.gz.enc` objects and compatible legacy `pg_dump` records with scoped
  `.pgdump.enc` or `.pgdump.enc.json` objects.
- Platform metadata downloads accept only completed, retained `pg_dump`
  records with scoped `.pgdump.enc` objects.
- Download requires typed confirmation (`DOWNLOAD <tenant>` or
  `DOWNLOAD platform`), a reason, TOTP step-up, and an audit event before the
  encrypted archive is streamed.
- Recovery-key export is a separate guarded action requiring
  `DOWNLOAD KEY <tenant>` or `DOWNLOAD KEY platform`, a reason, TOTP step-up,
  and an audit event before the no-store response is returned.
- Manual archive deletion requires `DELETE BACKUP <tenant>` or
  `DELETE BACKUP platform`, an operator reason, and TOTP step-up. The encrypted
  object is removed, while non-sensitive operational metadata is retained with
  `status = deleted` and the action is written to the platform audit chain.
- Tenant restore requires typed confirmation (`RESTORE <tenant>`), a reason,
  TOTP step-up, and an audited restore job.
- Tenant restore requires the tenant to be suspended through the guarded
  lifecycle control first. Queueing and execution recheck that state under the
  same tenant operation lock; reactivation waits until the destructive restore
  finishes. Reactivate it only after restore verification.
- Platform database restore is intentionally not executed by the serving web
  process. Use the external disaster-recovery runbook to replace the platform
  metadata database.
- The portal streams only the encrypted `.json.gz.enc` or `.pgdump.enc`
  archive appropriate to the record. A recovery-key download contains only that
  archive's data key; the portal never exposes a decrypted payload or reusable
  tenant/platform KEK.

If a restore must be executed outside the portal, use the corresponding
break-glass CLI/runbook path and record the same reason, actor, tenant/platform
scope, and backup ID in the platform audit log.

## What's Included in a Backup

Managed tenant JSON archives include:

- Versioned format metadata and creation time
- Application table schema and rows
- Core and plugin migration fingerprints and migration-log rows
- Empty schema definitions for transient queue, session, and backup tables

Rows from `queued_jobs`, `queue_processes`, `sessions`, and `backups` are
intentionally excluded so transient runtime state and session tokens do not
cross environments. Managed platform metadata archives remain full PostgreSQL
custom-format dumps.

The current JSON engine materializes the logical payload and compressed archive
in worker memory during export and restore. Size worker memory for the largest
tenant, monitor peak memory and job duration during onboarding, and require a
successful tenant-scoped restore drill before approving large tenants. Do not
assume the JSON path is bounded-memory.

**Not included**: uploaded documents (if using local storage). For complete disaster recovery, also back up:
- The `images/uploaded/` directory (local storage), or verify cloud storage replication (Azure/S3)
- Your `.env` configuration file

## Disaster Recovery

Managed multi-tenant regional failover is covered in the [Managed Platform Region Failover Runbook](region-failover-runbook.md). The steps below are the archived self-hosted baseline.

1. Provision a new server and install Docker
2. Copy the `deploy/vpc/` templates and your `.env` file
3. Start the stack: `docker compose up -d`
4. Restore the database: `./restore.sh backups/latest.sql.gz`
5. Restore uploaded documents from cloud storage or file backup
6. Verify the application: `curl -s https://your-domain.com/health`

## Backup Retention

Managed-platform retention runs from the `backup-retention` platform schedule
and can be invoked safely on demand:

```bash
cd app
bin/cake platform backups prune --limit 500
```

Expired objects are deleted from configured storage. Their non-sensitive
metadata remains with `status = expired`; failed object deletions retain the URI
for a later retry and make the command fail. Retention uses the same archive
lock and active-restore rejection as manual deletion, records a fail-closed
expiration intent before touching storage, and routes current `backup://`,
historical `local://`, and legacy `.kmpbackup` records through their owning
storage adapters. Interrupted finalization remains `expiring` and is retried by
the next retention run.

Operators can remove a superseded archive before retention expiry from the
Platform Admin backup screen. Download, recovery-key, restore, and delete
approvals open in a shared modal so backup rows remain compact without weakening
the typed confirmation, reason, TOTP, or audit requirements.

Deletion records a fail-closed audit intent before touching storage and is
rejected while a queued or running restore references the archive. Successful
deletion retains the backup checksum, size, retention history, failure
diagnostics, and audit records while clearing only the stored object reference.

Legacy `kmpbackup_json` records remain available for guarded download and
deletion. They do not expose a portable recovery-key export or a direct
restore action; download the `.kmpbackup` file and re-import it through the
Platform Admin **Import Legacy Backup** flow (with the original application
backup key) to convert it into a restorable managed backup.

Rows in each tenant's legacy `backups` table (the retired self-service
system) remain visible read-only on the tenant `/backups` page for download
with the old passphrase (`Backup.encryptionKey` is retained for this).
The old reaper is gone, so plan a one-time cleanup and a follow-up migration
that drops the table once the fleet has converged on managed backups.

For archived self-hosted installations, manage retention manually or via cron:

```bash
# Keep only the last 30 days of local backups
find /opt/kmp/backups/ -name "*.sql.gz" -mtime +30 -delete
```

For cloud storage, configure lifecycle rules in your S3 bucket or Azure storage account to automatically expire old backups. Managed platform default retention windows and evidence requirements are defined in the [Managed Platform Legal and Security Governance Template](legal-governance.md#retention-policy-defaults).

## KEK Escrow Verification Runbook

Platform mode requires escrow for every per-tenant KEK and for the platform secrets database-driver KEK. The go-live process is a Shamir 3-of-5 split among trusted platform-admin officers, with each share placed in a tamper-evident sealed envelope. KMP records only ceremony metadata; raw KEKs, Shamir share plaintext, recovery codes, and envelope contents must never be stored in the platform database, ticketing systems, chat, or documentation.

### Initial sealed-envelope ceremony

1. Confirm the KEK name and version for each tenant and for the global `secrets-db-driver-kek`.
2. Use a vetted Shamir implementation approved for production operations. The current in-repo splitter is a deterministic non-production placeholder and refuses production use.
3. Split each KEK with threshold `3` and share count `5`.
4. Assign each share to a trusted platform-admin officer. Seal each share in a labeled envelope; store only hashes/labels and custody metadata in platform records.
5. Record ceremony metadata in the platform database after `bin/cake platform_migrate` has created escrow tables.

### Quarterly verification ceremony

Every quarter, verify that all envelopes are present, untampered, and still assigned to available custodians. Do not open envelopes during routine quarterly checks unless an approved drill requires it. Record the result:

```bash
bin/cake platform escrow record-verification \
  --key-name tenant.example.kek \
  --key-version 2026-q2 \
  --threshold 3 \
  --share-count 5 \
  --status verified \
  --metadata '{"envelopes_checked":5,"tamper_evidence":"intact"}' \
  --notes 'Quarterly sealed-envelope verification complete.'
```

Use `--tenant-id` for tenant KEKs and omit it for global platform keys such as `secrets-db-driver-kek`. The command redacts sensitive metadata keys before inserting `platform.escrow_verifications`, but operators must still avoid placing plaintext KEKs or share contents in command arguments.

### Phase 8 reassembly drill

Phase 8 must include a real recovery drill: select test/non-production escrow material, gather any 3 of 5 custodians, open envelopes under dual control, reassemble with the approved Shamir implementation, verify the recovered KEK fingerprint, rotate/reseal any exposed shares, and record the drill outcome in `escrow_verifications` with `status=verified` or `status=failed`. Production go-live is blocked until this placeholder Shamir implementation is replaced by a vetted implementation and the drill evidence is recorded.

## Scheduled Tenant Restore Drills

Use restore drills to prove that recent tenant backups are restorable without overwriting tenant data by default.

```bash
# Non-destructive default: select the most recent completed tenant backup and verify a restore plan.
cd app
bin/cake tenant restore_drill --lookback-hours 36

# Scope to one tenant.
bin/cake tenant restore_drill --tenant example --lookback-hours 36
```

The command records a `tenant_restore_drill` row in `platform_jobs` with the selected tenant, backup ID, dry-run flag, and final status. Failed selection, validation, or dry-run verification records a failed job with a redacted `last_error` so stale-job/failed-job alerting can page without exposing secrets.

Destructive drill execution is intentionally opt-in and must never be used from routine schedules:

```bash
bin/cake tenant restore_drill \
  --tenant example \
  --execute-restore \
  --confirm-destructive-drill RESTORE-DRILL-DESTRUCTIVE
```

### Scheduling guidance

- Weekly: schedule `bin/cake tenant restore_drill --lookback-hours 36` after nightly tenant backups complete.
- Monthly/quarterly: run a tenant-scoped drill for high-value or large tenants, starting with the default non-destructive mode.
- Treat any failed `tenant_restore_drill` platform job as a backup-readiness incident.

### Acceptance criteria

- A recent `tenant_backups.status = completed` backup is selected and recorded in `platform_jobs.parameters`.
- Default runs finish with status `planned`, verify decryption, JSON structure,
  and target connectivity, and do not mutate tenant schema or data.
- No completed backup in the lookback window creates a failed drill job with a clear, redacted error.
- Command output and stored errors must not include passwords, tokens, wrapped DEKs, or raw secret material.
