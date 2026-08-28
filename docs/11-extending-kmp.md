---
layout: default
---
[← Back to Table of Contents](index.md)

# 11. Extending KMP

KMP is extended through focused core services or first-party CakePHP plugins. Choose the smallest ownership boundary that keeps authorization, tenant isolation, workflows, UI composition, and migrations explicit.

## Choose the boundary

Add to core when the capability is required by several domains or owns shared infrastructure such as identity, tenant binding, workflows, documents, cache, mail, or platform operations. Use a plugin when the feature has its own models, routes, policies, services, migrations, UI, or release cadence and can remain namespace-isolated.

Do not use the disabled `app/plugins/Template` skeleton as a starter. It is incomplete and does not demonstrate all current contracts. Use the active plugin closest to the feature:

- Activities for approval context, API routes, and a manager-backed lifecycle.
- Officers for workflow-orchestrated creation plus shared release/recalculation services.
- Awards for complex workflows, action items, synchronization, and many focused services.
- Waivers for document storage, mobile UI, retention, and gathering integration.

## 1. Establish ownership and guidance

Create `app/plugins/Feature` with a PascalCase PHP namespace and PSR-4 mapping in the plugin's `composer.json`. Before editing, follow the repository, `app/`, `app/plugins/`, and nearest plugin `AGENTS.md` chain. Add a plugin-local `AGENTS.md` when the subtree has durable workflows or rules not captured by its parents.

A typical plugin contains only the directories it needs:

```text
app/plugins/Feature/
├── config/Migrations/
├── src/
│   ├── Controller/
│   ├── Model/Entity/
│   ├── Model/Table/
│   ├── Policy/
│   ├── Services/
│   ├── KMP/GridColumns/
│   ├── View/Cell/
│   └── FeaturePlugin.php
├── templates/
├── assets/                     # optional
└── tests/TestCase/
```

## 2. Register the plugin and migrations

Enable the plugin in `app/config/plugins.php`. Domain plugins implement `App\KMP\KMPPluginInterface` and return their configured migration order. Pick an order from real dependencies, not an arbitrary gap, and review tenant provisioning/migration behavior before changing an existing order.

Plugin schema migrations live in `app/plugins/Feature/config/Migrations`. They are tenant migrations and must work for a new tenant and every existing tenant. Platform registry, fleet job, or platform audit schema changes instead belong in `app/config/PlatformMigrations` and require a platform-architecture review.

Migration rules:

- use PostgreSQL-compatible CakePHP migrations;
- preserve rollback where safely possible;
- do not rely on numeric IDs seeded by another tenant;
- make data backfills bounded and restart-safe;
- test a clean install and an upgrade path;
- never add `tenant_id` to normal domain tables merely for isolation.

## 3. Follow project model and policy bases

Plugin controllers extend the app's web or API controller base. Tables extend `BaseTable`, entities extend `BaseEntity` (or the appropriate ActiveWindow base), and policies extend `BasePolicy`. Preserve strict types, footprint/soft-delete/restore-lock conventions, impersonation logging, and public-ID patterns used by the neighboring domain.

Every HTTP entry point needs the correct entity/model authorization. Every list, grid, count, export, autocomplete, and API projection needs a scoped query. Do not hide unauthorized data in the template after loading it.

Put reusable mutations and calculations in `src/Services`. Register injectable interfaces in the plugin's `services()` method. Return `ServiceResult` for expected domain outcomes where that is the local pattern; use exceptions for unexpected consistency/infrastructure failure.

## 4. Integrate without coupling core UI

Register navigation through `NavigationRegistry` and detail content through `ViewCellRegistry`. Use approval-context and ActionItem-form registries for those specialized surfaces. Do not hard-code plugin links, tabs, models, or conditions into core controllers/templates.

Plugin cells should load only the authorized data they render. Preserve detail-tab `data-tab-order` and visual order conventions. User-facing output must remain WCAG 2.2 Level AA: semantic elements, labeled controls, visible focus, keyboard operation, synchronized ARIA state, announced asynchronous updates, adequate contrast, and non-color cues.

