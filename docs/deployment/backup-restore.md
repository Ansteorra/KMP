---
layout: default
title: "Managed Backup and Restore"
description: "Tenant and platform backup formats, encryption, retention, restore controls, and recovery drills."
---

# Managed Backup and Restore

Backups are a platform responsibility in the hosted multi-tenant model. The
managed system schedules, encrypts, stores, retains, downloads, and restores
tenant and platform data through audited workflows.

[← Back to Deployment and Operations](README.md)

## Ownership and policy

| Concern | Current owner/path |
| --- | --- |
| Tenant backup cadence | Global Platform Admin policy plus `tenant-backup-fleet` schedule |
| Retention for new backups | Same global policy; 1–365 days |
| Backup execution | Unified `platform worker run` job |
| Tenant on-demand request | Tenant user with **Can Manage Backups** or Platform Admin |
| Tenant/platform backup request | Platform Admin queues a `platform_jobs` row |
| Restore | Platform Admin only; tenant target must be suspended |
| Archive/recovery-key download | Guarded tenant or Platform Admin action |
| Expired object deletion | `backup-retention` schedule / `platform backups prune` |

The implemented policy supports `daily` or `weekly` cadence and defaults to
daily with 30-day retention. Fleet health warns after one missed cadence window
and becomes critical after three. Governance templates may specify stricter
targets, but weekly/monthly retention tiers are not automatically created by the
current policy service.

Tenant users cannot schedule their own recurring jobs, manage reusable KEKs, or
execute restores. On-demand tenant requests use the same managed format and are
limited to one request per hour.

## Backup formats

Managed tenant and platform backups intentionally differ:

| Scope | Record type/job | Payload | Stored object |
| --- | --- | --- | --- |
| Tenant | `backup_type=json` / `tenant_backup` | Versioned logical JSON, migration fingerprints, table schema/rows; transient queue/session/backup rows excluded | `tenants/<slug>/<uuid>.json.gz.enc` |
| Platform metadata | `backup_type=pg_dump` / `platform_database_backup` | PostgreSQL custom-format dump | `platform/<uuid>.pgdump.enc` |
| Imported historical tenant archive | Converted to `backup_type=json` | Decrypted legacy logical archive re-encrypted through the managed pipeline | Current tenant format |

Each new backup uses a random data-encryption key. That key is wrapped by
`tenant.<slug>.kek` or `platform.backup.kek` from the configured secret store.
The platform database records object scope, size, SHA-256, encryption metadata,
status, and `retention_until`. Retrieval and restore recheck scope, size, and
hash.

Legacy tenant `pg_dump` records remain readable by compatibility paths, but new
tenant backups are JSON. The JSON engine materializes logical/compressed data in
worker memory; size workers and prove restore behavior for large tenants.

## Key readiness

A writable platform secret store can create missing backup KEKs:

    cd app
    bin/cake platform backup-keys ensure

The command is idempotent and skips archived tenants. Managed deployment uses:

    bin/cake platform backup-keys ensure --allow-read-only

That mode lets the ordered migration job inspect an environment during the
legacy secret transition. It does not make a missing key safe: any tenant
migration or backup that requires an absent password/KEK still fails closed.

Do not confuse `BACKUP_ENCRYPTION_KEY` with managed KEKs. It decrypts the
bundled POC seed archive only.

## Creating backups

### Scheduled tenant fleet

The `tenant-backup-fleet` platform schedule runs
`tenant_backups_enqueue` at the configured cadence. It queues each due active
tenant unless another lifecycle operation is in flight. Queueing is idempotent
per tenant/day.

An operator can run the same enqueue sweep from an approved worker context:

    cd app
    bin/cake tenant_backups_enqueue

The three-minute unified worker executes the queued `tenant_backup` jobs.
Starting a parked Azure `sched-*` compatibility job is not the current path.

### Direct CLI execution

These commands execute backup work synchronously and are best suited to an
approved worker/recovery shell, not an HTTP request:

    cd app
    bin/cake tenant backup --tenant example --retention-days 30
    bin/cake platform backup --retention-days 30

Platform Admin normally queues equivalent jobs from:

- `/platform-admin/backups` for platform metadata; and
- `/platform-admin/tenants/<slug>/backups` for tenant operations.

Tenant users with permission request an on-demand backup from `/backups`.

## Archive and recovery-key downloads

Current-format tenant and platform backups support a separate portable
`.kmpbackup-key.json` file containing the data key for that one backup. It does
not contain the reusable tenant/platform KEK.

The recovery package is bound to the backup ID, scope/tenant, archive type,
algorithm, size, and SHA-256. A mismatched archive or target is rejected.

Guardrails:

- archive download requires typed confirmation, a reason, current TOTP, and a
  hash-chained platform audit event;
- recovery-key export is a separate confirmation/reason/TOTP action;
- tenant self-service recovery-key export is one-time per backup;
- the original KEK must exist when the portable key is exported; and
- responses are marked `no-store`.

Keep archive and recovery key in separate access-controlled recovery locations.
Never put a recovery key, reusable KEK, wrapped DEK, archive credential, or raw
backup in source control, tickets, chat, screenshots, or ordinary document
storage.

The current application audit chain is not a cloud WORM store. If immutable
evidence is required, verify the separately deployed external control.

## Tenant restore

All tenant restores are platform-admin operations:

1. Verify the source backup is completed, retained, hash-valid, and belongs to
   the expected source tenant.
