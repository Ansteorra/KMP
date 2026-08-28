# Kingdom Management Portal (KMP)

KMP is a CakePHP 5 membership and operations platform for SCA kingdoms. The
hosted architecture is database-per-tenant: a platform PostgreSQL database owns
tenant routing and operations metadata, while each kingdom has an isolated
application database.

## Developer quick start

Docker Compose is the supported local workflow. It runs PHP 8.4/Apache,
PostgreSQL 16, Mailpit, pgAdmin, Vite dependencies, and the bounded platform
scheduler/queue worker.

```bash
./dev-up.sh --build
```

On first use, the script copies `app/config/.env.example` to
`app/config/.env`. Unless `KMP_RESET_DB_ON_UP=false`, every start resets and
seeds the local platform database plus two tenant databases.

| Service | Default URL |
| --- | --- |
| Primary tenant | <http://kmp.localhost:8080> |
| Second tenant | <http://kmp2.localhost:8080> |
| Mailpit | <http://localhost:8025> |
| pgAdmin | <http://localhost:5050> |

The reserved platform-admin host is
<http://platform.kmp.localhost:8080/platform-admin>. The portal is disabled by
default; enable it only when working on platform operations.

Run application tooling inside the app container:

```bash
docker compose exec app bash bin/verify.sh
docker compose exec app vendor/bin/phpunit --testsuite core-unit
docker compose exec app npm run test:js
docker compose exec app npm run dev
docker compose exec app bin/cake tenant migrate --all --include-suspended --status
```

Seeded developer accounts use `TestPassword`. See
[`app/tests/TestDataReference.md`](app/tests/TestDataReference.md) for stable
fixtures and personas. Never reuse development credentials outside the local
stack.

Useful lifecycle commands:

```bash
./dev-up.sh                 # start; reset and seed by default
./dev-reset-db.sh --seed    # rebuild platform and tenant databases
./dev-down.sh               # stop the stack
docker compose logs -f app scheduler
```

## Documentation

- [Developer documentation](docs/index.md)
- [Multi-tenant architecture](docs/3.1-multi-tenant-architecture.md)
- [Docker development](docs/docker-development.md)
- [Testing contract](app/docs/testing-suite.md)
- [Managed deployment and operations](docs/8-deployment.md)
- [Generated API reference portal](docs/api/index.md)

The legacy standalone installer and self-hosted deployment material remain only
as maintenance references. New hosted environments use the managed
multi-tenant deployment workflow.

## Repository map

| Path | Responsibility |
| --- | --- |
| `app/` | CakePHP application, first-party plugins, frontend, and tests |
| `docs/` | Published developer and operator documentation |
| `deploy/azure/` | Managed Azure platform templates and deployment scripts |
| `docker/` | Local and production container images and entrypoints |
| `installer/` | Retired standalone installer implementation |

Contributors should read [`AGENTS.md`](AGENTS.md) and the nearest child
`AGENTS.md` before changing a subtree.
