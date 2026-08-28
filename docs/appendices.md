---
layout: default
---
[← Back to Table of Contents](index.md)

# Appendices

This page is a compact troubleshooting and terminology reference. Use the linked
architecture and deployment pages for the owning contracts and procedures.

## A. Troubleshooting

Start with the failing request's host, tenant, datasource, and authorization
context. In a multi-tenant application, an apparently valid record ID or route
can still belong to the wrong database or host.

### Tenant or database connection fails

KMP uses separate datasource tracks:

- the platform database stores tenant registry and platform operations data;
- the request-selected tenant database stores application and plugin data.

The supported container and managed deployment path uses PostgreSQL. Confirm
that the platform datasource is healthy, the request host maps to an active
tenant, the tenant's secret can be resolved, and that tenant's database has the
expected migrations. Do not solve a tenant failure by pointing the default
datasource at the platform database.

For local browser requests, use the configured tenant hostname such as
`kmp.localhost`; an IP-only URL may not select the intended tenant. See
[Configuration](2-configuration.md), [Multi-tenant architecture](3.1-multi-tenant-architecture.md),
and [Deployment troubleshooting](deployment/troubleshooting.md).

### A migration fails

Identify whether it is a platform migration or a tenant application/plugin
migration before running anything. Check the migration state for the affected
database, plugin order, referenced tables, and PostgreSQL constraint error.
Fleet migrations must report failures per tenant; do not mark the release
healthy because a different tenant migrated successfully.

Follow [Migration documentation](3.4-migration-documentation.md) and the
multi-tenant deployment runbook instead of improvising schema changes.

### Login or authorization fails

Check these layers in order:

1. The hostname resolved an active tenant.
2. The session identity belongs to that tenant and is active.
3. The controller authorized the resource or model.
4. The policy scope includes the current branch and record.
5. Restore lock, impersonation, or step-up authentication rules permit the
   operation.

A working URL and a known numeric ID do not imply access. Inspect CakePHP logs
and the relevant policy; never bypass authorization or expose debug details in
production.

### Frontend assets do not load

The application resolves hashed frontend files through
`webroot/.vite/manifest.json`. From `app/`, run a current build and confirm the
logical entry exists:

```bash
npm ci
npm run dev
```

If an old bundle still loads, confirm the rendered URL came from the current
manifest, then inspect browser and service-worker caches. See
[Asset management](10.4-asset-management.md).

### A Stimulus controller does not connect

Confirm that the file ends in `-controller.js`, lives in a discovered core or
plugin controller directory, and registers its identifier in
`window.Controllers`. Check the browser console for duplicate identifiers or
module failures, then run the focused Jest test and rebuild the `controllers`
entry. See [JavaScript development](10-javascript-development.md).

### A Turbo modal or grid loses state

Check the inner table frame ID, the Turbo Stream `Accept` header, CSRF token,
`data-turbo="true"`, and the hidden `page_context_url`. The context must be a
same-origin relative path with the current query string. See
[Hotwire navigation](hotwire-navigation.md) and
[Dataverse Grid system](9.1-dataverse-grid-system.md).

### Data appears under the wrong tenant

Treat this as a security incident until disproved. Record the request host,
resolved tenant ID, datasource name, route, and job/CLI context without logging
credentials or secrets. Stop the affected worker or flow if it could continue
cross-tenant work, then follow the deployment incident process. Never repair the
symptom by copying records between tenant databases.

### A page is slow or memory-heavy

Use request traces, query logs, and targeted profiling to find the cause. Apply
authorization scope before pagination, request only needed associations and
columns, and use the Dataverse Grid query context for computed/filter fields.
Do not enable an application-wide cache or raise memory limits before confirming
that cache keys and queries remain tenant-scoped.

## B. Glossary

| Term | Meaning in KMP |
| --- | --- |
| **Active window** | Inclusive start/end period during which a time-bound record, such as a warrant or authorization, is active. |
| **Branch** | Hierarchical SCA organizational unit used for ownership and authorization scope. |
| **Dataverse Grid** | KMP's server-backed grid abstraction for scoped queries, system/user views, filters, sorting, pagination, export, and Turbo updates. |
| **Gathering** | Scheduled event with activities, staffing, attendance, waivers, and an explicit display timezone. |
| **Member** | Tenant-owned person/domain record; it is not interchangeable with a platform tenant or database user. |
| **Office** | Role within the SCA organizational structure that may be held under a warrant. |
| **Platform database** | Shared control-plane database containing tenant registry and platform operational metadata, not tenant business records. |
| **Service principal** | Non-human identity used for approved automation with explicit tenant and permission scope. |
| **System view** | Code-defined Dataverse Grid view with stable filters/columns; distinct from a user's saved view. |
| **Tenant** | Isolated KMP organization selected primarily by request host and backed by its own application database. |
| **Tenant context** | Resolved tenant identity and connection state carried by a web request, worker job, or CLI operation. |
| **Tenant database** | Database containing one tenant's core and enabled-plugin business data. |
| **Tenant host** | Registered hostname that resolves an incoming request to one tenant. |
| **Turbo Frame** | Named page region that can navigate and replace its own HTML. |
| **Turbo Stream** | Server response containing one or more DOM update actions such as replace or remove. |
| **View cell** | Registry-provided, authorization-aware UI contribution used by core and plugins for tabs, details, modals, JSON, or mobile navigation. |
| **Warrant** | Time-bounded authorization for a member to hold an office within an organizational scope. |
| **Warrant roster** | Managed group of warrant requests/decisions for a branch and term. |
| **Workflow definition** | Durable description of workflow states, transitions, actors, and actions. |
| **Workflow version** | Immutable published revision of a workflow definition. |
| **Workflow instance** | Runtime execution of one workflow version for a domain record. |
| **Approval** | Human decision requested and recorded by a workflow step. |
| **Action item** | User-facing task produced by a workflow or domain process. |

## C. Primary references

### Project documentation

- [Getting started](2-getting-started.md)
- [Configuration](2-configuration.md)
- [Architecture](3-architecture.md)
- [Multi-tenant architecture](3.1-multi-tenant-architecture.md)
- [Deployment](8-deployment.md)
- [Generated PHP API](api/php/index.html)
- [Generated JavaScript API](api/js/index.html)
- [KMP source repository](https://github.com/Ansteorra/KMP)

### Frameworks and tools

- [CakePHP 5 Book](https://book.cakephp.org/5/en/index.html)
- [CakePHP 5 API](https://api.cakephp.org/5.x/)
- [PHP manual](https://www.php.net/manual/en/)
- [Composer documentation](https://getcomposer.org/doc/)
- [Vite guide](https://vite.dev/guide/)
- [Stimulus handbook](https://stimulus.hotwired.dev/handbook/introduction)
- [Turbo handbook](https://turbo.hotwired.dev/handbook/introduction)
- [Bootstrap 5.3 documentation](https://getbootstrap.com/docs/5.3/)
- [Bootstrap Icons](https://icons.getbootstrap.com/)
- [Font Awesome](https://fontawesome.com/docs)
- [Jest documentation](https://jestjs.io/docs/getting-started)
- [Playwright documentation](https://playwright.dev/docs/intro)
