---
layout: default
title: "Archived Fly.io Deployment Note"
description: "Unsupported historical Fly.io deployment boundary and migration guidance."
---

# Archived Fly.io deployment note

[← Deployment and operations](README.md)

> **Unsupported and incomplete.** KMP has no current Fly.io deployment
> runbook. This page replaces an obsolete quick start that used a retired
> installer, a historical image namespace, and a single Postgres database.

The archived provider contains partial launch, update, and coarse status code.
Its log streaming, backup, restore, and rollback methods return “not
implemented.” The public `kmp install` command is retired and never invokes the
provider. Nothing in that code provisions KMP's current platform database,
tenant fleet, database-backed secrets, unified three-minute worker, or managed
backup system.

Do not create a new Fly.io environment from the old examples. For current
architecture and operations use:

- [Managed deployment](../8-deployment.md)
- [Environment setup](../8.1-environment-setup.md)
- [Azure deployment runbook](https://github.com/Ansteorra/KMP/blob/main/deploy/azure/README.md)
- [Backup and restore](backup-restore.md)

If an existing Fly.io installation must be recovered, first inventory its
image digest, database attachment, volumes, secrets, DNS/TLS, scheduled work,
and backups through Fly.io's current documentation. Treat migration, backup,
restore, and rollback as an environment-specific recovery project; this
repository does not provide a verified Fly.io procedure.
