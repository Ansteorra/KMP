# Docker / VPC Quick Start

Deploy KMP on any server with Docker — your own VPC, a cloud VM, or even a local machine.

[← Back to Deployment Guide](README.md)

## Prerequisites

- Docker Engine 24+ and Docker Compose v2
- A domain name pointing to your server (for automatic SSL)
- Ports 80 and 443 open

## Option A: Automated Install (Recommended)

```bash
# Install the KMP management tool
curl -fsSL https://raw.githubusercontent.com/jhandel/KMP/main/installer/scripts/install.sh | bash

# Launch the interactive installer
kmp install
```

Select **Docker (Local/VPC)** when prompted. The wizard will:
1. Ask for your domain name
2. Generate secure database passwords and security salt
3. Create the Docker Compose stack from `deploy/vpc/` templates
4. Start all services (App + MariaDB + Caddy)
5. Provision a TLS certificate via Let's Encrypt

## Option B: Manual Install

Use the templates in [`deploy/vpc/`](../../deploy/vpc/) directly.

```bash
# Copy templates to your server
scp -r deploy/vpc/ user@server:/opt/kmp/
ssh user@server

# Configure
cd /opt/kmp
cp .env.example .env
nano .env
```

### Required `.env` Settings

```bash
DOMAIN=kmp.example.com
SECURITY_SALT=$(openssl rand -hex 32)
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 24)
MYSQL_PASSWORD=$(openssl rand -base64 24)
```

### Start the Stack

```bash
docker compose up -d
```

See [`deploy/vpc/README.md`](../../deploy/vpc/README.md) for the full VPC template reference.

## Architecture

```
Internet → Caddy (:80/:443) → App (:8080) → MariaDB (:3306)
              ↑ automatic HTTPS        ↑ internal only
```

- **Caddy** handles TLS termination with automatic Let's Encrypt certificates
- **App** runs the KMP Docker image on port 8080 (not directly exposed)
- **MariaDB** is only accessible from the app container

## SSL / HTTPS

SSL is handled automatically by Caddy:

- Set `DOMAIN` in `.env` to your fully qualified domain name
- Ensure DNS points to your server **before** starting the stack
- Caddy obtains and renews Let's Encrypt certificates automatically
- HTTP traffic on port 80 is redirected to HTTPS

For local/development use, set `DOMAIN=localhost` to use Caddy's self-signed certificate.

## First Login

1. Visit `https://your-domain.com`
2. Complete the web-based setup wizard (create admin account, configure kingdom settings)
3. Log in with the admin credentials you just created

## Configuration

See the [Configuration Reference](configuration.md) for all available environment variables.

Key optional settings:
- **Email**: Set `EMAIL_SMTP_*` variables to enable outbound email
- **Storage**: Set `DOCUMENT_STORAGE_ADAPTER` to `azure` or `s3` for cloud document storage
- **Image tag**: Set `KMP_IMAGE_TAG` to pin a specific release version

## Scheduled Backups

Set up a cron job for automated backups:

```bash
# Daily at 3 AM, local backup
0 3 * * * /opt/kmp/backup.sh >> /var/log/kmp-backup.log 2>&1

# Daily at 3 AM, upload to S3
0 3 * * * /opt/kmp/backup.sh --upload s3 >> /var/log/kmp-backup.log 2>&1
```

Or use the management tool:

```bash
kmp backup
```

See [Backup & Restore](backup-restore.md) for full details.

## Updates

```bash
# Using the management tool
kmp update

# Or manually
docker compose pull
docker compose up -d
```

See [Updating & Rollback](updating.md) for full details.

## Troubleshooting

```bash
# Check service status
docker compose ps

# View logs
docker compose logs -f app

# Health check
curl -s https://your-domain.com/health
```

See [Troubleshooting](troubleshooting.md) for common issues.
