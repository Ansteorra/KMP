---
description: Verify KMP browser behavior with the supported Playwright BDD lanes and tenant-aware hosts
---

# Playwright verification for KMP

Use Playwright for browser flows, Turbo Frames, modals, navigation, accessibility interactions, and multi-page behavior.

## Before running

1. Read `AGENTS.md`, `app/AGENTS.md`, and any nearer guide for the changed path.
2. Inspect `app/package.json`, `app/playwright.config.cjs`, `app/bin/run-playwright-lane.sh`, and `app/tests/ui/support/tenant-context.cjs` instead of relying on copied commands.
3. Confirm which tenant or platform host the scenario requires:
   - primary tenant: `http://kmp.localhost:8080`
   - second tenant: `http://kmp2.localhost:8080`
   - platform portal: `http://platform.kmp.localhost:8080/platform-admin`
4. Use only disposable local data. Root startup/reset helpers and Playwright lanes can recreate or reseed databases; obtain authorization before invoking them.

## Supported lanes

From `app/`, use the package scripts or the lane wrapper:

```bash
npm run test:ui:smoke
npm run test:ui:journey
npm run test:ui:platform-provisioning
npm run test:ui:uat
bash bin/run-playwright-lane.sh full
```

Read the current scripts for flags and reset behavior. Do not invent a raw `localhost` base URL that bypasses host-based tenant resolution.

## Test expectations

- Put BDD scenarios under `app/tests/ui/bdd/` and reuse the current support fixtures and seeded constants.
- Verify authorization at both route and object scope.
- For tenant features, prove the intended tenant succeeds and a second tenant cannot observe or mutate the first tenant's data.
- For platform flows, use the platform host and platform context; do not silently fall back to a tenant connection.
- Turbo Drive is disabled. Assert full-page navigation or Turbo Frame behavior according to the actual markup.
- Prefer semantic locators and accessible names. Check keyboard operation, focus order/visibility, labels, ARIA state, status announcements, and error feedback.
- Keep credentials, storage state, traces, screenshots, and member data out of commits unless they are sanitized test fixtures.

Report the lane, host/context, scenario coverage, and any skipped or unverified behavior.
