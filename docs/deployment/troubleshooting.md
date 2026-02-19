# Troubleshooting

Solutions for common KMP deployment issues.

[← Back to Deployment Guide](README.md)

## Diagnostics

Start by gathering information:

```bash
# Deployment health and version
kmp status

# Application logs
kmp logs -f

# Docker-specific
docker compose ps
docker compose logs -f app
```

## Container Won't Start

**Symptoms**: Container exits immediately or enters a restart loop.

**Check logs:**
```bash
docker compose logs app | tail -50
```

**Common causes:**

| Cause | Fix |
|-------|-----|
| Missing `.env` file | Copy `.env.example` to `.env` and configure |
| Invalid `SECURITY_SALT` | Generate a new one: `openssl rand -hex 32` |
| Port conflict | Check if another service is using ports 80/443: `ss -tlnp` |
| Insufficient memory | Ensure at least 1 GB RAM is available |

## Database Connection Errors

**Symptoms**: "SQLSTATE[HY000] [2002] Connection refused" or similar.

**Check the database container:**
```bash
docker compose ps db
docker compose logs db | tail -20
```

**Common causes:**

| Cause | Fix |
|-------|-----|
| Database not ready | Wait 30 seconds after first start; MariaDB initializes on first run |
| Wrong credentials | Verify `MYSQL_PASSWORD` matches in `.env` |
| Wrong host | Use `MYSQL_HOST=db` for Docker Compose (the service name) |
| Database doesn't exist | The container creates the database on first run; check logs for init errors |

**Test connectivity manually:**
```bash
docker compose exec db mysql -u kmpuser -p"$MYSQL_PASSWORD" kmp -e "SELECT 1;"
```

## SSL Certificate Issues

**Symptoms**: Browser shows "connection not secure" or Caddy logs certificate errors.

**Common causes:**

| Cause | Fix |
|-------|-----|
| DNS not pointing to server | Verify with `dig your-domain.com` — must resolve to your server's IP |
| Ports 80/443 blocked | Open both ports in your firewall / security group |
| `DOMAIN` not set | Set `DOMAIN=your-domain.com` in `.env` |
| Rate limited | Let's Encrypt has [rate limits](https://letsencrypt.org/docs/rate-limits/) — wait and retry |

**Check Caddy logs:**
```bash
docker compose logs caddy | grep -i "tls\|cert\|error"
```

## Migration Failures

**Symptoms**: Application shows database errors after an update.

**Check migration status:**
```bash
docker compose exec app bin/cake migrations status
docker compose exec app bin/cake migrations status -p Activities
docker compose exec app bin/cake migrations status -p Officers
docker compose exec app bin/cake migrations status -p Awards
```

**Re-run migrations:**
```bash
docker compose exec app bin/cake migrations migrate
docker compose exec app bin/cake migrations migrate -p Activities
docker compose exec app bin/cake migrations migrate -p Officers
docker compose exec app bin/cake migrations migrate -p Awards
```

**If migrations are stuck**, restore from backup and try the update again:
```bash
kmp restore <backup-id>
kmp update
```

## Health Endpoint

KMP exposes a `/health` endpoint for monitoring:

```bash
curl -s https://your-domain.com/health | jq .
```

Expected response:
```json
{
  "status": "ok",
  "version": "1.2.3"
}
```

If the health endpoint doesn't respond, the application container is not running or not reachable.

## Performance Issues

**Symptoms**: Slow page loads, timeouts.

**Quick checks:**
```bash
# Container resource usage
docker stats

# Check database slow queries
docker compose exec db mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SHOW PROCESSLIST;"
```

**Common fixes:**
- Increase container memory limits in `docker-compose.yml`
- Check if the database needs more buffer pool: adjust `innodb_buffer_pool_size` in `mariadb.cnf`
- Verify storage I/O isn't saturated (especially on cloud VMs with limited IOPS)

## Fly.io Specific

```bash
fly status              # App status
fly doctor              # Diagnose issues
fly ssh console         # SSH into container
fly postgres connect    # Connect to Postgres
```

## Railway Specific

- Check the Railway dashboard for deploy logs and service status
- Verify MySQL reference variables resolve correctly (e.g., `${{MySQL.MYSQLHOST}}`)
- Ensure the `PORT` environment variable is not overridden (Railway sets it automatically)

## Getting Help

1. **Check the logs** — most issues are visible in `kmp logs` or `docker compose logs`
2. **Search existing issues** — [github.com/jhandel/KMP/issues](https://github.com/jhandel/KMP/issues)
3. **Open a new issue** — include your platform, KMP version (`kmp status`), and relevant log output
