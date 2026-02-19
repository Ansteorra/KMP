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

## Supported Deployment Targets

- **Local/VPC** — Docker Compose + Caddy (auto-SSL)
- **Azure** — Container Apps + Azure DB for MySQL
- **AWS** — ECS Fargate + RDS MySQL + S3
- **Fly.io** — Fly Machines + Fly Postgres
- **Railway** — Railway containers + managed MySQL
- **VPS** — Any SSH-accessible host with Docker
