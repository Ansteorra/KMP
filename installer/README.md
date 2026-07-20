# KMP Installer (Archived)

Archived standalone management tool for legacy self-hosted Kingdom Management Portal (KMP) deployments.

> The installer is retired for new deployments. KMP is moving to a managed multi-tenant hosting model. This directory is kept so the self-hosted implementation details are not lost.

## Historical Install Bootstrap

```bash
curl -fsSL https://raw.githubusercontent.com/jhandel/KMP/main/installer/scripts/install.sh | bash
```

## Legacy Usage

```
kmp install              # Retired for new deployments
kmp update [--channel X] # Legacy self-hosted maintenance
kmp status               # Legacy self-hosted health view
kmp logs [--follow]      # Legacy self-hosted logs
kmp backup [--now]       # Legacy self-hosted backup
kmp restore <backup-id>  # Legacy self-hosted restore
kmp rollback             # Legacy self-hosted rollback
kmp config               # Legacy self-hosted config
kmp self-update          # Update this archived tool
kmp version              # Show versions
```

## Building (Archive / Maintenance)

```bash
make build          # Build for current platform
make all            # Cross-compile for all platforms
make install        # Install to $GOPATH/bin
```

## Testing

```bash
make test           # Full installer test suite
make test-sidecar   # Fast sidecar-only tests (no Docker daemon required)
```

### Sidecar test workflow (recommended)

Use this loop while changing `internal/updater`:

1. `make test-sidecar` after each change (fast unit checks of update/rollback state transitions).
2. `go test ./...` before commit (ensures no regressions outside updater package).
3. Optional smoke run with Docker Compose in a dev environment for end-to-end validation.

## Supported Deployment Targets

- **Local/VPC** — Docker Compose + Caddy (auto-SSL)
- **Azure** — Container Apps + Azure DB for MySQL
- **AWS** — ECS Fargate + RDS MySQL + S3
- **Fly.io** — Fly Machines + Fly Postgres
- **Railway** — Railway containers + optional managed MySQL/Redis (requires `railway` CLI + `railway login`)
- **VPS** — Any SSH-accessible host with Docker
