# Services layer guide

## Purpose

Own reusable business workflows, side effects, integrations, registries, workflow engine behavior, backup/restore operations, storage, security helpers, and cross-layer orchestration.

## Ownership

- Domain services implement reusable workflows that should not live in controllers or templates.
- Registry services own extension points for navigation, view cells, workflow actions, workflow triggers, and plugin-provided data.
- Infrastructure services own backup, restore, cache, secrets, storage, security, and platform operations.

## Local Contracts

- Prefer dependency injection with optional TableRegistry fallbacks only where existing patterns require it.
- Use `ServiceResult` when operations need a standard success/failure flag, reason, and data payload.
- Be explicit about side effects: queued jobs, mail, files, cache mutation, database writes, and workflow triggers.
- Do not hide failures in broad catches or success-shaped fallbacks.
- Services assume authorization has already been enforced unless the service is specifically an authorization helper.
- Tenant-aware data must use tenant-safe cache keys and context handling.

## Work Guidance

1. Search for an existing service or registry before adding a new class.
2. Keep service methods focused and composable; split large workflow steps into named helpers or collaborating services.
3. Wrap multi-record writes in transactions where partial completion would corrupt workflow state.
4. Add service tests for state transitions, failure paths, queueing, and cache effects.
5. When changing workflow engine or registry contracts, update related docs and plugin integrations.

## Verification

- Service tests: `vendor/bin/phpunit tests/TestCase/Services/...`
- Workflow/UI side effects when affected: targeted Playwright lane or `npm run test:ui`
- Changed PHP files: `vendor/bin/phpcs path/to/service.php`
- Cross-service changes: `bash bin/verify.sh`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
