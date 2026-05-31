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

Run from `app/` unless noted.

| Check | Command | Notes |
|-------|---------|-------|
| Full suite | `bash bin/verify.sh` | PHPUnit + Jest + Vite + PHPCS + PHPStan |
| PHPUnit | `composer test` | Do **not** use `--testsuite all` (incomplete; runs ~509/1018) |
| Jest | `npm run test:js` | Stimulus controller tests |
| Build | `npm run dev` | Vite/Webpack asset build |
| PHPCS | `composer cs-check` | Changed files only in verify.sh |
| PHPStan | `composer stan` | Level 5; baseline handles pre-existing issues |
| E2E | `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8080 npm run test:ui` | Reset DB first if needed |

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

- Apache HTTP on port **8080** (not HTTPS locally)
- DB: see `app/config/.env` (`KMPSQLDEV` / `KMP_DEV`)
- Reset DB: `sudo bash reset_dev_database.sh` (repo root)
- Test password for seeded users: `TestPassword`

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
