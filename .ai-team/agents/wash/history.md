# Project Context

- **Owner:** Josh Handel (josh@liveoak.ws)
- **Project:** KMP — Membership management system for SCA Kingdoms. Handles members, officers, warrants, awards, activities, and workflow-driven approvals. ~2 years of active development.
- **Stack:** CakePHP 5.x, Stimulus.JS, MariaDB, Docker, Laravel Mix, Bootstrap, plugin architecture
- **Created:** 2026-02-10

## Learnings

<!-- Append new learnings below. Each entry is something lasting about the project. -->

### 2026-02-10: Frontend Architecture (summarized from full audit)

#### Asset Pipeline
- Config: `app/webpack.mix.js`. Build: `npm run dev`/`npm run prod` (in `app/`)
- Entry: `assets/js/index.js` → `webroot/js/index.js`
- Controllers auto-discovered from `assets/js/controllers/` and `plugins/*/assets/js/controllers/` → `webroot/js/controllers.js`
- Core libs (bootstrap, stimulus, turbo) extracted → `webroot/js/core.js`
- CSS: `app.css`, `signin.css`, `cover.css`, `dashboard.css`. Only Waivers plugin CSS auto-compiled — other plugins must be manually added to webpack.mix.js
- Runtime: bootstrap 5.3.6, stimulus 3.2.2, turbo 8.0.21, easymde, pdfjs-dist, qrcode, fontawesome 7.1
- **Turbo Drive DISABLED** — only Turbo Frames used

#### Controller Registration
All controllers use `window.Controllers["name"] = ControllerClass` pattern. Registered in `index.js` via `stimulusApp.register()` loop. NOT Stimulus webpack auto-loader.

#### Controller Inventory (81 total)
**Core (60):** Grid/data (`grid-view`, `filter-grid`, `csv-download`), forms (`app-setting-form`, `member-verify-form`, `gathering-form`, `role-add-*`, `permission-*`), UI (`detail-tabs`, `modal-opener`, `turbo-modal`, `popover`, `delete-confirmation`, `image-preview/zoom`), communication (`outlet-btn` — hub for inter-controller events), editor (`code-editor`, `markdown-editor`, `email-template-*`), autocomplete (`ac`), mobile/PWA (`member-mobile-card-*`, `mobile-calendar`, `mobile-offline-overlay`), misc (`session-extender`, `timezone-input`, `qrcode`, `kanban`, `sortable-list`, `nav-bar`, `delayed-forward`).

**Plugin:** Activities (5: auth request/approve/renew, mobile, GW sharing), Officers (5: roster search/table, edit/assign officer, office form), Waivers (10: upload wizard, camera, calendar, attestation, template, exemptions, retention, add-requirement), GitHubIssueSubmitter (1), Template (1: hello-world demo).

#### Template System
- 6 layouts: default (block-based), ajax, turbo_frame, mobile_app (PWA), public_event, error
- Blocks: `$this->KMP->startBlock()`/`endBlock()` — works across view cells
- Plugin content via `pluginTabButtons.php`, `pluginTabBodies.php`, `pluginDetailBodies.php` elements
- ViewCellRegistry: types `tab`, `detail`, `modal`, `json`, `mobile_menu`. Route-matched via `validRoutes`.

#### Tab Ordering
CSS flexbox with `data-tab-order="N"` + `style="order: N;"` on both button and content. Plugin tabs from ViewCellRegistry config. Guidelines: 1-10 plugins, 10-20 primary, 20-30 secondary, 30+ admin, 999 fallback. State in URL.

#### CSS
Bootstrap 5.3.6 primary. Icons: Bootstrap Icons (CDN) + FontAwesome (npm). Custom CSS minimal — use Bootstrap utilities first. Plugin CSS in `plugins/{Plugin}/assets/css/`.

#### View Helpers
KmpHelper (block mgmt, autocomplete, CSV, settings), MarkdownHelper, TimezoneHelper, SecurityDebugHelper. AppView loads: AssetMix, Identity, Bootstrap.Modal/Navbar, Url, Kmp, Markdown, Glide, Tools.Format/Time, Icon, Timezone, SecurityDebug.

