# Railway Quick Start

Deploy KMP to Railway with managed MySQL — zero infrastructure configuration required.

[← Back to Deployment Guide](README.md)

## Prerequisites

- [Railway account](https://railway.app/)
- [`railway` CLI](https://docs.railway.app/develop/cli) installed and authenticated (`railway login`)

## Option A: Automated Install (Recommended)

```bash
# Install the KMP management tool
curl -fsSL https://raw.githubusercontent.com/jhandel/KMP/main/installer/scripts/install.sh | bash

# Launch the interactive installer
kmp install
```

Select **Railway** when prompted. The wizard will:
1. Authenticate with your Railway account
2. Create a new Railway project
3. Optionally provision Railway MySQL/Redis services
4. Ask whether to use Railway-managed or existing database/cache services
5. Configure environment variables
6. Deploy the KMP Docker image

If your Railway CLI version does not support one of the automated provisioning commands, the installer will stop with a command-specific error so you can run that step manually and retry.

## Option B: Manual Deployment

### Create Project and MySQL Service

1. Go to [railway.app/new](https://railway.app/new)
2. Select **Deploy from Docker Image**
3. Enter: `ghcr.io/jhandel/kmp:latest`
4. Click **Add Service** → **MySQL** to add a managed database

### Configure Environment Variables

In the Railway dashboard, add these variables to the KMP service:

| Variable | Value |
|----------|-------|
| `MYSQL_HOST` | `${{MySQL.MYSQLHOST}}` (Railway reference) |
| `MYSQL_DB_NAME` | `${{MySQL.MYSQLDATABASE}}` |
| `MYSQL_USERNAME` | `${{MySQL.MYSQLUSER}}` |
| `MYSQL_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
| `SECURITY_SALT` | Generate with `openssl rand -hex 32` |
| `DEBUG` | `false` |
| `APP_NAME` | `KMP` |
| `REDIS_URL` | Optional if using existing Redis (for Railway Redis, use references to `${{Redis.REDISUSER}}`, `${{Redis.REDISPASSWORD}}`, `${{Redis.REDISHOST}}`, `${{Redis.REDISPORT}}`) |

### Using the CLI

```bash
# Link to your project
railway link

# Set secrets
railway variables set SECURITY_SALT=$(openssl rand -hex 32)
railway variables set DEBUG=false
railway variables set APP_NAME=KMP

# Deploy
railway up
```

## Custom Domain

1. In the Railway dashboard, go to your KMP service → **Settings** → **Networking**
2. Click **Generate Domain** for a `*.railway.app` subdomain, or
3. Click **Custom Domain** and enter your domain name
4. Update your DNS records as instructed

Railway provisions TLS certificates automatically for custom domains.

## Reverse Proxy on Railway

For standard KMP Railway deployments, deploy the **app image directly**. Railway's edge network terminates TLS and routes traffic; you do not need to run Caddy/nginx just for HTTPS.

Run a custom proxy only if you need advanced internal routing across multiple services.

## Scaling

Railway auto-scales within your plan limits. To configure:

1. Go to your service **Settings**
2. Adjust **Replicas** and **Resource Limits** as needed

## Health Check

Railway monitors your app automatically. The KMP `/health` endpoint is available at:

```bash
curl -s https://your-app.railway.app/health
```

## Logs

View logs in the Railway dashboard, or via CLI:

```bash
railway logs
railway logs --follow
```

## Updates

```bash
# Using the management tool
kmp update

# Or redeploy with the latest image
railway up
```

See [Updating & Rollback](updating.md) for full details.

## Troubleshooting

- **Build failures**: Railway deploys pre-built Docker images — ensure the image tag exists at `ghcr.io/jhandel/kmp`
- **Database connection**: Verify MySQL reference variables resolve correctly in the dashboard
- **Port binding**: KMP binds Apache to Railway's `PORT` environment variable at startup

See [Troubleshooting](troubleshooting.md) for more common issues.
