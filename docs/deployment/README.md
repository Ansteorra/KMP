# KMP Deployment Guide

## Quick Start (60 seconds)

### Install the KMP manager:
```bash
curl -fsSL https://raw.githubusercontent.com/jhandel/KMP/main/installer/scripts/install.sh | bash
```

### Deploy:
```bash
kmp install
```

The interactive wizard will guide you through choosing a platform and configuring your deployment.

## Supported Platforms

| Platform | Type | Database | SSL | Difficulty |
|----------|------|----------|-----|------------|
| Docker (Local/VPC) | Self-hosted | Bundled MariaDB or BYO | Caddy (auto Let's Encrypt) | ⭐ Easy |
| Fly.io | PaaS | Fly Postgres | Automatic | ⭐ Easy |
| Railway | PaaS | Managed MySQL / Redis (optional) | Automatic (Railway edge TLS, no extra proxy required) | ⭐ Easy |
| Azure | Cloud | Azure DB for MySQL | Automatic | ⭐⭐ Moderate |
| AWS | Cloud | RDS MySQL | ALB/ACM | ⭐⭐ Moderate |
| VPS (SSH) | Self-hosted | Bundled MariaDB | Caddy | ⭐⭐ Moderate |
| Shared hosting (no root) | Traditional web host | Provider-managed or external | Provider-managed | ⭐⭐ Moderate |

## How It Works

1. Download the `kmp` management tool (single binary, no dependencies)
2. Run `kmp install` — the TUI wizard guides you through platform selection and configuration
3. KMP pulls a pre-built Docker image from ghcr.io (no building required)
4. The installer provisions infrastructure (database, storage, SSL) for your chosen platform
5. KMP runs — visit your domain to complete the web-based setup wizard

## Lifecycle Commands

| Command | Description |
|---------|-------------|
| `kmp install` | Deploy KMP to a new environment |
| `kmp update` | Check for and apply updates |
| `kmp status` | Show deployment health and version info |
| `kmp logs [-f]` | View application logs |
| `kmp backup` | Create a database backup |
| `kmp restore <id>` | Restore from a backup |
| `kmp rollback` | Revert to the previous version |
| `kmp config` | View deployment configuration |
| `kmp self-update` | Update the kmp tool itself |

## Release Channels

| Channel | Stability | Use Case |
|---------|-----------|----------|
| `release` | Stable | Production deployments (default) |
| `beta` | Pre-release | Testing upcoming features |
| `dev` | Development | Latest main branch |
| `nightly` | Nightly build | Bleeding edge |

## Architecture

The KMP deployment system uses pre-built Docker images:

```
GitHub Releases → ghcr.io/jhandel/kmp:{tag} → Your infrastructure
```

Every release is:
- Multi-architecture (amd64 + arm64)
- Smoke-tested in CI before publishing
- Tagged with version, channel, and SHA digest
- Immutable once published

## Platform-Specific Guides

- [Docker/VPC Quick Start](quickstart-vpc.md)
- [Fly.io Quick Start](quickstart-fly.md)
- [Railway Quick Start](quickstart-railway.md)
- [Azure Quick Start](quickstart-azure.md)
- [AWS Quick Start](quickstart-aws.md)
- [Updating & Rollback](updating.md)
- [Backup & Restore](backup-restore.md)
- [Configuration Reference](configuration.md)
- [Troubleshooting](troubleshooting.md)
