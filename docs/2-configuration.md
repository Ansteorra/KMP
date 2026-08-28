---
layout: default
title: Configuration
description: Configuration sources and boundaries for the KMP application, platform, tenants, and secret stores.
---

[← Documentation home](index.md)

# 2. Configuration

KMP uses committed PHP defaults plus environment-specific values. Configuration
must preserve the distinction between platform state, tenant state, and secrets.
The committed local sample is a development convenience, not a production
configuration template.

## Load order

`app/config/bootstrap.php` loads configuration in this order:

1. `app/config/.env` when local-dotenv loading is allowed;
2. `app/config/app.php` for committed defaults;
3. optional untracked `app/config/app_local.php` overrides;
4. `app/config/app_queue.php`; and
5. `app/config/secrets.php`.

Later CakePHP configuration files override earlier keys. Real process
environment variables are the production-facing input to the committed config.
Do not commit `app/config/.env`, `app_local.php`, secret-store files, connection
strings, recovery material, or customer-specific values.

## Local configuration

`./dev-up.sh` creates `app/config/.env` from
`app/config/.env.example` when it is absent. Edit only the untracked copy.
Important local controls include:

| Area | Representative variables |
| --- | --- |
| Runtime | `KMP_ENV`, `DEBUG`, `SECURITY_SALT`, `REQUIRE_HTTPS` |
| Tenant datasource | `DATABASE_URL` or `DB_*` |
| Platform datasource | `PLATFORM_DATABASE_URL` or `PLATFORM_DB_*` |
| Host routing | `KMP_TENANCY_ENABLED`, `KMP_DEV_TENANT_HOST`, `KMP_PLATFORM_ADMIN_HOSTS` |
| Local services | `KMP_APP_PORT`, `KMP_DB_HOST_PORT`, `KMP_MAILPIT_*`, `KMP_PGADMIN_*` |
| Background work | `KMP_SCHEDULER_POLL_INTERVAL`, `QUEUE_*` |

Use URL-style datasource variables where the deployment platform supplies them;
otherwise the individual host, port, username, password, and database variables
are supported. PostgreSQL is the current local and hosted database. MySQL
compatibility branches remain in configuration for legacy environments and are
not the managed-platform baseline.

## Datasource boundaries

KMP configures two durable connection roles:

- `platform` owns the tenant/host registry and platform operational state;
- `default` is the application connection name expected by CakePHP tables.

With tenancy enabled, `TenantConnectionManager` creates a physical `tenant`
connection for the resolved database and temporarily aliases it to `default`.
Application and plugin tables therefore continue to use `default` without
becoming platform tables. Code must not move a business table to `platform` for
convenience or read tenant data before tenant resolution.

Tests use separate application and platform test database settings. Use the
project test harness so both are created and reset consistently.

## Tenant-derived configuration

Tenant-specific settings are not selected directly from environment variables
on an HTTP request. Once the host is resolved, the connection manager and
supporting services apply scoped values such as:

- tenant database credentials from the configured secret store;
- tenant mail transport/profile overrides;
- document storage container or prefix; and
- tenant-aware cache namespaces.

Those values are restored after the request or job. A new tenant-specific
integration must follow the same enter/leave lifecycle and have a leak test.
See [Multi-tenant architecture](3.1-multi-tenant-architecture.md).

## Secret stores

`app/config/secrets.php` defines `env`, local `file`, encrypted `database`, and
`chain` drivers.

- The default local chain reads environment values and the local secret file,
  and writes to the file.
- The file driver is restricted to local/development/test-style environments.
- The database driver stores encrypted secret records in the platform database;
  its master wrapping key must come from a separate configured source.
- Managed deployment imports missing environment-backed values before backup-key
  reconciliation. It does not overwrite a value already rotated into the
  database-backed store.

Secret names and metadata may be logged where explicitly safe. Secret values,
connection URLs, tenant database passwords, KEKs, DEKs, TOTP seeds, and recovery
keys must never appear in logs, documentation, command histories, fixtures, or
error responses.

## Sessions, cookies, and host isolation

Tenant web sessions use host-only cookies by default. Keep secure cookies and
HTTPS enforcement enabled in production and trust proxy headers only from the
configured proxy boundary. The platform administration portal has its own
central identity and session flow on an allowlisted platform-admin host; it does
not reuse tenant member authentication.

Do not claim that CSRF tokens are cryptographically tenant-bound. KMP has a
`TenantCsrfTokenScope` extension point, but the current middleware contract is
host resolution before the normal CSRF/authentication stack plus host-only
session isolation.

## Cache and queue configuration

Use `TenantAwareCache` for tenant-derived cache data. Platform cache entries use
an explicit platform namespace; the tenant host-map cache is platform-scoped
and invalidated when registry data changes. Never construct a cache key from a
record ID alone when records may exist in different tenant databases.

Application queue records live in the current tenant database. Platform jobs
and schedule definitions live centrally. The unified platform worker selects a
tenant, enters its connection context, drains bounded work, and restores the
previous state before continuing.

## Mail, storage, and observability

Mail, document storage, and telemetry all have committed defaults in
`app/config/app.php` and deployment overrides through environment/secret values.
Follow these rules:

- keep application timestamps in UTC and convert only at input/display edges;
- route documents through the tenant storage resolver rather than constructing
  container names or prefixes in controllers;
- use Mailpit for local delivery;
- keep high-cardinality or identifying tenant data out of telemetry labels; and
- do not enable a compliance or immutable-audit claim until its configured sink
  and retention controls are actually deployed and verified.

## Adding configuration

When introducing a durable setting:

1. Choose the owner: platform-wide, tenant-specific, plugin-specific, or local
   developer tooling.
2. Add a safe default and validation close to the consuming component.
3. Put sensitive values behind the secret-store abstraction.
4. Update `app/config/.env.example` only when developers need a local example.
5. Add tests for missing, invalid, and cross-tenant configuration.
6. Update this guide or the nearest domain/operations guide.

Avoid reading `getenv()` throughout domain code. Prefer CakePHP `Configure` or a
small typed resolver/service at the owning boundary.

## Troubleshooting

- **Every host is 404/503:** verify the platform datasource, tenant/host registry,
  tenant status, and schema version before changing routes.
- **Wrong tenant data appears:** stop immediately; inspect tenant resolution,
  connection cleanup, TableLocator reset, cache namespace, and worker context.
- **A rotated secret appears ignored:** check the configured driver, namespace,
  and import semantics; do not overwrite the store to make the environment win.
- **URLs use HTTP behind a proxy:** verify `TRUST_PROXY`, the trusted proxy list,
  and `X-Forwarded-Proto` handling.
- **Local settings do not change:** confirm whether the value is overridden in
  `app_local.php`, queue/secrets config, or a real process environment variable.

## Related guides

- [Getting started](2-getting-started.md)
- [Multi-tenant architecture](3.1-multi-tenant-architecture.md)
- [Security practices](7.1-security-best-practices.md)
- [Environment setup](8.1-environment-setup.md)
