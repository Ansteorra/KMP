# KMP coding instructions

KMP is a CakePHP 5 application with a Laravel Mix/Webpack frontend, Stimulus controllers, Bootstrap UI, Turbo Frames, and first-party CakePHP plugins. Use these instructions as the source of truth for coding sessions in this repository.

## First principles

- Work primarily in `app/` for application code. The repository root contains deployment, installer, docs, and agent configuration.
- Preserve existing behavior unless the user explicitly asks for a behavior change.
- Keep all user-facing UI WCAG 2.2 Level AA compliant; treat accessibility regressions as bugs.
- Prefer the project's base classes, helpers, services, and registries over new one-off abstractions.
- Search for an existing pattern before adding a new one. Core patterns usually already exist in `app/src`, `app/templates/element`, `app/assets/js/controllers`, and the first-party plugins.
- Keep edits surgical. Do not reformat unrelated files or apply broad automated fixes.
- Never commit secrets. Do not copy values from `app/config/.env` into code, docs, logs, or chat output.

## Project layout

```text
app/
  src/
    Controller/          CakePHP controllers and controller traits
    Model/               Tables, entities, behaviors, validation
    Policy/              Authorization policies
    Services/            Business services and registries
    KMP/                 Domain helpers, grid columns, identity interfaces
    View/                Helpers, cells, and view-layer classes
    Mailer/              Mailers and mail transports
    Queue/Task/          Async queue tasks
  templates/             CakePHP templates and reusable elements
  assets/js/             Frontend entrypoint, utilities, Stimulus controllers
  assets/css/            CSS entry files bundled by Laravel Mix
  plugins/               First-party plugins
  tests/                 PHPUnit, Jest, and Playwright tests
```

Active first-party plugins are `Activities`, `Officers`, `Awards`, and `Waivers`. `Template` exists as a skeleton but is not enabled in `app/config/plugins.php`.

## Verification commands

Run commands from `app/` unless noted.

| Need | Command |
| --- | --- |
| Full local verification | `bash bin/verify.sh` |
| PHP regression suite | `composer test` |
| Targeted PHPUnit suite | `vendor/bin/phpunit --testsuite core-unit`, `core-feature`, or `plugins` |
| Targeted PHPUnit file/filter | `vendor/bin/phpunit path/to/Test.php` or `vendor/bin/phpunit --filter TestName` |
| JavaScript unit tests | `npm run test:js` |
| Webpack/Laravel Mix build | `npm run dev` |
| Playwright BDD tests | `npm run test:ui` |
| PHP code style on changed files | `vendor/bin/phpcs path/to/file.php` |
| PHPStan | `vendor/bin/phpstan analyse --no-progress` |

`bin/verify.sh` runs PHPUnit, Jest, Webpack, PHPCS for changed PHP files under `app/src`, `app/plugins`, and `app/tests`, and PHPStan. `app/phpstan.neon` currently has no analysis level configured, so PHPStan may report "No rules detected"; do not invent a new level unless the task is specifically about static analysis configuration.

Do not run `phpcbf` across the whole codebase. The PHPCS config intentionally excludes some native type-hint sniffs because automatic fixes can add signatures that violate CakePHP/plugin inheritance compatibility.

## PHP standards

- Start PHP files with:

  ```php
  <?php
  declare(strict_types=1);
  ```

- Follow CakePHP/PSR-12 style and the existing formatting in nearby files.
- Use native parameter and return types where safe, but do not force types onto inherited CakePHP/plugin methods when that would break LSP compatibility.
- Keep inline docblocks maintenance-focused: purpose, parameters, return type, non-obvious side effects, and `@throws` when useful. Put usage examples and workflow narratives in `app/docs`, not inline docblocks.
- Prefer clear domain names over abbreviations. Use constants for repeated magic strings or status values.

## Controllers

- Web controllers extend `App\Controller\AppController`; API controllers extend `App\Controller\Api\ApiController`.
- Call `parent::initialize()` before controller-specific setup.
- Configure model-level authorization with `$this->Authorization->authorizeModel(...)` for index/add/grid actions.
- Authorize entity actions with `$this->Authorization->authorize($entity)`.
- Apply query scopes before returning lists or grids:

  ```php
  $query = $this->Authorization->applyScope($query, 'index');
  ```

- Use `DataverseGridTrait::processDataverseGrid()` for standard data grids. Grid actions normally set grid metadata, handle CSV export through `CsvExportService`, and select `dv_grid_content` or `dv_grid_table` for Turbo Frame requests.
- Use `getHeaderLine('Turbo-Frame')` or the existing AppController Turbo handling for frame-aware responses.
- Use `skipAuthorization()` only for genuinely public, health-check, or framework-required actions.
- Keep navigation, plugin validation, restore-lock, impersonation state, and view-cell behavior centralized in `AppController`.

## Authorization and policies

- Policies extend `App\Policy\BasePolicy`.
- Use `canAdd`, `canEdit`, `canDelete`, `canView`, `canIndex`, and `canGridData` naming. `canGridData()` should usually delegate to `canIndex()`.
- Put branch filtering in `scopeIndex()` and table `addBranchScopeQuery()` implementations.
- Use entity `getBranchId()` for branch-scoped permissions.
- Let `BasePolicy::before()` handle super-user bypass; do not duplicate super-user checks throughout policy methods unless there is a specific reason.
- Preserve flexible signatures on base/overridable policy methods. Adding narrower native types can break plugin policies.
- Log or return explicit authorization failures consistently with the existing policy code. Do not silently allow or deny without matching existing patterns.

## Models, tables, and entities

- Tables extend `App\Model\Table\BaseTable`; entities extend `App\Model\Entity\BaseEntity`.
- Override `BaseEntity::getBranchId()` when authorization depends on a related record rather than a direct `branch_id` field.
- Use `BaseTable` cache invalidation constants when writes affect cached settings, permissions, or lookup data:

  ```php
  protected const CACHES_TO_CLEAR = [['cache_key', 'cache_config']];
  protected const ID_CACHES_TO_CLEAR = [['prefix_', 'cache_config']];
  protected const CACHE_GROUPS_TO_CLEAR = ['group_name'];
  ```

- Use `PublicIdBehavior` for non-sequential public identifiers and prefer `find('byPublicId', [$id])` for URL-facing lookups where the table supports it.
- Use CakePHP validation in `validationDefault()` and business rules in `buildRules()`.
- Eager-load needed associations with `contain()` to avoid N+1 queries.
- Preserve impersonation audit logging in `BaseTable` write hooks.

## Services and domain logic

- Put reusable business logic in `app/src/Services` or a plugin `src/Services` directory instead of controllers or templates.
- Use `ServiceResult` for operations that need a standard success/failure flag, reason, and data payload.
- Prefer dependency injection with optional fallbacks to `TableRegistry` for services that need tables; this keeps tests simple.
- Keep service methods explicit about side effects such as queueing mail, writing files, mutating records, or clearing cache.
- Use existing domain services before creating new ones: grid views, CSV export, member registration/authentication/profile, backup/restore, documents, gatherings, navigation/view-cell registries, warrants, and active-window management.

## Grid and CSV patterns

- Grid column definitions extend `App\KMP\GridColumns\BaseGridColumns`.
- Implement `getColumns()` and mark metadata such as `defaultVisible`, `searchable`, `sortable`, `filterable`, and `filterType`.
- Override `getSystemViews()` for built-in grid views.
- Use `getSearchableColumns()` and `getDropdownFilterColumns()` helpers instead of duplicating metadata scans.
- Handle CSV export with `CsvExportService` and the controller grid result. Exports should respect grid column visibility and filtering.

## View cells, navigation, and tabs

- Register core and plugin cells through `ViewCellRegistry`; do not hard-code plugin cells in controllers or templates.
- Use `ViewCellRegistry::PLUGIN_TYPE_TAB`, `PLUGIN_TYPE_DETAIL`, `PLUGIN_TYPE_MODAL`, `PLUGIN_TYPE_JSON`, and `PLUGIN_TYPE_MOBILE_MENU` as appropriate.
- Include `validRoutes`, `order`, and optional `authCallback` in cell definitions.
- KMP tab ordering is CSS-flex based. For template-defined tabs, set matching `data-tab-order` and `style="order: X;"` on both the tab button and content panel.
- Use order ranges consistently: plugin tabs in lower values, primary entity tabs around 10-20, secondary/admin tabs later, and 999 as fallback.

## Templates and helpers

- Templates live under `app/templates` or `app/plugins/PluginName/templates` using CakePHP's lowercase/snake_case conventions.
- Escape output with `h()` unless rendering already-sanitized HTML.
- Use existing helpers: `KmpHelper`, `TimezoneHelper`, `MarkdownHelper`, AssetMix integration, BootstrapUI patterns, and reusable elements.
- Keep data transformation in controllers/services/helpers, not templates.
- Preserve Turbo Frame IDs and Stimulus data attributes; tests often depend on them.

## Accessibility and WCAG

- Invoke the `wcag-accessibility` skill for UI accessibility audits or changes that affect user-facing templates, CSS, Stimulus behavior, forms, modals, tabs, Turbo Frames, navigation, grids, or mobile layouts.
- All user-facing pages, templates, components, and interactive states must meet WCAG 2.2 Level AA.
- Prefer semantic HTML before ARIA. Use buttons for actions, links for navigation, headings in order, lists/tables where structurally appropriate, and labels for every form control.
- Keep all functionality keyboard operable with a logical tab order, no keyboard traps, visible focus states, and predictable focus movement after modals, validation, Turbo Frame updates, and dynamic content changes.
- Maintain AA color contrast, do not communicate meaning by color alone, and ensure icons/images have useful accessible names or are marked decorative with `aria-hidden="true"`.
- Forms must associate inputs with labels, help text, and validation errors using `for`, `aria-describedby`, and existing CakePHP/Bootstrap patterns. Announce asynchronous validation or status changes through `KMP_accessibility` where appropriate.
- Dynamic content should use accessible status messaging (`aria-live`, `role="status"`, or `KMP_accessibility.announce()`) when updates are not otherwise obvious.
- Interactive controls should meet WCAG 2.2 target-size expectations where practical and provide adequate spacing on touch/mobile layouts.
- Respect reduced-motion preferences and avoid auto-moving or auto-updating content without controls.
- When changing UI behavior, include keyboard/focus expectations in tests or manual verification notes, and use Playwright for complex flows involving modals, tabs, Turbo Frames, or mobile interactions.

## Timezones and dates

- Store date/time data in UTC.
- Convert for display/input using `App\KMP\TimezoneHelper`.
- Mailer inputs should receive pre-formatted strings from the controller or service layer. Email templates should display those strings directly and must not perform timezone conversion.
- Do not use virtual `_to_string` date fields or raw CakePHP `format()` calls for user-facing email dates when kingdom timezone formatting is required.

## Mailers and queues

- Mailers live in `app/src/Mailer` and extend the project's mailer base/patterns.
- Use application settings such as sender addresses through `StaticHelpers::getAppSetting()`.
- Keep templates presentation-only; build variables before invoking the mailer.
- Queue async work through CakePHP queue tasks in `app/src/Queue/Task` or plugin task directories. Queue tasks extend `Cake\Queue\QueueTask` and implement `execute(array $args)`.

## Plugins

- Follow CakePHP plugin structure:

  ```text
  app/plugins/PluginName/
    src/Controller/
    src/Model/
    src/Policy/
    src/Services/
    src/KMP/GridColumns/
    src/View/Cell/
    templates/
    config/Migrations/
    assets/js/
    assets/css/
    tests/
  ```

- Use the plugin namespace in PHP (`Awards\...`, `Activities\...`, etc.).
- Register enabled plugins and migration order in `app/config/plugins.php`.
- Register plugin view cells, navigation, routes, migrations, policies, and assets using the existing registry/bootstrap patterns.
- Keep plugin code isolated unless shared behavior belongs in core `app/src`.

## JavaScript and frontend

- Build assets with Laravel Mix (`app/webpack.mix.js`), not Vite.
- Place application controllers in `app/assets/js/controllers/*-controller.js`; plugin controllers go under `app/plugins/PluginName/assets/js/controllers`.
- Controllers extend `@hotwired/stimulus` `Controller` and register through the global registry:

  ```javascript
  if (!window.Controllers) {
      window.Controllers = {}
  }
  window.Controllers["my-feature"] = MyFeatureController
  ```

- Use kebab-case controller identifiers that match the filename.
- Define `static targets`, `static values`, `static classes`, and `static outlets` instead of querying arbitrary DOM where possible.
- Clean up event listeners, timers, observers, and pending async work in `disconnect()`.
- Use namespaced custom events for cross-component communication, such as `offline-queue:*` or controller-specific prefixes.
- Turbo Drive is disabled in `assets/js/index.js`; Turbo Frames remain in use. Do not re-enable Drive without a focused compatibility review.
- Use `KMP_utils` for URL and sanitization helpers and `KMP_accessibility` for accessible alert/confirm/prompt/announce flows.
- Bootstrap is globally provided by Webpack. Use Bootstrap classes and components consistently with existing templates.
- Keep controllers accessibility-aware: preserve focus, update ARIA state (`aria-expanded`, `aria-selected`, `aria-busy`, etc.), avoid inaccessible hidden content, and announce async results when needed.

## JavaScript tests

- Jest uses jsdom with setup in `app/tests/js/setup.js`.
- Tests live in `app/tests/js/**/*.test.js` and should cover controller registration, static targets/values, DOM behavior, and cleanup.
- Mock `window.Controllers`, `window.Stimulus`, Bootstrap, `KMP_utils`, and `KMP_accessibility` consistently with existing tests.
- Use the module alias `@/` for `app/assets/js` when helpful.

## PHPUnit and Playwright tests

- Unit/service tests should extend `App\Test\TestCase\BaseTestCase`.
- HTTP feature tests should extend `App\Test\TestCase\Support\HttpIntegrationTestCase`.
- Plugin-focused tests should extend the plugin integration support classes already under `tests/TestCase/Support`.
- Use seeded constants from `BaseTestCase` instead of duplicating magic member/branch IDs.
- Use `$this->reseedDatabase()` only when a test performs destructive operations that require a full reset.
- Playwright uses `app/playwright.config.js` with `playwright-bdd`; features live in `app/tests/ui/bdd`, generated specs in `app/tests/ui/gen`, and reports in `app/tests/ui-reports`.

## Database and migrations

- Use migrations for schema changes and keep seed data in seed files or existing seed SQL workflows.
- Name database tables plural, lowercase, and underscore-separated.
- Add indexes and foreign keys when adding relational columns.
- Use `bash bin/update_database.sh` or Cake migration commands from `app/` when the task requires applying migrations locally.
- For command-line database debugging, read connection settings from `app/config/.env` and do not expose them.

## Security and privacy

- Prefer public IDs for URL-facing records when available.
- Preserve CSRF, security token, authentication, and authorization checks.
- Escape template output and sanitize user-controlled HTML through existing helpers.
- Do not bypass restore lock, impersonation logging, retention policies, audit trails, or authorization scopes for convenience.
- Do not ship inaccessible user-facing UI; WCAG 2.2 Level AA regressions should be fixed with the same priority as functional regressions.
- Avoid broad `try/catch` blocks that hide failures. Surface errors through Flash messages, logs, exceptions, or service results consistent with nearby code.
- Keep `face-api.js` at the currently configured package version unless the task is specifically to upgrade and validate face detection behavior.

## Git and documentation

- Do not revert or overwrite user changes. Inspect `git status` before and after edits.
- Use conventional commit messages when creating commits.
- Update docs when changing behavior, public commands, plugin setup, or developer workflow.
- For documentation-only edits, a full app verification run is usually unnecessary; at minimum inspect the diff for formatting and accuracy.
- For code edits, run targeted tests first and the broader verification command when practical.
