---
layout: default
---
[← Back to Table of Contents](index.md)

# 4. Core Domains

KMP's core application owns the shared identity, organization, security, event, document, workflow, and platform capabilities used by every tenant. Business features that can remain isolated belong in first-party plugins; cross-plugin orchestration belongs in core services.

## Tenant boundary

Each normal request is resolved from its host name before application data is read. `TenantResolutionMiddleware` identifies the tenant, and `TenantConnectionManager` makes that tenant database the default CakePHP connection, resets the table locator, applies tenant mail settings, and enters `TenantContext`. Storage services consult that context later when resolving tenant-aware Azure container/prefix configuration. Unknown hosts fail closed. The platform administration host uses the separate platform database and must not execute tenant-domain queries.

Consequences for domain code:

- Core and plugin tables normally use the default connection; the request or worker must already have an active tenant context.
- Never add `tenant_id` filters to tenant-domain tables. Isolation is database-per-tenant.
- Cache keys, document storage, mail settings, queued work, and scheduled workflows must be tenant-aware.
- Code that can run without an HTTP request must enter and leave a tenant context explicitly.

See [Multi-Tenant Architecture](3.1-multi-tenant-architecture.md) for the request, worker, and platform boundaries.

## Domain map

| Domain | Primary responsibility | Main entry points |
| --- | --- | --- |
| Members | Identity, profile, membership verification, minor handling, and role assignments | `MembersTable`, `MemberRegistrationService`, `MemberProfileService` |
| Branches | Tenant-local organizational tree and branch-scoped access | `BranchesTable`, `Branch` |
| RBAC | Roles, permissions, policies, query scopes, and time-bounded grants | `AuthorizationService`, `PermissionsLoader`, `BasePolicy` |
| Warrants | Time-bounded authorization records and roster approval | `DefaultWarrantManager`, `warrants-roster-approval.json` |
| Gatherings | Events, attendance, staff, activities, schedules, public calendar, and feeds | `GatheringsController`, gathering services |
| Documents | Metadata, tenant-routed object/local storage, previews, and retention | `DocumentService`, `RetentionPolicyService` |
| Workflow | Versioned definitions, instances, tasks, approvals, action items, and schedules | `Services/WorkflowEngine`, `Services/WorkflowRegistry` |
| Platform | Tenant provisioning, fleet health, migrations, jobs, backups, and recovery | `Services/Platform`, `Services/Backups` |

## Detailed guides

- [4.1 Member lifecycle](4.1-member-lifecycle.md)
- [4.2 Branch hierarchy](4.2-branch-hierarchy.md)
- [4.3 Warrant lifecycle](4.3-warrant-lifecycle.md)
- [4.4 RBAC and security](4.4-rbac-security-architecture.md)
- [4.5 View patterns](4.5-view-patterns.md)
- [4.6 Gatherings](4.6-gatherings-system.md)
- [4.7 Documents and retention](4.7-document-management-system.md)
- [4.9 Impersonation](4.9-impersonation-mode.md)
- [5. Plugins](5-plugins.md)
- [6. Services](6-services.md)

## Change rule

Controllers coordinate HTTP concerns, policies authorize, tables enforce persistence rules, and services own reusable workflows. Prefer the workflow engine for durable multi-step processes and plugin registries for cross-module UI integration. Do not place business decisions in templates or duplicate tenant resolution inside a domain service.
