# Project Context

- **Owner:** Josh Handel (josh@liveoak.ws)
- **Project:** KMP — Membership management system for SCA Kingdoms. Handles members, officers, warrants, awards, activities, and workflow-driven approvals. ~2 years of active development.
- **Stack:** CakePHP 5.x, Stimulus.JS, MariaDB, Docker, Laravel Mix, Bootstrap, plugin architecture
- **Created:** 2026-02-10

## Learnings

<!-- Append new learnings below. Each entry is something lasting about the project. -->

### 2026-02-10: Complete Frontend Architecture Audit

#### Asset Pipeline (Laravel Mix + Webpack)
- **Config**: `app/webpack.mix.js`
- **Build scripts**: `npm run dev` / `npm run prod` (in `app/` directory)
- **Entry point**: `assets/js/index.js` → `webroot/js/index.js`
- **Controller bundle**: All `*-controller.js` files from `assets/js/controllers/` AND `plugins/*/assets/js/controllers/` are auto-discovered and bundled → `webroot/js/controllers.js`
- **Service bundle**: `assets/js/services/*-service.js` also bundled into controllers.js
- **Core libs extracted**: bootstrap, popper.js, @hotwired/turbo, @hotwired/stimulus → `webroot/js/core.js`
- **Runtime chunk**: `webroot/js/manifest.js`
- **CSS files**: `app.css`, `signin.css`, `cover.css`, `dashboard.css` compiled from `assets/css/`
- **Plugin CSS**: Only Waivers plugin CSS (`waivers.css`, `waiver-upload.css`) is explicitly compiled in webpack.mix.js
- **Fonts**: FontAwesome webfonts copied to `webroot/fonts/`
- **Source maps enabled** in dev

#### NPM Dependencies (app/package.json)
- **Runtime**: bootstrap 5.3.6, @hotwired/stimulus 3.2.2, @hotwired/turbo 8.0.21, easymde, guifier, pdfjs-dist, qrcode, @fortawesome/fontawesome-free 7.1, popper.js
- **Dev**: laravel-mix 6.x, jest, playwright, babel, sass, jsdoc
- **Turbo Drive is DISABLED** (`Turbo.session.drive = false`) — only Turbo Frames are used

#### JavaScript Architecture
- **Stimulus registration pattern**: Controllers register via `window.Controllers["name"] = ControllerClass`. In `index.js`, `Application.start()` iterates `window.Controllers` and calls `stimulusApp.register()`.
- **Global utilities**: `window.KMP_utils` (sanitizeString, sanitizeUrl, urlParam), `window.KMP_Timezone` (detectTimezone, formatDateTime, formatDate, formatTime, toLocalInput, toUTC, getTimezoneOffset, initializeDatetimeInputs, convertFormDatetimesToUTC)
- **Services**: `OfflineQueueService` (IndexedDB-backed offline action queue with auto-sync), `RsvpCacheService` (IndexedDB RSVP cache for offline PWA)
- **Bootstrap tooltips** auto-initialized on page load and after `turbo:render` events
- **Bootstrap popovers** managed by dedicated `popover` Stimulus controller

#### Complete Stimulus Controller Catalog

**Core Controllers (60 files in `app/assets/js/controllers/`):**

