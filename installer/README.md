# Retired installer and updater

The standalone installer, update sidecar, executable downloads and release workflows have
been removed. They are no longer maintained or distributed. Historical source is available
in Git history only; it is not a supported installation or recovery path.

Existing installations do not change when this repository is updated. Operators must stop
and remove the updater container and its Docker socket mount, disable installer/update and
legacy backup cron jobs, and remove downloaded management binaries from operational hosts.
Preserve existing databases and backups until recovery has been verified; do not delete data
or attempt an image-only migration to the multi-tenant architecture.

Before retiring a legacy installation:

1. Inventory its database, documents, settings, credentials and existing recovery material.
2. Restrict existing backup files/directories to their operator and encrypt copies using the
   organization's approved authenticated-encryption tool. Keep recovery keys separately.
3. Rehearse recovery in a disposable, isolated environment; never pipe an unverified dump
   into the live database. Historical SQL dumps require an explicit reviewed import plan.
4. Coordinate migration with the managed-service operator, validate tenant data and document
   access, and rotate migrated credentials before switching users.
5. Disable old public endpoints and remove the retired tooling after successful validation.

Use the [managed deployment runbook](../deploy/azure/README.md) and
[backup/recovery documentation](../docs/deployment/backup-restore.md) for the supported system.
Previously published releases and already installed copies require separate operator action;
this source change does not revoke or repair them.
