# Services layer guide

## Purpose

Own reusable business workflows, side effects, integrations, registries, workflow engine behavior, backup/restore operations, storage, security helpers, and cross-layer orchestration.

## Ownership

- Domain services implement reusable workflows that should not live in controllers or templates.
- `IconCatalog` resolves registered icon IDs and exact compatibility aliases from generated static data; templates and frontend consumers follow `app/docs/icons.md`. Keep selection keywords separate from identifier aliases.
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
- Registered live resources use ORM saves/deletes or `UiBulkMutation` for bulk writes. The latter locks the matched
  records in ID order, mutates that fixed set, and records old/new owners in the same tenant transaction. Keep
  optimistic predicates in its selection conditions. Direct `updateAll`/`deleteAll` bypass live callbacks and require
  a documented native/admin exception or explicit same-transaction invalidation. See `app/docs/hotwire-interactions.md`.
- Actor-driven gathering activity and schedule mutations reauthorize through
  `GatheringActivityAuthorizationService` inside their transactions. Plugin-owned activity grants use
  `GatheringActivityDelegationRegistry`; providers must resolve the active tenant lazily and evaluate
  only native grantor permissions. Keep general gathering editing and Other scheduling independent.
- Owner-specific ActionItem delegation uses `ActionItemDelegationRegistry`; core does not import plugin policies.
  Candidate conditions only discover possible work; all lists, counts, transitions, and reverse assignment resolution
  still use the live assignee resolver. Providers must check native eligibility to prevent delegation chains and resolve
  the current tenant locator on every call rather than retaining tenant tables from registration or a prior tenant.
- Logical backup manifests preserve PostgreSQL UUID columns and partial-index predicates; restore must not widen conditional uniqueness. Unsupported target engines must fail while planning, before dropping tables.
- Tenant-aware data must use tenant-safe cache keys and context handling.
- Release GD images with normal object lifetime or `unset()`. Do not call the obsolete `imagedestroy()` function: PHP 8.5 deprecations can corrupt image-upload JSON responses when development errors are displayed.
- Deferred workflow jobs and state commit on the same tenant connection. Execute async
  actions before advancing; bind delivery to the exact execution-log ID. Durable action
  retries use persisted attempts and delayed queue jobs, with a savepoint around each
  attempt. See `app/docs/workflow-api-endpoints.md` for execution and recovery contracts.
- Grid email subscriptions use the server-owned `GridSubscriptionRegistry`. Every delivery reruns the registered read-only grid with a fresh recipient identity and current authorization; queue only subscription IDs/claim tokens, never report rows. See `app/docs/grid-email-subscriptions.md`.
- Document reads and writes must not provision remote containers. Azure container lifecycle and restricted runtime grants belong to dedicated administrative provisioning; archives use independent `Backups.storage` configuration. Derived image variants use deterministic, versioned paths and bounded lazy generation after controller authorization.
- Explicitly public artwork uses `PublicAssetService` with tenant-namespaced, immutable content keys and direct static/CDN URLs. Keep it separate from private Documents; runtime writes must not provision containers or change public access. See `app/docs/gathering-activities.md` for storage and caching requirements.

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
