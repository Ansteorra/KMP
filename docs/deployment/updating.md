# Updating & Rollback

How to keep your KMP deployment up to date and recover from failed updates.

[← Back to Deployment Guide](README.md)

## How Updates Work

KMP uses pre-built Docker images published to `ghcr.io/jhandel/kmp`. Updating is:

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
| `dev` | `dev` | Development | Latest from `main` branch |
| `nightly` | `nightly` | Nightly | Bleeding edge |

Switch channels:

```bash
kmp update --channel beta
```

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
fly deploy --image ghcr.io/jhandel/kmp:latest
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
fly deploy --image ghcr.io/jhandel/kmp:v1.2.3
```

## Best Practices

- **Always back up before updating** — `kmp update` does this automatically; for manual updates, run `kmp backup` first
- **Test on staging first** — use the `beta` channel on a staging environment before updating production
- **Monitor after updates** — check `kmp status` and `kmp logs` after applying an update
- **Pin versions in production** — for critical deployments, set `KMP_IMAGE_TAG` to a specific version tag rather than `latest`
