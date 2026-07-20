# Deployment guide

## Purpose

Own cloud deployment templates, environment examples, bootstrap scripts, CI/CD deployment tooling, and deployment-specific operational files.

## Ownership

- `deploy/azure` owns Azure Bicep/JSON templates, environment parameters, OIDC configuration, Container Apps cutover scripts, and Azure environment examples.
- `deploy/vpc` owns VPC-oriented Docker Compose, Caddy, database config, backup, restore, and environment examples.
- Application behavior and defaults remain in `app/`; deployment files should parameterize environment-specific values.

## Local Contracts

- Never commit secrets, credentials, private endpoints, or production environment values.
- Keep deployment templates parameterized and compatible with app configuration expectations.
- Deployment scripts must be safe to run repeatedly or clearly document non-idempotent behavior.
- Update docs when changing operational prerequisites, resource shape, backup/restore behavior, or deployment commands.
- GitHub Actions uses separate resource-group-scoped OIDC identities for `poc` and `production`; do not add client secrets.
- A successful `dev` image build deploys automatically to POC. Published non-prerelease `v*` releases require `production` environment approval before exact-digest promotion.
- Both environments must use `.github/workflows/azure-deploy.yml` and the ordered unified-worker cutover rather than duplicating migration or web-update logic.

## Work Guidance

1. Validate deployment changes with the relevant provider tooling when available.
2. Keep Azure-specific changes in `deploy/azure` and VPC/container-host changes in `deploy/vpc`.
3. Coordinate app config changes with `app/config` and Docker changes with `docker`.
4. Prefer environment examples with placeholder values over prose-only setup instructions.
5. Preserve immutable image promotion, pre-cutover snapshots, worker canary, migration, post-migration worker verification, web health checks, and retained-job alignment.

## Verification

- Azure template changes: validate with the existing Azure CLI/Bicep workflow when available.
- VPC compose changes: run the relevant Docker Compose config/build check when practical.
- Script changes: run shell syntax or dry-run checks when supported by the script.

## Child AGENTS index

No child `AGENTS.md` files are currently present.
