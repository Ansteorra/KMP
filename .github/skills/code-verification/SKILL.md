---
name: code-verification
description: Verify KMP changes with the current PHPUnit, Jest, Vite, PHPCS, PHPStan, and Playwright entry points.
---

# Verify KMP changes

Read `AGENTS.md`, `app/AGENTS.md`, and any nearer guide first. Run application commands from `app/` unless the command explicitly starts at the repository root.

## Choose the narrowest useful check

| Change | Minimum useful verification |
| --- | --- |
| PHP controller, model, service, policy | Targeted PHPUnit + PHPCS on changed PHP + relevant PHPStan |
| Stimulus behavior | Targeted Jest or `npm run test:js` |
| Vite imports or asset bundling | Jest as relevant + `npm run dev` |
| Template or browser interaction | Relevant PHPUnit/Jest + an appropriate Playwright lane |
| Migration, configuration, cross-cutting change | `bash bin/verify.sh` when practical |
| Documentation only | Link/path/fence checks and diff review |

For the full local gate:

```bash
cd app
bash bin/verify.sh
```

The script is the authority for current suites, skip budgets, seed checks, frontend build, PHPCS selection, PHPStan, and optional coverage or mutation modes. Do not copy test totals, baseline counts, or timing estimates into documentation.

## Focused commands

```bash
cd app
vendor/bin/phpunit path/to/Test.php
vendor/bin/phpunit --filter TestName
vendor/bin/phpcs path/to/ChangedFile.php
vendor/bin/phpstan analyse --no-progress
npm run test:js
npm run dev
```

Use project base test classes and seeded constants. Exercise authorization failures and tenant isolation as well as success paths.

## Browser verification

Playwright uses `app/playwright.config.cjs` and the BDD support under `app/tests/ui/`. Prefer the package scripts or:

```bash
cd app
bash bin/run-playwright-lane.sh smoke
bash bin/run-playwright-lane.sh journey
bash bin/run-playwright-lane.sh platform
bash bin/run-playwright-lane.sh uat
```

The wrapper and root startup helpers may reset or reseed local databases. Read their source and obtain authorization before running them. Never point reset-capable tests at shared, POC, or production data.

Use host-aware contexts: tenant traffic resolves by hostname, while platform administration uses the platform host and connection. Add explicit cross-tenant denial coverage for tenant-scoped changes.

## Quality rules

- Fix changed files manually; never run `phpcbf` across the repository.
- Do not add native type hints to inherited CakePHP/plugin methods solely from docblocks.
- Preserve CSRF/security tokens, authorization scopes, restore locks, and impersonation logging.
- For UI changes, verify WCAG 2.2 AA keyboard, focus, label, ARIA, announcement, contrast, and non-color requirements.
- Treat install, startup, reset, migration, and seed commands as state-changing. Do not run them as troubleshooting guesses.

Mutation commands and scopes are defined in `app/composer.json` and `app/package.json`; consult those files before using them.
