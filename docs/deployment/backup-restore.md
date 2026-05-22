# Backup & Restore

Protect your KMP data with automated and on-demand backups.

[← Back to Deployment Guide](README.md)

## Quick Reference

| Command | Description |
|---------|-------------|
| `kmp backup` | Create a backup now |
| `kmp restore <id>` | Restore from a specific backup |
| `kmp backup --list` | List available backups |

## Creating Backups

### Using the Management Tool

```bash
kmp backup
```

This creates a compressed database dump and stores it locally. The output includes a backup ID for use with `kmp restore`.

### Managed Platform Admin

Platform operators use `/platform-admin/backups` for platform database backups
and each tenant's **Backups** page under `/platform-admin/tenants/<slug>/backups`
for tenant-scoped platform operations. These actions use the same encrypted
JSON `.kmpbackup` archive model as the tenant Backups UI and enqueue audited
`platform_jobs` instead of running long backup work in the web request.

Backup requests capture the target scope, archive format, retention days, and an
idempotency key. Secret values, object URIs, wrapped keys, and raw job errors are
not rendered in Platform Admin.

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

Platform Admin destructive and sensitive backup actions are guarded before they
run:

- Only completed `kmpbackup_json` records with safe `.kmpbackup` object names
  can be restored or downloaded.
- Download requires typed confirmation (`DOWNLOAD <tenant>` or
  `DOWNLOAD platform`), a reason, TOTP step-up, and an audit event before the
  encrypted archive is streamed.
- Restore requires typed confirmation (`RESTORE <tenant>` or
  `RESTORE platform`), a reason, TOTP step-up, and an audited restore job.
- Tenant restore requires the tenant to be suspended first.
- The portal streams encrypted `.kmpbackup` archives only; it never exposes
  decrypted database dumps.

If a restore must be executed outside the portal, use the corresponding
break-glass CLI/runbook path and record the same reason, actor, tenant/platform
scope, and backup ID in the platform audit log.

## What's Included in a Backup

- Full database dump (all tables, data, and schema)
- Backup metadata (timestamp, KMP version, database version)

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

Manage backup retention manually or via cron:

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
- Default runs finish with status `planned` and do not execute `pg_restore`.
- No completed backup in the lookback window creates a failed drill job with a clear, redacted error.
- Command output and stored errors must not include passwords, tokens, wrapped DEKs, or raw secret material.
