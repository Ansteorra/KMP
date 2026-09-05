---
layout: default
title: "Archived Docker/VPC Quick Start"
description: "Unsupported historical Docker Compose and VPC maintenance boundary."
---

# Archived Docker/VPC quick start

[← Deployment and operations](README.md)

> **Unsupported for new deployments.** The VPC Compose stack is a historical
> single-installation MariaDB design. It does not implement KMP's supported
> PostgreSQL platform database, tenant database registry/fleet, managed
> background worker, managed secrets, or managed backup controls.

Use the [managed Azure runbook](https://github.com/Ansteorra/KMP/blob/main/deploy/azure/README.md) for a new
environment. Legacy management executables and updater distribution have been removed.

For an existing legacy installation, follow the [legacy retirement instructions](https://github.com/Ansteorra/KMP/blob/main/installer/README.md) before changing it.
The retained Compose example has no updater or Docker socket mount. Installed older
copies must be disabled separately. Removed backup scripts created plaintext dumps of one
MariaDB database; inventory and secure those archives and verify off-host recovery.

Safe read-only diagnostics for an already deployed stack:

```bash
docker compose ps
docker compose logs --tail 200 app
curl -fsS http://127.0.0.1:8080/livez
curl -fsS http://127.0.0.1:8080/health
```

Preserve off-host, verified backups and rehearse recovery on a disposable clone
before any maintenance. See [legacy update limitations](updating.md#historical-self-hosted-maintenance)
and [backup limitations](backup-restore.md#historical-vpc-backups).
