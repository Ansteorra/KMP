# Backup & Restore

Protect your KMP data with automated and on-demand backups.

[← Back to Deployment Guide](README.md)

## Quick Reference

| Command | Description |
|---------|-------------|
| `kmp backup` | Create a backup now |
| `kmp restore <id>` | Restore from a specific backup |
| `kmp backup --list` | List available backups |

## Creating Backups

### Using the Management Tool

```bash
kmp backup
```

This creates a compressed database dump and stores it locally. The output includes a backup ID for use with `kmp restore`.

### Using the VPC Backup Script

If deployed with `deploy/vpc/`, use the included backup script:

```bash
cd /opt/kmp

# Local backup (saved to ./backups/)
./backup.sh

# Backup and upload to S3
./backup.sh --upload s3

# Backup and upload to Azure Blob Storage
./backup.sh --upload azure
```

## Automated Backup Schedule

### Cron (VPC / Self-Hosted)

```bash
# Daily at 3 AM
0 3 * * * /opt/kmp/backup.sh >> /var/log/kmp-backup.log 2>&1

# Daily at 3 AM with S3 upload
0 3 * * * /opt/kmp/backup.sh --upload s3 >> /var/log/kmp-backup.log 2>&1
```

### Fly.io

Use Fly.io's built-in Postgres snapshots, which are taken automatically. You can also create on-demand snapshots:

```bash
fly postgres backup create --app kmp-db
fly postgres backup list --app kmp-db
```

### Railway

Railway provides automatic MySQL backups. Check the Railway dashboard under your MySQL service for backup management.

## Cloud Storage for Backups

### Amazon S3

Set these environment variables before running backup with `--upload s3`:

```bash
export AWS_ACCESS_KEY_ID=your-key
export AWS_SECRET_ACCESS_KEY=your-secret
export AWS_REGION=us-east-1
export AWS_BUCKET=kmp-backups
```

### Azure Blob Storage

Set these environment variables before running backup with `--upload azure`:

```bash
export AZURE_STORAGE_CONNECTION_STRING="DefaultEndpointsProtocol=https;AccountName=..."
export AZURE_BACKUP_CONTAINER=kmp-backups
```

## Restoring from Backup

### Using the Management Tool

```bash
# List available backups
kmp backup --list

# Restore a specific backup
kmp restore 2026-02-19-030000
```

### Using the VPC Restore Script

```bash
cd /opt/kmp
./restore.sh backups/2026-02-19-030000.sql.gz
```

### Manual Restore

```bash
# Decompress and import
gunzip -c backup.sql.gz | docker compose exec -T db mysql -u root -p"$MYSQL_ROOT_PASSWORD" kmp
```

> **Warning**: Restoring a backup will overwrite all current data. Always confirm you're restoring to the correct environment.

## What's Included in a Backup

- Full database dump (all tables, data, and schema)
- Backup metadata (timestamp, KMP version, database version)

**Not included**: uploaded documents (if using local storage). For complete disaster recovery, also back up:
- The `images/uploaded/` directory (local storage), or verify cloud storage replication (Azure/S3)
- Your `.env` configuration file

## Disaster Recovery

1. Provision a new server and install Docker
2. Copy the `deploy/vpc/` templates and your `.env` file
3. Start the stack: `docker compose up -d`
4. Restore the database: `./restore.sh backups/latest.sql.gz`
5. Restore uploaded documents from cloud storage or file backup
6. Verify the application: `curl -s https://your-domain.com/health`

## Backup Retention

Manage backup retention manually or via cron:

```bash
# Keep only the last 30 days of local backups
find /opt/kmp/backups/ -name "*.sql.gz" -mtime +30 -delete
```

For cloud storage, configure lifecycle rules in your S3 bucket or Azure storage account to automatically expire old backups.
