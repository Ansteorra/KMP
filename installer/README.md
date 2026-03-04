# KMP Installer

Standalone management tool for the Kingdom Management Portal (KMP).

## Quick Install

```bash
curl -fsSL https://raw.githubusercontent.com/jhandel/KMP/main/installer/scripts/install.sh | bash
```

## Usage

```
kmp install              # Deploy KMP to a new environment
kmp update [--channel X] # Check & apply updates
kmp status               # Show deployment health
kmp logs [--follow]      # View application logs
kmp backup [--now]       # Create a backup
kmp restore <backup-id>  # Restore from backup
kmp rollback             # Revert to previous version
kmp config               # View/edit deployment config
kmp self-update          # Update this tool
kmp version              # Show versions
```

## Building

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
