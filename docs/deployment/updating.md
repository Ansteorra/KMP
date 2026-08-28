---
layout: default
title: "Updating, Release, and Rollback"
description: "Immutable POC-to-production promotion, migration ordering, rollback limits, and legacy maintenance."
---

# Updating, Release, and Rollback

KMP's managed Azure environments use gated GitHub Actions workflows and immutable
image digests. They do not update by pulling a mutable tag on the server, and the
web Container App does not run migrations on startup.

[← Back to Deployment and Operations](README.md)

## Managed Azure release procedure

### Release to POC

1. Merge the approved pull request into official `main`.
2. Wait for `Quality Gates` to pass for that exact commit.
3. Fast-forward official `dev` to that commit and push `dev`.
4. `Nightly / Dev Docker Image` verifies the existing quality-gate evidence,
   builds `ghcr.io/ansteorra/kmp:dev-<short-sha>` for amd64 and arm64, and
   smoke-checks the image.
5. `POC / Deploy to Azure` resolves the immutable GHCR digest, imports it to POC
   ACR, captures rollback evidence, canaries the unified worker, runs the ordered
   migration job, cuts over web, verifies `/livez` and `/health`, and aligns
   retained jobs.
6. Only after that deployment succeeds, the workflow applies
   `poc-validated-<12-char-sha>` to the same digest.
7. Validate tenant login, host resolution, queue/worker processing, Platform
   Admin access on its reserved host, backup readiness, and the release's changed
   user journeys.

A scheduled `main` build publishes the `nightly` channel but does not
automatically deploy it to POC. POC deployment is triggered by a successful
`dev` image build or an explicit workflow dispatch.

### Release to production

1. Update `app/CHANGELOG.md` before POC validation.
2. Publish a stable, non-prerelease `v*` GitHub Release targeting the exact
   commit validated in POC. Its notes must exactly match that changelog section.
3. `Release Docker Image` verifies that the release commit is on official
   `main`, has successful quality-gate evidence, and has a
   `poc-validated-<sha>` image.
4. The workflow applies semantic version, SHA, and stable channel tags to that
   POC-validated digest with `docker buildx imagetools create`. It does not
   rebuild the image or rerun the test suites.
5. Review and approve the protected `production` environment deployment.
6. Production imports and deploys the exact same digest, runs the same worker
   canary and migration contract, cuts over web, probes health, and aligns jobs.
7. Verify the active Container Apps digest, critical tenant hosts/login, worker
   executions, Platform Admin, Redis-backed sessions, and new backup execution.

Do not edit release notes after POC validation without treating the result as a
new candidate. Do not release another commit or digest.

### Repository shorthand

Repository agents interpret:

- **Push to dev** as the POC procedure only. It never changes production.
- **Do a release** as changelog update, POC validation of that exact commit and
  digest, stable GitHub Release, protected production approval, and production
  verification.

## Migration behavior during deployment

The reusable Azure deployment starts the migration job and requires this chain
to finish before web cutover:

    bin/cake migrations migrate &&
    bin/cake schema_cache clear &&
    bin/cake updateDatabase &&
    bin/cake platform_migrate migrate &&
    bin/cake schema_cache clear --connection platform &&
    bin/cake platform secrets import-env &&
    bin/cake platform backup-keys ensure --allow-read-only &&
    bin/cake tenant migrate --all --include-suspended --fail-fast &&
    bin/cake cache clear _cake_model_

Pending active or suspended tenant migrations create their normal recovery
marker and encrypted backup. Current tenants are inspected and skipped without
another backup. One tenant failure blocks cutover, and rerunning resumes by
reinspecting state.

The optional release-manifest and nightly migration-drill tools are not wired
into the current deployment workflow. Use them only as explicit staging/pilot
steps unless the workflow is changed to generate and consume the manifest.

## Rollback boundaries

The deployment workflow captures the current web and Container Apps Job
definitions before cutover. The worker cutover helper can restore those runtime
definitions and re-enable compatibility schedules. That rollback:

- does not reverse an application, platform, or tenant migration;
- does not restore tenant application data;
- does not make an older image compatible with a newer schema; and
- does not undo a database-backed secret rotation.

Before shifting traffic back to an older revision, confirm its supported schema
and data contract. If data recovery is required, use the managed backup/restore
workflow and its audit/TOTP/suspension guardrails. Never assume image rollback is
database rollback.

## Image tags and evidence

| Reference | Meaning |
| --- | --- |
| `dev-<short-sha>` | Commit-specific release candidate |
| `poc-<sha-or-dispatch>-<run>` | ACR deployment tag for a POC run |
| `poc-validated-<sha>` | Digest that completed the automated POC deployment |
| `vX.Y.Z` / semantic tags | GitHub Release references applied to the validated digest |
| `sha-<short-sha>` | Release commit reference |
| `dev`, `nightly`, `latest`, `beta` | Mutable convenience channels; not deployment evidence |

Use the digest attached to the GitHub Release and the active Container Apps
revision when recording evidence.

## Historical self-hosted maintenance

The `kmp` management tool and Docker/VPC, Fly.io, and Railway pages are
historical and unsupported for new deployments.

Facts that matter when maintaining an old installation:

- `kmp install` is retired and returns an error.
- `kmp update --check` checks registry availability; `kmp status` reports
  deployment health and version but does not check for updates.
- `kmp update` pulls/restarts through its provider and verifies health. It does
  not create a database backup.
- `kmp rollback` does not restore a database or reverse migrations. Several
  providers are stubs, and the Docker provider may have no recorded prior tag.
- Historical production-image startup migrations can continue after migration
  errors and are not safe substitutes for the managed deployment job.
- A legacy VPC SQL backup covers one MariaDB database only.

For an existing legacy deployment, take and verify an engine-appropriate backup,
pin an immutable version, rehearse in a clone, and define schema rollback
manually before changing production. The project does not provide a safe
one-command rollback for that architecture.
