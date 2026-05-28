<!-- refreshed: 2026-05-23 -->
# Architecture

**Analysis Date:** 2026-05-23

## System Overview

```text
┌─────────────────────────────────────────────────────────────────────┐
│                   HTTP Request (Apache:8080)                         │
└────────────────────────────┬────────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────────┐
│                   Middleware Stack (10 layers)                        │
│  ErrorHandler → SecurityHeaders → Assets → Routing → BodyParser     │
│  → CSRF → Authentication → Authorization → Footprint                │
│  `app/src/Application.php`                                           │
└────────────────────────────┬────────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────────┐
│                       Controller Layer                               │
│  `app/src/Controller/` | `app/plugins/*/src/Controller/`           │
│  AppController (base) + DataverseGridTrait for list pages           │
└───────────┬────────────────┬───────────────────────┬───────────────┘
            │                │                       │
    ┌───────▼──────┐ ┌───────▼──────┐    ┌──────────▼─────────┐
    │  Model/Table │ │   Services   │    │   Templates/Views   │
    │  `src/Model` │ │ `src/Services`│    │  `app/templates/`  │
    └───────┬──────┘ └──────────────┘    └────────────────────┘
            │
    ┌───────▼──────┐
    │   Database   │
    │  MariaDB/    │
    │  MySQL       │
    └──────────────┘
```

## Component Responsibilities

| Component | Responsibility | Location |
|-----------|----------------|----------|
| Application | Bootstrap, middleware, DI container | `app/src/Application.php` |
| AppController | Base controller: nav, turbo, plugin cells | `app/src/Controller/AppController.php` |
| DataverseGridTrait | Reusable grid processing for list pages | `app/src/Controller/DataverseGridTrait.php` |
| BasePolicy | Policy resolution, super-user bypass | `app/src/Policy/BasePolicy.php` |
| ControllerResolver | Maps controllers to policy classes | `app/src/Policy/ControllerResolver.php` |
| ActiveWindowManagerInterface | Manages date-bounded entity lifecycle | `app/src/Services/ActiveWindowManager/` |
| WarrantManagerInterface | Warrant request/approval/cancellation | `app/src/Services/WarrantManager/` |
| NavigationRegistry | Static plugin nav registration (not event-based) | `app/src/Services/NavigationRegistry.php` |
| ViewCellRegistry | Plugin view cell registration per URL | `app/src/Services/ViewCellRegistry.php` |
| ServiceResult | Standard service return type (success/reason/data) | `app/src/Services/ServiceResult.php` |
| StaticHelpers | App settings (db-stored config), utilities | `app/src/KMP/StaticHelpers.php` |
| TimezoneHelper | Date formatting (always format before mailer) | `app/src/View/Helper/TimezoneHelper.php` |
| KMPMailer | Base mailer with queued send support | `app/src/Mailer/KMPMailer.php` |
| GridColumns | Per-entity column/filter/sort metadata | `app/src/KMP/GridColumns/` |

## Pattern Overview

**Overall:** CakePHP 5 MVC with a service layer, plugin architecture, and policy-based authorization.

**Key Characteristics:**
- Controllers are thin: delegate business logic to injected services
- Plugins are self-contained modules (own controllers, models, templates, migrations)
- Authorization is policy-class-based — every action must be authorized
- List pages use the Dataverse Grid pattern (lazy-loaded via Turbo Frames)
- Frontend uses Stimulus.JS controllers registered globally on `window.Controllers`

## Layers

**Middleware Layer:**
- Purpose: Security, routing, authentication, authorization, body parsing
- Location: `app/src/Application.php::middleware()`
- Order: ErrorHandler → SecurityHeaders → AssetMiddleware → RoutingMiddleware → BodyParser → CSRF → Authentication → Authorization → Footprint
- Special: CSRF is skipped for `/api/` routes; API routes use Bearer token auth

**Controller Layer:**
- Purpose: Request handling, authorization calls, data preparation for views
- Location: `app/src/Controller/`, `app/plugins/*/src/Controller/`
- Contains: Action methods, use of `$this->Authorization->authorize()`, DataverseGridTrait for index pages
- Depends on: Services (DI), Model/Table, Policy
- API controllers under: `app/src/Controller/Api/V1/` (BranchesController, MembersController, RolesController, ServicePrincipalsController)

**Service Layer:**
- Purpose: Business logic extracted from controllers; always returns `ServiceResult`
- Location: `app/src/Services/`, `app/plugins/*/src/Services/`
- Key services: `WarrantManagerInterface`, `ActiveWindowManagerInterface`, `MemberRegistrationService`, `MemberAuthenticationService`, `MemberProfileService`, `GatheringActivityService`, `CsvExportService`, `ICalendarService`, `ImpersonationService`
- Registered in: `app/src/Application.php::services()`

**Model Layer:**
- Purpose: Database access via CakePHP ORM; entities represent rows
- Location: `app/src/Model/Entity/`, `app/src/Model/Table/`, `app/plugins/*/src/Model/`
- Behaviors: `ActiveWindowBehavior`, `JsonFieldBehavior`, `PublicIdBehavior`, `SortableBehavior`
- Base classes: `BaseEntity` → `ActiveWindowBaseEntity` for time-bounded entities; `BaseTable`

**Policy Layer:**
- Purpose: Fine-grained authorization — one policy class per entity and table
- Location: `app/src/Policy/`, `app/plugins/*/src/Policy/`
- Base: `BasePolicy` — auto-grants super users, delegates to `_hasPolicy()` for DB-stored role/permission checks
- Two policy types per entity: `EntityPolicy` (row-level) and `TablePolicy` (collection-level)

**View Layer:**
- Purpose: Server-rendered HTML using CakePHP templates + Bootstrap 5.3
- Location: `app/templates/`, `app/plugins/*/templates/`
- View cells: `AppNavCell`, `NavigationCell`, `NotesCell` — rendered dynamically, plugin cells registered via `ViewCellRegistry`
- Helpers: `KmpHelper`, `TimezoneHelper`, `MarkdownHelper`, `SecurityDebugHelper`

## Data Flow

### Standard Web Request

1. HTTP request arrives at Apache → `app/webroot/index.php`
2. Middleware stack processes: error handling, security headers, asset serving, routing
3. CSRF token validated (skipped for `/api/` paths)
4. `AuthenticationMiddleware` checks session or form credentials via `KMPBruteForcePasswordIdentifier`
5. `AuthorizationMiddleware` wraps identity with authorization service
6. `RoutingMiddleware` dispatches to controller action (e.g., `MembersController::view()`)
7. `AppController::beforeFilter()` runs: CSV detection, plugin validation, nav history, view cell loading
8. Controller calls `$this->Authorization->authorize($entity)` or `$this->Authorization->authorizeModel(...)`
9. Policy class method (e.g., `MemberPolicy::canView()`) evaluated
10. Controller calls Table or Service, gets data, sets view variables
11. Template renders HTML; Turbo Frames handle lazy-loaded sections

### Dataverse Grid Request (list pages)

1. `index()` action renders the empty grid shell template
2. Browser loads Turbo Frame; fires request to `gridData()` action
3. `DataverseGridTrait::processDataverseGrid()` applies filters, sorting, pagination
4. Authorization scope applied to base query via `$this->Authorization->applyScope($query, 'index')`
5. `GridColumns` class defines columns, filters, sortable fields
6. Response renders `dv_grid_content` / `dv_grid_table` elements

### API Request

1. Request to `/api/v1/*` routes
2. CSRF skipped; `ServicePrincipalAuthenticator` validates Bearer token or `X-API-Key` header
3. Controller renders JSON response
4. Auth: `ServicePrincipal` entities with token-based access, IP restrictions

## Key Abstractions

**ActiveWindowBaseEntity:**
- Purpose: Time-bounded entity with status lifecycle: `Upcoming → Current → Expired/Deactivated/Released`
- Examples: `Warrant`, officer assignments, `Authorization` (Activities), `MemberRole`
- Statuses: `UPCOMING_STATUS`, `CURRENT_STATUS`, `RELEASED_STATUS`, `REPLACED_STATUS`, `EXPIRED_STATUS`, `DEACTIVATED_STATUS`, `CANCELLED_STATUS`
- Location: `app/src/Model/Entity/ActiveWindowBaseEntity.php`
- Console sync: `bin/cake sync_active_window_statuses` — sets `modified_by = 1`

**Dataverse Grid:**
- Purpose: Standardized list page with filters, sorting, pagination, CSV export, column picker
- Components: `GridColumns` class + `DataverseGridTrait` + `dv_grid*` templates + `grid-view-controller.js`
- Each entity needing a grid has a `*GridColumns.php` in `app/src/KMP/GridColumns/`

**Plugin Navigation/View Cell Registration:**
- Plugins register navigation via `NavigationRegistry::register(source, items, callback)` in their `bootstrap()` method
- Plugins register view cells (tabs, detail panels, modals) via `ViewCellRegistry::register(source, cells, callback)`
- View cell types: `PLUGIN_TYPE_TAB`, `PLUGIN_TYPE_DETAIL`, `PLUGIN_TYPE_MODAL`, `PLUGIN_TYPE_JSON`, `PLUGIN_TYPE_MOBILE_MENU`

**ServiceResult:**
- Purpose: Standard return value from all service methods
- Fields: `bool $success`, `?string $reason`, `mixed $data`
- Location: `app/src/Services/ServiceResult.php`

## Entry Points

**Web:**
- Location: `app/webroot/index.php`
- Triggers: All HTTP requests via Apache

**CLI:**
- Location: `app/bin/cake`
- Commands in: `app/src/Command/` (SyncActiveWindowStatusesCommand, AgeUpMembersCommand, BackupCommand, KmpInstallCommand, etc.)

**Queue Worker:**
- Location: `app/plugins/Queue/` (Dereuromark Queue plugin)
- Tasks in: `app/src/Queue/Task/`

## Architectural Constraints

- **Authentication:** Session-based for web; Bearer token / `X-API-Key` for API (`/api/v1/*`)
- **CSRF:** Enforced for all web routes; explicitly skipped for `/api/` prefix paths
- **Super user bypass:** `BasePolicy::before()` returns `true` for super-user identity, skipping all policy checks
- **Plugin migration order:** Defined in `app/config/plugins.php` via `migrationOrder` key; Activities=1, Officers=2, Awards=3, Waivers=4
- **Table locator:** `allowFallbackClass(false)` for web requests — all table classes must be explicitly defined
- **Email dates:** All `DateTime` objects must be pre-formatted via `TimezoneHelper` before passing to mailers — never pass raw `DateTime` to mailer methods or templates
- **Turbo Drive disabled:** `Turbo.session.drive = false` — Turbo Frames are used for partial page updates; full-page SPA navigation is off
- **Global state:** `NavigationRegistry` and `ViewCellRegistry` use static arrays, populated during plugin `bootstrap()` calls

## Anti-Patterns

### Running phpcbf globally

**What happens:** Running `vendor/bin/phpcbf` on the whole codebase automatically adds type hints from docblocks.
**Why it's wrong:** The added type hints violate PHP's Liskov Substitution Principle for overridable methods (previously broke `WarrantsTable::afterSave()` and `ServicePrincipalPolicy::canAdd()`).
**Do this instead:** Only manually fix PHPCS issues in files you modify; never run phpcbf globally.

### Adding type hints to BasePolicy overridable methods

**What happens:** Adding native type hints to `canAdd()`, `canEdit()`, etc. in `app/src/Policy/BasePolicy.php`.
**Why it's wrong:** Plugin policy classes override these methods with different signatures; native types break LSP compatibility.
**Do this instead:** Keep `$query`/`$entity` params untyped in `BasePolicy` overridable methods.

### Passing DateTime objects to mailers

**What happens:** Passing `DateTime` objects or calling format methods inside mailer classes/templates.
**Why it's wrong:** Mailers must receive pre-formatted strings for consistent timezone display.
**Do this instead:** Call `TimezoneHelper::formatDate()` / `formatDateTime()` in the controller or service before passing to the mailer.

## Error Handling

**Strategy:** Exceptions bubble to `ErrorHandlerMiddleware` (first in stack); custom error pages in `app/templates/Error/`.

**Patterns:**
- Services return `ServiceResult` (never throw for expected failures)
- Controllers check `$result->success` after service calls
- Authorization failures → `ForbiddenException` caught by `AuthorizationMiddleware` → redirect to `/pages/unauthorized`
- Unauthenticated access → `MissingIdentityException` → redirect to login with `redirectUrl` query param
- API routes: `ApiAwareRedirect` unauthorized handler returns JSON errors instead of redirects

## Cross-Cutting Concerns

**Logging:** CakePHP Log facade (`Cake\Log\Log`); performance logging middleware (optional, ENV-controlled via `PERF_REQUEST_LOG_ENABLED`)
**Validation:** CakePHP ORM validation rules in Table classes; form-level via `app/src/Form/`
**Authentication:** `KMPBruteForcePasswordIdentifier` with ORM resolver; legacy MD5 → bcrypt migration on login
**Audit Trail:** `Muffin/Footprint` middleware sets `created_by`/`modified_by` on all save operations; `ImpersonationSessionLog` and `ServicePrincipalAuditLog` for privileged actions
**App Settings:** DB-stored configuration via `app_settings` table; accessed via `StaticHelpers::getAppSetting()` with version-based auto-update in `Application::bootstrap()`

---

*Architecture analysis: 2026-05-23*
