# KMP agent guide

This file is the quick-start runbook for coding agents working in this repository. For detailed conventions, read `.github/copilot-instructions.md`.

## Mission

Deliver small, correct, verified changes that match the existing CakePHP 5, Stimulus, Bootstrap, Turbo Frame, and plugin patterns. Keep user-facing UI WCAG 2.2 Level AA compliant and prefer proven project abstractions over new one-off code.

## Start every task

1. Check the current worktree with `git status --short`.
2. Identify whether the change belongs in root-level infrastructure or in `app/`.
3. Search for existing patterns before editing.
4. Preserve unrelated user changes.
5. Choose the narrowest useful verification command before finishing.

## Important paths

| Path | Purpose |
| --- | --- |
| `.github/copilot-instructions.md` | Detailed coding instructions for Copilot sessions |
| `app/src/Controller` | Web and API controllers |
| `app/src/Model` | CakePHP tables, entities, and behaviors |
| `app/src/Policy` | Authorization policies and scopes |
| `app/src/Services` | Business services, registries, and domain workflows |
| `app/src/KMP/GridColumns` | Dataverse grid column definitions |
| `app/templates` | CakePHP views and reusable elements |
| `app/assets/js/controllers` | Core Stimulus controllers |
| `app/plugins` | First-party plugins |
| `app/tests` | PHPUnit, Jest, and Playwright tests |
| `.github/skills/wcag-accessibility` | WCAG 2.2 Level AA accessibility review and implementation skill |
| `app/bin/verify.sh` | Full local verification script |

## Current stack

- PHP 8.3, CakePHP 5, CakePHP Authentication and Authorization plugins.
- JavaScript bundled with Laravel Mix/Webpack.
- Stimulus controllers registered through `window.Controllers`.
- Bootstrap 5 UI components.
- Turbo Drive disabled; Turbo Frames used for dynamic content.
- PHPUnit, Jest/jsdom, and Playwright BDD tests.

## Core coding patterns

- PHP files use `declare(strict_types=1);`.
- Web controllers extend `AppController`; API controllers extend `ApiController`.
- Tables extend `BaseTable`; entities extend `BaseEntity`; policies extend `BasePolicy`.
- Use `$this->Authorization->authorize(...)`, `authorizeModel(...)`, and `applyScope(...)` instead of ad-hoc permission checks.
- Use `DataverseGridTrait` plus `BaseGridColumns` for grid screens.
- Use `ViewCellRegistry` and navigation registries for plugin UI integration.
- Use `TimezoneHelper` for display/input date conversion and pre-format dates before passing them to mailers.
- Put business workflows in `app/src/Services` or plugin `src/Services`, not templates.
- Frontend controllers live in `*-controller.js`, use static targets/values/outlets, and register in `window.Controllers`.
- User-facing templates and frontend changes must preserve WCAG 2.2 Level AA accessibility.
- Tests should use project base classes and seeded constants instead of raw magic IDs.

## Commands

Run these from `app/` unless noted.

| Need | Command |
| --- | --- |
| Full verification | `bash bin/verify.sh` |
| PHP tests | `composer test` |
| Targeted PHP suite | `vendor/bin/phpunit --testsuite core-unit` |
| Targeted PHP test | `vendor/bin/phpunit path/to/Test.php` or `vendor/bin/phpunit --filter Name` |
| JS tests | `npm run test:js` |
| Webpack build | `npm run dev` |
| Playwright BDD | `npm run test:ui` |
| PHPCS on a file | `vendor/bin/phpcs path/to/file.php` |
| PHPStan | `vendor/bin/phpstan analyse --no-progress` |

`bin/verify.sh` checks PHPUnit, Jest, Webpack, changed PHP files with PHPCS, and PHPStan.

## Do not do these

- Do not run `phpcbf` over the whole codebase.
- Do not add native type hints to inherited CakePHP/plugin methods just because a docblock exists.
- Do not bypass authorization, restore-lock handling, impersonation logging, CSRF/security token checks, or branch scopes.
- Do not hard-code plugin UI into core controllers/templates; use registries and view cells.
- Do not re-enable Turbo Drive without a focused compatibility review.
- Do not ship inaccessible UI or treat accessibility as optional.
- Do not expose `.env` values or other secrets.
- Do not downgrade or casually change face-detection dependencies.
- Do not reformat unrelated files.

## Verification guidance

- Documentation-only changes normally need diff review rather than a full app verification run.
- PHP behavior changes should get a targeted PHPUnit run and PHPCS on changed PHP files.
- Frontend behavior changes should get `npm run test:js`; run `npm run dev` when bundling or imports change.
- UI/template changes should include an accessibility check for keyboard operation, focus visibility/order, labels, ARIA state, status announcements, contrast, and non-color-only cues.
- Cross-cutting changes should get `bash bin/verify.sh` when practical.
- Playwright is appropriate for browser flows, Turbo Frames, modals, and multi-page interactions.

## Plugin guidance

Active first-party plugins are `Activities`, `Officers`, `Awards`, and `Waivers`. `Template` is present as a skeleton but is not enabled.

When changing plugin behavior:

1. Keep plugin namespaces and directories isolated.
2. Add migrations under the plugin `config/Migrations` directory.
3. Put plugin controllers, policies, services, grid columns, cells, assets, and tests inside the plugin.
4. Register cells/navigation through the existing registries.
5. Add or update plugin-specific tests under the plugin or `app/tests/TestCase/Plugins`.

## Frontend guidance

Invoke the `wcag-accessibility` skill for UI accessibility audits or changes that affect user-facing templates, CSS, Stimulus behavior, forms, modals, tabs, Turbo Frames, navigation, grids, or mobile layouts.

Use existing utilities:

- `KMP_utils` for URL and sanitization helpers.
- `KMP_accessibility` for accessible alert, confirm, prompt, and announce behavior.
- Bootstrap for modals, tabs, tooltips, and styling.
- `detail-tabs-controller` tab ordering conventions for mixed core/plugin tabs.

Clean up listeners in `disconnect()`, preserve data attributes used by templates/tests, and use namespaced custom events for cross-component state changes. Preserve WCAG 2.2 Level AA by using semantic HTML, labeled controls, visible focus, keyboard-operable interactions, adequate contrast, meaningful alt text or decorative `aria-hidden`, and accessible announcements for async updates.

## Documentation guidance

Keep inline docblocks short and maintenance-oriented. Put usage examples, architecture narratives, and workflow documentation in `app/docs` or another existing docs location. Update `.github/copilot-instructions.md` and this file when project-wide agent guidance changes.
