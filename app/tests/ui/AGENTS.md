# Playwright UI test guide

## Purpose

Own browser-driven Playwright and playwright-bdd coverage for authentication, tenancy, members, roles, activities, officers, gatherings, awards, Hotwire/Turbo grids, workflows, and platform admin flows.

## Ownership

- Feature files live under `bdd/@domain`.
- Shared steps live in `bdd/SharedSteps.js`; domain-specific steps live in each `@domain/steps.js`.
- Support helpers in `support` own tenant contexts, app environment handling, PHP fixture setup, queue draining, and stable assertions.
- Standalone module fixtures serve the real imported dependencies with JavaScript MIME types; unknown module requests return 404 instead of the HTML shell.
- `gen`, `ui-reports`, and `ui-results` are generated output.

## Local Contracts

- Run only one Playwright lane at a time because lanes share the local Docker app, database, worker, scheduler, and Mailpit.
- Run UAT and the opt-in platform lane with separate fixture resets. Platform provisioning replaces the seeded second tenant required by tenancy scenarios.
- Platform operational jobs run through the dedicated admin worker (`PLAYWRIGHT_ADMIN_WORKER_CONTAINER`, default `kmp-admin-worker`), never by injecting administrative credentials into the web container.
- Platform MFA helpers wait for unused TOTP counters. Preserve replay protection instead of resetting consumed counters between sensitive actions.
- Use `runPhpJson()` for fixture setup and pass JSON through STDIN.
- Use `flushWorkflowsAndQueue()` and `waitForQueueSettled()` before asserting email or queued side effects.
- Tenant scenarios must use host-bound contexts and assert tenant isolation.
- The UAT accessibility examples scan core/plugin screens and shared dialogs at desktop, 320px mobile and short-window sizes with axe. Retain sanitized rule attachments; automated scans do not replace keyboard or screen-reader review.
- Check label and ARIA references against duplicate IDs across the whole document, including hidden dialogs and retained tabs.
- Include calendar hover and keyboard-focus states: controls hidden until interaction still need adequate target size, spacing and visible focus.
- UAT also runs real native waiver image-to-PDF upload/download and public RSVP browser checks. Their synthetic fixtures and uploaded files must be cleaned after failures. Wait for Bootstrap's focused, shown dialog before keyboard submission; do not replace that lifecycle assertion with sleeps.
- Wait for reopened editors too: existing field values can be present before the dialog finishes taking focus. Assert the submitted value when verifying dependent content so input races are distinguishable from stale rendering.
- Before deleting fixture owners, remove their queued jobs and progress rows by exact fixture IDs/references. Cleanup must also work after a failed assertion so later scenarios do not inherit orphaned work.
- Do not hard-code credentials or IDs when a support helper or seeded fixture can provide them.
- Exercise autocomplete suggestions through the visible control and submit the server-issued identifier (public IDs for core member pickers). Do not inject numeric IDs into hidden fields: input handlers can clear that fabricated selection. Officer hiring tests must assert the submitted assignee as well as the dates.
- Plain HTTP browser test environments explicitly disable secure session cookies; HTTPS deployment defaults stay secure.
- Full hosted release UAT runs without retries. CI archives reports/results together before artifact upload so tagged paths remain intact and relative report links still work.
- Retain a trace for the first CI failure; retry-only tracing produces no evidence in release UAT. Keyboard pagination checks establish focus before activation and verify it on the replacement page control.
- Document-navigation assertions still count actual navigation requests unconditionally. Distinguish authorized background screen reconciliation (`X-KMP-UI-Refresh: screen`) from Turbo document fetches; the load harness must still measure those reads.
- Exercise real navigation controls when leaving an editor. Do not inject links into live content: legitimate reconciliation may remove them before a click and create a fixture race.
- Await intermediate native document loads before an explicit return visit. A URL-only wait can abort that document's scripts before initialization completes.

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
