---
layout: default
title: Architecture
description: System structure, request flow, data boundaries, plugins, workflows, and background processing in KMP.
---

[← Documentation home](index.md)

# 3. Architecture

KMP is a modular CakePHP application deployed as a database-per-tenant managed
platform. Server-rendered HTML is the default interface, with Stimulus and Turbo
Frames adding focused interactivity. Domain plugins and a shared workflow engine
extend the core without bypassing its authorization or tenant boundaries.

## System map

```text
                      ┌────────────────────────────┐
request host ────────▶│ KMP web application       │
                      │ routing → tenant binding   │
                      │ → authn → authz → domain   │
                      └──────────┬─────────────────┘
                                 │
                 ┌───────────────┴────────────────┐
                 │                                │
       ┌─────────▼─────────┐            ┌─────────▼─────────┐
       │ platform database │            │ selected tenant DB │
       │ registry, secrets,│            │ core + active      │
       │ jobs, audit, ops  │            │ plugin data        │
       └───────────────────┘            └────────────────────┘
```

The platform database is not a shared application database. Tenant business
records never move into it merely to make cross-tenant queries easier. Each
request or background operation may have at most one selected tenant database.

## Repository boundaries

| Area | Responsibility |
| --- | --- |
| `app/config` | CakePHP configuration, routes, migrations, seeds, plugin loading |
| `app/src/Controller` | HTTP orchestration and authorization boundaries |
| `app/src/Model` | ORM persistence, entities, table rules, reusable behaviors |
| `app/src/Policy` | resource authorization and query scoping |
| `app/src/Services` | domain workflows, tenancy, platform operations, integrations |
| `app/src/KMP` | small application primitives such as `TenantContext` |
| `app/templates` | accessible server-rendered views and elements |
| `app/assets` | Vite-bundled Stimulus controllers and CSS |
| `app/plugins` | isolated first-party and infrastructure plugins |
| `app/tests` | PHPUnit, Jest, and Playwright tests |
| `deploy`, `docker`, `installer` | managed deployment, containers, legacy packaging |

Controllers stay thin: parse input, load a resource, authorize it, call a domain
service/table operation, and choose a response. Multi-step business workflows
belong in services; templates only render already-authorized data.

## HTTP request flow

The important middleware ordering is:

1. error handling, request correlation/performance context, and response security
   headers;
2. static assets and CakePHP routing;
3. `TenantResolutionMiddleware`;
4. restore-maintenance enforcement and request-body parsing;
5. security-token and CSRF handling;
6. tenant member authentication and authorization; and
7. footprint/audit attribution.

Routing occurs before tenant resolution so the middleware can recognize the
health and platform-admin paths, but tenant binding occurs before any normal
application authentication, authorization, controller, table, or template work.
The platform-admin host follows its own central identity/session path and does
not receive a tenant datasource binding.

For a tenant request, the host resolver reads the central registry and
`TenantConnectionManager` temporarily makes the selected physical connection
CakePHP's `default` datasource. It installs a fresh table locator, applies
scoped mail settings, enters `TenantContext`, executes the request, and restores
all state in `finally`. An open transaction at scope exit is rolled back and
reported as an error.

See [Multi-tenant architecture](3.1-multi-tenant-architecture.md) for failure
responses, lifecycle states, cache/storage rules, and background context.

## Application foundation

KMP builds on a small set of project abstractions:

- web controllers extend `AppController`; API controllers extend
  `ApiController`;
- tables extend `BaseTable`, entities extend `BaseEntity`, and policies extend
  `BasePolicy`;
- `AuthorizationComponent` methods such as `authorize()`, `authorizeModel()`,
  and `applyScope()` enforce access consistently;
- registries such as `ViewCellRegistry` and navigation registries let plugins
  contribute UI without hard-coded core conditionals;
- `DataverseGridTrait` plus `BaseGridColumns` define searchable and filterable
  grid screens;
- `TimezoneHelper` and frontend timezone utilities handle the UTC/display
  boundary.

Use these established abstractions before introducing a parallel framework.
More detail is in [Application foundation](3.1-core-foundation-architecture.md).