#### Key Conventions
- Controller files: `{name}-controller.js` (kebab-case)
- Registration key matches kebab-case, except `auto-complete` → `ac`
- Inter-controller: `outlet-btn` dispatches events, connected controllers handle via `outletBtnOutletConnected/Disconnected`
- Empty controller files exist: `gathering-public`, `mobile-hub`
- Two duplicate `hello-world-controller.js` (Template and Waivers plugins)

#### Key Paths
JS: `app/assets/js/controllers/`, `app/assets/js/index.js`, `app/assets/js/KMP_utils.js`, `app/assets/js/timezone-utils.js`. CSS: `app/assets/css/`. Build: `app/webpack.mix.js`. Layouts: `app/templates/layout/`. Elements: `app/templates/element/`. Helpers: `app/src/View/Helper/`. Cells: `app/src/View/Cell/`. Compiled: `app/webroot/js/`, `app/webroot/css/`.

📌 Team update (2026-02-10): Architecture overview documented — plugin registration flow, ViewCellRegistry/NavigationRegistry patterns, 8 dangerous-to-change areas including window.Controllers pattern — decided by Mal
📌 Team update (2026-02-10): Backend patterns documented — ServiceResult pattern, DI registration, plugin architecture conventions, email sending must be async via queue — decided by Kaylee
📌 Team update (2026-02-10): Test suite audited — 88 files but ~15-20% real coverage, no frontend/JS tests exist, no CI pipeline — decided by Jayne
📌 Team update (2026-02-10): Josh directive — no new features until testing is solid. Test infrastructure is the priority. — decided by Josh Handel
📌 Team update (2026-02-10): Test infrastructure overhaul complete — all 370 project-owned tests pass (was 121 failures + 76 errors). Auth strategy: standardize on TestAuthenticationHelper, deprecate old traits. — decided by Jayne, Kaylee, Mal

📌 Team update (2026-02-10): Queue plugin ownership review — decided to own the plugin, security issues found, test triage complete

📌 Team update (2026-02-10): Documentation accuracy review completed — all 4 agents reviewed 96 docs against codebase

### 2026-02-10: Frontend Documentation Modernization

Completed 9 documentation tasks (8 modified, 1 no-change-needed):

#### Full Rewrites
- **10.4-asset-management.md**: Replaced with accurate webpack.mix.js config showing dynamic controller/service discovery, correct output files (index.js, controllers.js, core.js, manifest.js), full npm scripts from package.json, AssetMix helper instead of Html, accurate KMP_utils implementation (regex-based not DOM-based), plain CSS not SCSS
- **10.1-javascript-framework.md**: Fixed Turbo version (^8.0.21 not ^8.0.4), corrected index.js imports (includes timezone-utils.js and specific controller imports), documented Turbo.session.drive = false, added full dependency table with versions, documented turbo:render tooltip re-init
- **10.2-qrcode-controller.md**: Fixed errorCorrectionLevel default (H not M), documented canvas target is a div (not canvas element), documented Promise-based generate() with throw Error for missing values, documented actual download mechanism (toDataURL approach), fixed registration section

#### Targeted Fixes
- **9-ui-components.md**: Removed fictional form-handler and toasts controllers, fixed autocomplete controller name to "ac" with note about auto-complete-controller.js filename
- **4.5-view-patterns.md**: Added missing helpers (Markdown, Timezone, SecurityDebug), added missing layouts (mobile_app.php, public_event.php)
- **9.1-dataverse-grid-system.md**: Fixed Gatherings→GatheringsGridColumns mapping (was wrong as GatheringTypesGridColumns), added missing GatheringTypes row, removed non-existent applyFilter method
- **9.2-bootstrap-icons.md**: Corrected both version references to 1.11.3 (was 1.13.1 and 1.11)
- **10-javascript-development.md**: Removed duplicate Detail Tabs and Modal Opener controller sections, fixed controller example to use window.Controllers pattern instead of export default

#### No Change Needed
- **10.3-timezone-handling.md**: timezone_examples.php element confirmed to exist, no fix required

#### Key Learnings
- The qrcode controller's canvas target is a container div, not a canvas element — the controller creates the canvas dynamically
- Bootstrap Icons version is 1.11.3 (from CSS header), not managed via npm
- Only Waivers plugin CSS is auto-compiled; other plugins need manual webpack.mix.js entries
- Service files (assets/js/services/) are also bundled into controllers.js
