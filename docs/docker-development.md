# Docker Development Environment

This project includes a multi-container Docker setup optimized for local development and agentic/hosted development environments.

## Quick Start

```bash
# Start the development environment
./dev-up.sh

# Stop the environment (preserves database)
./dev-down.sh

# Reset database to clean state
./dev-reset-db.sh

# Reset database and load seed data
./dev-reset-db.sh --seed
```

## Architecture

The setup uses Docker Compose with three separate containers:

| Service | Container | Purpose | Port |
|---------|-----------|---------|------|
| `app` | kmp-app | PHP 8.3 + Apache | 8080 |
| `db` | kmp-db | MariaDB 11 | 3306 |
| `mailpit` | kmp-mailpit | Email testing | 8025 (UI), 1025 (SMTP) |

### Benefits of Multi-Container Setup

- **Agentic-friendly**: Each service can be inspected/managed independently
- **Fast iteration**: Code changes reflect immediately (volume mounted)
- **Persistent data**: Database survives container restarts
- **Clean separation**: Easy to debug service-specific issues
- **Quick rebuilds**: Only rebuild what changed

## Usage

### Starting the Environment

```bash
# First time or after pulling changes
./dev-up.sh --build

# Normal startup (uses cached images)
./dev-up.sh
```

### Accessing the Application

- **Web App**: http://localhost:8080
- **Mailpit UI**: http://localhost:8025 (view sent emails)
- **MySQL**: localhost:3306 (user: `KMPSQLDEV`, password: `P@ssw0rd`)

### Running Commands in the Container

```bash
# Shell into the app container
docker compose exec app bash

# Run CakePHP commands
docker compose exec app bin/cake migrations status
docker compose exec app bin/cake migrations migrate

# Run Composer
docker compose exec app composer install
docker compose exec app composer require some/package

# Run tests
docker compose exec app vendor/bin/phpunit

# Run npm commands
docker compose exec app npm install
docker compose exec app npm run build
```

### Database Management

```bash
# Reset to clean state (runs migrations)
./dev-reset-db.sh

# Reset and load seed data
./dev-reset-db.sh --seed

# Connect to MySQL directly
docker compose exec db mysql -uKMPSQLDEV -pP@ssw0rd KMP_DEV

# View database logs
docker compose logs db
```

### Stopping the Environment

```bash
# Stop containers (preserves database volume)
./dev-down.sh

# Stop and DELETE all data (fresh start)
./dev-down.sh --volumes
```

## Configuration

### Environment Variables

Copy the example environment file and customize:

```bash
cp docker/.env.example .env
```

Available variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `MYSQL_ROOT_PASSWORD` | rootpassword | MySQL root password |
| `MYSQL_DB_NAME` | KMP_DEV | Database name |
| `MYSQL_USERNAME` | KMPSQLDEV | Database user |
| `MYSQL_PASSWORD` | P@ssw0rd | Database password |

### Xdebug

Xdebug is pre-configured for step debugging:

- Mode: `debug,develop`
- Port: 9003
- Host: `host.docker.internal`

Configure your IDE to listen on port 9003.

## Troubleshooting

### Container won't start

```bash
# Check logs
docker compose logs app
docker compose logs db

# Rebuild from scratch
docker compose down -v
./dev-up.sh --build
```

### Database connection issues

```bash
# Verify database is healthy
docker compose ps

# Check database logs
docker compose logs db

# Try connecting directly
docker compose exec db mysql -uroot -prootpassword -e "SHOW DATABASES;"
```

### Permission issues

```bash
# Fix permissions inside container
docker compose exec app chown -R www-data:www-data /var/www/html/logs /var/www/html/tmp
docker compose exec app chmod -R 775 /var/www/html/logs /var/www/html/tmp
```

### Clear all caches

```bash
docker compose exec app bin/cake cache clear_all
```

## Comparison with Devcontainer

| Feature | Docker Compose | Devcontainer |
|---------|---------------|--------------|
| IDE Integration | Any editor | VS Code required |
| Code Location | Local (mounted) | In container |
| Startup Time | ~30s | ~2-3 min |
| Agentic Dev | ✅ Excellent | ⚠️ Limited |
| Service Isolation | ✅ Separate containers | ❌ Single container |
| Database Persistence | ✅ Docker volume | ⚠️ Rebuilt each time |

## File Structure

```
docker/
├── Dockerfile.app      # PHP/Apache container
├── apache-vhost.conf   # Apache configuration
├── app_local.php       # CakePHP config for Docker
├── entrypoint.sh       # Container initialization script
└── .env.example        # Environment variable template

docker-compose.yml      # Service definitions
dev-up.sh              # Start environment
dev-down.sh            # Stop environment
dev-reset-db.sh        # Reset database
```