2. Suspend the target tenant. Queueing and execution recheck suspension under the
   tenant operation lock.
3. Use the guarded same-tenant or cross-tenant restore action with typed
   `RESTORE <target-slug>` confirmation, reason, and current TOTP.
4. Watch the queued `tenant_restore` platform job and its redacted events.
5. Validate schema, data sampling, login, authorization, document references,
   queues, and tenant host resolution.
6. Reactivate only after verification succeeds.

A safe CLI plan can be run from an approved recovery context:

    bin/cake tenant restore --backup <tenant-backup-uuid> --dry-run

The destructive CLI requires `--confirm-destructive` and, for a different
target, `--mode cross-tenant --target-tenant <slug>`. Prefer Platform Admin so
actor, reason, step-up, and audit evidence are captured consistently.

Legacy `.kmpbackup` files can be uploaded with **Import Legacy Backup**. Import
is non-destructive: KMP validates/decrypts the passphrase archive, converts it
to the managed envelope format, and records a normal backup. The later restore
still requires a suspended target and all normal guardrails.

## Platform metadata recovery

The serving web process can queue and download a platform backup, but it does
not replace the live platform database. Platform metadata must be restored
before tenant databases because it contains tenant IDs, hosts, database/secret
references, jobs, schedules, backup metadata, and audit linkage.

On a secured recovery host, decrypt an exported archive and its separately
escrowed portable key:

    cd app
    bin/cake platform backup decrypt \
      --archive /secure/input/platform-<uuid>.pgdump.enc \
      --recovery-key /separate/escrow/platform-<uuid>.kmpbackup-key.json \
      --output /secure/work/platform-<uuid>.pgdump \
      --confirm WRITE-PLAINTEXT-PLATFORM-BACKUP

The command verifies the package/archive binding, refuses to overwrite an
existing output, and writes owner-only plaintext. Inspect with
`pg_restore --list`, restore to the isolated recovery PostgreSQL database with
the approved external procedure, then securely dispose of plaintext according
to incident evidence policy.

The repository does not currently automate provisioning a second Azure region
or restoring this dump into it. See [Region Failover](region-failover-runbook.md).

## Retention and deletion

New backup rows receive a fixed `retention_until` from the global policy.
`bin/cake platform backups prune` removes expired encrypted objects and retains
non-sensitive metadata with an expired/deleted status. Failed object removal is
recorded for retry.

Manual deletion in Platform Admin requires typed
`DELETE BACKUP <tenant|platform>` confirmation, reason, and TOTP. It removes the
encrypted object while retaining audit/operational metadata.

Azure Blob soft delete and GRS are infrastructure protections, not substitutes
for application backup retention or a proven restore.

## Restore drills

The default drill is non-destructive:

    cd app
    bin/cake tenant restore_drill --lookback-hours 36
    bin/cake tenant restore_drill --tenant example --lookback-hours 36

It selects a recent completed backup, verifies archive retrieval/decryption,
JSON structure, and target connectivity, and records a
`tenant_restore_drill` job. A successful default run finishes as `planned`; it
does not mutate tenant schema or data.

Destructive drill execution is explicit and must use an isolated approved target:

    bin/cake tenant restore_drill \
      --tenant example \
      --execute-restore \
      --confirm-destructive-drill RESTORE-DRILL-DESTRUCTIVE

Treat missing recent backups, failed verification, or unredacted stored errors as
incidents. A scheduled command existing in metadata is not proof of a completed
restore drill; retain execution evidence.

## KEK escrow status

`bin/cake platform escrow record-verification` records ceremony metadata only.
The in-repository Shamir splitter is a deterministic non-production placeholder
and refuses production use. The Azure template does not implement a 3-of-5
custodian ceremony or external escrow vault.

If escrow is a launch requirement:

1. select and approve a vetted implementation and custody system;
2. define which current key names/versions are in scope;
3. rehearse with non-production material under dual control;
4. store only fingerprints, custody labels, threshold/share count, status, and
   evidence references in platform metadata; and
5. mark launch no-go until recovery and reseal/rotation evidence exists.

Do not say escrow is implemented merely because the metadata command exists.

## Historical VPC backups

`deploy/vpc/backup.sh` is an unsupported single-database MariaDB dump. It does
not include platform metadata, other tenant databases, document objects, or
database-backed secrets.

The Azure upload option in that script uses the Azure CLI with
`--auth-mode login`; it does not consume
`AZURE_STORAGE_CONNECTION_STRING`. S3 upload uses the AWS CLI. Both require
operator authentication and external retention verification.

`deploy/vpc/restore.sh` streams SQL into the live single database after a prompt.
It does not quiesce application writes, recreate the database transactionally,
verify application/schema compatibility, or take a pre-restore backup. Do not
use it for managed tenancy or as a production recovery guarantee.

The retired `kmp backup` command has no `--list` option. `kmp restore` delegates
to an incomplete historical provider and is not a managed restore path.

## Complete recovery scope

Database backups do not copy document objects. A complete recovery plan must
also prove:

- Azure/S3 document and backup-object replication, versioning/soft delete, and
  access in the recovery environment;
- platform database restore before tenant restore;
- access to the database secret-store master key and required tenant/platform
  KEKs or portable per-backup keys;
- the exact application digest and compatible schema;
- DNS/custom host and session behavior;
- one active scheduler/worker region; and
- a fresh post-recovery platform and tenant backup.

Store secret recovery material in an approved encrypted system—not as a copied
plaintext `.env` file.
