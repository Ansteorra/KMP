# Railway Quick Start

Deploy KMP to Railway with managed MySQL — zero infrastructure configuration required.

[← Back to Deployment Guide](README.md)

## Prerequisites

- [Railway account](https://railway.app/)
- [`railway` CLI](https://docs.railway.app/develop/cli) installed and authenticated (optional for manual deploy)

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
3. Provision a MySQL service
4. Configure environment variables
5. Deploy the KMP Docker image

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
- **Port binding**: KMP listens on port 8080; Railway detects this automatically via the `PORT` environment variable

See [Troubleshooting](troubleshooting.md) for more common issues.
