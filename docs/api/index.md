---
layout: default
---

# API Reference Portal

Explore auto-generated API documentation for the KMP project. Both sets now use modern, dark-themed layouts with built-in search.

| Resource | Description |
|----------|-------------|
| **[REST API (Swagger UI)](/api-docs/)** | Interactive OpenAPI documentation for the v1 REST API |
| **[PHP API Reference](php/index.html)** | Controllers, Services, Models, Behaviors, and Plugins |
| **[JavaScript API Reference](js/index.html)** | Stimulus controllers, utilities, and frontend modules |

## REST API Quick Start

The v1 REST API uses **service principal** authentication. Include your token as a Bearer header:

```bash
curl -H "Authorization: Bearer <token>" \
     https://your-kmp-instance/api/v1/officers/roster
```

The OpenAPI spec is available at [`/api-docs/openapi.json`](/api-docs/openapi.json) (merged from
base + plugin fragments) and can be imported into Postman, Insomnia, or any OpenAPI-compatible tool.

## Available Endpoints

### Core (no authentication required)

| Endpoint | Description |
|----------|-------------|
| `GET /api/v1/branches` | List branches with parent IDs for tree reconstruction |
| `GET /api/v1/branches/{id}` | Branch detail with children and plugin-injected data |

### Core (authentication required)

| Endpoint | Description |
|----------|-------------|
| `GET /api/v1/members` | List members with branch and role filters |
| `GET /api/v1/members/{id}` | Member detail |
| `GET /api/v1/roles` | List roles |
| `GET /api/v1/roles/{id}` | Role detail with permissions |
| `GET /api/v1/service-principals/me` | Current service principal info |

### Officers Plugin

| Endpoint | Description |
|----------|-------------|
| `GET /api/v1/officers/roster` | Officer roster with branch/office filters |
| `GET /api/v1/officers/roster/{id}` | Officer detail |
| `GET /api/v1/officers/offices` | List offices |
| `GET /api/v1/officers/offices/{id}` | Office detail |
| `GET /api/v1/officers/departments` | List departments |
| `GET /api/v1/officers/departments/{id}` | Department detail |

### Activities Plugin

| Endpoint | Description |
|----------|-------------|
| `GET /api/v1/activities/member-authorizations` | Look up a member's current activity authorizations by `membership_number`, `sca_name`, or `email` |

### Public Endpoints (No Authentication)

| Endpoint | Description |
|----------|-------------|
| `GET /gatherings/feed` | iCalendar subscription feed for calendar apps. Optional filters: `?branch={public_id}&type={gathering_type_id}` |

## Developer Guides

Building or extending the REST API? See the developer documentation:

| Guide | Description |
|-------|-------------|
| **[Creating API Endpoints](../11-extending-kmp.md#118-creating-rest-api-endpoints)** | Controllers, routes, auth, and response helpers |
| **[OpenAPI Documentation](../11-extending-kmp.md#119-openapi-documentation-for-plugin-apis)** | Adding plugin spec fragments to Swagger |
| **[Injecting Data into APIs](../11-extending-kmp.md#1110-injecting-data-into-other-api-responses)** | ApiDataRegistry pattern for plugin data enrichment |

## Regenerating Docs

```bash
# From repository root
./generate_api_docs.sh
```

> 💡 **Tip:** Run this script before publishing to GitHub Pages so the hosted docs stay current.

[← Back to Documentation Home](../index.md)
