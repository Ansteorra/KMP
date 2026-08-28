---
layout: default
title: Introduction
description: What KMP is, who the developer documentation serves, and the boundaries of the managed platform.
---

[← Documentation home](index.md)

# 1. Introduction

Kingdom Management Portal (KMP) is a membership and administration system for
Society for Creative Anachronism kingdoms. It combines a CakePHP application,
first-party domain plugins, and a managed platform layer so multiple kingdoms
can use the same deployed application without sharing application data.

## What KMP manages

The core application provides:

- members, households, branches, roles, permissions, warrants, gatherings, and
  documents;
- policy-based authorization with branch-aware scopes;
- workflow definitions and approval runs used by core and plugin features;
- audit-oriented lifecycle state, impersonation controls, and restore locks;
- accessible server-rendered views enhanced with Stimulus and Turbo Frames.

First-party plugins add Activities, Officers, Awards, and Waivers. The Queue
plugin supplies asynchronous job infrastructure. `Template` is a development
skeleton and is not enabled as a product feature.

## Current platform model

Hosted KMP is a database-per-tenant system:

1. A central PostgreSQL **platform database** stores the tenant and host
   registry, central operator identities and sessions, encrypted secret
   records, platform jobs and schedules, backup metadata, audit records, and
   operational telemetry.
2. Every kingdom has a separate PostgreSQL **tenant database** containing its
   members and all other application and plugin data.
3. The request host selects a tenant before authentication and authorization.
   Unknown, inactive, unavailable, or schema-incompatible tenants fail closed.
4. Background work explicitly enters the target tenant context before touching
   tenant tables.

There is no shared `tenant_id` discriminator in business tables. Code must not
infer tenant identity from user-controlled request data. See
[Multi-tenant architecture](3.1-multi-tenant-architecture.md) for the complete
isolation contract.

## Technology baseline

- PHP 8.4 runtime and CakePHP 5.4
- PostgreSQL 16 for platform and tenant databases
- CakePHP Authentication and Authorization
- Vite, Stimulus, Turbo Frames, Bootstrap 5, and Bootstrap Icons
- PHPUnit, Jest/jsdom, and Playwright BDD tests
- Docker Compose for the supported local environment
- Azure Container Apps for the managed hosted platform

Check `app/composer.json`, `app/package.json`, the Dockerfiles, and deployment
templates before relying on an exact patch version.

## Who these guides are for

These guides support application developers, plugin authors, reviewers, test
authors, and platform operators. Start with:

- [Getting started](2-getting-started.md) for a local stack;
- [Architecture](3-architecture.md) for system boundaries;
- [Development workflow](7-development-workflow.md) before changing code;
- [Deployment and operations](8-deployment.md) for the managed service.

Product behavior is ultimately defined by source code, migrations, tests, and
runtime configuration. These pages explain durable contracts and workflows;
they intentionally avoid exhaustive snapshots of every class or database
column because generated references and migrations serve that purpose better.

## Design principles

- **Isolation first:** tenant selection and connection state are explicit and
  fail closed.
- **Authorization at the boundary:** controllers authorize resources and apply
  policy scopes; templates do not make permission decisions.
- **Domain ownership:** business workflows live in services, model concerns in
  tables/entities/behaviors, and plugin behavior inside its plugin.
- **Operationally safe change:** platform, core, and plugin migrations are
  versioned separately and a release verifies every selected tenant schema.
- **Accessible by default:** user-facing UI targets WCAG 2.2 Level AA.
- **Document durable truth:** temporary plans, rollout notes, and generated
  class inventories are not maintained as architecture documentation.

[Next: Getting started →](2-getting-started.md)
