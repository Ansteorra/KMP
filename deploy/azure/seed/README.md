# Azure POC seed archive

> **Scope:** This archive is only for rebuilding the disposable default POC
> database. It is not a production backup, a tenant-fleet backup, or a platform
> database backup. The restore job destroys the target default database before
> loading it.

`nightly-seed.kmpbackup` is a tracked, encrypted logical backup baked into the
application image at `/opt/kmp/seed/nightly-seed.kmpbackup`. The
`<prefix>-restore` Azure Container Apps Job invokes
`docker/reset-and-seed.sh` to consume it.

This seed format is distinct from managed operations:

| Artifact | Scope | Format |
| --- | --- | --- |
| POC seed | Disposable default application database | `.kmpbackup` |
| Managed tenant backup | One tenant application database | `.json.gz.enc` |
| Managed platform backup | Platform metadata database | `.pgdump.enc` |

See [backup and restore operations](../../../docs/deployment/backup-restore.md)
for the managed formats.

## Why the seed is a logical backup

The legacy `BackupService` serializes application records, compresses them, and
encrypts the result with AES-256-GCM. Restore uses CakePHP's ORM, so an archive
created from a supported local MySQL/MariaDB or PostgreSQL database can seed the
POC PostgreSQL database. It is functionally aligned with the curated local seed;
it is not byte-for-byte database state.

The archive includes a migration fingerprint. A schema mismatch is a signal to
rebuild the archive from a clean, current database—not a reason to bypass the
check in the managed POC workflow.

## Files and data contract

| Path | Purpose |
| --- | --- |
| `nightly-seed.kmpbackup` | Tracked encrypted seed artifact |
| `bake-seed.sh` | Validates curated fixtures and writes a replacement artifact |
| `.gitattributes` | Marks `*.kmpbackup` as binary |

The artifact is present in the repository. Its size varies with fixtures and is
not a validity criterion; inspect the actual file with
`ls -lh deploy/azure/seed/nightly-seed.kmpbackup`.

The scale fixtures use fictional identities and stable markers such as
`scale.member+####@example.test`, `SCALE-######`, and
`scale-seed-gathering-####`. Never add production member records, credentials,
recommendations, bestowals, audit identities, uploaded documents, or other
customer data.

The seed includes the approved Ansteorra award configuration while excluding
member and award-transaction data. Keep the deterministic SQL snapshots aligned
with:

```bash
php app/scripts/seed/fictionalize-scale-data.php --write
php app/scripts/seed/fictionalize-scale-data.php --check
php app/scripts/seed/sync-ansteorra-award-catalog.php --write
php app/scripts/seed/sync-ansteorra-award-catalog.php --check
```

The `--write` modes update tracked fixture data. Review those changes before
baking. The bake helper also runs both `--check` commands and guarded
`--apply-local-database` operations; those operations refuse non-local database
hosts.

## What the restore job actually does

`docker/reset-and-seed.sh` runs this sequence against the configured default
application database:

1. `DEBUG=true bin/cake resetDatabase` drops and recreates its schema.
2. `bin/cake updateDatabase` applies core and enabled-plugin migrations.
3. `bin/cake backup restore nightly-seed.kmpbackup --yes --fail-on-not-valid-fk`
   restores the logical archive through the local backup adapter.
4. Every restored member password is reset to the known POC test password.
5. Application caches are cleared.

It does not restore `PLATFORM_DATABASE_URL`, create or restore tenant registry
databases, restore uploaded documents, or recover production audit evidence.
Use the platform and tenant workflows for those scopes.

Before starting `<prefix>-restore`, resolve the exact Azure resource group and
job name and independently confirm that its default database contains only
disposable POC data. Do not run it against production or a customer database.

## Baking an updated archive

Prerequisites:

- a clean local KMP checkout and working local database;
- the database reset to an intentional, reviewed fixture state;
- `BACKUP_ENCRYPTION_KEY` obtained from an approved secret manager without
  printing it or recording it in shell history;
- the key matching the version that the POC restore job will use.

From the repository root:

```bash
# Optional only when the tracked SQL snapshots themselves need regeneration.
php app/scripts/seed/fictionalize-scale-data.php --write
php app/scripts/seed/sync-ansteorra-award-catalog.php --write

# Validate tracked snapshots.
php app/scripts/seed/fictionalize-scale-data.php --check
php app/scripts/seed/sync-ansteorra-award-catalog.php --check

# Rebuild the local database deliberately before capturing it.
./reset_dev_database.sh

# Make BACKUP_ENCRYPTION_KEY available securely, then bake.
test -n "${BACKUP_ENCRYPTION_KEY:-}"
./deploy/azure/seed/bake-seed.sh

git diff --stat -- deploy/azure/seed/nightly-seed.kmpbackup
```

The helper:

1. validates the tracked scale fixtures and award catalog;
2. applies those managed records to the local database using guarded scripts;
3. runs `CACHE_ENGINE=apcu bin/cake backup create`;
4. moves the newly created archive to
   `deploy/azure/seed/nightly-seed.kmpbackup`.

Review and commit the binary artifact with the related fixture changes. The
artifact reaches Azure only after a new application image containing it is
built and that immutable image digest is deployed.

## Encryption-key coordination

The seed key is a compatibility key for this POC artifact. It is not the
managed platform backup KEK, and it must never be committed.

A safe rotation is coordinated:

1. Generate and escrow the new value through the approved secret process.
2. Bake and review a new seed archive with that value.
3. Build the immutable image that contains the new archive.
4. Deploy the Azure parameters so Key Vault receives the matching
   `backup-encryption-key` and the restore job definition references the
   intended image and secret.
5. Start the restore job only in the disposable POC target and verify the
   completed execution.
6. Retain the prior key for the approved recovery window while any old artifact
   or job revision could still be used, then retire it under the key policy.

Do not assume that changing Key Vault or restarting only the web app updates a
Container Apps Job. Verify the job's active definition and a real restore
execution.

## Troubleshooting

**Schema fingerprint mismatch**

The application migrations changed after the archive was created, or the local
source was stale. Reset a current local database and rebake. Do not add
`--ignore-schema-mismatch` to the managed POC restore.

**Decryption or authentication failure**

The archive and `BACKUP_ENCRYPTION_KEY` do not match, or the artifact is
corrupt. Compare the intended key versions without printing the secret, verify
the deployed image digest and job revision, and rebake/redeploy as a coordinated
change.

**Foreign-key validation failure**

The source seed contains inconsistent or obsolete relationships. Rebuild from a
clean reset and fix the fixture source. The managed restore intentionally uses
`--fail-on-not-valid-fk`.

**Memory exhaustion**

The restore script defaults `KMP_BACKUP_RESTORE_MEMORY_LIMIT` to `512M`.
Increase it only after measuring a larger reviewed seed and confirming the
Container Apps Job has sufficient memory.
