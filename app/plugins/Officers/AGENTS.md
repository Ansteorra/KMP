# Officers plugin guide

## Purpose

Own officer assignment management, department and office hierarchy, roster APIs, warrant integration, temporal assignments, and officer workflow actions/conditions.

## Ownership

- Parent plugin contracts live in `app/plugins/AGENTS.md`.
- `OfficersPlugin.php` registers navigation, view cells, API data providers, API routes, settings, officer manager services, workflow actions, and read-only API services.
- Plugin docs are under `docs/5.1*officers*` and related office reporting docs.

## Local Contracts

- Plugin path is `/officers`; API routes expose departments, offices, and roster resources under API v1.
- Migration order is `2` in `app/config/plugins.php`; it depends on earlier activity/authorization foundations.
- Officer lifecycle behavior belongs in `OfficerManagerInterface` implementations and related services.
- Warrant interactions should use `WarrantManagerInterface`; do not duplicate warrant state logic.
- `InitCalendarDeputySeed` and the Calendar Deputy migration provision the kingdom
  deputy office, its role, global gathering preparation, and calendar publication.
  Reuse safe existing permissions and repair required policy mappings. Preserve local
  Seneschal scopes and existing permission requirements; reject deleted, superuser,
  non-global, or conflicting office configuration instead of expanding authority.
  Office appointments still use the normal officer and warrant workflows.
- Navigation, view cells, and branch API data must stay registered through plugin providers.
- Settings use `Officer.*` and `Plugin.Officers.*` keys through `StaticHelpers`.
- Hiring lookup options must distinguish same-name accounts with textual membership verification,
  group, and account ID labels. Escape names and labels, preserve the selected account ID, and
  retain warrant eligibility restrictions; never infer that identical names mean duplicate accounts.

## Work Guidance

1. Keep officer assignment and release behavior service-driven and test state transitions.
2. Preserve department, office, roster, and warrant relationships when changing schema or policies.
3. Update API read-only services when changing data exposed to core branch screens or API consumers.
4. Update docs when officer hierarchy, roster behavior, or warrant integration changes.

## Verification

- Plugin tests: `vendor/bin/phpunit plugins/Officers/tests/TestCase`
- All plugin tests: `vendor/bin/phpunit --testsuite plugins`
- Changed PHP files: `vendor/bin/phpcs plugins/Officers/src`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
