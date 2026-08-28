# KMP container runtime

This directory defines the development, CI, and production container images and
their entrypoints. The supported managed runtime is the production image
deployed by [`deploy/azure`](../deploy/azure/README.md); the legacy VPC Compose
stack is documented separately as unsupported.

## File map

| Path | Role |
| --- | --- |
| `Dockerfile.base` | PHP 8.4/Apache base with application extensions and database clients |
| `Dockerfile.prod` | Immutable application image with compiled frontend assets, production dependencies, health check, and POC seed |
| `entrypoint.prod.sh` | Production configuration, database wait, compatibility migrations/cron, and command launch |
| `Dockerfile.app` | Local-development application image |
| `entrypoint.sh` | Local-development setup and optional cron |
| `scheduler-loop.sh` | Long-running local/self-hosted scheduler loop |
| `reset-and-seed.sh` | Destructive default-POC database rebuild |
| `Dockerfile.ci` | Test image |
| `app_local.php` | Docker-oriented local configuration |
| `apache-vhost.conf` | Apache development vhost |

## Managed Azure process model

All managed roles use the same production image digest but different commands:

| Role | Contract |
| --- | --- |
| Web | Apache serves requests; `KMP_SKIP_MIGRATIONS=true` and `KMP_SKIP_CRON=true` |
| Migration job | Runs the complete ordered application, platform, secret, key, tenant-fleet, and cache migration chain |
| Unified worker job | Runs one bounded `platform worker run` cycle every three minutes |
| Provision job | Manual command shape for a named tenant |
| POC restore job | Runs `/opt/kmp/reset-and-seed.sh` against disposable default POC data |
| `sched-*` jobs | Parked compatibility shapes; stored platform schedules are authoritative |

The deployment-authoritative migration command is:

```bash
bin/cake migrations migrate \
  && bin/cake schema_cache clear \
  && bin/cake updateDatabase \
  && bin/cake platform_migrate migrate \
  && bin/cake schema_cache clear --connection platform \
  && bin/cake platform secrets import-env \
  && bin/cake platform backup-keys ensure --allow-read-only \
  && bin/cake tenant migrate --all --include-suspended --fail-fast \
  && bin/cake cache clear _cake_model_
```

The unified worker command is:

```bash
bin/cake platform worker run \
  --schedule-limit 100 \
  --max-jobs 100 \
  --max-runtime 45 \
  --cycle-budget 240 \
  --platform-limit 1 \
  --json
```

Do not replace it with `queue run`; that command sees only the current default
datasource and is not a tenant-fleet worker.

## Production entrypoint boundaries

`entrypoint.prod.sh`:

1. writes `config/app_local.php` from runtime environment values;
2. detects MySQL or PostgreSQL and waits for the default database;
3. prepares writable runtime directories;
4. optionally runs a historical application migration pass;
5. optionally installs in-container cron; and
6. executes the requested process, normally `apache2-foreground`.

The entrypoint's compatibility migration path deliberately tolerates several
migration failures and does not include the full platform/tenant sequence.
Therefore it is not a deployment gate. Managed web revisions disable it, and
the dedicated migration job's explicit chained command determines deployment
success. The migration job may still execute the compatibility pass before its
explicit command because it uses the same entrypoint.

The entrypoint's cron mode is for legacy/self-hosted compatibility. Managed
Azure keeps cron out of the web process and uses the unified Container Apps Job.
Running both creates competing schedulers.

## Health endpoints

The production image's Docker health check calls `/livez`:

- `/livez` is static process liveness and does not prove database/cache
  readiness.
- `/health` checks the configured database and required cache/session backend.
- `/platform-admin/health` is the privileged platform view when Platform Admin
  is enabled and the request uses an allowed administration host.

Deployment probes must test `/livez` and `/health` separately.

## POC seed boundary

`Dockerfile.prod` bundles
`deploy/azure/seed/nightly-seed.kmpbackup` and
`reset-and-seed.sh`. The script drops/recreates only the configured default
application database, restores the logical `.kmpbackup` archive, resets all
member passwords to the POC password, and clears caches.

It does not restore the platform database or a tenant fleet. Never run it
against production or customer data. See the
[seed contract](../deploy/azure/seed/README.md).

## Secrets and writable data

Do not bake `.env` files, database URLs, keys, or credentials into an image.
Azure supplies Key Vault references and managed-identity configuration at
runtime. `config/app_local.php` is generated inside the container and must not be
treated as a durable secret store.

Logs, temporary files, uploaded images, and image-cache paths are writable
runtime data. In managed Azure they are replica-local unless the application
uses its configured document-storage adapter. Replica replacement can discard
local cache/log state; customer documents belong in managed storage.

## Contract verification

From the repository root:

```bash
bash deploy/azure/test-runtime-contract.sh
```

This checks the web skip flags, ordered migration command, unified worker
arguments, and retained job shape. Build/test changes should also follow the
verification commands in [`docker/AGENTS.md`](AGENTS.md).
