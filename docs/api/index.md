---
layout: default
title: API reference
---

# API reference

KMP exposes a tenant-scoped REST API plus generated source references. Every API request must
use the intended tenant hostname; host resolution selects the tenant database before the API
controller runs.

## Reference sets

| Resource | Purpose |
| --- | --- |
| [PHP API reference](php/index.html) | Generated controllers, models, policies, services, commands, and plugin PHP |
| [JavaScript API reference](js/index.html) | Generated Stimulus controllers, utilities, and plugin JavaScript |
| `<tenant-base-url>/api-docs/` | Runtime Swagger UI for the merged REST specification |
| `<tenant-base-url>/api-docs/openapi.json` | Runtime OpenAPI document, including fragments from loaded plugins |

The runtime links belong to a running KMP tenant, not the GitHub Pages documentation host.
For local development, for example, use
`http://kmp.localhost:8080/api-docs/`.

## Authentication and tenancy

Most `/api/v1/*` endpoints require a tenant service-principal token. The Bearer
header is the preferred transport:

```bash
curl \
  -H 'Authorization: Bearer <token>' \
  -H 'Accept: application/json' \
  'https://tenant.example.org/api/v1/service-principals/me'
```

The authenticator also accepts `X-API-Key`. URL query credentials are no longer
accepted; migrate any `api_key` integration to one of these headers before rollout.
Never put credentials into URLs, logs, or browser history.

A token is resolved inside the tenant chosen by the request host. A valid token from one
tenant must not authorize a request to another tenant host. API controllers return JSON and
use the shared success (`data`, optional `meta`) and error (`error`) envelopes.

Branches are the deliberate public exception: `GET /api/v1/branches` and
`GET /api/v1/branches/<public_id>` allow unauthenticated read-only access, but still expose
only the resolved tenant's public branch data. The public gathering iCalendar feed at
`GET /gatherings/feed` is a web endpoint rather than a v1 JSON endpoint and likewise remains
tenant-host bound.

## Current v1 surface

| Area | Routes |
| --- | --- |
| Service principal | `GET /api/v1/service-principals/me` |
| Members | `GET /api/v1/members`, `GET /api/v1/members/<id>` |
| Branches (public) | `GET /api/v1/branches`, `GET /api/v1/branches/<public_id>` |
| Roles | `GET /api/v1/roles`, `GET /api/v1/roles/<id>` |
| Officers plugin | read-only departments, offices, and roster list/detail under `/api/v1/officers/*` |
| Activities plugin | `GET /api/v1/activities/member-authorizations` |

Loaded plugins publish routes through `KMPApiPluginInterface::registerApiRoutes()` and merge
OpenAPI fragments through the existing registry. Do not hard-code plugin endpoints into core
API controllers.

## Extending the API

See [Extending KMP](../11-extending-kmp.md) for controller, route, service, policy, plugin, and
OpenAPI patterns. The canonical implementation sources are:

- `app/config/routes.php`
- `app/src/Controller/Api/ApiController.php`
- `app/src/Controller/Api/V1`
- `app/src/Services/OpenApiMergeService.php`
- `app/webroot/api-docs/openapi.yaml`
- each loaded plugin's `registerApiRoutes()` and OpenAPI fragment

Add route/authentication/policy tests, response-envelope tests, merged-spec tests, and a
cross-tenant host/token denial case for changes that affect tenant data.

## Regenerating source references

Generated PHP and JavaScript references are build artifacts; do not edit their HTML by hand.
After installing `app/` dependencies, run from the repository root:

```bash
./generate_api_docs.sh
```

The `docs/api/php` and `docs/api/js` trees are generated-only, ignored build artifacts.
The script builds both in a temporary directory on the same filesystem and publishes the
complete API tree through a rollback-safe same-filesystem rename transaction only after the
pinned, checksum-verified phpDocumentor release and strict JSDoc generator both pass.
Existing references remain available if a tool, download, checksum, generation, or diagnostic
check fails; phpDocumentor warnings and errors are both fatal.

The script requires Bash, PHP, `curl`, `sha256sum`, npm, `flock`, GNU core utilities,
and installed `app/` dependencies, matching the project dev container and Linux CI runner.
The lock rejects concurrent builds. Traps restore the saved tree after a normal failure or
signal; if the process ends abruptly during the rename transaction, the next run recovers the
orphaned backup before building. The GitHub Pages workflow regenerates these ignored outputs
before Jekyll, so retired pages cannot survive a published build.

Production plugin PHP paths are intentionally explicit in `app/phpdoc.dist.xml` so test
fixtures, templates, and migrations do not enter the reference. Add every new plugin's
`src` directory there; JSDoc discovers supported plugin JavaScript through its config.

[Back to documentation home](../index.md)
