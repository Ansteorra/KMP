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
environment. The retired `kmp install` command always fails intentionally.

For an existing legacy installation, read
[`deploy/vpc/README.md`](https://github.com/Ansteorra/KMP/blob/main/deploy/vpc/README.md) before making changes. The
important constraints are:

- the tracked image namespace and mutable tags are historical;
- the app maps host `127.0.0.1:8080` to container port `80` behind Caddy;
- the Compose stack owns one MariaDB database, not a tenant fleet;
- `backup.sh` dumps only that named database to an unencrypted `.sql.gz`;
- `restore.sh` imports into the existing database and does not restore files,
  other databases, or schema compatibility;
- the updater sidecar mounts the Docker socket; and
- updating or rolling back an image does not back up, restore, or reverse the
  database automatically.

Safe read-only diagnostics for an already deployed stack:

```bash
docker compose config
docker compose ps
docker compose logs --tail 200 app
curl -fsS http://127.0.0.1:8080/livez
curl -fsS http://127.0.0.1:8080/health
```

Preserve off-host, verified backups and rehearse recovery on a disposable clone
before any maintenance. See [legacy update limitations](updating.md#historical-self-hosted-maintenance)
and [backup limitations](backup-restore.md#historical-vpc-backups).