## 5. Add routes and APIs

The plugin may define its own web routes in the CakePHP plugin class. Plugins that publish API endpoints implement `KMPApiPluginInterface::registerApiRoutes()`, which is called inside the host API v1 scope.

API controllers extend `ApiController`, authorize and scope like web controllers, return bounded DTO/projection data, and keep plugin routes namespace-isolated. Update API tests and the OpenAPI merge/source when the public contract changes; do not expose ORM entities wholesale.

## 6. Add workflow capabilities

Implement `KMPWorkflowPluginInterface` for a new plugin that contributes triggers, actions, conditions, or entity schemas. The core `WorkflowPluginLoader` discovers that contract. Existing first-party plugins also use provider classes explicitly registered by the loader, so inspect it before altering their registration.

Workflow actions should be thin adapters over domain services. Conditions must be deterministic and side-effect free. Registry metadata is consumed by the designer and validator, so keep keys, schemas, service methods, and JSON definitions synchronized.

Versioned definition JSON lives under `app/config/Seeds/WorkflowDefinitions` and references `schema.json`. Adding a JSON file alone does not update installed tenants: pair it with the supported migration/version-manager publication path. Never edit a published version in place or let a new version silently reinterpret an active instance.

Use:

- approval nodes for decisions that gate progression;
- human-task nodes for workflow-owned manual completion;
- core ActionItems for durable follow-up work with assignee/form integration;
- Queue jobs for bounded asynchronous execution, not as a substitute for workflow history.

Dispatch triggers only after the owning state is durable, and make retried actions idempotent.

## 7. Add frontend behavior and assets

Plugin Stimulus controllers belong in `assets/js/controllers/*-controller.js`, self-register in `window.Controllers`, and are discovered eagerly by `app/assets/js/controllers-entry.js` from either supported plugin asset casing. Preserve controller identifiers and clean up listeners/observers in `disconnect()`.

Assets are built with Vite. Import small plugin CSS/JS through an existing entry when appropriate; add a named `vite.config.js` entry only when the plugin needs a separately loaded bundle. Render assets through `ViteHelper`, never a Mix/Webpack manifest or hard-coded hashed filename. Run `npm run dev` after entry/import changes.

Use Turbo Frames only for bounded server-rendered regions. Turbo Drive remains disabled.

## 8. Respect tenant runtime boundaries

Normal plugin tables use the tenant-bound default connection. HTTP requests receive context from `TenantResolutionMiddleware`; CLI, queue, and scheduled work must enter the tenant through `TenantConnectionManager` before resolving tables.

Use:

- `TenantAwareCache` for tenant data;
- `TenantDocumentStorageConfigResolver`/`DocumentService` for files;
- tenant mail configuration and secret references for outbound mail;
- platform services for fleet-wide work, one tenant at a time.

Never carry an entity across contexts, place secrets in workflow/queue payloads, use platform cache keys for plugin data, or let a tenant controller query the platform registry directly.

## 9. Test and document the extension

Keep tests in `plugins/Feature/tests/TestCase` and use project base classes/seed constants. Cover policy allow/deny and scope, service success/failure/rollback, workflow retry/cancel/version behavior, migrations, API shape, tenant isolation, and any accessible keyboard/focus/status behavior.

From `app/`, choose the narrowest useful verification:

```bash
vendor/bin/phpunit plugins/Feature/tests/TestCase
vendor/bin/phpcs plugins/Feature/src
npm run test:js
npm run dev
```

Use `vendor/bin/phpunit --testsuite plugins` or `bash bin/verify.sh` for cross-plugin/core changes, and Playwright for real browser flows, Turbo Frames, modals, multi-page workflows, and responsive UI.

When a plugin exports PHP source, add its `src` directory to `app/phpdoc.dist.xml`, then
run `./generate_api_docs.sh` from the repository root to validate both source references.

Update the nearest owning documentation and `AGENTS.md` contract whenever the extension changes durable purpose, ownership, inputs/outputs, permissions, workflow, side effects, or verification requirements.
