# Deployment guide

## Purpose

Own cloud deployment templates, environment examples, bootstrap scripts, nightly deployment scripts, and deployment-specific operational files.

## Ownership

- `deploy/azure` owns Azure Bicep/JSON templates, staging parameters, nightly deployment scripts, and Azure environment examples.
- `deploy/vpc` owns VPC-oriented Docker Compose, Caddy, database config, backup, restore, and environment examples.
- Application behavior and defaults remain in `app/`; deployment files should parameterize environment-specific values.

## Local Contracts

- Never commit secrets, credentials, private endpoints, or production environment values.
- Keep deployment templates parameterized and compatible with app configuration expectations.
- Deployment scripts must be safe to run repeatedly or clearly document non-idempotent behavior.
- Update docs when changing operational prerequisites, resource shape, backup/restore behavior, or deployment commands.

## Work Guidance

1. Validate deployment changes with the relevant provider tooling when available.
2. Keep Azure-specific changes in `deploy/azure` and VPC/container-host changes in `deploy/vpc`.
3. Coordinate app config changes with `app/config` and Docker changes with `docker`.
4. Prefer environment examples with placeholder values over prose-only setup instructions.

## Verification

- Azure template changes: validate with the existing Azure CLI/Bicep workflow when available.
- VPC compose changes: run the relevant Docker Compose config/build check when practical.
- Script changes: run shell syntax or dry-run checks when supported by the script.

## Child AGENTS index

No child `AGENTS.md` files are currently present.
