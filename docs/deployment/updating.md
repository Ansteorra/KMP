# Updating & Rollback

How to keep your KMP deployment up to date and recover from failed updates.

[← Back to Deployment Guide](README.md)

## How Updates Work

KMP uses pre-built Docker images published to `ghcr.io/ansteorra/kmp`. Updating is:

1. **Pull** the new image
2. **Restart** the container(s)
3. **Run migrations** automatically on startup

No building, compiling, or dependency installation is needed on your server.

## Using `kmp update`

The simplest way to update:

```bash
kmp update
```

This will:
- Check the current release channel for a newer version
- Show you a changelog summary
- Pull the new image
- Create an automatic backup before applying
- Restart the application
- Verify the health endpoint responds

### Update with a Specific Channel

```bash
kmp update --channel beta     # Update to latest beta
kmp update --channel release  # Update to latest stable (default)
```

### Check for Updates Without Applying

```bash
kmp status
```

The status command shows your current version and whether an update is available.

## Release Channels

| Channel | Image Tag | Stability | Use Case |
|---------|-----------|-----------|----------|
| `release` | `latest` | Stable | Production (default) |
| `beta` | `beta` | Pre-release | Testing upcoming features |
| `dev` | `dev` | Development | Latest gated build from the `dev` branch |
| `nightly` | `nightly` | Nightly | Bleeding edge |

Switch channels:

```bash
kmp update --channel beta
```

## Managed Azure Release Procedure

The official Azure environments use gated GitHub Actions workflows and immutable
image digests.

### Release to POC

1. Merge the approved pull request into `main`.
2. Fast-forward the official `dev` branch to the exact `main` commit selected for
   release and push `dev`.
3. Wait for `Nightly / Dev Docker Image` to complete. It runs the full quality
   gates before building the image; a failed gate prevents image creation and POC
   deployment.
4. Wait for `POC / Deploy to Azure` to import the image digest, run the worker
   canary and migrations, cut over the web revision, and align retained jobs.
5. Validate POC health, tenant login, worker/queue processing, and the release's
   changed user journeys before promoting the commit.

### Release to Production

1. Create and publish a non-prerelease GitHub Release with a `v*` tag, targeting
   the exact commit validated in POC.
2. Wait for `Release Docker Image` to rerun the full quality gates against the
   tag, build the release image, and verify its health.
3. Review the pending `production` environment deployment and approve it.
4. The deployment imports the exact tested digest into production ACR, verifies
   the digest, runs the worker canary and migrations, cuts over the web revision,
   and aligns retained jobs.
5. Confirm production readiness, tenant hosts, login, queue processing, and the
   active Container Apps image digest.

Do not create a production release from a commit that differs from the POC-tested
commit. Prereleases and published tags that do not start with `v` cannot promote
to the production Azure environment.

### Agent shorthand

Repository agents treat these requests as complete operational instructions:

- **"Push to dev"** performs the POC procedure only and verifies the resulting
  deployment. It does not change production.
- **"Do a release"** first updates the user-facing `app/CHANGELOG.md`, validates
  that exact changelog-bearing commit in POC, publishes a stable `v*` GitHub
  Release using the same changelog section as its release notes, waits for
  production approval, and verifies the rollout.

The in-app changelog and GitHub Release must share one canonical set of
user-facing notes. Updating notes after POC validation changes the release
candidate and requires another POC deployment.

## Manual Updates by Platform

### Docker / VPC

```bash
cd /opt/kmp
docker compose pull
docker compose up -d
```

In-app web-triggered updates for Docker/VPC are currently disabled; use `kmp update` or the manual compose commands above.

### Fly.io

```bash
fly deploy --image ghcr.io/ansteorra/kmp:latest
```

### Railway

Redeploy from the Railway dashboard or CLI:

```bash
railway up
```

### Shared Hosting (No Root Access)

Use a manual, least-privilege workflow:

1. Upload the new application release package with your hosting panel/FTP.
2. Run application database migrations using the hosting-provided job/console tooling (if available).
3. Verify `/health` responds and key admin workflows load successfully.

Host-managed components (web server/proxy, database engine) are upgraded by your hosting provider, not by KMP.

## Rollback

If an update causes issues, roll back to the previous version:

```bash
kmp rollback
```

This will:
1. Stop the current deployment
2. Restore the previous image version
3. Restore the pre-update database backup (if migrations were applied)
4. Restart the application
5. Verify health

### Manual Rollback (Docker / VPC)

```bash
cd /opt/kmp

# Pin to a specific version
KMP_IMAGE_TAG=v1.2.3 docker compose up -d

# Or restore from backup first
./restore.sh backups/2026-02-19-030000.sql.gz
KMP_IMAGE_TAG=v1.2.3 docker compose up -d
```

### Manual Rollback (Fly.io)

```bash
# Deploy a specific version
fly deploy --image ghcr.io/ansteorra/kmp:v1.2.3
```

## Best Practices

- **Always back up before updating** — `kmp update` does this automatically; for manual updates, run `kmp backup` first
- **Test on staging first** — use the `beta` channel on a staging environment before updating production
- **Monitor after updates** — check `kmp status` and `kmp logs` after applying an update
- **Pin versions in production** — for critical deployments, set `KMP_IMAGE_TAG` to a specific version tag rather than `latest`
