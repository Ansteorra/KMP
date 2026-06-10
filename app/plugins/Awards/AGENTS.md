# Awards plugin guide

## Purpose

Own award domains, levels, recommendations, recommendation feedback approvals, bestowals, state logs, award workflow actions/conditions, and award-related notification variables.

## Ownership

- Parent plugin contracts live in `app/plugins/AGENTS.md`.
- `AwardsPlugin.php` registers navigation, view cells, approval context rendering, recommendation feedback listeners, console commands, settings, services, and workflow actions/conditions.
- Plugin docs are under `docs/5.2*awards*` and app-local awards redesign/workflow notes.

## Local Contracts

- Plugin path is `/awards` and supports `json`, `pdf`, and `csv` extensions.
- Migration order is `3` in `app/config/plugins.php`; it loads after Officers.
- Recommendation and bestowal state transitions belong in the dedicated transition/update/state-log services.
- Recommendation feedback approval context uses the registered `AwardsFeedback` renderer.
- State/status rules and plugin settings are stored in `Awards.*`, `Member.AdditionalInfo.*`, and `Plugin.Awards.*` settings.
- The `awards migrate_award_recommendations` command is registered by the plugin.

## Work Guidance

1. Do not bypass recommendation or bestowal transition services for state changes.
2. Keep feedback approvals wired through the listener, approval resolver, and context renderer.
3. Preserve CSV/PDF/JSON response expectations for award grids and exports.
4. Update docs when state machines, approval rules, or recommendation/bestowal workflows change.

## Verification

- Plugin tests: `vendor/bin/phpunit plugins/Awards/tests/TestCase`
- All plugin tests: `vendor/bin/phpunit --testsuite plugins`
- Changed PHP files: `vendor/bin/phpcs plugins/Awards/src`
- UI workflow changes: targeted awards Playwright scenarios under `tests/ui/bdd/@awards`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
