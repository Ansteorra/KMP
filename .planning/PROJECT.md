# Kingdom Management Portal (KMP)

## What This Is

KMP is a membership management system for SCA (Society for Creative Anachronism) Kingdoms. It handles member records, branch hierarchy, officer warrants, award recommendations, activity authorizations, and event attendance — all behind a policy-based role system. Built on CakePHP 5.x with Stimulus.js and Hotwire Turbo; deployed as a Docker container to SCA kingdom instances.

## Core Value

Members and officers can reliably track participation, warrants, and awards across a kingdom hierarchy with proper access control.

## Requirements

### Validated

- ✓ Member registration, profile management, and SCA membership tracking — existing
- ✓ Branch hierarchy (Nested Set Model) with scoped permissions (Global / Branch Only / Branch and Children) — existing
- ✓ Role-based authorization with policy classes and warrant enforcement — existing
- ✓ Officer roster management with warrant lifecycle (Upcoming → Current → Expired) — existing
- ✓ Award recommendations and processing workflow (Awards plugin) — existing
- ✓ Activity authorizations and martial participation tracking (Activities plugin) — existing
- ✓ Event/gathering management with attendance and staff tracking — existing
- ✓ Waiver management (Waivers plugin) — existing
- ✓ Background job queue for async operations (Queue plugin) — existing
- ✓ REST API with Bearer token / X-API-Key auth for service principals — existing
- ✓ Dataverse Grid pattern for filterable, sortable, paginated list pages — existing
- ✓ Email notifications with timezone-aware formatting — existing
- ✓ Database backup and restore tooling — existing
- ✓ Member mobile card with QR code — existing

### Active

- [ ] Continuous bug fixes as discovered during use
- [ ] UX polish — layout, wording, and workflow improvements for members and officers
- [ ] Code quality improvements — targeted refactors, tech debt reduction, test coverage

### Out of Scope

- New major plugins or features — maintenance cycle only; no new capabilities planned
- Architecture changes — the CakePHP 5 / Stimulus / Turbo stack is settled

## Context

KMP is a mature, production system used by SCA kingdoms. It is currently in **maintenance mode** — no large feature work is planned. Work is driven by the lead developer's personal list of known issues and quality improvements rather than a formal issue tracker.

Key characteristics to keep in mind:
- Plugin system means fixes must stay within the correct plugin boundary
- Policy layer is sensitive to LSP violations — never add native type hints to overridable `BasePolicy` methods
- PHPStan baseline has ~1947 pre-existing suppressed errors; new code must not add to it
- PHPCS has ~3400 pre-existing violations; only check changed files (`composer run-script cs-check`)
- Frontend assets require a webpack build step (`npm run dev`) to take effect
- All dates passed to mailers must be pre-formatted via `TimezoneHelper` — never pass raw `DateTime`

## Constraints

- **Tech Stack**: CakePHP 5.x / PHP 8.3 / MariaDB / Stimulus.js / Hotwire Turbo — no stack changes
- **Backward Compatibility**: Plugin policy method signatures must remain compatible with `BasePolicy` overrides (LSP)
- **Test Suite**: PRs must pass PHPUnit (~1018 tests) and Jest (~27 tests); do not use `--testsuite all`
- **Code Style**: Run PHPCS only on changed files; never run `phpcbf` globally (breaks LSP type hints)
- **Deployment**: Docker-based; release pipeline publishes `ghcr.io/ansteorra/kmp` on GitHub release

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Maintenance mode — no new plugins | Existing feature set covers kingdom needs; focus on stability over growth | — Pending |
| Work sourced from personal list, not issue tracker | Lead developer knows the codebase best; informal tracking is sufficient for this scale | — Pending |
| Continuous shipping, no release gates | Small fixes ship as ready; no batch milestones needed | — Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-06-14 after initialization*
