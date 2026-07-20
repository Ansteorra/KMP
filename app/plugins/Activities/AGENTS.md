# Activities plugin guide

## Purpose

Own member authorization and participation workflows for activities, activity groups, approval context rendering, activity navigation/view cells, and related workflow actions.

## Ownership

- Parent plugin contracts live in `app/plugins/AGENTS.md`.
- `ActivitiesPlugin.php` registers navigation, view cells, approval context rendering, API routes, settings, services, workflow actions, and workflow conditions.
- Plugin docs are under `docs/5.6*activities*`.

## Local Contracts

- Plugin path is `/activities`; API route `/activities/member-authorizations` is registered under API v1.
- Migration order is `1` in `app/config/plugins.php`; this plugin is a base dependency for later domain plugins.
- Authorization lifecycle logic belongs in `AuthorizationManagerInterface` implementations and related services.
- Navigation and view-cell UI must go through `ActivitiesNavigationProvider` and `ActivitiesViewCellProvider`.
- Settings use `Activities.*` and `Plugin.Activities.*` keys through `StaticHelpers`.

## Work Guidance

1. Use services for authorization request, approval, denial, revocation, and workflow behavior.
2. Keep activity UI extension points dynamic through the registered providers.
3. Preserve redirects from legacy authorization approval URLs to unified approvals unless intentionally removing compatibility.
4. Update plugin docs when authorization lifecycle or approval context contracts change.

## Verification

- Plugin tests: `vendor/bin/phpunit plugins/Activities/tests/TestCase`
- All plugin tests: `vendor/bin/phpunit --testsuite plugins`
- Changed PHP files: `vendor/bin/phpcs plugins/Activities/src`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
