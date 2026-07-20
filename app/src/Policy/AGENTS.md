# Policy layer guide

## Purpose

Own authorization decisions and row-level scopes for core resources through CakePHP Authorization policies.

## Ownership

- Policies extend `App\Policy\BasePolicy`.
- Entity and table policies own `can*` methods and scope methods for their resources.
- Controllers invoke policies; policies do not render UI or perform persistence side effects.

## Local Contracts

- Use `canAdd`, `canEdit`, `canDelete`, `canView`, `canIndex`, and `canGridData` naming.
- `canGridData()` should usually delegate to `canIndex()`.
- Put branch filtering in `scopeIndex()` and table `addBranchScopeQuery()` implementations.
- Let `BasePolicy::before()` handle super-user bypass.
- Preserve flexible signatures on base and overridable policy methods.
- Return explicit authorization failures consistently with nearby policy code.

## Work Guidance

1. Review `BasePolicy` and a neighboring policy before adding or changing permissions.
2. Keep authorization decisions deterministic and based on identity, permissions, entity state, and branch scope.
3. Add targeted tests for new policy branches and denied cases, not just allowed paths.
4. When a policy affects grids or index pages, verify the controller applies the matching scope.

## Verification

- Policy tests: `vendor/bin/phpunit tests/TestCase/Policy/...`
- Core unit suite: `vendor/bin/phpunit --testsuite core-unit`
- Changed PHP files: `vendor/bin/phpcs path/to/policy.php`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
