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
- Changes to base dependencies must be compatible with PHP 8.3, CakePHP, Vite builds, and Playwright/test workflows.

## Work Guidance

1. Search deployment and app docs before changing container behavior.
2. Keep development and production behavior separate unless a shared base dependency truly belongs in `Dockerfile.base`.
3. Update deployment or Docker docs when startup commands, ports, services, volumes, or health behavior change.
4. Avoid changing generated or local-only files outside Docker support paths.

## Verification

- Dockerfile or entrypoint changes: build the affected image when practical.
- Compose-affecting changes: run the existing Docker Compose config/build path when practical.
- Shell script changes: run a syntax check such as `bash -n path/to/script.sh`.

## Child AGENTS index

No child `AGENTS.md` files are currently present.
