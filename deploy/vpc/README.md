# Legacy VPC and self-hosted stack

> **Unsupported for new deployments.** This Docker Compose stack predates the
> managed PostgreSQL multi-tenant architecture. It remains in the repository
> only to help operators understand and maintain an existing installation.
> Start new environments with the [managed Azure runbook](../azure/README.md).

## What this stack contains

| Component | Current template behavior |
| --- | --- |
| `app` | Legacy KMP image, host `127.0.0.1:8080` to container port `80` |
| `db` | One MariaDB 11 database and one persistent volume |
| `caddy` | Public ports `80`/`443` and automatic TLS |
| `kmp-updater` | Archived update sidecar with the Docker socket mounted |

The app and database are single-installation infrastructure. The template does
not provision the platform metadata database, a tenant database fleet, managed
Redis, database-backed platform secrets, managed backup jobs, or Platform Admin
host controls required by the supported architecture.

The tracked Compose file also references the historical
`ghcr.io/jhandel/kmp` image namespace and a mutable updater image. Do not treat
those references as a supported release source. Managed releases promote a
POC-validated image by immutable digest.

## Existing-installation maintenance

Resolve the exact Compose directory and inspect state before changing anything:

```bash
docker compose config
docker compose ps
docker compose logs --tail 200 app
curl -fsS http://127.0.0.1:8080/livez
curl -fsS http://127.0.0.1:8080/health
```

`/livez` proves only that the web server is alive. `/health` checks the
application's configured database and cache dependencies.

The updater container mounts `/var/run/docker.sock` and therefore has
host-equivalent control over Docker. Existing operators should review whether
it is still needed, restrict access to the Compose host, and avoid mutable
image tags. An image rollback does not reverse schema migrations or restore
data.

## Legacy backup scripts

`backup.sh` creates an unencrypted `.sql.gz` dump of only
`MYSQL_DB_NAME` (default `kmp`). It can leave the file locally or copy it to S3
or Azure Blob. The Azure path uses the operator's `az` login
(`--auth-mode login`). These uploads do not establish immutability, retention
lock, or verified recovery.

`restore.sh` imports a selected dump into the existing named MariaDB database
after an interactive prompt. It does not:

- recreate the database;
- restore a platform database or other tenant databases;
- restore uploaded documents or object storage;
- run a migration compatibility plan;
- reverse migrations after an image rollback; or
- prove that the resulting application is healthy.

Before maintaining a legacy installation, copy backups off-host, verify their
checksums and encryption under the organization's policy, and rehearse restore
against a disposable clone. Coordinate downtime and validate both `/health`
and representative data afterward.

For the supported tenant and platform formats, retention policy, and restore
controls, use [Backup and restore](../../docs/deployment/backup-restore.md).

## Files

| Path | Purpose |
| --- | --- |
| `docker-compose.yml` | Archived app, MariaDB, Caddy, and updater topology |
| `Caddyfile` | Reverse proxy and TLS configuration |
| `.env.example` | Legacy single-installation variables |
| `mariadb.cnf` | MariaDB configuration |
| `backup.sh` | One-database SQL dump helper |
| `restore.sh` | Destructive SQL import helper |

The authoritative status and limitations of all self-hosted material are
summarized in the
[historical deployment reference](../../docs/deployment/README.md#historical-self-hosted-reference).
