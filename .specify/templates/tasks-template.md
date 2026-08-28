---
description: Task-list template for KMP feature implementation
---

# Tasks: [FEATURE NAME]

**Input**: Design documents from `specs/[###-feature-name]/`
**Prerequisites**: `spec.md` and `plan.md`; include research, data model, contracts, or quickstart only when needed.

Tasks are grouped by independently testable user outcome. Replace every sample and placeholder below with exact repository-relative paths and commands.

## Format

`[ID] [P?] [Story?] Description`

- `[P]` means the task can run in parallel because it touches different files and has no unmet dependency.
- `[US1]`, `[US2]`, and similar labels map tasks to specification user stories.
- Include the owning tenant/platform scope, authorization boundary, and verification where relevant.

## Path conventions

- Core: `app/src/`, `app/templates/`, `app/assets/`, `app/config/Migrations/`, `app/tests/`
- Plugin: `app/plugins/[PluginName]/src/`, `templates/`, `assets/`, `config/Migrations/`, `tests/`
- Controllers: `app/src/Controller/[Name]Controller.php`
- Tables/entities: `app/src/Model/Table/[Name]Table.php` and `app/src/Model/Entity/[Name].php`
- Services: `app/src/Services/[ServiceName].php`
- Policies: `app/src/Policy/[Name]Policy.php`
- Stimulus: `app/assets/js/controllers/[name]-controller.js`
- Platform/tenant infrastructure: use the exact existing path found during planning

## Phase 1: Setup and ownership

- [ ] T001 Confirm the applicable `AGENTS.md` chain and preserve unrelated worktree changes
- [ ] T002 Choose core or plugin ownership and list the exact files/integration registries involved
- [ ] T003 Identify tenant, platform, host, connection, cache, job, storage, and migration scope
- [ ] T004 [P] Identify existing services/components/tests to reuse
- [ ] T005 [P] Add only required Composer/npm configuration; update Vite inputs in `app/vite.config.js` if assets require it

**Checkpoint**: Ownership and trust boundaries are explicit.

## Phase 2: Foundational work

Select only applicable tasks and renumber the final list.

- [ ] T010 Add core/plugin/platform migration(s) in the owning migration directory
- [ ] T011 Define tenant/platform connection behavior and failure handling
- [ ] T012 [P] Add or update entities/tables, validation, associations, and indexes
- [ ] T013 [P] Add authorization policies/scopes, including denial and cross-tenant cases
- [ ] T014 Add service/job boundaries for multi-step work and explicit side effects
- [ ] T015 [P] Register plugin navigation, view cells, routes, assets, or events through existing registries
- [ ] T016 Add cache, document/object-storage, queue, backup/restore, or idempotency scoping where applicable
- [ ] T017 Add foundational tests before user-story implementation
- [ ] T018 Verify migrations with the project-supported tenant/platform workflow; do not invent a single-database command

**Checkpoint**: No user story begins until shared security and tenancy prerequisites are ready.

## Phase 3: User story 1 — [TITLE] (P1)

**Goal**: [Independent user value]
**Independent test**: [Observable behavior, host/context, role, and isolation expectation]

- [ ] T020 [P] [US1] Add success-path test(s) in [exact path]
- [ ] T021 [P] [US1] Add validation/authorization/cross-tenant test(s) in [exact path]
- [ ] T022 [US1] Implement domain behavior in [service/model path]
- [ ] T023 [US1] Implement controller/command/job orchestration in [exact path]
- [ ] T024 [P] [US1] Implement accessible template/Stimulus/Turbo behavior in [exact path], if needed
- [ ] T025 [US1] Register integrations and verify the story independently

**Checkpoint**: User story 1 works without unfinished later stories.

## Phase 4: User story 2 — [TITLE] (P2)

**Goal**: [Independent user value]
**Independent test**: [Observable behavior, host/context, role, and isolation expectation]

- [ ] T030 [P] [US2] Add success and negative-path tests in [exact path]
- [ ] T031 [US2] Implement the smallest independent domain slice in [exact path]
- [ ] T032 [P] [US2] Implement accessible UI behavior in [exact path], if needed
- [ ] T033 [US2] Verify the story independently and with user story 1

## Phase 5: User story 3 — [TITLE] (P3)

**Goal**: [Independent user value]
**Independent test**: [Observable behavior, host/context, role, and isolation expectation]

- [ ] T040 [P] [US3] Add success and negative-path tests in [exact path]
- [ ] T041 [US3] Implement the smallest independent domain slice in [exact path]
- [ ] T042 [P] [US3] Implement accessible UI behavior in [exact path], if needed
- [ ] T043 [US3] Verify the story independently and with prior stories

Add or remove story phases to match the specification.

## Final phase: Cross-cutting verification and documentation

- [ ] T090 Run targeted PHPUnit and PHPCS for changed PHP
- [ ] T091 [P] Run Jest for changed Stimulus/frontend behavior
- [ ] T092 [P] Run `npm run dev` when Vite imports or assets changed
- [ ] T093 Run the appropriate Playwright lane for browser/Turbo/host flows
- [ ] T094 Verify WCAG 2.2 AA keyboard, focus, label, ARIA, announcement, contrast, and non-color behavior
- [ ] T095 Verify intended-tenant success, cross-tenant denial, and platform separation where applicable
- [ ] T096 Run `cd app && bash bin/verify.sh` for cross-cutting changes when practical
- [ ] T097 Update owning documentation and the applicable `AGENTS.md` chain
- [ ] T098 Update `app/CHANGELOG.md` and release/operational notes when applicable
- [ ] T099 Review the diff for unrelated changes, secrets, generated files, and stale placeholders

## Dependency rules

- Setup precedes shared foundational work.
- Foundational tenancy, schema, and authorization work blocks dependent stories.
- A user story must remain independently testable; record unavoidable dependencies explicitly.
- Tests and independent files marked `[P]` may proceed in parallel.
- Models/services generally precede controller or UI orchestration.
- Do not defer authorization, tenant isolation, accessibility, migrations, or documentation to an undefined “polish later” task.
