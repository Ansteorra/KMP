# Docker runtime guide

## Purpose

Own container image definitions, entrypoints, Apache configuration, local app configuration, scheduler loops, reset/seed helpers, and Docker support files.

## Ownership

- `Dockerfile.base` owns shared PHP/system dependencies.
- `Dockerfile.app` owns local/development image behavior.
- `Dockerfile.prod` owns production image behavior.
- Entrypoint and helper scripts own container startup, scheduler loops, and reset/seed operations.

## Local Contracts

- KMP is a PostgreSQL-only SaaS platform for kingdoms. Do not add MySQL/MariaDB drivers, clients, configuration paths, or test lanes. Preserve applied migrations and legacy logical-backup import into PostgreSQL.
- Production final stages must reject prebuilt runtime overrides without `pdo_pgsql` or with enabled `mysqli`/`pdo_mysql`. Rebuild the shared base when changing its dependency contract; do not revive retired engines to use a stale base.
- Do not bake secrets or environment-specific credentials into images.
- Keep entrypoints explicit about side effects such as migrations, seeds, cache clears, scheduler loops, and health behavior.
- Local startup prepares backup-directory ownership before dropping scheduler/admin loops to `www-data`. Keep backup directories private and use the same OS user for web and worker file access.
- Keep reset/seed scripts idempotent when possible because local and test workflows may call them repeatedly.
- Changes to base dependencies must be compatible with PHP 8.4, CakePHP, Vite builds, and Playwright/test workflows.
- Shared and production runtime builds use the same digest-pinned PHP 8.5 Apache image on Debian Trixie and `install-runtime.sh`. Refresh inherited Debian packages before compiling extensions, preserve the PostgreSQL signing-key checksum, and select its repository using the image's release codename.
- Every image build must run `verify-php-version.php`, including CI and production builds using a prebuilt `BASE_IMAGE`. Supported PHP branches require 8.3.36, 8.4.27, or 8.5.12 or later within that branch; new minor branches require review. Final-image security audits run the current policy independently of the image's bundled checker.
- Local development may select a verified Apache/Bookworm parent through `PHP_IMAGE`; keep the security check enabled and record source-build provenance when published patched tags are unavailable. Preserve `jq` and Python for full repository verification, and use built-in OPcache when the selected PHP includes it.
- Pin npm independently through `NPM_VERSION` in development images and the production frontend builder. Verify the npm archive's bundled dependency versions and scan the rebuilt image; application lock audits do not cover global npm tooling.
- Keep `.dockerignore` and `docker/.dockerignore.prod` identical. Exclude generated frontend bundles, source maps, and manifests from build inputs, and rebuild from locked dependencies so stale local bundles cannot enter the production image.
- Remove discovered build tools and development headers after compilation while retaining shared runtime dependencies, including the GCC base support package. Verify PHP extensions, GD formats, PostgreSQL clients, and PDF processing in the finished image after native upgrades.
- Production images remove SUID/SGID from `mount` and `umount`, including when using a prebuilt base override. Azure mounts volumes outside the application; preserve ordinary utility execution without granting application users elevated mount privileges. This does not patch libmount or protect privileged `nsenter --join-cgroup` use.
- Keep `app/resources/security/document-blob-condition.txt` in the production image; dependency-stage validation must fail if the storage authorization policy is missing. Production dependencies must come from clean build stages, with no local vendor or runtime-data overlays.

## Work Guidance

1. Search deployment and app docs before changing container behavior.
2. Keep development and production behavior separate unless a shared base dependency truly belongs in `Dockerfile.base`.
3. Update deployment or Docker docs when startup commands, ports, services, volumes, or health behavior change.
4. Avoid changing generated or local-only files outside Docker support paths.

## Verification

- Dockerfile or entrypoint changes: build the affected image when practical.
- Compose-affecting changes: run the existing Docker Compose config/build path when practical.
- Shell script changes: run a syntax check such as `bash -n path/to/script.sh`.
- Native upgrades: scan the finished image and compare advisory IDs as well as package rows. Record unresolved findings separately from repaired vulnerabilities; an empty fixable-vulnerability report does not establish that an image is vulnerability-free.

## Child AGENTS index

No child `AGENTS.md` files are currently present.
