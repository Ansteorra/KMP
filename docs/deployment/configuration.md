---
layout: default
title: "Legacy Self-Hosted Configuration Appendix"
description: "Archived configuration reference for existing single-database self-hosted KMP installations."
---

# Legacy Self-Hosted Configuration Appendix

This page is an archived reference for existing single-database installations.
It is not the managed platform environment reference and is not complete for
multi-tenancy.

For current configuration, use
[Environment Setup and Variables](../8.1-environment-setup.md) and
`deploy/azure/main.bicep`.

[← Back to Deployment and Operations](README.md)

## Unsupported scope

The historical Docker/VPC profile uses one MariaDB database, Caddy, the
production application image, local or credential-based document storage, and an
optional updater sidecar. It does not provide:

- `PLATFORM_DATABASE_URL` or the platform metadata schema;
- database-per-tenant provisioning and host routing;
- database-backed platform secrets and tenant KEKs;
- the managed three-minute unified worker;
- the managed migration job and tenant fleet migration;
- encrypted managed tenant/platform backup formats; or
- same-digest POC-to-production promotion.

Do not use these values to construct a new managed deployment.

## Historical VPC environment

The archived `deploy/vpc/docker-compose.yml` and
`deploy/vpc/.env.example` are the exact source of truth. The main variables are:

| Variable | Historical purpose |
| --- | --- |
| `DOMAIN` | Caddy host and certificate name |
| `SECURITY_SALT` | CakePHP security salt |
| `MYSQL_HOST` | Compose MariaDB service, normally `db` |
| `MYSQL_DB_NAME` | Single application database |
| `MYSQL_USERNAME` / `MYSQL_PASSWORD` | Application database credentials |
| `MYSQL_ROOT_PASSWORD` | MariaDB container administration |
| `KMP_IMAGE_TAG` | Image tag used by Compose |
| `DOCUMENT_STORAGE_ADAPTER` | `local`, `azure`, or `s3` |
| `EMAIL_SMTP_HOST` / `EMAIL_SMTP_PORT` | SMTP endpoint |
| `EMAIL_SMTP_USERNAME` / `EMAIL_SMTP_PASSWORD` | SMTP credentials |
| `EMAIL_SMTP_TLS` / `EMAIL_FROM` | SMTP TLS and sender |

Use a pinned immutable tag/digest when maintaining an old installation. The
archived Compose file still uses the historical `ghcr.io/jhandel/kmp` package;
the current official managed package is `ghcr.io/ansteorra/kmp`. Do not switch
registries without first proving image compatibility.

### Historical document storage names

Current application configuration uses:

- `AWS_S3_BUCKET` and `AWS_DEFAULT_REGION` as canonical S3 names;
- `AWS_BUCKET` and `AWS_REGION` only as production-entrypoint compatibility
  aliases;
- `AZURE_STORAGE_CONNECTION_STRING` for legacy/dev Azure shared-key access; and
- managed identity variables for the supported Azure platform.

Local container storage is not durable unless the deployment mounts a persistent
volume and backs it up. It is never suitable for ephemeral managed replicas.

## Retired management-tool file

The archived Go tool reads `~/.kmp/config.yaml` with this shape:

    version: 1
    deployments:
      default:
        provider: docker
        channel: release
        domain: kmp.example.org
        image: ghcr.io/jhandel/kmp
        image_tag: latest
        compose_dir: /opt/kmp
        storage_type: local
        backup_enabled: true
        backup_schedule: "0 3 * * *"
        backup_retention_days: 30

Backup fields are flat under the deployment. The tool does not accept the old
nested `backup:` example that previously appeared on this page.

The installer, updater and backup/restore executables have been removed. This configuration
is historical only. Follow the [legacy retirement instructions](https://github.com/Ansteorra/KMP/blob/main/installer/README.md) for existing installations.

## Historical database warning

The retained Compose template assumes one MariaDB database. Removed backup scripts
dumped only `MYSQL_DB_NAME`. Historical archives cannot restore platform
metadata plus a managed tenant fleet.

Managed Azure uses PostgreSQL Flexible Server and separate `DATABASE_URL` and
`PLATFORM_DATABASE_URL` connections. Read
[Backup and Restore](backup-restore.md) before moving data between the
architectures.

## Secret handling

Never commit `.env` or `~/.kmp/config.yaml`. Keep permissions restricted and
store recovery copies in an approved encrypted secret manager. Do not paste
database URLs, storage connection strings, SMTP credentials, salts, KEKs, or
backup passphrases into tickets or documentation.

Changing `SECURITY_SALT` can invalidate security-sensitive application state.
Rotating a database password or storage key also requires updating every runtime
that consumes it and verifying health; a container restart alone is not a
complete rotation procedure.

## Historical verification

For an existing VPC installation:

    docker compose ps
    docker compose logs --tail 200 app
    curl -fsS https://<legacy-host>/livez
    curl -fsS https://<legacy-host>/health

`/livez` proves only that the container serves the static probe. `/health`
checks the configured default database and cache and returns HTTP 503 when
readiness is degraded.

New deployments must use the managed runbooks rather than this appendix.
