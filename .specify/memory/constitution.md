<!--
Sync Impact Report:
Version: 1.1.0 → 2.0.0
Rationale: MAJOR update for the host-resolved multi-tenant/platform architecture, PostgreSQL 16 runtime, Vite assets, disabled Turbo Drive, WCAG 2.2 AA, and current release/verification contracts.
Updated templates:
  - plan-template.md
  - tasks-template.md
  - spec-template.md
Unchanged generic templates:
  - checklist-template.md
  - agent-file-template.md
-->

# Kingdom Management Portal constitution

This constitution guides Spec Kit feature planning for KMP. The applicable `AGENTS.md` chain is the operational contract for implementation; current source, tests, and workflows are authoritative for volatile details. If guidance conflicts, stop and update the stale document instead of choosing the convenient rule.

## Core principles

### I. CakePHP conventions and project abstractions

KMP changes must follow CakePHP 5 conventions and established project base classes.

- Web controllers extend `AppController`; API controllers extend `ApiController`.
- Tables extend `BaseTable`, entities extend `BaseEntity`, and policies extend `BasePolicy`.
- Complex workflows belong in `app/src/Services` or the owning plugin's `src/Services`, not controllers or templates.
- Use migrations for schema changes and the owning core/plugin migration directory.
- Use established grid, navigation, view-cell, timezone, cache, storage, and accessibility abstractions before creating one-off alternatives.
- PHP files use `declare(strict_types=1);`. Do not change inherited framework/plugin signatures merely because a docblock suggests a native type.

Rationale: consistent abstractions reduce framework friction and keep cross-cutting behavior testable.

### II. Tenant and platform isolation (non-negotiable)

KMP is a host-resolved multi-tenant platform, not a single shared application database.

- Tenant resolution flows through `TenantResolutionMiddleware` and `TenantConnectionManager`.
- Tenant requests, queries, caches, documents, queues, backups, migrations, and tests must carry the resolved tenant context.
- Platform registry, provisioning, fleet jobs, secrets, and platform backups use the separate platform connection and platform authorization boundary.
- Cache keys, object paths, idempotency keys, and job payloads must include or safely derive tenant identity where data is tenant-scoped.
- Features that touch tenant data must test the intended tenant and cross-tenant denial. Unresolved, disabled, suspended, and platform hosts require explicit behavior.
- Never fall back silently from a tenant connection to the platform connection or another tenant.

Rationale: a tenant breakout or connection mix-up exposes member data across independent organizations.

### III. Plugin boundaries and integration

Cohesive optional domains belong in first-party plugins when they can remain independently owned.

- Keep plugin controllers, policies, services, migrations, grid columns, cells, templates, assets, and tests inside the plugin.
- Register navigation and UI through the current provider/registry and view-cell mechanisms.
- Keep plugin namespaces and migration order explicit.
- Move behavior into core only when it is genuinely shared platform/application infrastructure.
- Active first-party feature plugins are documented by `AGENTS.md` and `app/config/plugins.php`; do not infer enablement from a directory alone.

Rationale: explicit boundaries allow domains to evolve without hard-coded core coupling.

### IV. Accessible server-rendered frontend

KMP uses CakePHP templates, Bootstrap, Stimulus, Turbo Frames/Streams, and Vite.

- Turbo Drive is disabled. Do not design around page-wide Turbo navigation or re-enable it without focused compatibility review.
- Use Turbo Frames and existing Turbo Stream response patterns for targeted updates.
- Stimulus controllers live in `*-controller.js` files, use declared targets/values/outlets, register through `window.Controllers`, and release listeners/observers in `disconnect()`.
- Build assets with Vite via `app/vite.config.js` and resolve them through `ViteHelper`.
- All user-facing work must meet WCAG 2.2 Level AA: semantic structure, labeled controls, keyboard operation, visible/logical focus, adequate contrast, non-color cues, accessible names, and announcements for non-obvious async updates.

Rationale: the frontend remains lightweight and server-centric while being operable for all users.

