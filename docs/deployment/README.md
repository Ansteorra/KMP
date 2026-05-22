# Archived Self-Hosted Deployment Reference

KMP is moving to a managed multi-tenant hosting model. The standalone installer is retired for new deployments, but the self-hosted deployment knowledge is preserved here for legacy operators and maintainers.

## Status

| Area | Current status |
|------|----------------|
| Managed multi-tenant hosting | Primary deployment path |
| `kmp install` | Retired for new deployments |
| `bin/cake kmp_install` | Retired for new deployments |
| Self-hosted guides below | Archived reference |

## Legacy Self-Hosted Platforms

| Platform | Type | Database | SSL | Difficulty |
|----------|------|----------|-----|------------|
| Docker (Local/VPC) | Self-hosted | Bundled MariaDB or BYO | Caddy (auto Let's Encrypt) | ⭐ Easy |
| Fly.io | PaaS | Fly Postgres | Automatic | ⭐ Easy |
| Railway | PaaS | Managed MySQL / Redis (optional) | Automatic (Railway edge TLS, no extra proxy required) | ⭐ Easy |
| Azure | Cloud | Azure DB for MySQL | Automatic | ⭐⭐ Moderate |
| AWS | Cloud | RDS MySQL | ALB/ACM | ⭐⭐ Moderate |
| VPS (SSH) | Self-hosted | Bundled MariaDB | Caddy | ⭐⭐ Moderate |
| Shared hosting (no root) | Traditional web host | Provider-managed or external | Provider-managed | ⭐⭐ Moderate |

## Legacy Lifecycle Commands

| Command | Description |
|---------|-------------|
| `kmp install` | Retired; retained as historical reference only |
| `kmp update` | Legacy self-hosted maintenance |
| `kmp status` | Legacy self-hosted health and version checks |
| `kmp logs [-f]` | Legacy self-hosted log access |
| `kmp backup` | Legacy self-hosted database backup |
| `kmp restore <id>` | Legacy self-hosted restore |
| `kmp rollback` | Legacy self-hosted rollback |
| `kmp config` | Legacy self-hosted deployment configuration |
| `kmp self-update` | Legacy tool maintenance |

## Self-Hosted Image Channels

| Channel | Stability | Use Case |
|---------|-----------|----------|
| `release` | Stable | Production deployments (default) |
| `beta` | Pre-release | Testing upcoming features |
| `dev` | Development | Latest main branch |
| `nightly` | Nightly build | Bleeding edge |

## Legacy Self-Hosted Architecture

The KMP deployment system uses pre-built Docker images:

```
GitHub Releases → ghcr.io/jhandel/kmp:{tag} → Your infrastructure
```

Every app image release is:
- Multi-architecture (amd64 + arm64)
- Smoke-tested in CI before publishing
- Tagged with version, channel, and SHA digest
- Immutable once published

## Archived Guides

- [Docker/VPC Quick Start](quickstart-vpc.md)
- [Fly.io Quick Start](quickstart-fly.md)
- [Railway Quick Start](quickstart-railway.md)
- Azure Quick Start (legacy guide not included in this archive)
- AWS Quick Start (legacy guide not included in this archive)
- [Updating & Rollback](updating.md)
- [Backup & Restore](backup-restore.md)
- [Managed Platform Region Failover Runbook](region-failover-runbook.md)
- [Managed Platform Legal and Security Governance Template](legal-governance.md)
- [Managed Platform Data Protection Templates](data-protection-agreement-template.md)
- [Configuration Reference](configuration.md)
- [Two-Tenant Staging POC](multi-tenant-poc.md)
- [Platform Admin v2 and Tenant Trust Surface](platform-admin-v2-trust-surface.md)
- [Managed Platform Published Trust Documentation Index](trust-docs-index.md)
- [Managed Platform Launch Readiness Gate](launch-readiness-gate.md)
- [Managed Platform Penetration Test Scope and Evidence Checklist](penetration-test-scope-checklist.md)
- [Managed Platform DR Drill Execution Checklist](dr-drill-execution-checklist.md)
- [Managed Platform Security Regression Checklist](security-regression-checklist.md)
- [Pilot Ring Exit Criteria and Rollback Plan](pilot-ring-exit-criteria.md)
- [Pilot Go/No-Go Checklist Template](pilot-go-no-go-checklist.md)
- [Pilot Kingdom Migration Rehearsal Runbook](pilot-migration-runbook.md)
- [Troubleshooting](troubleshooting.md)

## Managed Platform Release Compatibility Contract

Managed image promotion uses an explicit release manifest so CI and deployment jobs can fail closed before an image is rolled out to tenants on an incompatible schema. Copy `app/config/release_manifest.example.json` to a generated `release_manifest.json` during image build or release packaging, then stamp it with the app version, immutable image digest, tenant schema bounds, N-1 compatible schema versions, migration policy, and rollback notes.

Required manifest fields:

| Field | Purpose |
|-------|---------|
| `app.version` | Human-readable app/release version. |
| `app.image` / `app.digest` | Image reference and immutable `sha256:` digest being promoted. |
| `tenant_schema.min` / `tenant_schema.max` | Inclusive tenant schema range supported by this image. |
| `tenant_schema.compatible_previous` | Explicit N-1 schema versions accepted during rolling deploys. |
| `migration_policy` | Safe rollout policy metadata for CI/deploy automation. |
| `rollback_notes` | Operator guidance for image/schema rollback limits. |

Before deploy, validate the generated manifest and active tenant schemas:

```bash
cd app
bin/cake platform release_check --manifest config/release_manifest.json --all
```

Tenant migrations can also enforce the same contract before any migration job row is created:

```bash
cd app
bin/cake tenant migrate --tenant example --manifest config/release_manifest.json
```

The check accepts tenants whose schema is between `min` and `max`, or exactly listed in `compatible_previous` for N-1 rolling compatibility. Missing, malformed, below-minimum, or above-maximum schema versions fail with a tenant-specific error.

### Nightly migration drill

Run the nightly migration drill from staging/canary jobs before promoting tenant schema changes. The safe metadata-only preflight checks the release manifest and target tenant schema compatibility, records a `nightly_migration_drill` row in `platform_jobs`, and prints a monitor-friendly summary line:

```bash
cd app
bin/cake platform nightly_migration_drill --all --plan-only --manifest config/release_manifest.json
```

The tenant-connected drill is still non-destructive: it runs `tenant migrate --status`, `tenant migrate --marker-only --manifest ...`, and `tenant migrate --dry-run --manifest ...` for each selected active tenant. The marker-only probe creates the pre-migration recovery marker/backup and then stops before applying migrations. The drill is gated by both an environment variable and a command flag so it cannot run against real tenants by accident:

```bash
cd app
KMP_ENABLE_NIGHTLY_MIGRATION_DRILL=true \
  bin/cake platform nightly_migration_drill --all --allow-staging --manifest config/release_manifest.json
```

For a single staging/canary tenant, replace `--all` with `--tenant <slug>`. Do not schedule this command for production tenants; use disposable canary or staging tenants until the promotion is approved.

Acceptance criteria:

- Release manifest parses and all selected tenants are within `tenant_schema.min/max` or `compatible_previous`.
- Each selected tenant status probe succeeds.
- Each selected tenant marker-only probe creates the recovery marker/backup and exits before migrations run.
- Each selected tenant dry-run migration probe succeeds without updating tenant schema versions.
- The aggregate `nightly_migration_drill` platform job completes and the final output line reports `status=completed`.

Failure triage:

- Inspect the failed `nightly_migration_drill` row in `platform_jobs` for the aggregate result and redacted error.
- Inspect tenant-level `tenant_migration` dry-run rows created during the drill for per-tenant migration output.
- If manifest compatibility fails, regenerate the manifest or fix schema bounds before deploying the image.
- If dry-run fails, treat it as a migration blocker; do not run destructive tenant migrations until the failing migration is fixed and the drill is green.