## Domain and plugin model

Core owns members, branches, RBAC, warrants, gatherings, documents, settings,
and the workflow engine. Active first-party domain plugins are loaded in this
migration order:

1. Activities
2. Officers
3. Awards
4. Waivers

The Queue plugin is active infrastructure. `Template` remains an unloaded
skeleton. Plugin controllers, policies, services, cells, assets, migrations, and
tests remain under the plugin namespace. Cross-plugin collaboration uses public
services, events, registries, or workflow providers rather than querying another
plugin's tables from templates.

## Authorization model

Authentication establishes an identity; authorization decides what it may do.
A request normally combines:

- an explicit resource or model authorization check;
- a policy scope applied to collection queries;
- branch hierarchy rules where the permission grants branch or descendant
  reach; and
- restore-lock and impersonation restrictions where applicable.

Do not replace these layers with controller name checks, raw role IDs, UI-only
hiding, or ad hoc branch predicates. Platform operators are also not implicit
tenant superusers: platform administration and tenant membership are separate
identity domains.

## Workflow engine

The shared workflow engine owns reusable definitions, instances, states,
transitions, approvals, schedules, actions, and conditions. Core and plugins
register domain-specific handlers through the existing provider/registry
patterns. A domain record may trigger or reference a workflow, but the workflow
engine remains the lifecycle authority once that flow begins.

Keep state changes transactional and idempotent. Scheduled reconciliation must
enter tenant context and tolerate retries. Use the current approval endpoints
and services rather than recreating historical feature-specific approval
entities. See [Workflow approval nodes](6.5-workflow-approval-nodes.md).

## Background processing

There are two persistence lanes:

- `queued_jobs` in each tenant database for tenant application work; and
- `platform_jobs` plus `platform_schedules` in the platform database for fleet
  operations.

`PlatformWorkerService` performs a bounded pass: dispatch due platform schedules,
drain enabled active-tenant queues with explicit context switching, then run
platform jobs. No job may retain a table instance, connection alias, mail
profile, cache namespace, or tenant metadata for use after the scope ends.

## Data and migration tracks

KMP has independent platform, core tenant, and plugin tenant migrations. The
platform schema is migrated first. A tenant release then inspects the complete
core/plugin catalog, takes a PostgreSQL advisory lock per tenant, creates an
encrypted pre-migration backup marker when needed, applies pending migrations,
and verifies every history scope before recording the tenant schema version.
Suspended tenants remain migration-current so they can be safely reactivated.

The current database contract lives in migrations and table classes, not copied
SQL in this guide. See [Data architecture](3.3-database-schema.md) and
[Migration lifecycle](3.4-migration-documentation.md).

## Frontend architecture

CakePHP templates render semantic HTML. Bootstrap 5 provides the visual
component layer, Stimulus owns local behavior, and Turbo Frames update selected
regions. Turbo Drive is disabled. Controllers register through
`window.Controllers` and must remove global listeners in `disconnect()`.

Vite builds JavaScript and CSS and writes the manifest consumed by
`ViteHelper`. UI changes must preserve WCAG 2.2 Level AA, including keyboard
operation, visible focus, useful labels, live announcements, contrast, and
non-color-only cues.

## Cross-cutting rules

- Store timestamps in UTC and convert only at input/output boundaries.
- Use public IDs where routes or external references require non-sequential
  identifiers; public IDs are not an authorization mechanism.
- Route tenant caches, mail, documents, jobs, and settings through their scoped
  services.
- Do not log secrets, customer data, raw request bodies, or high-cardinality
  identifiers in platform telemetry.
- Preserve unrelated plugin and tenant behavior with targeted tests before a
  full verification run.

## Related guides

- [Multi-tenant architecture](3.1-multi-tenant-architecture.md)
- [Model behaviors](3.2-model-behaviors.md)
- [Core modules](4-core-modules.md)
- [Plugin architecture](5-plugins.md)
- [Service ownership map](6-services.md)
- [Security practices](7.1-security-best-practices.md)
