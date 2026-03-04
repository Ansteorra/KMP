# Configuration Reference

Complete reference for all KMP deployment configuration options.

[← Back to Deployment Guide](README.md)

## Environment Variables

### Required

| Variable | Description | Example |
|----------|-------------|---------|
| `DOMAIN` | Domain name for SSL certificate | `kmp.example.com` |
| `SECURITY_SALT` | Application security salt (hex string) | `openssl rand -hex 32` |
| `MYSQL_ROOT_PASSWORD` | Database root password | `openssl rand -base64 24` |
| `MYSQL_PASSWORD` | Database application user password | `openssl rand -base64 24` |

### Application

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_NAME` | `KMP` | Application display name |
| `DEBUG` | `false` | Enable debug mode (`true`/`false`) |
| `KMP_IMAGE_TAG` | `latest` | Docker image tag (version or channel) |
| `KMP_DEPLOY_PROVIDER` | `docker` | Deployment provider identifier (`docker`, `vpc`, `railway`, `fly`, `aws`, `azure`, `shared`) |
| `DEPLOYMENT_PROVIDER` | `docker` | App runtime provider override (falls back to `KMP_DEPLOY_PROVIDER` when unset) |

### Database

| Variable | Default | Description |
|----------|---------|-------------|
| `MYSQL_HOST` | `db` | Database hostname |
| `MYSQL_DB_NAME` | `kmp` | Database name |
| `MYSQL_USERNAME` | `kmpuser` | Database username |
| `MYSQL_PASSWORD` | — | Database password (required) |
| `MYSQL_ROOT_PASSWORD` | — | Database root password (required for VPC) |

### Email (SMTP)

| Variable | Default | Description |
|----------|---------|-------------|
| `EMAIL_SMTP_HOST` | — | SMTP server hostname |
| `EMAIL_SMTP_PORT` | `587` | SMTP server port |
| `EMAIL_SMTP_USERNAME` | — | SMTP authentication username |
| `EMAIL_SMTP_PASSWORD` | — | SMTP authentication password |

### Document Storage

| Variable | Default | Description |
|----------|---------|-------------|
| `DOCUMENT_STORAGE_ADAPTER` | `local` | Storage backend: `local`, `azure`, or `s3` |
| `AZURE_STORAGE_CONNECTION_STRING` | — | Azure Blob Storage connection string |
| `AWS_ACCESS_KEY_ID` | — | AWS access key for S3 storage |
| `AWS_SECRET_ACCESS_KEY` | — | AWS secret key for S3 storage |
| `AWS_REGION` | `us-east-1` | AWS region for S3 |
| `AWS_BUCKET` | — | S3 bucket name |

## Config File (`~/.kmp/config.yaml`)

The `kmp` management tool stores deployment configuration in `~/.kmp/config.yaml`. This file is created automatically by `kmp install`.

```yaml
# Example config.yaml
provider: docker
domain: kmp.example.com
channel: release
image: ghcr.io/jhandel/kmp:latest
backup:
  enabled: true
  schedule: "0 3 * * *"
  retention_days: 30
  upload: local          # local, s3, or azure
```

### Config Commands

```bash
kmp config               # View current configuration
```

## Database Configuration

### MySQL (Default)

KMP uses MySQL/MariaDB as its primary database. The bundled VPC stack includes MariaDB with tuned settings (`mariadb.cnf`):

- `innodb_buffer_pool_size = 256M`
- `max_connections = 100`
- Character set: `utf8mb4` with `utf8mb4_unicode_ci` collation

### Bring Your Own Database

To use an external MySQL database, set the `MYSQL_HOST`, `MYSQL_DB_NAME`, `MYSQL_USERNAME`, and `MYSQL_PASSWORD` environment variables to point to your managed database instance.

Requirements:
- MySQL 5.7+ or MariaDB 10.2+
- `utf8mb4` character set support
- A dedicated database and user for KMP

## Email / SMTP Setup

KMP requires SMTP for sending email notifications (password resets, warrant notices, etc.).

**Example for common providers:**

```bash
# Gmail (use App Password, not your Google password)
EMAIL_SMTP_HOST=smtp.gmail.com
EMAIL_SMTP_PORT=587
EMAIL_SMTP_USERNAME=your-email@gmail.com
EMAIL_SMTP_PASSWORD=your-app-password

# Amazon SES
EMAIL_SMTP_HOST=email-smtp.us-east-1.amazonaws.com
EMAIL_SMTP_PORT=587
EMAIL_SMTP_USERNAME=your-ses-smtp-username
EMAIL_SMTP_PASSWORD=your-ses-smtp-password

# Mailgun
EMAIL_SMTP_HOST=smtp.mailgun.org
EMAIL_SMTP_PORT=587
EMAIL_SMTP_USERNAME=postmaster@your-domain.com
EMAIL_SMTP_PASSWORD=your-mailgun-password
```

## Security Settings

| Setting | Recommendation |
|---------|---------------|
| `DEBUG` | Always `false` in production |
| `SECURITY_SALT` | Unique per deployment, at least 64 hex characters |
| SSL/TLS | Always enabled — Caddy (VPC) or platform-managed (PaaS) |
| Database passwords | Randomly generated, at least 24 characters |

## Storage Adapter Configuration

### Local Storage (Default)

Files are stored in the container's filesystem. Suitable for development and small deployments.

### Azure Blob Storage

See the [existing Azure Blob Storage documentation](../8-deployment.md#84-azure-blob-storage-configuration) for detailed setup instructions.

### Amazon S3

Set `DOCUMENT_STORAGE_ADAPTER=s3` and configure the `AWS_*` environment variables listed above.
