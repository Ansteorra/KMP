---
layout: default
---
[← Back to Table of Contents](index.md)

# 5. Plugin Architecture

KMP uses CakePHP plugins to isolate optional or domain-specific behavior while sharing core identity, authorization, workflow, and tenant infrastructure. `app/config/plugins.php` is the source of truth for what is enabled and for first-party migration order.

## Enabled first-party domains

| Order | Plugin | Owns |
| --- | --- | --- |
| 1 | [Activities](5.6-activities-plugin.md) | Activity definitions and member authorizations |
| 2 | [Officers](5.1-officers-plugin.md) | Departments, offices, officer assignments, rosters, and warrant integration |
| 3 | [Awards](5.2-awards-plugin.md) | Award catalog, recommendations, approval processes, feedback, bestowals, and court agendas |
| 4 | [Waivers](5.7-waivers-plugin.md) | Gathering waiver requirements, uploads, attestations, compliance, and closure |

[Bootstrap](5.5-bootstrap-plugin.md) and
[GitHubIssueSubmitter](5.4-github-issue-submitter-plugin.md) are enabled local
utility plugins. [Queue](5.3-queue-plugin.md) is the external background-job
plugin. Other framework plugins in `plugins.php` provide authentication,
authorization, migrations, auditing, soft deletion, image handling, and exports.

`Template` is disabled and incomplete. It is not a production-ready starter and should not be copied as the basis of a new plugin.

## Shared contracts

- `KMPPluginInterface` supplies migration order.
- `KMPApiPluginInterface` lets API-capable plugins register routes below the host API scope.
- `KMPWorkflowPluginInterface` is the generic workflow contribution contract. Current first-party domain providers are also registered centrally by `WorkflowPluginLoader`; inspect that loader before changing registration.
- `NavigationRegistry` and `ViewCellRegistry` are the supported UI integration points.
- `ApprovalContextRendererRegistry` and `ActionItemCompletionFormRegistry` provide workflow-specific UI extension points.
- CakePHP's service container owns injectable interfaces and implementations.

## Expected plugin shape

```text
plugins/Feature/
├── config/Migrations/
├── src/
│   ├── Controller/
│   ├── Model/{Entity,Table}/
│   ├── Policy/
│   ├── Services/
│   ├── View/Cell/
│   ├── KMP/GridColumns/
│   └── FeaturePlugin.php
├── templates/
├── assets/                 # only when the plugin has frontend assets
└── tests/TestCase/
```

Namespaces and ownership stay inside the plugin. Plugin controllers extend the app controller base, tables/entities/policies extend the project bases, and migrations live in the plugin that owns the data.

## Bootstrap responsibilities

A first-party plugin bootstrap class may:

- record migration order from its configuration;
- register navigation, view cells, approval renderers, or API data providers;
- register service interfaces and implementations;
- seed/update tenant-local plugin settings by configuration version;
- publish plugin routes and console commands.

Keep bootstrap work deterministic and inexpensive. Durable business processing belongs in a service or workflow, not in plugin bootstrap.

## Multi-tenant rules

Plugin tables use the active tenant database. Plugin migrations and settings must be applied to every tenant through the platform lifecycle/migration tooling. Plugin code must not open a tenant connection from request data, read the platform registry directly, or add `tenant_id` columns to tenant-domain tables.

Queued jobs, scheduled workflows, cache entries, documents, and mail initiated by a plugin must retain tenant context. Cross-tenant fleet work belongs in platform services, which enter one tenant at a time.

## UI and API integration

Do not modify core controllers/templates to insert plugin-specific UI. Register navigation and cells from the plugin, and preserve detail-tab ordering conventions. API routes remain plugin-owned and must use the app API base, resource policies/scopes, and generated OpenAPI merge path.

Plugin frontend assets are built by Vite. Stimulus controllers use `window.Controllers`; do not add Laravel Mix manifests or standalone unversioned scripts.

## Verification

- Targeted plugin tests: `vendor/bin/phpunit plugins/Feature/tests/TestCase`
- All plugin tests: `vendor/bin/phpunit --testsuite plugins`
- Changed PHP style: `vendor/bin/phpcs plugins/Feature/src`
- JavaScript behavior: `npm run test:js`
- Changed imports/bundles: `npm run dev`

See [Extending KMP](11-extending-kmp.md) for an implementation checklist.
