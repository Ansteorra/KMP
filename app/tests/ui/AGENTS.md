# Playwright UI test guide

## Purpose

Own browser-driven Playwright and playwright-bdd coverage for authentication, tenancy, members, roles, activities, officers, gatherings, awards, Hotwire/Turbo grids, workflows, and platform admin flows.

## Ownership

- Feature files live under `bdd/@domain`.
- Shared steps live in `bdd/SharedSteps.js`; domain-specific steps live in each `@domain/steps.js`.
- Support helpers in `support` own tenant contexts, app environment handling, PHP fixture setup, queue draining, and stable assertions.
- `gen`, `ui-reports`, and `ui-results` are generated output.

## Local Contracts

- Run only one Playwright lane at a time because lanes share the local Docker app, database, worker, scheduler, and Mailpit.
- Use `runPhpJson()` for fixture setup and pass JSON through STDIN.
- Use `flushWorkflowsAndQueue()` and `waitForQueueSettled()` before asserting email or queued side effects.
- Tenant scenarios must use host-bound contexts and assert tenant isolation.
- Do not hard-code credentials or IDs when a support helper or seeded fixture can provide them.

## Work Guidance

1. Search existing shared steps before adding a domain-specific step.
2. Keep domain steps local to the domain unless they are reusable across features.
3. Avoid direct workflow action calls; prove the user-visible trigger path.
4. Preserve generated Playwright output; edit source feature/step files instead.

## Verification

- UAT lane: `npm run test:ui`
- Curated journey lane: `npm run test:ui:journey`
- Headed UAT lane: `npm run test:ui:headed`
- Debug UAT lane: `npm run test:ui:debug`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
