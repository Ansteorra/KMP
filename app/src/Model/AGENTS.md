# Model layer guide

## Purpose

Own data persistence, validation, associations, entity behavior, table rules, and model-level cache invalidation for core application data.

## Ownership

- Tables extend `App\Model\Table\BaseTable`.
- Entities extend `App\Model\Entity\BaseEntity`.
- Behaviors hold reusable persistence concerns.
- Migrations live outside this subtree in the owning migration directory.

## Local Contracts

- Put validation in `validationDefault()` and integrity rules in `buildRules()`.
- Use `PublicIdBehavior` and `find('byPublicId', ...)` for URL-facing IDs where supported.
- Override `BaseEntity::getBranchId()` when authorization depends on a related record rather than a direct field.
- Use `CACHES_TO_CLEAR`, `ID_CACHES_TO_CLEAR`, and `CACHE_GROUPS_TO_CLEAR` for table write cache invalidation.
- Eager-load required associations with `contain()` to avoid N+1 queries.
- Preserve impersonation audit behavior inherited from `BaseTable`.

## Work Guidance

1. Search existing tables and behaviors before adding a new persistence pattern.
2. Keep authorization out of model classes; expose data needed by policies instead.
3. Prefer focused custom finders that services and controllers can compose.
4. Add fixtures or test setup for new associations, validation rules, and cache-sensitive writes.

## Verification

- Model tests: `vendor/bin/phpunit tests/TestCase/Model/...`
- Core unit suite: `vendor/bin/phpunit --testsuite core-unit`
- Changed PHP files: `vendor/bin/phpcs path/to/table-or-entity.php`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
