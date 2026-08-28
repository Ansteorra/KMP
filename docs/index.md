---
layout: default
title: KMP Developer Documentation
description: Architecture, development, extension, testing, and operations guides for Kingdom Management Portal.
---

# KMP developer documentation

Kingdom Management Portal is a CakePHP 5.4 application with a Vite-built
Stimulus/Bootstrap frontend and first-party domain plugins. Hosted KMP is a
managed, database-per-tenant platform: one PostgreSQL database owns platform
metadata and each kingdom has an isolated application database.

These guides describe current behavior. Source code, migrations, tests, and
runtime configuration remain authoritative when a low-level detail changes.
Generated PHP and JavaScript class references are rebuilt during the Pages
workflow instead of being maintained by hand.

## Start here

| Goal | Guide |
| --- | --- |
| Learn the system's purpose and boundaries | [Introduction](1-introduction.md) |
| Run KMP locally | [Getting started](2-getting-started.md) |
| Understand the system | [Architecture](3-architecture.md) |
| Work safely across tenants | [Multi-tenant architecture](3.1-multi-tenant-architecture.md) |
| Configure the runtime | [Configuration](2-configuration.md) |
| Make and verify a change | [Development workflow](7-development-workflow.md) |
| Choose the correct test lane | [Testing infrastructure](7.3-testing-infrastructure.md) |
| Extend KMP or a plugin | [Extending KMP](11-extending-kmp.md) |
| Operate the managed platform | [Deployment and operations](8-deployment.md) |

## Architecture and data

- [Architecture overview](3-architecture.md)
- [Multi-tenant architecture and isolation contract](3.1-multi-tenant-architecture.md)
- [Application foundation](3.1-core-foundation-architecture.md)
- [Model behaviors](3.2-model-behaviors.md)
- [Data architecture](3.3-database-schema.md)
- [Application, plugin, and platform migrations](3.4-migration-documentation.md)
- [Data seeding](3.6-seed-documentation.md)

## Domain and extension points

- [Core modules](4-core-modules.md)
- [Authorization and RBAC](4.4-rbac-security-architecture.md)
- [Gatherings](4.6-gatherings-system.md)
- [Documents and retention](4.7-document-management-system.md)
- [Plugin architecture](5-plugins.md)
- [Activities](5.6-activities-plugin.md), [Officers](5.1-officers-plugin.md),
  [Awards](5.2-awards-plugin.md), and [Waivers](5.7-waivers-plugin.md)
- [Service ownership map](6-services.md)
- [Workflow approval nodes](6.5-workflow-approval-nodes.md)

## Frontend and UI

- [UI and view patterns](9-ui-components.md)
- [Dataverse grids](9.3-dataverse-grid-complete-guide.md)
- [JavaScript and Stimulus](10-javascript-development.md)
- [Vite asset management](10.4-asset-management.md)
- [Timezone handling](10.3-timezone-handling.md)
- [Turbo Frame navigation](hotwire-navigation.md)
- [Accessibility review baseline](accessibility-audit-report.md)

## Development and quality

- [Development workflow](7-development-workflow.md)
- [Security practices](7.1-security-best-practices.md)
- [Testing infrastructure](7.3-testing-infrastructure.md)
- [Security debug tooling](7.4-security-debug-information.md)
- [Console command map](7.7-console-commands.md)
- [Performance sizing and telemetry](7.8-performance-sizing.md)
- [API reference portal](api/index.md)
- [Troubleshooting, glossary, and framework references](appendices.md)

## Operations

- [Managed deployment overview](8-deployment.md)
- [Environment and role configuration](8.1-environment-setup.md)
- [Docker development](docker-development.md)
- [Deployment runbooks](deployment/README.md)
- [Backup and restore](deployment/backup-restore.md)
- [Managed trust documentation](deployment/trust-docs-index.md)

The standalone installer and provider-specific single-tenant quick starts are
retained only where they help maintain legacy environments. They are not a
supported path for new hosted tenants.

## Documentation maintenance

When behavior changes, update the nearest owning guide and remove superseded
instructions. Do not copy secrets, customer data, generated reports, temporary
plans, or exhaustive class snapshots into this site. See
[`docs/AGENTS.md`](AGENTS.md) for the documentation contract.

The [`for_kids`](for_kids/index.md) material is a separate educational audience;
it is not part of the developer reference.
