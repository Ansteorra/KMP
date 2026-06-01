# KMP Agent Guide

Kingdom Management Portal — agent context for Cursor and other AI coding tools.

## Guidance hierarchy

1. **Constitution** — `.specify/memory/constitution.md` (non-negotiable principles)
2. **Copilot instructions** — `.github/copilot-instructions.md` (patterns and examples)
3. **Cursor rules** — `.cursor/rules/*.mdc` (always-on and file-scoped conventions)
4. **Cursor skills** — `.cursor/skills/*/SKILL.md` (workflows; read when relevant)
5. **Shared skills** — `.github/skills/` (also symlinked under `.cursor/skills/`)
6. **Documentation** — `docs/` (architecture and usage)
7. **Memories** — `.github/copilot-memories.md` (verified facts with citations)

## Project overview

- **Stack**: CakePHP 5.x, PHP 8.1+, MySQL/MariaDB, Hotwired (Turbo + Stimulus), Bootstrap, Laravel Mix
- **App root**: `app/` (Composer, PHPUnit, npm, webroot)
- **Plugins**: `app/plugins/` (Awards, Activities, Waivers, Officers, Queue, …)
## Verification runbook

**Run checks inside the app container** (or via root scripts that exec into it). Paths below are container paths (`/var/www/html` = host `./app`).

| Check | Command | Notes |
|-------|---------|-------|
| Full suite | `docker compose exec app bash -lc 'bash bin/verify.sh'` | From repo root |
| Full suite (in container) | `bash bin/verify.sh` | After `docker compose exec app bash` |
| PHPUnit | `composer test` | Do **not** use `--testsuite all` (incomplete; runs ~509/1018) |
| Jest | `npm run test:js` | Stimulus controller tests |
| Build | `npm run dev` | Vite/Webpack asset build |
| PHPCS | `composer cs-check` | Changed files only in verify.sh |
| PHPStan | `composer stan` | Level 5; baseline handles pre-existing issues |
| E2E | `PLAYWRIGHT_SKIP_WEBSERVER=1 npm run test:ui` (default `http://kmp.localhost:8080`; fixture PHP via Docker on host) | Reset DB first: `./dev-reset-db.sh --seed` |

**Minimum by change type** (see `.cursor/skills/code-verification/SKILL.md`):

- PHP: PHPUnit + PHPCS + PHPStan
- JS/Stimulus: Jest + build
- Migrations: full PHPUnit + E2E
- Config: full `verify.sh`

## Dangerous patterns

- **Never** run `phpcbf` on the entire codebase — breaks LSP/type hints in policies and tables
- **Never** add native type hints to overridable `BasePolicy` methods (`$query`, `$entity`)
- **Keep** `face-api.js` at `^0.22.2` (downgrade weakens photo validation)
- **PHPUnit**: use `composer test`, not `--testsuite all`
- **PR merges**: Ansteorra/KMP disallows squash merges — use merge commits

## Dev environment

**Default workflow is Docker Compose** — source code lives in this repo on the host; containers bind-mount it and run PHP, Composer, Node, and the database stack. Do not assume the host has PHP/Composer/Node installed or can reach DB hostnames like `db` directly.

| What | Where / how |
|------|-------------|
| App source | `./app` on host → `/var/www/html` in container |
| Compose project | `kmp` (`docker-compose.yml` at repo root) |
| App container | `kmp-app` — run Composer, Cake, PHPUnit, npm here |
| DB container | `kmp-db` — PostgreSQL 16 (default local driver) |
| App URL | http://kmp.localhost:8080 (Apache HTTP, not HTTPS) |
| Test password | `TestPassword` (seeded users) |

### Root scripts (preferred — they wrap `docker compose`)

Run from **repo root**, not from inside the container:

| Script | Purpose |
|--------|---------|
| `./dev-up.sh` | Start stack; optionally `--build`; resets DB by default on first bring-up |
| `./dev-down.sh` | Stop stack (`--volumes` only when intentionally wiping DB volumes) |
| `./dev-reset-db.sh` | Drop/recreate DB, migrate, `updateDatabase`; rebuilds test DB schema |
| `./dev-reset-db.sh --seed` | Same + load `pg_seed_baseline.sql` demo data (full local dev dataset) |

After schema/migration changes that add **reference/seed data** (workflow definitions, state machines, permissions): run `./dev-reset-db.sh --seed` and confirm Cake seeds ran. PostgreSQL tests import schema-only dumps — configuration seeds must be wired like `InitWorkflowDefinitionsSeed` / `SeedManager` (see `app/tests/TestCase/Support/SeedManager.php`).

### In-container commands (when not using a root script)

```bash
docker compose exec app bash -lc 'composer test'
docker compose exec app bash -lc 'bash bin/verify.sh'
docker compose exec app bash -lc 'bin/cake migrations migrate -p Awards'
docker compose exec app bash -lc 'bash bin/setup_test_database.sh'
```

`node_modules` and Composer cache live in Docker volumes — use container commands for npm/Jest/Vite.

### Legacy (non-Docker)

- `sudo bash reset_dev_database.sh` — older host-Apache/MySQL path; prefer `./dev-reset-db.sh` when using Docker
- DB config: `app/config/.env`

## Test data

Seed SQL loaded via `SeedManager`; tests wrap in transactions (`BaseTestCase`). Constants like `self::ADMIN_MEMBER_ID` in test support classes. See `app/tests/TestDataReference.md`.

## Architecture essentials

- **Plugins**: self-contained under `app/plugins/`, registered in `config/plugins.php`
- **Services**: business logic in `src/Services/` or `plugins/*/src/Services/` — not controllers
- **Policies**: `src/Policy/` + plugin policies; authorization via CakePHP Authorization plugin
- **Frontend**: Turbo frames/streams + Stimulus controllers in `assets/js/controllers/`
- **No inline JS** in templates — Stimulus data attributes only

## Agent tooling map

| Harness | Location | Cursor equivalent |
|---------|----------|-------------------|
| GitHub Copilot instructions | `.github/copilot-instructions.md` | `.cursor/rules/kmp-*.mdc` |
| Copilot memories | `.github/copilot-memories.md` | This file + rules |
| Copilot skills | `.github/skills/` | Symlinks in `.cursor/skills/` |
| Claude Code skills | `.claude/skills/` | Prefer `.github/skills/playwright-cli` |
| Constitution | `.specify/memory/constitution.md` | `.cursor/rules/kmp-core.mdc` |

## When to use which skill

| Task | Skill |
|------|-------|
| Verify changes / write tests | `code-verification` |
| Security review / pen test | `security-audit` |
| Deploy nightly Azure env | `nightly-deploy` |
| Conventional commit | `git-commit` |
| Update user changelog | `sync-changelog` |
| Review docs quality | `double-check-docs` |
| Browser/E2E automation | `playwright-cli` |
| Install community skills | `install-skills` |