### V. Authentication, authorization, and data safety

Security is enforced at every boundary.

- Use CakePHP Authentication and Authorization policies/scopes; do not add ad-hoc role or identifier checks.
- Preserve CSRF/FormProtection, restore locks, impersonation logging, branch scopes, and security-token handling.
- Validate input, escape untrusted output, parameterize queries, constrain uploads, and avoid logging secrets or sensitive member data.
- Platform-admin authorization and tenant authorization are distinct and both require negative-path tests.
- Secrets, tenant credentials, backup/recovery keys, and production data must never enter source, fixtures, docs, or command output.

Rationale: KMP manages sensitive identity and organizational data.

### VI. Explicit services and durable work

Side effects and long-running work must be explicit and recoverable.

- Services coordinate multi-step domain behavior and make side effects such as mail, storage, cache, queues, backups, and external calls visible.
- Background jobs carry sufficient tenant/platform context, use stable idempotency behavior where retries are possible, and fail safely.
- Backup and restore work must distinguish platform metadata from tenant databases and preserve encryption, retention, readiness, restore-lock, and audit requirements.
- Controllers orchestrate requests; templates present already-prepared data.

Rationale: explicit boundaries make failures, retries, and tests understandable.

### VII. Proportional verification and durable documentation

Every behavior change requires verification proportional to risk.

- Use project base test classes and seeded constants rather than magic IDs.
- Test success, validation, authorization denial, and tenant isolation where applicable.
- Run targeted PHPUnit and PHPCS for narrow PHP work, Jest for Stimulus behavior, Vite for bundling/import changes, and Playwright for browser flows.
- Use `cd app && bash bin/verify.sh` for cross-cutting changes when practical. The script, not copied counts or timings, defines the current gate.
- Update the closest owning documentation and `AGENTS.md` chain when a durable contract, workflow, boundary, or structure changes.
- “Push to dev” and “Do a release” follow `.github/skills/release-deploy/SKILL.md`. Production promotes the exact POC-validated digest without rebuilding.

Rationale: source-backed tests and documentation prevent architectural drift.

## Technology baseline

- PHP 8.4 and CakePHP 5
- PostgreSQL 16 for local Docker Compose and deployed runtime
- MariaDB/MySQL tooling only for explicitly supported compatibility validation
- CakePHP Authentication and Authorization
- Bootstrap, Stimulus, Turbo Frames/Streams with Turbo Drive disabled
- Vite for assets
- PHPUnit, Jest/jsdom, and Playwright BDD
- Composer and npm
- Host-based tenant routing plus a separate platform database/portal

Exact dependency versions, commands, enabled plugins, and workflow names must be read from current manifests, scripts, and configuration.

## Planning and implementation workflow

1. Read the applicable `AGENTS.md` chain and inspect the current worktree.
2. Identify whether data and behavior are tenant-scoped, platform-scoped, or intentionally both.
3. Decide whether the work belongs in core or an owning plugin and list integration points.
4. Define authorization, migration, cache, job, storage, backup/restore, and accessibility implications.
5. Write independently testable user outcomes plus negative and cross-tenant scenarios.
6. Implement the smallest coherent change using existing patterns.
7. Run proportional checks and record exact commands/results.
8. Update owning documentation and release notes when applicable.

Do not run reset, seed, migration, deploy, release, scanner, or credential-changing commands without confirming their scope and side effects.

## Governance

- Amendments require a rationale, an impact review, and synchronized changes to affected Spec Kit templates and repository guidance.
- Use semantic versioning: MAJOR for incompatible principle/governance changes, MINOR for new principles or materially expanded requirements, PATCH for clarification.
- Reviews must verify the constitution and applicable `AGENTS.md` chain before approving a deviation.
- Exceptions must be explicit, narrowly scoped, security/accessibility safe, and recorded in the feature plan's complexity table.

**Version**: 2.0.0
**Ratified**: 2025-10-07
**Last amended**: 2026-08-28
