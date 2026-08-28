---
layout: default
title: "Archived Railway Deployment Note"
description: "Unsupported historical Railway deployment boundary and migration guidance."
---

# Archived Railway deployment note

[← Deployment and operations](README.md)

> **Unsupported and incomplete.** KMP has no current Railway deployment
> runbook. This page replaces an obsolete single-installation MySQL quick start
> that used the retired installer and a historical image namespace.

The archived Railway provider contains partial project/service, update, and
status behavior. Its backup, restore, rollback, and destroy methods return “not
implemented.” `kmp install` is retired and never invokes that provider. The old
shape does not provision the current platform database, tenant database fleet,
database-backed secrets, unified three-minute worker, or managed backup system.

Do not create a new Railway environment from the old examples. Use:

- [Managed deployment](../8-deployment.md)
- [Environment setup](../8.1-environment-setup.md)
- [Azure deployment runbook](https://github.com/Ansteorra/KMP/blob/main/deploy/azure/README.md)
- [Backup and restore](backup-restore.md)

For an existing Railway installation, use Railway's current control plane to
inventory the deployed image digest, database/cache services, secrets,
networking, scheduled work, and retained backups before making changes.
Recovery and migration need an explicit environment-specific plan; this
repository does not provide a verified Railway backup or rollback procedure.
