# KMP container runtime

This directory defines the development, CI, and production container images and
their entrypoints. The supported managed runtime is the production image
deployed by [`deploy/azure`](../deploy/azure/README.md); the legacy VPC Compose
stack is documented separately as unsupported.

## File map

| Path | Role |
| --- | --- |
| `Dockerfile.base` | Pinned PHP 8.5/Apache base with application extensions and database clients |
| `Dockerfile.prod` | Immutable application image with compiled frontend assets, production dependencies, health check, and POC seed |
| `entrypoint.prod.sh` | Production configuration, database wait, compatibility migrations/cron, and command launch |
| `Dockerfile.app` | Local-development application image |
| `entrypoint.sh` | Local-development setup and optional cron |
| `scheduler-loop.sh` | Long-running local/self-hosted scheduler loop |
| `reset-and-seed.sh` | Destructive default-POC database rebuild |
| `Dockerfile.ci` | Test image |
| `app_local.php` | Docker-oriented local configuration |
| `apache-vhost.conf` | Apache development vhost |
| `verify-php-version.php` | Branch-specific minimum security versions for container builds and final-image audits |

## PHP security updates

The October 21, 2026 PHP releases fix
[CVE-2026-109618](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w)
and [CVE-2026-109619](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp).
KMP container builds require at least PHP 8.3.36, 8.4.27, or 8.5.12 within the
corresponding supported branch. Older PHP 8.5 releases fail even though their
version number is higher than PHP 8.4.27. Prereleases and unreviewed minor
branches fail. See the [PHP advisory](https://www.php.net/archive/2026.php#2026-10-21-5).

Update the matching PHP tag and registry-verified manifest digest in both
`Dockerfile.base` and `Dockerfile.prod`. Rebuilding a digest-pinned image or
upgrading Debian packages does not update PHP compiled into the official image.
The local-development image also checks the actual interpreter before installing
dependencies. The devcontainer package build requires PHP 8.4.27 or later in 8.4.
CI and production builds check prebuilt base overrides as well as the default base.

The production/base pins select the official PHP 8.5.12 Apache/Trixie manifest
`sha256:4d1f268dd3b670bd003861c5385cb2f8ba7196dbc88ee84dcae47270c116f242`,
verified to include Linux AMD64 and ARM64 images. Replace both pins together
when refreshing this runtime; never substitute an unverified digest or bypass
the version check. The local Bookworm tag may remain affected until its publisher
refreshes it.

For local development, `Dockerfile.app` accepts a `PHP_IMAGE` build argument.
Use it with a verified patched Apache/Bookworm image when rebuilding before the
floating upstream tag is refreshed. The version check still applies. A locally
built upstream recipe is local test evidence, not a replacement production pin.
The development image includes `jq` and Python for the complete repository
verification suite, and uses PHP's built-in OPcache when available.
Native extension builds use four jobs by default; `PHP_BUILD_JOBS` adjusts this
for development and production runtime builds.

Development images and the production frontend build stage pin npm separately
through `NPM_VERSION`. Node distributions can bundle older vulnerable npm
dependencies even when application locks are current. Review the published npm
archive, rebuild, and scan the finished image when refreshing this pin. The
frontend builder does not copy Node or npm into the production runtime.
Generated frontend files are excluded from the Docker context so local or
previously committed hashed bundles cannot accompany the newly built assets.

```bash
docker compose --env-file app/config/.env build \
  --build-arg PHP_IMAGE=your-verified-local-php-image app
docker compose --env-file app/config/.env up -d --no-deps --no-build \
  --force-recreate app scheduler admin-worker
```

Recreating these application services preserves the database volume. Browser
test lanes can reset local databases; snapshot any local data needed beforehand.
Local scheduler and admin loops run as `www-data` after root startup setup.
The entrypoint assigns local backup storage to that user with a private parent
directory, so encrypted archives written by workers remain readable by guarded
web download actions without making the directory public.
Use `PLAYWRIGHT_BASE_URL=http://kmp.localhost:8080` for host browser tests and
resolve the tenant/platform hostnames locally. Chromium rejects navigation with
a mismatched manually supplied `Host` header on a loopback URL.

Build and scan a new application image, run the quality gates, validate the exact
image digest in POC, and promote that same digest through the existing release
approval process. Recreate all web and worker processes with the patched image.
Configuration workarounds do not establish safety, and multipart forms can remain
functional after patching. This change does not alter upload or session settings.

Run the security regression tests from the repository root:

```bash
python3 -m unittest discover -s security -p 'test_*.py'
```

The PHP boundary tests use the local `php` command, or an existing local PHP image
when `KMP_TEST_PHP_IMAGE` is set. The test container has no network and mounts only
the version checker read-only. This does not restart the development stack.

## Managed Azure process model

Production final images remove SUID/SGID from `/usr/bin/mount` and
`/usr/bin/umount`, with build-time assertions for both files. Azure mounts
volumes outside the application; web users do not need elevated mount helpers.
The ordinary commands remain executable. This reduces a local escalation
precondition, but does not patch libmount or restrict callers already running as
root/with capabilities. Do not use privileged `nsenter --join-cgroup` against
untrusted targets until the affected tooling is patched. See the
[1.5.13 native review](../security/reviews/1.5.13-native.md) for residual risks
and verification limits.

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

Grid email subscriptions use the tenant-scoped `grid-subscriptions` platform schedule.
Single-tenant local scheduling invokes `bin/cake grid_subscriptions_enqueue` every
15 minutes; both modes deliver through the existing queue worker.
