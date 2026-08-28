# App-local developer notes

This directory holds implementation contracts that are too code-specific for the published
developer guide. Start with [`../../docs/index.md`](../../docs/index.md) for system architecture,
local setup, deployment, and the complete documentation map.

## Maintained references

| Document | Purpose |
| --- | --- |
| [`workflow-api-endpoints.md`](workflow-api-endpoints.md) | Workflow definition, instance, and approval route ownership |
| [`date-range-filtering-implementation.md`](date-range-filtering-implementation.md) | Dataverse grid date-range metadata, URL, timezone, and view contract |
| [`case-insensitive-text.md`](case-insensitive-text.md) | PostgreSQL `citext`/`unaccent` and portable query rules |
| [`kingdom-calendar.md`](kingdom-calendar.md) | Tenant-host public calendar, feed, publication, and royal progress |
| [`testing-suite.md`](testing-suite.md) | App-local test harness and multi-tenant verification quick reference |
| [`domain-risk-matrix.md`](domain-risk-matrix.md) | Risk gates, with tenant/platform trust boundaries as P0 |

## Awards implementation decisions

| Document | Purpose |
| --- | --- |
| [`awards-recommendation-approvals-redesign.md`](awards-recommendation-approvals-redesign.md) | Implemented ownership and lifecycle ADR |
| [`awards-recommendation-bestowal-workflow-diagrams.md`](awards-recommendation-bestowal-workflow-diagrams.md) | Cross-service flows, locks, synchronization, and test map |
| [`awards-bestowal-todo-security.md`](awards-bestowal-todo-security.md) | Branch-scoped permissions, warrants, To-Do assignment, and migration checks |

## Historical decision records

[`realtime-websockets-assessment.md`](realtime-websockets-assessment.md) records that no real-time
transport is currently implemented. It lists the constraints a future ADR must satisfy; it is
not a deployment or implementation plan.

Keep these files concise and source-backed. Remove completed task logs, environment-specific
verification snapshots, volatile test counts, and branch-era plans once their durable decision
has been captured.
