# Session: 2026-02-10 — Initial Codebase Exploration

**Requested by:** Josh Handel

## Who Worked

| Agent | Role | Domain |
|-------|------|--------|
| Mal | Lead / Architect | Full architecture mapping |
| Kaylee | Backend Dev | Services, models, workflows, DI |
| Wash | Frontend Dev | Stimulus controllers, templates, assets |
| Jayne | Tester | Test suite audit and coverage |

## What Was Done

All four agents explored their domains in parallel and documented findings.

### Mal (Lead)
- Mapped full application architecture from `Application.php` through middleware, DI, auth, and plugins
- Cataloged 6 active plugins (Activities, Officers, Awards, Waivers, Queue, GitHubIssueSubmitter) + 2 inactive (Template, Events)
- Documented service layer: 5 DI services, 3 static registries, 10+ non-DI services
- Mapped auth chain: dual authentication (session+Bearer), policy-based authorization, 37 policy classes
- Identified 8 dangerous-to-change areas (BaseEntity/BaseTable hierarchy, PermissionsLoader, ServiceResult, registries, middleware order, ActiveWindowBehavior, transaction ownership, window.Controllers)

### Kaylee (Backend Dev)
- Deep-dived backend services, models, and workflow engine
- Documented 14 critical backend patterns: ServiceResult, DI registration, transaction management, entity/table hierarchy, policy pattern, plugin architecture, email sending, JSON columns, controller authorization, ActiveWindow entities, AppSettings, permissions flow, DataverseGrid
- Mapped DI dependency graph: ActiveWindowManager → WarrantManager → OfficerManager
- Documented warrant workflow engine (request → approve/decline → cancel/expire)
- Identified key gotchas: termYears is actually months, transaction ownership split, dead code in cancel()

### Wash (Frontend Dev)
- Cataloged 81 Stimulus controllers: 60 core + 21 plugin (Activities 5, Officers 5, Waivers 10, GitHubIssueSubmitter 1)
- Documented asset pipeline: Laravel Mix auto-discovery, 4 output bundles (controllers.js, core.js, manifest.js, CSS)
- Mapped template system: 6 layouts, block-based architecture, ViewCellRegistry integration
- Documented tab ordering system: CSS flexbox with data-tab-order, order guidelines 1-999
- Cataloged inter-controller communication via outlet-btn pattern

### Jayne (Tester)
- Audited full test suite: 88 files, ~536 methods
- Identified ~70 methods are `markTestIncomplete` stubs (effectively no-ops)
- Estimated real coverage: 15-20% of application code
- Found strongest coverage in authorization service; weakest in controllers (20/26 untested), policies (36/37 untested), mailers (0 tests)
- Confirmed no CI pipeline for tests — only JS build in GitHub Actions
- Documented test infrastructure: BaseTestCase, seeded DB approach, transaction isolation

## Decisions Made

All four agents wrote decisions to the inbox:
- `mal-architecture-overview.md` — Architecture overview and dangerous-to-change areas
- `kaylee-backend-patterns.md` — 14 backend patterns and conventions
- `wash-frontend-patterns.md` — Frontend patterns, controller catalog, template system
- `jayne-test-patterns.md` — Test patterns, gaps, and recommendations

All merged to `decisions.md` by Scribe.

## Key Outcomes

- Team now has shared understanding of a 2-year-old codebase
- Critical patterns documented for safe development
- 8 dangerous-to-change areas identified
- Test coverage gaps quantified — security-critical areas largely untested
- No CI test pipeline exists — tests don't run on PRs
