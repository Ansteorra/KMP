# Retired self-hosted deployment

This directory retains the old app/database/proxy Compose topology as a historical reference.
It is not supported for new deployments. The privileged updater sidecar and legacy plaintext
backup/restore executables have been removed and must not be restarted from old instructions.

Repository changes do not stop installed sidecars or remove existing plaintext backups.
Follow the [retirement and migration notice](../../installer/README.md) before changing an
existing installation. Preserve data until an isolated recovery and migration have succeeded.
Do not treat the historical image namespace or Compose stack as a managed release source.

The supported system uses the [managed Azure runbook](../azure/README.md) and
[managed backup/recovery](../../docs/deployment/backup-restore.md). An image rollback does not
reverse database migrations or restore documents.
