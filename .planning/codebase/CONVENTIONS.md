# Coding Conventions

**Analysis Date:** 2026-05-23

## PHP Standards

**Version & Declarations:**
- PHP 8.3+ required (`"php": "^8.3"` in `app/composer.json`)
- Every PHP file must begin with `declare(strict_types=1);`
- Full native type declarations on all method parameters and return types

**Style:**
- PSR-12 enforced via CakePHP CodeSniffer ruleset (`app/phpcs.xml`)
- Ruleset: `CakePHP` (extends PSR-12 with CakePHP-specific rules)
- Three type-hint sniffs are explicitly excluded (see LSP compatibility section below)

**Docblocks:**
- Required on all public methods
- Format: brief single-sentence purpose + `@param`/`@return`/`@throws` tags
- Never include usage examples inline — put those in `/docs`
- Verbose `@property` and `@method` annotations on Table classes (see `app/src/Model/Table/MembersTable.php`)

Example:
```php
/**
 * Check if $user can view PII for a Member
 *
 * @param \App\KMP\KmpIdentityInterface $user The user.
 * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
 * @param mixed ...$optionalArgs Optional arguments
 * @return bool
 */
public function canViewPii(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
```

## Naming Conventions

**PHP — Files and Classes:**

| Type | Convention | Example |
|------|-----------|---------|
| Controllers | Plural PascalCase + `Controller` suffix | `MembersController`, `BranchesController` |
| Models/Entities | Singular PascalCase | `Member`, `Branch` |
| Tables | Plural PascalCase + `Table` suffix | `MembersTable`, `BranchesTable` |
| Policies (entity) | Singular PascalCase + `Policy` suffix | `MemberPolicy`, `BranchPolicy` |
| Policies (table) | Plural PascalCase + `TablePolicy` suffix | `MembersTablePolicy`, `BranchesTablePolicy` |
| Services | PascalCase + `Service` or `Manager` suffix | `CsvExportService`, `DefaultWarrantManager` |
| Interfaces | PascalCase + `Interface` suffix | `WarrantManagerInterface`, `ActiveWindowManagerInterface` |
| Plugins | PascalCase | `Awards`, `Activities`, `Officers`, `Waivers` |
| Commands | PascalCase + `Command` suffix | `SyncActiveWindowStatusesCommand` |

**Templates:**
- Lowercase `snake_case` directories and filenames
- Located in `templates/ControllerName/action_name.php`
- Plugin templates in `plugins/PluginName/templates/`

**JavaScript — Controllers:**
- Filename: kebab-case with `-controller.js` suffix (e.g., `auto-complete-controller.js`)
- Registration key: kebab-case string in `window.Controllers` (e.g., `window.Controllers["ac"]` or `window.Controllers["auto-complete"]`)
- Class name: PascalCase (e.g., `AutoComplete`, `GridView`)

**JavaScript — Methods and Variables:**
- `const`/`let` only — never `var`
- camelCase for all variables and methods
- Private/internal methods prefixed with underscore: `_selectOptions`, `_datalistLoaded`

## File Organization

**PHP source tree (`app/src/`):**
```
src/
├── Controller/          # Request handlers; AppController.php is the base
│   ├── AppController.php
│   ├── Component/       # Reusable controller components
│   └── Api/             # API-specific controllers
├── Model/
│   ├── Entity/          # Entity classes (singular names)
│   └── Table/           # Table classes; BaseTable.php is the base
├── Policy/              # Authorization policy classes; BasePolicy.php is the base
├── Services/            # Business logic services
│   └── ActiveWindowManager/  # Sub-namespace for complex services
├── KMP/                 # Framework utilities, grid column definitions
│   └── GridColumns/     # Dataverse grid column definitions
├── Form/                # CakePHP Form objects
├── Mailer/              # Email mailer classes
└── View/
    └── Helper/          # View helpers
```

**Plugin structure (`app/plugins/PluginName/`):**
```
plugins/PluginName/
├── src/
│   ├── Controller/
│   ├── Model/
│   │   ├── Entity/
│   │   └── Table/
│   ├── Policy/
│   └── Services/
├── assets/
│   └── js/controllers/  # Plugin Stimulus controllers
├── config/Migrations/   # Plugin-specific migrations
├── templates/           # Plugin templates
└── tests/TestCase/      # Plugin tests
```

**JavaScript source (`app/assets/js/`):**
```
assets/js/
├── index.js             # Entry point; starts Stimulus and registers controllers
├── controllers/         # All Stimulus controller files (*-controller.js)
└── utils/               # Shared utility modules
```

## JavaScript Standards

**ES Version:** ES6+ throughout

**Fetch/Async:**
- Always use `async/await` for fetch calls
- Never use `.then()` chains

