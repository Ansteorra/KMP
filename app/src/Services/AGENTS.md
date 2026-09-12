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
- Azure managed-identity credentials implement `azure-oss/identity` with an explicit `TokenRequestContext`. Storage and administrative management audiences remain separate; reject requested scopes that do not match the configured audience before contacting the identity endpoint.
- An actor-driven ActionItem completion is committed before its follow-on required-field cascade runs. If that cascade
  fails, return a successful `ServiceResult` with `data.cascadeWarning` so callers preserve the committed outcome and
  visibly report the related work that still needs attention. Owner-specific terminal completion and scheduling reversal
  use the optional `ActionItemLifecycleProviderInterface` on registered completion providers. Its transition callback
  runs inside the owner/item transaction after the item audit row; failure rolls back the item and all owner changes.
  Terminal completions skip the ordinary post-commit cascade and cannot auto-complete from satisfied fields. Automatic definition-sync cancellation provenance is
  the exact persisted system note constant; changing that text requires migrating existing logs.
- Services assume authorization has already been enforced unless the service is specifically an authorization helper.
- Tenant-aware data must use tenant-safe cache keys and context handling.
- Document reads and writes must not provision remote containers. Azure container lifecycle and restricted runtime grants belong to dedicated administrative provisioning; archives use independent `Backups.storage` configuration. Derived image variants use deterministic, versioned paths and bounded lazy generation after controller authorization.

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
