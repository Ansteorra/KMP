---
layout: default
---
[← Back to Table of Contents](index.md)

# 6. Service Architecture

KMP services own reusable business workflows and infrastructure coordination that do not belong in controllers, templates, or ORM callbacks. The current service layer also enforces the boundary between tenant-domain work and platform fleet operations.

## Service families

| Family | Examples | Responsibility |
| --- | --- | --- |
| Domain lifecycle | member, gathering, warrant, document, impersonation services | Reusable domain mutations and projections |
| Temporal state | `ActiveWindowManager`, `WarrantManager` | Consistent start/expiry/cancellation behavior |
| Workflow | `WorkflowEngine`, registries, approval manager, trigger dispatcher | Versioned durable orchestration |
| Action items | `ActionItemService`, assignee/form registries | Human follow-up work separate from approval gates |
| Integration registries | navigation, view cells, API data, approval context | Decoupled core/plugin composition |
| Tenant infrastructure | connection manager, cache, mail, document-storage resolver, default settings | Apply one tenant's runtime configuration safely |
| Platform operations | tenant lifecycle/migrations/health/jobs/audit/schedules | Fleet-wide control-plane work against the platform database |
| Backup/recovery | `Services/Backups`, compatibility/schema services | Tenant and platform backup, restore, retention, drills |
| Security/secrets | rate limiter, session cookies, CSRF scope, secret stores, escrow | Security-sensitive infrastructure |

The complete inventory is `app/src/Services`; exact APIs belong to source and generated reference documentation.

## Layering contract

- Controllers parse requests, authorize, select HTTP responses, and call services.
- Policies decide whether the identity may act and scope collection queries.
- Services implement reusable workflows and transaction boundaries.
- Tables/entities validate and persist state.
- Workflow definitions orchestrate long-running or multi-step service actions.
- Templates render already-authorized data.

A service is not an authorization boundary by default. The controller or workflow entry point must authorize before invoking it, unless the service explicitly documents an internal authorization contract.

## Results, exceptions, and transactions

Many domain managers return `ServiceResult` for expected success/failure outcomes. Unexpected programming, infrastructure, or consistency failures may throw. Do not discard either channel: surface a safe domain message and log unexpected details without secrets or sensitive content.

Keep one logical mutation and its audit/history records in the owning transaction. Dispatch queued work or workflow events at the lifecycle point expected by that service so consumers cannot observe rolled-back data. Design retries to be idempotent.

## Tenant-context contract

Request services normally use the tenant-bound default connection. Workers and platform services must enter one tenant with `TenantConnectionManager`, perform the unit of work, and always restore connection, table-locator, cache/mail/storage, and `TenantContext` state.

Never:

- select a tenant from untrusted payload data without platform resolution;
- carry ORM entities across a tenant-context switch;
- use raw global cache keys for tenant data;
- use the platform database for domain tables;
- let one tenant failure leak configuration or stop unrelated fleet work.

## Dependency and extension rules

Prefer constructor injection for interfaces registered in the CakePHP container. Use registries where providers are intentionally discoverable. Avoid service locators in new code except where Cake table lookup or an existing framework boundary requires one. Plugin-specific services remain in the plugin; extract to core only for a proven cross-plugin contract.

## Verification

Service changes should receive focused unit/integration tests for success, expected failure, rollback, retries/idempotency, policy entry-point assumptions, and tenant isolation. Run the narrow PHPUnit target and PHPCS on changed PHP; use the full verification script for cross-cutting container/workflow/platform changes.

Related guides: [authorization helpers](6.2-authorization-helpers.md), [email templates](6.3-email-template-management.md), [caching](6.4-caching-strategy.md), and [workflow approval nodes](6.5-workflow-approval-nodes.md).
