---
name: code-verification
description: Runs the current KMP PHPUnit, Jest, Vite, PHPCS, PHPStan, and Playwright checks with tenant-aware safety.
---

# Verify KMP changes

Read and follow `.github/skills/code-verification/SKILL.md`. That file is the canonical verification workflow; do not duplicate test totals, timing estimates, or baseline counts here.

Key guardrails:

- Run application commands from `app/` unless the canonical guide says otherwise.
- Use the narrowest check that covers the change; use `bash bin/verify.sh` for cross-cutting changes when practical.
- Playwright uses `app/playwright.config.cjs` and host-aware tenant/platform contexts.
- Root startup, lane, reset, migration, and seed helpers can mutate local data. Read their source and obtain authorization before running them.
- Fix style issues in changed files manually; never run `phpcbf` repository-wide.
