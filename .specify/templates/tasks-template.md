---
description: "Task list template for feature implementation"
---

# Tasks: [FEATURE NAME]

**Input**: Design documents from `/specs/[###-feature-name]/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: The examples below include test tasks. Tests are OPTIONAL - only include them if explicitly requested in the feature specification.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions
- **Core Features**: `app/src/`, `app/tests/TestCase/`, `app/templates/`, `app/assets/`
- **Plugin Features**: `app/plugins/[PluginName]/src/`, `app/plugins/[PluginName]/tests/`, etc.
- **Controllers**: `src/Controller/[Name]Controller.php`
- **Models**: `src/Model/Table/[Name]Table.php`, `src/Model/Entity/[Name].php`
- **Services**: `src/Services/[ServiceName].php`
- **Policies**: `src/Policy/[Name]Policy.php`
- **Templates**: `templates/[Controller]/[action].php`
- **Stimulus Controllers**: `assets/js/controllers/[name]-controller.js`
- **Migrations**: `config/Migrations/[YYYYMMDDHHMMSS]_[Description].php`
- **Tests**: `tests/TestCase/[Controller|Model|Service]/[Name]Test.php`
- Paths shown below follow CakePHP conventions - see plan.md for plugin vs core decision

<!-- 
  ============================================================================
  IMPORTANT: The tasks below are SAMPLE TASKS for illustration purposes only.
  
  The /speckit.tasks command MUST replace these with actual tasks based on:
  - User stories from spec.md (with their priorities P1, P2, P3...)
  - Feature requirements from plan.md
  - Entities from data-model.md
  - Endpoints from contracts/
  
  Tasks MUST be organized by user story so each story can be:
  - Implemented independently
  - Tested independently
  - Delivered as an MVP increment
  
  DO NOT keep these sample tasks in the generated tasks.md file.
  ============================================================================
-->

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [ ] T001 Create plugin or core feature structure per implementation plan (follow CakePHP conventions)
- [ ] T002 [P] Register plugin in config/plugins.php with migration order (if plugin-based)
- [ ] T003 [P] Add plugin loading to src/Application.php bootstrap (if plugin-based)
- [ ] T004 [P] Configure Composer dependencies if needed (update composer.json)
- [ ] T005 [P] Configure NPM dependencies if needed (update package.json)
- [ ] T006 [P] Setup asset compilation paths in webpack.mix.js (if new assets)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

Examples of foundational tasks (adjust based on your feature):

- [ ] T010 Create database migration(s) for new tables/columns in config/Migrations/
- [ ] T011 [P] Create base Entity classes in src/Model/Entity/ with validation rules
- [ ] T012 [P] Create base Table classes in src/Model/Table/ with associations and finders
- [ ] T013 [P] Create authorization Policy classes in src/Policy/ (if access control needed)
- [ ] T014 [P] Create base Service class(es) in src/Services/ for business logic
- [ ] T015 [P] Setup NavigationProvider in src/Services/ (if plugin adds menu items)
- [ ] T016 [P] Setup routing in config/routes.php (or plugin routes.php)
- [ ] T017 Run migrations: `bin/cake migrations migrate` and verify schema

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - [Title] (Priority: P1) 🎯 MVP

**Goal**: [Brief description of what this story delivers]

**Independent Test**: [How to verify this story works on its own]

### Tests for User Story 1 (OPTIONAL - only if tests requested) ⚠️

**NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T018 [P] [US1] Create test fixtures in tests/Fixture/[Name]Fixture.php
- [ ] T019 [P] [US1] Controller integration test in tests/TestCase/Controller/[Name]ControllerTest.php
- [ ] T020 [P] [US1] Model test in tests/TestCase/Model/Table/[Name]TableTest.php
- [ ] T021 [P] [US1] Service test in tests/TestCase/Service/[ServiceName]Test.php (if applicable)

### Implementation for User Story 1

- [ ] T022 [P] [US1] Create Controller in src/Controller/[Name]Controller.php
- [ ] T023 [P] [US1] Add authorization checks in controller initialize()
- [ ] T024 [US1] Create view templates in templates/[Controller]/[action].php
- [ ] T025 [US1] Wrap partial content in `<turbo-frame>` tags if used in multi-tab/multi-grid (see https://turbo.hotwired.dev/handbook/frames)
- [ ] T026 [US1] Add Turbo Stream responses for dynamic updates if needed (see https://turbo.hotwired.dev/handbook/streams)
- [ ] T027 [US1] Implement business logic in Service class (if complex logic)
- [ ] T028 [P] [US1] Create Stimulus controller in assets/js/controllers/[name]-controller.js (if interactive)
- [ ] T029 [P] [US1] Add CSS styling in assets/css/ if needed
- [ ] T030 [US1] Register Stimulus controller in assets/js/index.js
- [ ] T031 [US1] Compile assets: `npm run dev`
- [ ] T032 [US1] Add Form validation and error handling
- [ ] T033 [US1] Add Flash messages for user feedback
- [ ] T034 [US1] Test Turbo Frame navigation and updates (if applicable)

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - [Title] (Priority: P2)

**Goal**: [Brief description of what this story delivers]

**Independent Test**: [How to verify this story works on its own]

### Tests for User Story 2 (OPTIONAL - only if tests requested) ⚠️

- [ ] T032 [P] [US2] Create/update test fixtures in tests/Fixture/
- [ ] T033 [P] [US2] Controller integration test in tests/TestCase/Controller/
- [ ] T034 [P] [US2] Model test in tests/TestCase/Model/Table/
- [ ] T035 [P] [US2] Service test in tests/TestCase/Service/ (if applicable)

### Implementation for User Story 2

- [ ] T036 [P] [US2] Create/update Controller actions in src/Controller/
- [ ] T037 [P] [US2] Create/update view templates in templates/
- [ ] T038 [US2] Add Turbo Frame/Stream markup if partial updates needed
- [ ] T039 [US2] Implement Service methods for business logic
- [ ] T040 [P] [US2] Create/update Stimulus controller in assets/js/controllers/
- [ ] T041 [US2] Integrate with User Story 1 components (if needed)
- [ ] T042 [US2] Test Turbo navigation between User Story 1 and 2 features
- [ ] T043 [US2] Compile assets and run tests

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: User Story 3 - [Title] (Priority: P3)

**Goal**: [Brief description of what this story delivers]

**Independent Test**: [How to verify this story works on its own]

### Tests for User Story 3 (OPTIONAL - only if tests requested) ⚠️

- [ ] T042 [P] [US3] Create/update test fixtures in tests/Fixture/
- [ ] T043 [P] [US3] Controller integration test in tests/TestCase/Controller/
- [ ] T044 [P] [US3] Model test in tests/TestCase/Model/Table/
- [ ] T045 [P] [US3] Service test in tests/TestCase/Service/ (if applicable)

### Implementation for User Story 3

- [ ] T046 [P] [US3] Create/update Controller actions in src/Controller/
- [ ] T047 [P] [US3] Create/update view templates in templates/
- [ ] T048 [US3] Add Turbo Frame/Stream markup if partial updates needed
- [ ] T049 [US3] Implement Service methods for business logic
- [ ] T050 [P] [US3] Create/update Stimulus controller in assets/js/controllers/
- [ ] T051 [US3] Test Turbo Frame interactions across all user stories
- [ ] T052 [US3] Compile assets and run tests

**Checkpoint**: All user stories should now be independently functional

---

[Add more user story phases as needed, following the same pattern]

---

## Phase N: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [ ] TXXX [P] Update documentation in docs/ (architecture, features, workflow as needed)
- [ ] TXXX [P] Update .github/copilot-instructions.md if new patterns established
- [ ] TXXX [P] Run code standards check: `composer cs-check` and fix issues
- [ ] TXXX [P] Run static analysis: `vendor/bin/phpstan analyse`
- [ ] TXXX [P] Run all tests: `composer test` and ensure 100% pass
- [ ] TXXX [P] Check JavaScript linting: `npm run lint` and fix issues
- [ ] TXXX [P] Build production assets: `npm run production`
- [ ] TXXX Create database rollback test (migrations down/up cycle)
- [ ] TXXX Add PHPDoc comments for all public methods
- [ ] TXXX Verify authorization policies cover all controller actions
- [ ] TXXX Manual testing checklist (see below)

### Manual Testing Checklist

- [ ] Test with different user roles (admin, officer, member, guest)
- [ ] Test responsive layouts (desktop, tablet, mobile)
- [ ] Test in multiple browsers (Chrome, Firefox, Safari, Edge)
- [ ] Verify Flash messages appear correctly
- [ ] Check navigation menu updates (if using NavigationProvider)
- [ ] Test Turbo Frame navigation (verify partial updates, no full page reload)
- [ ] Test multi-tab scenarios if applicable (ensure frames target correctly)
- [ ] Test multi-grid scenarios if applicable (verify independent updates)
- [ ] Verify browser back/forward buttons work with Turbo Drive
- [ ] Test Turbo Stream updates if used (real-time updates without reload)
- [ ] Test error scenarios and validation messages
- [ ] Verify database constraints work (foreign keys, unique constraints)
- [ ] Check logging output for errors or warnings
- [ ] Verify Stimulus controllers connect/disconnect properly during Turbo navigation
- [ ] TXXX Code cleanup and refactoring
- [ ] TXXX Performance optimization across all stories
- [ ] TXXX [P] Additional unit tests (if requested) in tests/unit/
- [ ] TXXX Security hardening
- [ ] TXXX Run quickstart.md validation

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
  - User stories can then proceed in parallel (if staffed)
  - Or sequentially in priority order (P1 → P2 → P3)
- **Polish (Final Phase)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P2)**: Can start after Foundational (Phase 2) - May integrate with US1 but should be independently testable
- **User Story 3 (P3)**: Can start after Foundational (Phase 2) - May integrate with US1/US2 but should be independently testable

### Within Each User Story

- Tests (if included) MUST be written and FAIL before implementation
- Models before services
- Services before endpoints
- Core implementation before integration
- Story complete before moving to next priority

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel
- All Foundational tasks marked [P] can run in parallel (within Phase 2)
- Once Foundational phase completes, all user stories can start in parallel (if team capacity allows)
- All tests for a user story marked [P] can run in parallel
- Models within a story marked [P] can run in parallel
- Different user stories can be worked on in parallel by different team members

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together (if tests requested):
Task: "Contract test for [endpoint] in tests/contract/test_[name].py"
Task: "Integration test for [user journey] in tests/integration/test_[name].py"

# Launch all models for User Story 1 together:
Task: "Create [Entity1] model in src/models/[entity1].py"
Task: "Create [Entity2] model in src/models/[entity2].py"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: Test User Story 1 independently
5. Deploy/demo if ready

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → Deploy/Demo (MVP!)
3. Add User Story 2 → Test independently → Deploy/Demo
4. Add User Story 3 → Test independently → Deploy/Demo
5. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Story 1
   - Developer B: User Story 2
   - Developer C: User Story 3
3. Stories complete and integrate independently

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Verify tests fail before implementing
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence


