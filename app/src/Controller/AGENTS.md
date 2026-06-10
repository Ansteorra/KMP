# Controller layer guide

## Purpose

Own web and API request orchestration for core app features, including authorization, query scoping, grid responses, Turbo Frame rendering, CSV exports, Flash messages, and view variables.

## Ownership

- Web controllers extend `App\Controller\AppController`.
- API controllers extend `App\Controller\Api\ApiController`.
- `PlatformAdmin` controllers own platform-level operations and require extra care around tenancy, backup, and restore workflows.
- `DataverseGridTrait` is the standard grid orchestration surface.

## Local Contracts

- Call `parent::initialize()` before controller-specific setup.
- Use `$this->Authorization->authorizeModel(...)`, `$this->Authorization->authorize($entity)`, and `$this->Authorization->applyScope(...)`; do not hand-roll permission checks.
- Apply authorization scopes before returning lists, grids, or exports.
- Keep business logic in services and persistence concerns in tables.
- Turbo Frame requests must return frame-safe markup and preserve expected frame IDs.
- Grid actions should use existing grid services and CSV export patterns.

## Work Guidance

1. Search nearby controllers for the same action pattern before adding an action.
2. For plugin or tab UI, use registries/view cells instead of hard-coding plugin markup.
3. Keep redirects, Flash messages, and validation handling consistent with adjacent controllers.
4. For user-facing response changes, check keyboard/focus/accessibility behavior in the rendered templates.

## Verification

- Targeted controller tests: `vendor/bin/phpunit tests/TestCase/Controller/...`
- Core feature suite: `vendor/bin/phpunit --testsuite core-feature`
- UI/Turbo flows when affected: `npm run test:ui`
- Changed PHP files: `vendor/bin/phpcs path/to/controller.php`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
