# Waivers plugin guide

## Purpose

Own gathering waiver tracking: waiver types, gathering waivers, loss reports, file upload handling, mobile waiver flows, compliance settings, dashboard data, and waiver workflow actions/conditions.

## Ownership

- Parent plugin contracts live in `app/plugins/AGENTS.md`.
- `WaiversPlugin.php` registers navigation, view cells, settings, services, and workflow actions/conditions.
- Plugin docs are under `docs/5.7-waivers-plugin.md` and related core waiver exemption docs.

## Local Contracts

- Plugin path is `/waivers`.
- Migration order is `4` in `app/config/plugins.php`; it loads after Officers.
- Waiver state changes belong in `WaiverStateService`.
- Loss reports preserve original notes, actor, and timestamp separately from uploads/exemptions. Require the `recordLost` policy for reporting and reversal, and notes when reporting; leave role-to-permission assignments to administrators and never check role names; active loss reports satisfy their waiver type for completion and closure readiness, but never count as uploads or exemptions and never automatically mark ready or close a gathering. Reversed reports no longer satisfy requirements.
  The Lost reporting button belongs only in each grid row's Actions column on the Gathering Waivers summary, including pending rows. Bind the report to that row's waiver type; do not add a page-header action or put it in submission wizards.
  Allow required or previously submitted types regardless of upload, decline, exemption, or closure status.
  Preserve the normal close workflow and existing records/files. Authorized close requests do not require a prior ready marker in the built-in workflow; upgrades publish a new version only for unchanged built-in graphs.
  Gathering summaries show Lost for the affected waiver type in place of its uploaded count/status. Do not add a separate loss-report or history block to gathering summaries; keep audit details and reversal inside the standard waiver detail view.
  An active loss takes priority in the gathering grid's displayed status and waiver detail view. Embed Lost Report history in the standard detail view, including for pending types without uploads; old report URLs redirect there. Reversal records its actor/time without deleting history, restores the prior displayed status, and permits a new report. Only one report per gathering/type may be active; serialize reporting/reversal on the gathering and enforce the partial unique index. Always list uploaded files with PDF viewing/download links regardless of loss, reversal, or exemption state; preserve original upload, exemption, decline, retention, and closure data.
- File handling belongs in `WaiverFileService`; do not duplicate upload/storage validation in controllers.
- Native uploads carry their originating same-origin `page_context_url` through the wizard. Validate it before file processing, retain its tab/query on success, and fall back to the gathering's waiver tab. Never read legacy session history to choose a destination.
- Dashboard and mobile-specific behavior belongs in `WaiverDashboardService` and `WaiverMobileService`.
- Dashboard compliance is based on gathering end date: due through day 30, past due on day 31. Include all older incomplete gatherings, including partial submissions; branch counts and detail modals must use the same past-due records. Mobile selection orders authorized open gatherings by oldest end date first.
- Settings use `Waivers.*` and `Plugin.Waivers.*` keys through `StaticHelpers`.
- Plugin CSS entries are wired in `app/vite.config.js` for `waivers` and `waiver-upload`.

## Work Guidance

1. Keep waiver type and gathering waiver behavior inside this plugin.
2. Use services for state transitions, file handling, dashboard data, and mobile flow logic.
3. Preserve compliance day settings and navigation visibility behavior unless intentionally changing plugin defaults.
4. Update docs when waiver lifecycle, upload handling, or compliance behavior changes.

## Verification

- Plugin tests: `vendor/bin/phpunit plugins/Waivers/tests/TestCase`
- All plugin tests: `vendor/bin/phpunit --testsuite plugins`
- Changed PHP files: `vendor/bin/phpcs plugins/Waivers/src`
- Frontend asset changes: `npm run dev`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
