# KMP VPC/Self-Hosted Deployment

Production-ready Docker Compose stack for deploying KMP on a VPC or self-hosted server.

## Quick Start

```bash
# 1. Copy this directory to your server
scp -r deploy/vpc/ user@server:/opt/kmp/

# 2. Configure environment
cd /opt/kmp
cp .env.example .env
nano .env   # Set DOMAIN, passwords, and SECURITY_SALT

# 3. Start the stack
docker compose up -d

# 4. Visit your site
open https://your-domain.com
```

## What's Included

| File                  | Purpose                                      |
|-----------------------|----------------------------------------------|
| `docker-compose.yml`  | Production stack (app + MariaDB + Caddy)     |
| `Caddyfile`           | Reverse proxy with automatic HTTPS           |
| `.env.example`        | Configuration template                       |
| `mariadb.cnf`         | Tuned MariaDB settings                       |
| `backup.sh`           | Database backup with optional cloud upload   |
| `restore.sh`          | Database restore from backup                 |

## Configuration

### Required Settings

Edit `.env` and set these values:

```bash
DOMAIN=kmp.example.com                          # Your domain (for SSL)
SECURITY_SALT=$(openssl rand -hex 32)           # Application security salt
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 24)  # DB root password
MYSQL_PASSWORD=$(openssl rand -base64 24)       # DB app password
```

### Optional Settings

- **Email**: Set `EMAIL_SMTP_*` variables to enable outbound email
- **Storage**: Set `DOCUMENT_STORAGE_ADAPTER` to `azure` or `s3` for cloud storage
- **Image tag**: Set `KMP_IMAGE_TAG` to pin a specific release

## Updates

```bash
docker compose pull
docker compose up -d
```

## Backups

### Create a Backup

```bash
./backup.sh                      # Local backup to ./backups/
./backup.sh --upload s3           # Backup and upload to S3
./backup.sh --upload azure        # Backup and upload to Azure Blob
```

### Restore from Backup

```bash
./restore.sh backups/2026-02-19-030000.sql.gz
```

### Automated Backups (Cron)

```bash
# Daily at 3 AM, keep 30 days
0 3 * * * /opt/kmp/backup.sh --upload local >> /var/log/kmp-backup.log 2>&1
```

## Architecture

```
Internet → Caddy (:80/:443) → App (:8080) → MariaDB (:3306)
              ↑ automatic HTTPS        ↑ internal only
```

- **Caddy** handles TLS termination with automatic Let's Encrypt certificates
- **App** listens on `127.0.0.1:8080` (not exposed to the internet directly)
- **MariaDB** is only accessible from the app container

## Troubleshooting

```bash
# Check service status
docker compose ps

# View logs
docker compose logs -f app
docker compose logs -f caddy
docker compose logs -f db

# Restart a service
docker compose restart app

# Full reset (preserves data volumes)
docker compose down && docker compose up -d
```

## Further Reading

- [Quickstart Guide](https://github.com/jhandel/KMP/blob/main/docs/deployment/quickstart-vpc.md)
- [KMP Documentation](https://github.com/jhandel/KMP/blob/main/docs/)
