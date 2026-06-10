# KMP domain helpers guide

## Purpose

Own KMP-specific reusable helpers, grid column metadata, permission loading, identity interfaces, static helpers, and domain utility classes.

## Ownership

- `GridColumns` classes define Dataverse grid metadata and system views.
- Permission and identity helpers support role/permission loading and current-user behavior.
- Static/domain helpers must stay reusable and tested because they are consumed across controllers, services, templates, and plugins.

## Local Contracts

- Grid column definitions extend `App\KMP\GridColumns\BaseGridColumns`.
- Implement `getColumns()` and set metadata such as `defaultVisible`, `searchable`, `sortable`, `filterable`, and `filterType`.
- Override `getSystemViews()` for built-in grid views.
- Use helper methods such as `getSearchableColumns()` and `getDropdownFilterColumns()` instead of duplicating metadata scans.
- Keep utility methods deterministic and avoid request-specific state unless the class is explicitly context-aware.

## Work Guidance

1. Before adding a helper, search `app/src/KMP`, services, and view helpers for prior art.
2. For grid changes, verify filtering, sorting, visibility, CSV export, and system views stay aligned.
3. Keep domain helpers independent of plugin-specific details unless the helper is an explicit extension point.
4. Add tests for metadata shape and helper edge cases.

## Verification

- KMP helper tests: `vendor/bin/phpunit tests/TestCase/KMP/...`
- Related grid UI when affected: targeted Playwright or manual grid check
- Changed PHP files: `vendor/bin/phpcs path/to/helper.php`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
