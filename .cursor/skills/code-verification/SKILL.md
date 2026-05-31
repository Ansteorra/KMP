---
name: code-verification
description: Runs KMP quality checks (PHPUnit, Jest, Vite, PHPCS, PHPStan) and guides test writing. Use when verifying changes, before PRs, after implementation, or when the user asks to run tests or verify production readiness.
---

# Code Verification for KMP

Run commands from `app/` unless noted. Repo root paths use relative notation.

## Quick verification

```bash
cd app && bash bin/verify.sh
```

Runs PHPUnit, Jest, Vite build, PHPCS (changed files), PHPStan.

## Individual checks

| Check | Command | Expected |
|-------|---------|----------|
| PHPUnit | `composer test` | 1018+ tests, 0 failures |
| Jest | `npm run test:js` | 27+ tests, 0 failures |
| Build | `npm run dev` | Successful build |
| PHPCS | `composer cs-check` | 0 violations in changed files |
| PHPStan | `composer stan` | 1 known baseline error only |

**Do not** use `composer test --testsuite all` — incomplete suite (~509/1018).

## Minimum by change type

| Change | Minimum |
|--------|---------|
| PHP (controller/model/service/policy) | PHPUnit + PHPCS + PHPStan |
| Stimulus JS | Jest + build |
| Template | PHPUnit feature tests + build |
| Migration | Full PHPUnit + E2E |
| Config | Full `verify.sh` |
| CSS only | Build |

## E2E

```bash
bash dev-up.sh   # repo root
cd app
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8080 npm run test:ui
```

## Database reset

```bash
sudo bash reset_dev_database.sh   # repo root
```

## Test patterns

- Controller: extend `HttpIntegrationTestCase`; enable CSRF/security tokens; `authenticateAsSuperUser()` in setUp
- Model/service: extend `BaseTestCase`; use seed IDs from test support classes
- Plugin: `PluginIntegrationTestCase` with `PLUGIN_NAME`
- Stimulus: `tests/js/controllers/*-controller.test.js`
- BDD: `tests/ui/bdd/@feature-name/` with playwright-bdd

Full examples and edge-case checklists: `.github/skills/code-verification/SKILL.md`

## Mutation testing

- JS: `npm run test:mutate` (security controllers) or `test:mutate:all`
- PHP: `composer mutate` or `composer mutate:policy`

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Table not found | `reset_dev_database.sh` |
| Jest module error | `cd app && npm install` |
| Playwright fails | `bash dev-up.sh` |
| PHPCS on your files | Fix manually — never `phpcbf` repo-wide |
