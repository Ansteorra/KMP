
# KingdomMangementPortal
![CodeRabbit Pull Request Reviews](https://img.shields.io/coderabbit/prs/github/jhandel/KMP?utm_source=oss&utm_medium=github&utm_campaign=jhandel%2FKMP&labelColor=171717&color=FF570A&link=https%3A%2F%2Fcoderabbit.ai&label=CodeRabbit+Reviews)

Membership management system for SCA Kingdoms.

Please review the wiki for solution details https://github.com/Ansteorra/KMP/wiki

## Local Development

Local Docker Compose is the default development workflow. Your source stays in this folder, while PHP, Apache, MariaDB, Mailpit, Node, Xdebug, queue workers, and scheduled CakePHP cron jobs run in containers.

```bash
./dev-up.sh --build
```

After the app is healthy, `dev-up.sh` runs `dev-reset-db.sh --seed` so the database matches the current code and seeded dev users. The app is available at http://localhost:8080, Mailpit at http://localhost:8025, and MariaDB at 127.0.0.1:3306. Run Composer, CakePHP, npm, and tests inside the app container:

```bash
docker compose exec app bin/cake migrations status
docker compose exec app npm run build
docker compose exec app vendor/bin/phpunit
```

See [Docker Development](docs/docker-development.md) for host aliases, Xdebug setup, scheduled jobs, database reset, and troubleshooting.

## Deployment

KMP is moving to a managed multi-tenant hosting model. The standalone installer is retired for new environments and kept in the repository as archived reference only.

- 📖 [Archived self-hosted deployment reference](docs/deployment/README.md)
- 🛠️ [Legacy installer implementation notes](installer/README.md)

Dev users all have the password "TestPassword"

Dev Users : 
* admin@amp.ansteorra.org - System super user
* agatha@ampdemo.com - Local MoAS
* bryce@ampdemo.com - Local Seneschal
* caroline@ampdemo.com - Regional Seneschal
* devon@ampdemo.com - Regional Armored
* eirik@ampdemo.com - Kingdom Seneschal
* garun@ampdemo.com - Kingdom Rapier
* haylee@ampdemo.com - Kingdom MoAS
* iris@ampdemo.com - Basic User
* jael@ampdemo.com - Pricipality Coronet
* kal@ampdemo.com - Local Landed Nobility with a Canton
* forest@ampdemo.com - Crown
* leonard@ampdemo.com - Local Landed Nobility with Stronghold
* mel@ampdemo.com - Local Exchequer and Kingdom Social Media

## Utility Scripts

### dev-up.sh / dev-down.sh
Starts or stops the local Docker Compose development stack:
```bash
./dev-up.sh
./dev-down.sh
```

### dev-reset-db.sh
Resets the Docker development database:
```bash
./dev-reset-db.sh
./dev-reset-db.sh --seed
```

### fix_permissions.sh
Fixes file permissions for Apache web server access. Run this if you encounter permission errors with logs, tmp, or images directories:
```bash
./fix_permissions.sh
```

### reset_dev_database.sh
Resets the development database to a clean state with seed data:
```bash
./reset_dev_database.sh
```

### load_test.sh
Runs performance sizing benchmarks (route latency, concurrency, and DB query profile) against the application:
```bash
./load_test.sh
```

Optional environment overrides:
```bash
KMP_BASE_URL=http://127.0.0.1:8080 \
KMP_LOGIN_EMAIL=admin@amp.ansteorra.org \
KMP_LOGIN_PASSWORD=TestPassword \
KMP_CONCURRENCY_LEVELS=1,5,10,20 \
KMP_CPU_TARGET_UTIL_PCT=70 \
KMP_MEMORY_TARGET_UTIL_PCT=80 \
./load_test.sh
```

### security-checker.sh
Runs security checks on the application:
```bash
./security-checker.sh
```

### create_erd.sh
Generates Entity Relationship Diagrams for the database schema:
```bash
./create_erd.sh
```

### make_amp_seed_db.sh
Creates a seed database for the application:
```bash
./make_amp_seed_db.sh
```

### merge_from_upstream.sh
Merges changes from the upstream repository:
```bash
./merge_from_upstream.sh
```
