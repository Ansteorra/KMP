# Waivers plugin guide

## Purpose

Own gathering waiver tracking: waiver types, gathering waivers, file upload handling, mobile waiver flows, compliance settings, dashboard data, and waiver workflow actions/conditions.

## Ownership

- Parent plugin contracts live in `app/plugins/AGENTS.md`.
- `WaiversPlugin.php` registers navigation, view cells, settings, services, and workflow actions/conditions.
- Plugin docs are under `docs/5.7-waivers-plugin.md` and related core waiver exemption docs.

## Local Contracts

- Plugin path is `/waivers`.
- Migration order is `4` in `app/config/plugins.php`; it loads after Officers.
- Waiver state changes belong in `WaiverStateService`.
- File handling belongs in `WaiverFileService`; do not duplicate upload/storage validation in controllers.
- Dashboard and mobile-specific behavior belongs in `WaiverDashboardService` and `WaiverMobileService`.
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