| Controller Name | Registration Key | Summary |
|---|---|---|
| auto-complete | `ac` | AJAX autocomplete with keyboard nav, local filtering, custom values |
| activity-toggle | `activity-toggle` | Toggle description field based on checkbox |
| activity-waiver-manager | `activity-waiver-manager` | Waiver checkbox selection with count validation |
| add-activity-modal | `add-activity-modal` | Activity description update in modal |
| app-setting-form | `app-setting-form` | Settings form submit with button state |
| app-setting-modal | `app-setting-modal` | Edit modal for app settings with turbo-frame |
| base-gathering-form | _(base class, not registered)_ | Shared date validation for gathering forms |
| branch-links | `branch-links` | Dynamic branch link collection management |
| code-editor | `code-editor` | JSON/YAML editor with validation and line numbers |
| csv-download | `csv-download` | AJAX CSV download trigger |
| delayed-forward | `delayed-forward` | Auto-redirect after configurable delay |
| delete-confirmation | `delete-confirmation` | Context-aware delete confirmation dialogs |
| detail-tabs | `detail-tabs` | **Tab ordering system** — manages tabs with URL state and ordering |
| edit-activity-description | `edit-activity-description` | Populates edit activity modal |
| email-template-editor | `email-template-editor` | EasyMDE editor with variable insertion |
| email-template-form | `email-template-form` | Dynamic mailer/action/variable population |
| file-size-validator | `file-size-validator` | Pre-upload file size validation |
| filter-grid | `filter-grid` | Auto-submit filter forms (Turbo compatible) |
| gathering-clone | `gathering-clone` | Clone gathering modal with date validation |
| gathering-form | `gathering-form` | Extends BaseGatheringFormController |
| gathering-location-autocomplete | `gathering-location-autocomplete` | Google Places autocomplete for locations |
| gathering-map | `gathering-map` | Google Maps display with geocoding/directions |
| gathering-public | _(empty file)_ | No content |
| gathering-schedule | `gathering-schedule` | Schedule management with add/edit modals |
| gathering-type-form | `gathering-type-form` | Gathering type validation with char counting |
| gatherings-calendar | `gatherings-calendar` | Interactive calendar with attendance toggling |
| grid-view | `grid-view` | Grid pagination/filtering with bulk selection |
| guifier-control | `guifier-control` | Guifier JSON schema form integration |
| image-preview | `image-preview` | Instant image preview on file select |
| image-zoom | `image-zoom` | Mouse-wheel zoom and touch pinch-zoom |
| kanban | `kanban` | Drag-and-drop Kanban board with server sync |
| markdown-editor | `markdown-editor` | EasyMDE markdown editor wrapper |
| member-card-profile | `member-card-profile` | Multi-card member profile with AJAX loading |
| member-mobile-card-menu | `member-mobile-card-menu` | Mobile FAB menu with plugin items |
| member-mobile-card-profile | `member-mobile-card-profile` | Mobile member profile with offline support |
| member-mobile-card-pwa | `member-mobile-card-pwa` | PWA service worker, offline/online status |
| member-unique-email | `member-unique-email` | Real-time email uniqueness validation |
| member-verify-form | `member-verify-form` | Conditional form fields for verification |
| mobile-calendar | `mobile-calendar` | Touch-optimized calendar with offline RSVP |
| mobile-hub | _(empty file)_ | No content |
| mobile-offline-overlay | `mobile-offline-overlay` | Blocking offline overlay for mobile |
| modal-opener | `modal-opener` | Programmatic Bootstrap modal opening |
| my-rsvps | `my-rsvps` | RSVP management with past/upcoming filtering |
| nav-bar | `nav-bar` | Navigation expand/collapse state persistence |
| outlet-button | `outlet-btn` | Inter-controller communication via events |
| permission-add-role | `permission-add-role` | Role selection validation for permissions |
| permission-import | `permission-import` | JSON permission policy import workflow |
| permission-manage-policies | `permission-manage-policies` | Hierarchical permission checkboxes with AJAX |
| popover | `popover` | Bootstrap popover with HTML content |
| qrcode | `qrcode` | QR code generation with download/clipboard |
| revoke-form | `revoke-form` | Revocation workflow with outlet communication |
| role-add-member | `role-add-member` | Member selection validation for roles |
| role-add-permission | `role-add-permission` | Permission selection validation |
| security-debug | `security-debug` | Security debug panel toggle |
| select-all-switch | `select-all-switch` | Master checkbox for switch lists |
| session-extender | `session-extender` | Auto-extend sessions with timeout warning |
| sortable-list | `sortable-list` | Drag-and-drop list reordering |
| timezone-input | `timezone-input` | Datetime UTC/local conversion |
| turbo-modal | `turbo-modal` | Close modal before Turbo form submit |
| variable-insert | `variable-insert` | Template variable insertion at cursor |

**Plugin Controllers:**

