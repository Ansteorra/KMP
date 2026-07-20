# Azure nightly seed payload

The nightly Azure environment restores its test data from an **encrypted KMP
backup** baked into the Docker image at `/opt/kmp/seed/nightly-seed.kmpbackup`.
This directory is where that backup lives in the repo.

## Why a backup instead of a SQL dump?

- **Engine-agnostic.** The backup is JSON → gzip → AES-256-GCM; restore is
  ORM-based, so a backup created from MySQL/MariaDB restores cleanly into
  Postgres (and vice-versa). A SQL dump is tied to its source engine.
- **Self-validating.** The backup carries a migration fingerprint. Restore
  refuses to proceed if the target database schema drifted from the one the
  backup was taken against, preventing silent data/schema mismatches. See
  `app/src/Services/BackupService.php::getMigrationFingerprint()`.
- **One source of truth.** We reset locally with our normal
  `reset_dev_database.sh` (which already handles MySQL and Postgres) and then
  snapshot that known-good state — so the nightly environment matches the
  developer experience byte-for-byte.

## Files

| Path                     | Purpose                                                   |
|--------------------------|-----------------------------------------------------------|
| `nightly-seed.kmpbackup` | The encrypted seed blob consumed by the reset job.        |
| `bake-seed.sh`           | Helper that (re)produces `nightly-seed.kmpbackup` locally.|
| `.gitattributes`         | Treats `*.kmpbackup` as binary so git doesn't try to diff.|

`nightly-seed.kmpbackup` is not present on a fresh clone — a maintainer bakes
it the first time, commits it, and pushes. The image build tolerates its
absence (`docker/reset-and-seed.sh` fails fast with a helpful message if you
try to run a data restore without one).

## Encryption key

The backup is encrypted with a shared symmetric key that lives in two places:

1. **Azure Key Vault** under the secret name `backup-encryption-key`
   (populated by `deploy/azure/bootstrap.sh`; consumed by the reset Container
   Apps Job via `secretRef`).
2. **Maintainer's password manager**, so the seed can be re-baked locally.

**Never commit the key.** `bake-seed.sh` reads it from the
`BACKUP_ENCRYPTION_KEY` environment variable and fails if it's missing.

Rotating the key is a two-step deploy:

1. Re-bake `nightly-seed.kmpbackup` with the new key, commit, push.
2. Update `backup-encryption-key` in Key Vault so the nightly restart sees it.

Do these in the same change — if the key in Key Vault drifts from the key
the committed blob was baked with, the reset job will fail.

## How to (re)bake the seed

From a developer workstation with the app's local dev stack running
(Postgres or MySQL both work):

```bash
# 1. Make sure local DB is seeded to the state you want to snapshot.
./reset_dev_database.sh

# 2. Bake the backup (rotate BACKUP_ENCRYPTION_KEY to match what's in Key Vault).
export BACKUP_ENCRYPTION_KEY="$(cat ~/.secrets/kmp-nightly-backup-key)"
./deploy/azure/seed/bake-seed.sh

# 3. Commit the updated blob.
git add deploy/azure/seed/nightly-seed.kmpbackup
git commit -m "chore(seed): refresh nightly seed backup"
```

The next nightly image build bakes it in; the next reset job run restores it.

## Refresh cadence

No fixed schedule. Re-bake when:

- A new seed fixture has landed that stakeholders need (e.g. a new gathering
  activity, workflow definition, or test member role).
- Schema migrations have landed that change the shape of seeded data
  significantly. (The fingerprint check will make non-refreshed restores
  fail loudly.)

Each re-bake is ~15-40 KB today and ~O(few hundred KB) even with thousands
of members, because the payload is gzipped and only seeded dev data — not
uploaded documents, backups-of-backups, queue history, or session tokens
(all excluded; see `BackupService::EXCLUDED_TABLES` and `isExcludedTable`).

## Troubleshooting restore failures

**"Backup migration fingerprint does not match current database schema."**
→ Migrations have advanced since the seed was baked. Either re-bake (normal
path for the nightly), or pass `--ignore-schema-mismatch` on the restore CLI
if you *really* know what you're doing.

**"sodium_crypto_aead_aes256gcm_decrypt returned false"**
→ Key mismatch. Verify `BACKUP_ENCRYPTION_KEY` (env / Key Vault secret)
matches the key used when the blob was baked.

**"foreign key constraint ... cannot be validated"**
→ The backup carries orphaned rows (rare, but can happen if tables were
re-parented between seed runs). Bake a fresh one on a clean reset, or pass
`--fail-on-not-valid-fk=false` if you want the nightly job to tolerate it.
