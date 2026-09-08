# Docker runtime guide

## Purpose

Own container image definitions, entrypoints, Apache configuration, local app configuration, scheduler loops, reset/seed helpers, and Docker support files.

## Ownership

- `Dockerfile.base` owns shared PHP/system dependencies.
- `Dockerfile.app` owns local/development image behavior.
- `Dockerfile.prod` owns production image behavior.
- Entrypoint and helper scripts own container startup, scheduler loops, and reset/seed operations.

## Local Contracts

- Do not bake secrets or environment-specific credentials into images.
- Keep entrypoints explicit about side effects such as migrations, seeds, cache clears, scheduler loops, and health behavior.
- Keep reset/seed scripts idempotent when possible because local and test workflows may call them repeatedly.
- Changes to base dependencies must be compatible with PHP 8.4, CakePHP, Vite builds, and Playwright/test workflows.
- Shared and production runtime builds use the same digest-pinned PHP 8.4 Apache image on Debian Trixie and `install-runtime.sh`. Refresh inherited Debian packages before compiling extensions, preserve the PostgreSQL signing-key checksum, and select its repository using the image's release codename.
- Remove discovered build tools and development headers after compilation while retaining shared runtime dependencies, including the GCC base support package. Verify PHP extensions, GD formats, PostgreSQL clients, and PDF processing in the finished image after native upgrades.
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
