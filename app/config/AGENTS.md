# Application configuration guide

## Purpose

Own CakePHP app configuration that changes runtime wiring: routes, plugin activation, migration ordering, app defaults, bootstrap configuration, and environment-facing settings.

## Ownership

- `plugins.php` is the source of truth for enabled CakePHP plugins and first-party plugin migration order.
- `routes.php` owns core routing and application route scopes; plugin routes stay in plugin classes or plugin `config/routes.php`.
- Migration configuration and app configuration must stay compatible with install, Docker, and deployment workflows.

## Local Contracts

- Enabled first-party domain plugins currently load in this order: `Activities` 1, `Officers` 2, `Awards` 3, `Waivers` 4.
- Keep `Template` disabled unless the task is specifically about the skeleton plugin.
- Never hard-code secrets or deployment-only values; use environment configuration and existing app settings patterns.
- Core routes should use CakePHP conventions and dashed URLs; plugin-specific routes belong with the plugin.
- Schema changes require migrations in the owning app or plugin migration directory.
- Applied migrations are immutable: once a migration has run in any shared environment, add a new corrective migration instead of editing the original. Squash only unreleased migration chains that have not run in shared environments, and preserve restore/hydration paths.

## Work Guidance

1. Before adding a route, search existing route scopes and controller actions for the matching pattern.
2. Before changing plugin loading, review `app/plugins/AGENTS.md` and affected plugin child guides.
3. Keep configuration edits deterministic and safe for fresh installs and existing deployments.
4. Update related docs when adding public routes, plugin activation requirements, or migration order constraints.

## Verification

- Route load check: `bin/cake routes`
- Migration changes: run the relevant Cake migrations command or `bash bin/update_database.sh` when the task requires applying migrations locally.
- Broad config changes: `bash bin/verify.sh`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
