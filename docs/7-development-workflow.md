---
layout: default
title: Development workflow
description: Repository workflow, ownership rules, implementation patterns, and proportionate verification for KMP changes.
---

[← Documentation home](index.md)

# 7. Development workflow

A good KMP change is small, belongs to the right architectural boundary, proves
tenant isolation and authorization where relevant, and updates the durable guide
that owns its contract.

## Before editing

From the repository root:

```bash
git status --short
rg --files -g 'AGENTS.md'
```

Then:

1. identify every path you expect to touch;
2. read each `AGENTS.md` from the repository root to those paths;
3. preserve unrelated worktree changes;
4. search for an existing controller, service, policy, plugin, registry,
   migration, Stimulus controller, test, or documentation pattern; and
5. choose the narrowest verification that can falsify the proposed change.

Use `rg`/`rg --files` for discovery. Run app commands from `app/` unless a local
guide says otherwise.

## Place code by ownership

| Concern | Owner |
| --- | --- |
| HTTP orchestration | web `AppController` or API `ApiController` subclass |
| Persistence and local invariants | `BaseTable`/`BaseEntity` subclasses and behaviors |
| Authorization and collection reach | `BasePolicy` subclasses and scopes |
| Multi-step business work | core or plugin `src/Services` |
| Platform registry/fleet operations | `app/src/Services/Platform` and platform migrations |
| Domain plugin behavior | that plugin's controllers/models/policies/services/assets/tests |
| Plugin UI contribution | navigation/view-cell/action/workflow registries |
| Grid definitions | `DataverseGridTrait` and `BaseGridColumns` provider |
| User-facing behavior | accessible templates plus focused Stimulus controller |

Do not put business workflows in templates, tenant selection in request data,
plugin conditionals in core views, or authorization in hidden buttons alone.

## Tenant-safe implementation

Before creating a table or service, decide whether its data is platform-owned or
tenant-owned. Normal domain records belong in tenant core or an active plugin.
Hosted requests resolve the tenant host and bind a fresh connection/table locator
before authentication and authorization.

For tenant-sensitive changes, test:

- unknown/platform-unavailable/inactive/schema-behind requests fail closed;
- the same internal ID in tenant A and B resolves only in the active database;
- caches, mail, document storage, table locators, and static state restore after
  a scope;
- jobs enter the target tenant and cannot reuse the previous job's context; and
- suspended tenants are included only by explicitly authorized maintenance.

Use the complete contract in
[Multi-tenant architecture](3.1-multi-tenant-architecture.md).

## Backend conventions

PHP files use `declare(strict_types=1);` and CakePHP/project coding standards.
Controllers authorize loaded resources and scope collection queries. Prefer
named arguments where CakePHP's current APIs make them clearer, but do not add
native types to inherited framework/plugin methods merely because a docblock
mentions one.

Use transactions around atomic domain state, and ensure external/queued effects
are retry-safe. Store timestamps in UTC; convert through timezone helpers at
input/display boundaries. Use public IDs for supported external routes and then
perform normal authorization.

For schema changes, add a forward migration to exactly one platform, tenant-core,
or tenant-plugin track. Never edit a released migration. Fleet releases verify
all loaded plugin histories; see [Migration lifecycle](3.4-migration-documentation.md).

## Frontend conventions

Frontend source lives in `app/assets` and is built by Vite. Stimulus controllers
use `*-controller.js`, declare targets/values/outlets, register through
`window.Controllers`, and remove global listeners in `disconnect()`. Turbo Drive
is disabled; use established Turbo Frame response patterns.

Use Bootstrap components and existing `KMP_utils`/`KMP_accessibility` helpers.
Preserve WCAG 2.2 Level AA: semantic elements, labels, keyboard operation,
visible focus, correct focus order/return, ARIA state, announced async status,
contrast, and non-color-only cues. Keep data attributes used by templates and
tests stable.

## Tests while implementing

Run the fastest relevant lane frequently:

```bash
# PHP unit/model/service
vendor/bin/phpunit --testsuite core-unit

# HTTP/controller/command/view
vendor/bin/phpunit --testsuite core-feature

# Plugin behavior
vendor/bin/phpunit --testsuite plugins

# One PHP test or method
vendor/bin/phpunit tests/TestCase/path/Test.php
vendor/bin/phpunit --filter testMethodName

# JavaScript
npm run test:js

# Bundle/import changes
npm run dev
```

Use project base test classes and stable seed constants. For workflow side
effects, test the trigger-driven chain rather than directly calling an action in
isolation. Add Playwright for browser flows, Turbo Frames, modals, focus, and
multi-host tenancy.

## Verification before handoff

Choose checks proportionate to risk:

| Change | Minimum useful verification |
| --- | --- |
| Documentation only | diff review, link/stale-term scan; Jekyll build if site structure changed |
| PHP behavior | targeted PHPUnit plus `vendor/bin/phpcs <changed.php>` |
| Policy/security | positive and negative authorization tests, branch scope, tenant boundary |
| JavaScript behavior | focused Jest; `npm run dev` for imports/bundle changes |
| Template/UI | relevant PHP/Jest/Playwright plus accessibility review |
| Migration | clean setup + seeded upgrade + tenant catalog/status verification |
| Cross-cutting | `bash bin/verify.sh` when practical |

The standard verifier runs the three PHPUnit suites, skipped-test budget, seed
snapshot contracts, Jest, Markdown/JSDoc integrity, Vite, changed-PHP PHPCS,
Azure runtime contract, and PHPStan:

```bash
bash bin/verify.sh
```

Coverage and mutation lanes are opt-in:

```bash
bash bin/verify.sh --with-coverage=security
bash bin/verify.sh --with-mutation=security
```

Playwright is separate from `verify.sh`:

```bash
npm run test:ui
npm run test:ui:journey
```

Do not claim an unrun lane passed. Report the exact command and outcome, or the
reason it could not run.

## Documentation and generated references

Update the closest owning documentation when behavior, ownership, workflow,
permissions, inputs/outputs, side effects, commands, or operational expectations
change. Delete superseded text rather than appending historical corrections.
Temporary plans and cleanup trackers do not belong in `docs/` or `app/docs/`.

Validate handwritten documentation and JSDoc without changing generated output:

```bash
cd app
npm run docs:check
npm run docs:js:check
```

For a clean source-reference rebuild, run `./generate_api_docs.sh` from the
repository root. It builds both references in staging, fails on PHPDoc warnings/errors or
strict JSDoc diagnostics, and replaces the ignored output directories only after both pass.
The Pages workflow runs those checks on pull requests with read-only repository permissions,
builds Jekyll with the frozen `docs/Gemfile.lock`, and uploads/deploys only a successful
`main` artifact. Handwritten guides should describe durable patterns and link to generated
APIs rather than copying class inventories.

## Review checklist

- The diff contains no unrelated reformatting or generated runtime output.
- Every changed path follows its complete `AGENTS.md` chain.
- Platform versus tenant ownership is explicit.
- Authorization and policy scope are server-enforced.
- Plugin changes remain inside the plugin or use a documented registry.
- Queued/scheduled effects are idempotent and tenant-scoped.
- UI remains accessible and frontend listeners clean up.
- Tests cover failure/denial and a second tenant when relevant.
- Commands, documentation links, and examples match current source.

## Related guides

- [Testing infrastructure](7.3-testing-infrastructure.md)
- [Security practices](7.1-security-best-practices.md)
- [Console commands](7.7-console-commands.md)
- [Extending KMP](11-extending-kmp.md)
