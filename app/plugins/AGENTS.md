# Plugin development guide

## Purpose

Own first-party plugin contracts for namespace isolation, plugin loading, migration order, routes, services, navigation, view cells, assets, and plugin tests.

## Ownership

- This guide covers shared plugin structure and workflows.
- Active first-party domain plugin guides own local business rules for `Activities`, `Officers`, `Awards`, and `Waivers`.
- `Bootstrap`, `GitHubIssueSubmitter`, and `Queue` are enabled utility/infrastructure plugins; use this guide plus nearby code unless a child guide is added.
- `Template` is a disabled skeleton plugin and should remain a reference unless explicitly enabled.

## Local Contracts

- Plugin namespaces are PascalCase and map to `src/` through plugin `composer.json`.
- Plugin controllers extend app controller bases, models extend app model bases, and policies extend `App\Policy\BasePolicy`.
- Plugin migrations live in `config/Migrations` and run according to `app/config/plugins.php`.
- Plugin bootstrap classes register navigation through `NavigationRegistry` and reusable UI through `ViewCellRegistry`.
- API-capable plugins implement the app plugin API route contract and register routes from the plugin.
- Plugin-specific assets stay under the plugin and are wired into Vite only when needed by the app.
- Plugin tests live under `plugins/{Plugin}/tests/TestCase` and run through the `plugins` PHPUnit suite.

## Work Guidance

1. Keep plugin behavior inside the plugin unless extracting a shared core extension point.
2. Before changing migration order or plugin activation, review `app/config/plugins.php` and affected child guides.
3. Do not hard-code plugin UI into core templates or controllers; use registries, view cells, and navigation providers.
4. Add tests under the owning plugin or `app/tests/TestCase/Plugins` for cross-plugin harness behavior.
5. Create a child `AGENTS.md` when a plugin gains durable workflows or local rules not covered here.

## Verification

- All plugin tests: `vendor/bin/phpunit --testsuite plugins`
- Targeted plugin tests: `vendor/bin/phpunit plugins/PluginName/tests/TestCase`
- Changed plugin PHP files: `vendor/bin/phpcs plugins/PluginName/src`
- Cross-plugin changes: `bash bin/verify.sh`

## Child AGENTS index

| Path | Purpose |
| --- | --- |
| `app/plugins/Activities/AGENTS.md` | Authorization lifecycle, approval context integration, activity groups, and activity workflows |
| `app/plugins/Officers/AGENTS.md` | Officer roster, departments, offices, warrants, and officer workflow integration |
| `app/plugins/Awards/AGENTS.md` | Award recommendations, bestowals, feedback approvals, state transitions, and award workflows |
| `app/plugins/Waivers/AGENTS.md` | Gathering waiver types, waiver records, uploads, compliance services, and waiver workflows |