| Plugin | Controller | Key | Summary |
|---|---|---|---|
| Activities | request-auth | `activities-request-auth` | Authorization request with approver fetch |
| Activities | approve-and-assign-auth | `activities-approve-and-assign-auth` | Approval workflow with outlet integration |
| Activities | renew-auth | `activities-renew-auth` | Authorization renewal with outlet |
| Activities | mobile-request-auth | `mobile-request-auth` | Mobile auth request with offline queue |
| Activities | gw-sharing | `gw_sharing` | Auto-submit GW sharing toggle |
| GitHubIssueSubmitter | github-submitter | `github-submitter` | Feedback submission to GitHub Issues |
| Officers | officer-roster-search | `officer-roster-search` | Warrant period/department search filter |
| Officers | edit-officer | `officers-edit-officer` | Officer edit form population via outlet |
| Officers | assign-officer | `officers-assign-officer` | Member-to-office assignment form |
| Officers | officer-roster-table | `officer-roster-table` | Bulk row selection with outlet |
| Officers | office-form | `office-form` | Deputy/reports-to field toggle |
| Waivers | add-requirement | `waivers-add-requirement` | Waiver type dropdown for activities |
| Waivers | camera-capture | `camera-capture` | Mobile camera capture with preview |
| Waivers | waiver-upload-wizard | `waiver-upload-wizard` | 3-step waiver upload workflow |
| Waivers | waiver-upload | `waiver-upload` | File upload with progress tracking |
| Waivers | waiver-calendar | `waiver-calendar` | Monthly waiver status calendar |
| Waivers | waiver-attestation | `waivers-waiver-attestation` | Waiver exemption attestation modal |
| Waivers | waiver-template | `waiver-template` | Template source toggle (file/URL) |
| Waivers | exemption-reasons | `exemption-reasons` | Dynamic exemption reason list management |
| Waivers | retention-policy-input | `retention-policy-input` | Retention policy configuration UI |
| Template | hello-world | `hello-world` | Demo/reference controller |

#### Template Organization
- **Layouts** (`app/templates/layout/`):
  - `default.php` — Main layout with block-based architecture (html, title, css, topscript, content, modals, script, tb_flash, tb_footer). Loads manifest.js, core.js, controllers.js, index.js via AssetMix.
  - `ajax.php` — Bare content-only layout for AJAX responses
  - `turbo_frame.php` — Bare content-only for Turbo Frame responses
  - `mobile_app.php` — PWA mobile layout with service worker, FAB menu, offline support
  - `public_event.php` — Standalone public event page with custom CSS (no auth required)
  - `error.php` — Minimal error layout with milligram CSS
  - `TwitterBootstrap/` — Bootstrap-specific layout variants

- **View Cells** (`app/src/View/Cell/`):
  - `AppNavCell` — Main navigation bar rendering
  - `NavigationCell` — Hierarchical menu building from NavigationRegistry with active state detection
  - `NotesCell` — Entity-agnostic notes display/creation with public/private filtering

- **Elements** (`app/templates/element/`):
  - `pluginTabButtons.php` — Renders plugin tab buttons with `data-tab-order` and `style="order:"` from ViewCellRegistry
  - `pluginTabBodies.php` — Renders plugin tab content panels with matching order
  - `pluginDetailBodies.php` — Renders plugin detail sections
  - `autoCompleteControl.php` — Reusable autocomplete form element (uses `ac` controller)
  - `comboBoxControl.php` — Dropdown combo box element
  - `dataverse_table.php`, `dv_grid.php` — Data grid/table elements
  - `turboSubTable.php`, `turboActiveTabs.php` — Turbo-specific elements
  - Various gathering, member, role-specific elements

#### Tab Ordering System
- Managed by `detail-tabs` Stimulus controller
- Both tab buttons and content panels use `data-tab-order="N"` and `style="order: N;"` attributes
- Plugin tabs get order from `ViewCellRegistry` configuration (`$cell['order']`)
- Template tabs set order explicitly in templates
- Order guidelines: 1-10 plugin tabs, 10-20 primary, 20-30 secondary, 30+ admin, 999 fallback
- Tab state persisted in URL via controller's `updateUrl` value
- Controller listens for target connections and activates first tab by order

#### ViewCellRegistry System
- `App\Services\ViewCellRegistry` — Static registry replacing event-based system
- Plugin types: `tab`, `detail`, `modal`, `json`, `mobile_menu`
- Plugins register cells with `ViewCellRegistry::register()` in their Plugin.php
- Cells matched to routes via `validRoutes` array and optional `authCallback`
- Cells organized by type and sorted by `order` key

