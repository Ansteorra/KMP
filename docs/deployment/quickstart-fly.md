# Fly.io Quick Start

Deploy KMP to Fly.io's global edge network with managed Postgres.

[← Back to Deployment Guide](README.md)

## Prerequisites

- [Fly.io account](https://fly.io/app/sign-up)
- [`flyctl` CLI](https://fly.io/docs/hands-on/install-flyctl/) installed and authenticated
- A credit card on file (Fly.io requires one even for free-tier usage)

## Option A: Automated Install (Recommended)

```bash
# Install the KMP management tool
curl -fsSL https://raw.githubusercontent.com/jhandel/KMP/main/installer/scripts/install.sh | bash

# Launch the interactive installer
kmp install
```

Select **Fly.io** when prompted. The wizard will:
1. Authenticate with your Fly.io account (or prompt you to log in)
2. Create a Fly app and Fly Postgres cluster
3. Set required secrets (database URL, security salt)
4. Deploy the KMP Docker image
5. Output your app URL

## Option B: Manual Deployment

### Create the App

```bash
fly launch --image ghcr.io/jhandel/kmp:latest --no-deploy
```

### Provision Postgres

```bash
fly postgres create --name kmp-db
fly postgres attach kmp-db
```

### Set Secrets

```bash
fly secrets set \
  SECURITY_SALT=$(openssl rand -hex 32) \
  APP_NAME=KMP \
  DEBUG=false
```

The `DATABASE_URL` secret is automatically set by `fly postgres attach`.

### Deploy

```bash
fly deploy --image ghcr.io/jhandel/kmp:latest
```

### Verify

```bash
fly status
fly open
```

## Custom Domain

```bash
# Add your domain
fly certs create kmp.example.com

# Show DNS instructions
fly certs show kmp.example.com
```

Point your DNS CNAME to the address shown, then Fly.io will provision a TLS certificate automatically.

## Scaling

```bash
# Scale to 2 instances
fly scale count 2

# Scale VM size
fly scale vm shared-cpu-2x

# View current scale
fly scale show
```

## Environment Variables

Set additional configuration via secrets:

```bash
fly secrets set EMAIL_SMTP_HOST=smtp.example.com
fly secrets set EMAIL_SMTP_PORT=587
fly secrets set EMAIL_SMTP_USERNAME=user@example.com
fly secrets set EMAIL_SMTP_PASSWORD=your-password
```

See the [Configuration Reference](configuration.md) for all available variables.

## Health Check

Fly.io automatically monitors the `/health` endpoint. You can also check manually:

```bash
curl -s https://your-app.fly.dev/health
```

## Logs

```bash
fly logs           # Stream live logs
fly logs -i abc123 # Logs from a specific instance
```

## Updates

```bash
# Using the management tool
kmp update

# Or manually
fly deploy --image ghcr.io/jhandel/kmp:latest
```

See [Updating & Rollback](updating.md) for release channels and rollback procedures.

## Troubleshooting

```bash
fly status         # App status and instances
fly doctor         # Diagnose common issues
fly ssh console    # SSH into the running container
```

See [Troubleshooting](troubleshooting.md) for common issues.