**Stimulus Controller Structure (mandatory order):**
1. `static targets` — DOM targets array
2. `static values` — typed value descriptors object
3. `static outlets` — outlet names (if used)
4. `static classes` — CSS class names (if used)
5. `initialize()` — one-time setup, runs before `connect()`
6. `connect()` — DOM connected; wire up event listeners
7. Action handlers — methods called by `data-action` attributes
8. Private helpers — prefixed with underscore
9. `disconnect()` — cleanup

**Self-registration (required):**
```javascript
window.Controllers["my-feature"] = MyFeatureController;
```

This must appear at the bottom of every controller file. The key becomes the Stimulus identifier used in HTML `data-controller` attributes.

**JSDoc on controllers:**
- Controllers have extensive JSDoc including `@class`, `@extends`, `Targets:`, `Values:`, and HTML structure examples

## PHP Import Organization

**Order:**
1. `declare(strict_types=1);`
2. Namespace declaration
3. `use` statements (alphabetical, no blank lines between)
4. Class definition

Controller example from `app/src/Controller/MembersController.php`:
- CakePHP core imports first
- App-specific imports second
- Both groups alphabetically sorted within

## Service Dependency Injection

Services are injected via constructor — controllers declare dependencies using the static `$inject` property:

```php
public static array $inject = [CsvExportService::class];
protected CsvExportService $csvExportService;
```

Always type-hint against interfaces, not concrete implementations:
```php
// Correct
$container->add(WarrantManagerInterface::class, DefaultWarrantManager::class);

// Wrong
$container->add(DefaultWarrantManager::class, DefaultWarrantManager::class);
```

## Email/Date Formatting

All dates passed to mailer methods must be pre-formatted using `TimezoneHelper` before passing — never pass `DateTime` objects or call format methods inside mailer classes or templates:

```php
// Correct
$vars = ['startDate' => TimezoneHelper::formatDate($warrant->start_on)];
$this->queueMail('KMP', 'notifyOfWarrant', $member->email_address, $vars);

// Wrong — never do this inside a Mailer or template
$vars = ['startDate' => $warrant->start_on->format('Y-m-d')];
```

## Tab Ordering System

UI tabs use CSS flexbox ordering. Both the tab button and content panel need matching attributes:

```php
<button data-tab-order="10" style="order: 10;"><?= __("My Tab") ?></button>
<div data-tab-order="10" style="order: 10;"><!-- content --></div>
```

Order ranges: 1–10 = plugin tabs, 10–20 = primary entity tabs, 20–30 = secondary tabs, 30+ = admin/rare tabs, 999 = fallback.

## Dangerous Patterns to Avoid

**Never run `phpcbf` on the entire codebase.** It adds type hints from docblocks that violate PHP's LSP. Broke `WarrantsTable::afterSave()` and `ServicePrincipalPolicy::canAdd()` previously. Only manually fix PHPCS issues in files you are already modifying.

**Do not add native type hints to `BasePolicy` overridable methods.** `src/Policy/BasePolicy.php` uses untyped `$query`/`$entity` params intentionally for LSP compatibility — plugins override these methods and their child signatures must remain compatible.

**Do not downgrade `face-api.js` below `^0.22.2`.** Versions `^0.20.0` weaken face detection quality for profile-photo validation.

**`Members/emailTaken` must remain publicly accessible (anonymous).** Public registration uses the `member-unique-email` Stimulus controller to validate email uniqueness before authentication.

**`Members/PublicProfile` response must keep `data.external_links`.** The `rec-add-controller.js` reads this key directly; removing or renaming it breaks the submit-recommendation flow.

**Recommendation states behavior:** States outside `Need to Schedule`/`Scheduled`/`Given` automatically clear `gathering_id`. The `updateStates` bulk action also nulls `gathering_id` for unsupported states. This is intentional — do not treat it as a bug.

**Quick-login PIN upsert behavior:** The PIN intentionally upserts by `device_id` across members, reassigning device ownership to the currently authenticated member. This is by design.

## PHPCS Configuration

Config file: `app/phpcs.xml`

Ruleset: `CakePHP` with three excluded sniffs:
- `SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint` — excluded globally
- `SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint` — excluded globally
- `SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint` — excluded globally
- `SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint` — excluded for `*/src/Controller/*` only

Scanned paths: `src/` and `tests/`

In CI and `verify.sh`, PHPCS only checks files changed in the current branch (not the full codebase, due to ~3400 pre-existing violations globally).

## PHPStan Configuration

Level 5 analysis. Baseline file: `app/phpstan-baseline.neon` (suppresses 1947 pre-existing errors).

One known unbaselinable error exists (HtmlHelper type covariance) — `verify.sh` treats exactly 1 error as a pass.

---

*Convention analysis: 2026-05-23*