#### CSS Approach
- **Bootstrap 5.3.6** is the primary framework
- **Icons**: Bootstrap Icons 1.11.3 (via CDN) + FontAwesome Free 7.1 (via npm)
- `app.css` imports: `bootstrap.css`, Bootstrap Icons CDN, `bootstrap-icon-sizes.css`, `dashboard.css`, EasyMDE CSS, FontAwesome CSS
- Custom CSS is minimal — mostly Bootstrap utility classes used in templates
- Custom styles: drag-and-drop feedback (`.dragging`, `.drag-over`), navigation accordion (`.navheader`, `.appnav`), danger row highlighting
- Plugin CSS: Waivers plugin has dedicated `waivers.css`, `waiver-upload.css`, `waiver-upload-wizard.css`, `mobile-waiver-wizard.css`
- `public_event.php` layout has extensive inline custom CSS with CSS custom properties
- `signin.css`, `cover.css` for login/landing pages

#### View Helpers
- `KmpHelper` — Block management (`startBlock/endBlock` for view cells), autocomplete/combobox rendering, CSV export, boolean icons, app settings access, Mix URL versioning, upload limits, possessive name formatting
- `MarkdownHelper` — Markdown rendering
- `TimezoneHelper` — Timezone display formatting
- `SecurityDebugHelper` — Debug panel (only in debug mode)
- AppView loads: AssetMix, Authentication.Identity, Bootstrap.Modal, Bootstrap.Navbar, Url, Kmp, Markdown, ADmad/Glide, Tools.Format, Tools.Time, Templating.Icon, Templating.IconSnippet, Timezone, SecurityDebug

#### Inter-Controller Communication
- **Outlet pattern**: `outlet-btn` controller is the hub — fires custom events with `btnData` for other controllers to consume
- Used by: `revoke-form`, `approve-and-assign-auth`, `renew-auth`, `edit-officer`, `assign-officer`, `officer-roster-table`
- Pattern: Outlet controller dispatches event → connected controller's `outletBtnOutletConnected/Disconnected` handlers react

#### Plugins with Frontend Assets
- **Activities**: 5 controllers (auth request/approve/renew, mobile request, GW sharing)
- **Officers**: 5 controllers (roster search/table, edit/assign officer, office form)
- **Waivers**: 10 controllers (upload wizard, camera, calendar, attestation, template, exemption reasons, retention policy, add requirement, upload, hello-world)
- **GitHubIssueSubmitter**: 1 controller (github-submitter)
- **Template**: 1 controller (hello-world demo)
- **Awards**: No JS controllers (templates only)
- **Bootstrap**: No custom JS (Bootstrap UI plugin for CakePHP)
- **Queue**: No JS (background processing)

#### Key File Paths
- JS controllers: `app/assets/js/controllers/`
- JS services: `app/assets/js/services/`
- JS entry: `app/assets/js/index.js`
- JS utils: `app/assets/js/KMP_utils.js`, `app/assets/js/timezone-utils.js`
- CSS: `app/assets/css/`
- Webpack config: `app/webpack.mix.js`
- NPM config: `app/package.json`
- Layouts: `app/templates/layout/`
- Elements: `app/templates/element/`
- View cells: `app/src/View/Cell/`
- View helpers: `app/src/View/Helper/`
- Plugin controllers: `app/plugins/{Plugin}/assets/js/controllers/`
- Plugin CSS: `app/plugins/{Plugin}/assets/css/`
- ViewCellRegistry: `app/src/Services/ViewCellRegistry.php`
- Compiled output: `app/webroot/js/`, `app/webroot/css/`

📌 Team update (2026-02-10): Architecture overview documented — plugin registration flow, ViewCellRegistry/NavigationRegistry patterns, 8 dangerous-to-change areas including window.Controllers pattern — decided by Mal
📌 Team update (2026-02-10): Backend patterns documented — ServiceResult pattern, DI registration, plugin architecture conventions, email sending must be async via queue — decided by Kaylee
📌 Team update (2026-02-10): Test suite audited — 88 files but ~15-20% real coverage, no frontend/JS tests exist, no CI pipeline — decided by Jayne

📌 Team update (2026-02-10): Josh directive — no new features until testing is solid. Test infrastructure is the priority. — decided by Josh Handel

📌 Team update (2026-02-10): Test infrastructure overhaul complete — all 370 project-owned tests pass (was 121 failures + 76 errors). Auth strategy: standardize on TestAuthenticationHelper, deprecate old traits. — decided by Jayne, Kaylee, Mal
