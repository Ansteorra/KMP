# Core source guide

## Purpose

Own core CakePHP application source under `app/src`: controllers, models, policies, services, mailers, commands, middleware, cells, view helpers, queue tasks, and KMP domain helpers.

## Ownership

- `Controller` handles HTTP/API orchestration, authorization calls, request shaping, Turbo responses, and view variables.
- `Model` handles persistence, validation, associations, rules, entity behavior, and cache invalidation.
- `Policy` owns authorization decisions and row-level scopes.
- `Services` owns reusable business workflows and cross-cutting registries.
- `KMP` owns domain helpers such as grid columns, permissions, static helpers, and identity interfaces.
- `Mailer`, `Queue`, and `Command` own asynchronous, email, and CLI execution surfaces.

## Local Contracts

- Core PHP files use `declare(strict_types=1);` and follow existing CakePHP inheritance patterns.
- Do not put authorization rules in tables, services, templates, or JavaScript; route them through policies and controller authorization calls.
- Use project base classes: `AppController`, `ApiController`, `BaseTable`, `BaseEntity`, and `BasePolicy`.
- Put reusable workflows in services and keep controllers thin.
- Keep plugin-specific behavior out of core unless it is an extension point or shared abstraction.

## Work Guidance

1. Read the nearest layer guide before editing a child directory.
2. Prefer existing services, registries, traits, and base classes over new one-off abstractions.
3. When a change crosses layers, update each affected layer at the seam instead of hiding logic in one layer.
4. Add or update tests in `app/tests` for changed behavior.

## Verification

- Core unit/service/model changes: `vendor/bin/phpunit --testsuite core-unit`
- Controller, command, middleware, and view changes: `vendor/bin/phpunit --testsuite core-feature`
- Changed PHP files: `vendor/bin/phpcs path/to/file.php`
- Cross-cutting source changes: `bash bin/verify.sh`

## Child AGENTS index

| Path | Purpose |
| --- | --- |
| `app/src/Controller/AGENTS.md` | Web/API controllers, Turbo Frames, grids, authorization, and response contracts |
| `app/src/Model/AGENTS.md` | Tables, entities, behaviors, validation, associations, and cache invalidation |
| `app/src/Policy/AGENTS.md` | Authorization policies, `can*` methods, and query scopes |
| `app/src/Services/AGENTS.md` | Business workflows, registries, side effects, and service results |
| `app/src/KMP/AGENTS.md` | Grid column metadata, permission loading, identity helpers, and KMP domain utilities |
