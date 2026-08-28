# Implementation plan: [FEATURE]

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: [link]
**Input**: `specs/[###-feature-name]/spec.md`

**Note**: This template is filled by the Spec Kit planning workflow in `.github/prompts/speckit.plan.prompt.md`.

## Summary

[State the user outcome and the smallest source-backed technical approach.]

## Technical context

**Language/runtime**: PHP 8.4, CakePHP 5; [list other relevant runtimes]
**Primary dependencies**: [existing KMP services/plugins/frontend components; justify additions]
**Data scope**: [tenant / platform / both / none]
**Storage and connection**: PostgreSQL 16; [name tenant/platform connection, cache, documents, or object storage involved]
**Frontend**: [server-rendered / Stimulus / Turbo Frame or Stream / none]; Turbo Drive remains disabled
**Assets**: Vite via `app/vite.config.js` and `ViteHelper`
**Testing**: [targeted PHPUnit/Jest/Playwright lanes and negative/cross-tenant coverage]
**Target hosts**: [tenant host(s) / platform-admin host / API / background-only]
**Performance and scale**: [feature-specific measurable target or “no new target”]
**Operational effects**: [migrations, jobs, mail, files, cache, backup/restore, deployment, none]
**Constraints/unknowns**: [WCAG 2.2 AA, authorization, compatibility, and unresolved decisions]

Do not replace unknowns with generic numeric targets. Resolve them during research or mark them explicitly.

## Constitution check

*Gate: complete before research/design and re-check after design.*

- [ ] **Repository contract**: Applicable `AGENTS.md` files and existing patterns were read.
- [ ] **Ownership**: Core versus plugin ownership and integration points are justified.
- [ ] **Tenant/platform boundary**: Connection, host, cache, storage, job, and cross-tenant behavior are explicit.
- [ ] **Authorization/security**: Policies/scopes, negative paths, sensitive data, CSRF, restore locks, and impersonation implications are covered.
- [ ] **Frontend/accessibility**: Turbo/Stimulus/Vite choices fit current patterns and WCAG 2.2 AA behavior is testable.
- [ ] **Services/side effects**: Complex workflow and side effects live in explicit services/jobs.
- [ ] **Schema/operations**: Core, plugin, tenant, and platform migration or deployment effects are identified.
- [ ] **Verification**: Proportional automated and manual checks are named without hard-coded counts/timings.
- [ ] **Documentation/release**: Owning docs, `AGENTS.md` guidance, changelog, and release impact are identified.

**Deviations**: [None, or explain and record them in Complexity tracking.]

## Research decisions

For each consequential unknown, record:

- **Decision**: [chosen approach]
- **Evidence**: [source files, tests, documentation, or measured behavior]
- **Alternatives considered**: [viable alternatives and why not selected]
- **Tenant/platform impact**: [isolation and connection implications]
- **Operational impact**: [migration, job, storage, deployment, rollback]

## Feature documentation

```text
specs/[###-feature]/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
└── tasks.md
```

Include only artifacts the feature needs.

## Source layout

Choose existing paths; do not scaffold every option.

### Core

```text
app/src/Controller/
app/src/Model/
app/src/Policy/
app/src/Services/
app/src/KMP/GridColumns/
app/templates/
app/assets/js/controllers/
app/config/Migrations/
app/tests/
```

### Plugin

```text
app/plugins/[PluginName]/src/
app/plugins/[PluginName]/templates/
app/plugins/[PluginName]/assets/
app/plugins/[PluginName]/config/Migrations/
app/plugins/[PluginName]/tests/
```

### Platform and tenancy

[List platform migration/command/service paths, tenant-aware middleware/connection/cache/storage paths, and host-context tests actually touched.]

**Structure decision**: [Exact owning paths and rationale.]

## Complexity tracking

*Fill only for constitution deviations.*

| Deviation | Why required | Safe alternative rejected because | Mitigation and verification |
| --- | --- | --- | --- |
| [example] | [reason] | [reason] | [controls] |
