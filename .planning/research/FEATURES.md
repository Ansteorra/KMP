# Feature Landscape: UX Polish and Bug Categories

**Domain:** Organizational membership management (SCA Kingdom portal)
**System state:** Production, maintenance mode — no new plugins planned
**Researched:** 2026-06-14
**Method:** Direct codebase inspection — templates, controllers, entities, TODOs

---

## Table Stakes Quality

These are areas where rough edges cause daily friction. Officers and members hit them on
every workflow. If any of these are broken or slow, users lose trust in the system.

### Flash Messages After Modal/Turbo-Frame Actions

**Why expected:** Every save, release, assign, and delete in the officer and member
workflows redirects with a flash message. When the action fires from a modal (Bootstrap),
the redirect lands on the parent page and the flash renders in the `#flash-messages` div.
But if the action fires inside a Turbo Frame, the flash div is outside the frame boundary
and the message is silently lost unless the response is a `turbo-stream` that explicitly
replaces `#flash-messages`.

**Current state:** `AppSettingsController` and select other controllers manually read and
clear the flash session then render a `turbo-stream` response containing `turbo_stream_flash`.
`OfficersController` uses `redirect($this->referer())` without turbo-stream plumbing.
Most modal-based saves in the Officers, Activities, and Awards plugins follow the redirect
pattern, so flash messages may not appear when the save happens inside a Turbo Frame.

**Complexity:** Low per-controller fix — add `turbo_stream_flash` element to modal success
responses. Medium if a shared trait is extracted.

**Dependency:** None. Self-contained per action.

---

### Validation Errors Visible After Modal Submissions

**Why expected:** Bootstrap modals dismiss on any form submit. If the server returns
a validation error, the redirect back to the parent page loses the error context — the
modal is gone and the field errors are nowhere.

**Current state:** `MembersController::view()` explicitly reads `session()->read('MemberEdit')`
and patches the entity back so modal errors are shown. This is bespoke session-patching logic.
Officers and Awards modal forms (`assignModal`, `editModal`, `releaseModal`) do not show
field-level errors in a consistent way.

**Complexity:** Medium. The pattern is established in Members but needs to be propagated
to other plugin modals that have their own forms.

**Dependency:** None. Independent per modal.

---

### Consistent Empty State Messages on Grids

**Why expected:** The Dataverse Grid has a built-in empty state row ("No records found.")
in `dataverse_table.php`, but the message is generic and gives no guidance. Users who
apply a filter and get zero results don't know whether the filter is wrong, the data does
not exist, or they lack permission.

**Current state:** The empty state colspan is computed correctly. The message text
"No records found." is hardcoded in `dataverse_table.php` and is always the same regardless
of whether filters are active.

**Complexity:** Low — augment the empty state block to show different text when filters
or search are active vs. a genuinely empty table.

**Dependency:** Grid state object already carries filter/search info; no new data needed.

---

### Back-Navigation Works Correctly After Turbo Frame Interactions

**Why expected:** Users click into a member or officer view, drill through tabs, then
hit the browser back button. With Turbo Drive disabled (`Turbo.session.drive = false`),
full-page navigation uses the browser's native history. Turbo Frame navigations (tab loads,
grid pagination) do not push history entries, so back takes the user further than expected.

**Current state:** The `detail-tabs` Stimulus controller accepts a `data-detail-tabs-update-url-value`
flag. `turboActiveTabs.php` uses it; `turboActiveTabs.php` sets `updateUrl = true` by
default. Verification that all tab elements actually set this attribute correctly needs
spot-checking — `Members/view.php` uses a different tab element (`pluginTabButtons`) that
does not use `detail-tabs` at all and may not update the URL.

**Complexity:** Low to medium — audit which tab implementations update the URL and
which don't. Fix those that don't.

**Dependency:** None structural.

---

### Date Display Timezone Consistency

**Why expected:** Members and officers operate across time zones. The system has a
`TimezoneHelper` and a `timezone-input-controller.js`. Date-only fields (e.g., `start_on`,
`expires_on`, `membership_expires_on`) display using `$this->Timezone->format($date, 'Y-m-d', false)`
with the third argument `false` meaning "no timezone conversion" for date-only values.
Datetime fields use `true`. If any template uses `->toDateString()` or raw i18n format
without going through `TimezoneHelper`, the display will be inconsistent or wrong for
non-UTC kingdoms.

**Current state:** `members/memberDetails.php` uses `$this->Timezone->format(...)` for
membership and background check dates. The quick-login devices table uses
`$quickLoginDevice->created?->i18nFormat('yyyy-MM-dd HH:mm')` without `TimezoneHelper` —
a known inconsistency.

**Complexity:** Low — grep for `i18nFormat` usages in templates and convert to
`TimezoneHelper`.

**Dependency:** None.

---

### Membership Status Wording Is Clear

**Why expected:** The member detail shows `$member->status` as a raw string (e.g.,
`active`, `unverified_minor`). The verification instructions shown inline reference
contacting the Secretary and uploading a photo. Members must understand their status
to act on it.

**Current state:** `members/memberDetails.php` shows the raw `$member->status` constant
value. Instructions appear conditionally for `STATUS_ACTIVE` and `STATUS_MINOR_PARENT_VERIFIED`
only. The instructions reference a specific email setting. There is no human-readable
status label or badge.

**Complexity:** Low — map status constants to readable labels and apply a Bootstrap badge.

**Dependency:** None.

---

## UX Differentiators

Improvements that make the app feel more polished and purposeful to frequent users
(kingdom officers and heavy users of the Awards/Officers workflows).

### Confirmation State on Destructive Actions

**What:** The system uses CakePHP's `Form->postLink()` with a JavaScript `confirm()`
dialog for destructive actions (release officer, revoke warrant, delete member).
The browser native `confirm()` is not styleable, blocks the thread, and looks dated in
Bootstrap-heavy UI.

**Value:** Replace with a modal-based confirmation that shows the entity name being
deleted/released, making the action obvious and harder to accidentally confirm.

**Complexity:** Low-to-medium. A generic `delete-confirmation-controller.js` Stimulus
controller already exists. The pattern needs wiring to the postLink equivalents.

**Dependency:** Exists only after `delete-confirmation-controller.js` is verified functional.

---

### Filter Pill UX on Active Filters

**What:** The Dataverse Grid toolbar shows active filter pills via JavaScript. The grid
state correctly tracks filters and the pills container is populated by `grid-view-controller.js`.
When a filter is active, the pill shows the filter name and value, and has an `x` to
remove it. The pill rendering is JS-driven so it is not visible until the Turbo Frame
loads.

**Value:** Users who navigate to a pre-filtered grid view (e.g., from a dashboard link
with a query string) should see the active filters immediately without waiting for Turbo
Frame hydration. SSR filter pill rendering would remove the "loading" gap.

**Complexity:** Medium. The grid state is embedded as JSON in the page; the toolbar PHP
could render pills server-side using the same state. Requires matching CSS and logic
between PHP and JS, which risks drift.

**Dependency:** None structural. Primarily a maintenance complexity judgment call.

---

### Sortable Column Headers Show Current Sort Direction Clearly

**What:** The Dataverse Table renders sort indicators (`bi-caret-up-fill`,
`bi-caret-down-fill`, `bi-caret-down text-muted`) using Bootstrap Icons. The unsorted
indicator is muted but present on every sortable column, creating visual noise.

**Value:** Suppress the muted indicator by default; show it only on hover of sortable
headers. This reduces visual clutter without hiding the affordance.

**Complexity:** Low — CSS hover rule in `dataverse_table.php`'s style block.

**Dependency:** None.

---

### Pagination Shows Context-Sensitive Labels

**What:** Both `dv_grid_table.php` and `dv_grid_core_content.php` use
`$this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total'))`.
The `<<` / `>>` labels for first/last are text-only.

**Value:** Replace `<<` / `>>` first/last links with Bootstrap Icon icons
(`bi-chevron-bar-left`, `bi-chevron-bar-right`) and `<` / `>` with
`bi-chevron-left` / `bi-chevron-right`. Minor but consistent with the icon-forward
aesthetic of the rest of the UI.

**Complexity:** Low. One-line change per paginator call.

**Dependency:** Bootstrap Icons already loaded.

---

### Award Recommendation Board Readability

**What:** The kanban board in `Awards/Recommendations/board.php` uses a table-based
layout with `min-width: 1020px`. Cards show: award abbreviation, member SCA name,
truncated reason (100 chars), and "Last Modified" attribution. The "Edit" button and
the award link are crowded at the top of the card body.

**Value:** Separate the Edit button from the award title link so they are not on the
same line. Add visual spacing between the subtitle (member name) and the reason text.

**Complexity:** Low — CSS and HTML restructure within `board.php`.

**Dependency:** None.

---

### Profile Photo in Member List / Autocomplete

**What:** Member autocomplete (`Members/auto_complete.php` served by
`Members::autoComplete`) returns `sca_name`, `warrantable`, and `status`. The
`member-card-profile-controller.js` and `image-zoom-controller.js` handle profile photos
on the detail view. The member list grid does not show profile photos.

**Value:** This is a differentiator for large kingdoms where officers may not recognize
members by SCA name alone. However, it requires a new grid column type (image) and
careful handling of visibility (profile photos respect PII permissions).

**Complexity:** High relative to ROI in a maintenance cycle. See Anti-improvements below.

---

### Status Badge on Member Grid Rows

**What:** The Members grid (`MembersGridColumns.php`) presumably shows a `status`
column as a string. Using the `badge` column type with a `badgeConfig` mapping status
to Bootstrap badge colors (green = verified, yellow = unverified, red = deactivated)
makes the grid scannable.

**Value:** Officers using the verify queue or browsing the member list can quickly triage
status at a glance.

**Complexity:** Low — update `MembersGridColumns.php` to use `type => 'badge'` with
a `badgeConfig`. The badge renderer in `dataverse_table.php` already supports this.

**Dependency:** None.

---

## Code Quality Wins

Not user-facing directly, but prevents future bugs and makes maintenance safer.

### Privacy Settings on `Member::publicData()` — Complete the TODOs

**What:** `Member.php` has 15+ `//TODO Check Privacy Settings` comments in the
`publicData()` method. These fields currently return without privacy filtering.
`Officers::autoComplete()` has a `//TODO: Audit for Privacy` comment.

**Why it matters:** The public data method is called from the Awards recommendation
add form to pre-populate member info. If a member's contact information or birth date
is returned without privacy gating, it is visible to anyone submitting a recommendation
for them.

**Complexity:** Medium — needs a privacy settings design decision followed by
implementation. The `app_settings` table can store per-field privacy configuration.

**Dependency:** Needs a privacy field configuration design (could use `StaticHelpers::getAppSetting`
with a known key namespace like `Member.Privacy.*`).

---

### Warrant Membership Check Re-enablement

**What:** `DefaultWarrantManager.php` has `//TODO: Reactivate once we get reliable membership data`.
`Officers/RostersController.php` has the same comment. This suggests warrant eligibility
checking intentionally skips active membership verification.

**Why it matters:** An officer can be warranted even if their SCA membership has expired.
This is a data integrity risk if the membership sync ever becomes reliable.

**Complexity:** Medium — needs context on why it was disabled (reliability of membership
import). Implementation is straightforward once the data is trustworthy.

**Dependency:** Requires reliable membership data import. Blocked externally.

---

### AppSettings Caching

**What:** `AppSettingsTable.php` has `//TODO: Create a caching strategy for this`.
`StaticHelpers::getAppSetting()` is called in templates, controllers, and helpers
throughout the request lifecycle. Every call hits the database.

**Why it matters:** App settings rarely change. Each page request for a heavily
templated view (member detail with multiple tabs, each rendered by a plugin cell) may
call `getAppSetting` dozens of times.

**Complexity:** Low — add a request-scoped static cache in `StaticHelpers` or use
CakePHP's built-in `Cache` facade with a short TTL and a cache clear on settings save.

**Dependency:** None. The `AppSettingsController` already handles saves; add a
cache-clear hook there.

---

### SortableBehavior Error Handling

**What:** `SortableBehavior.php` has four `// TODO: Log error` comments. Silent failures
in sort operations make debugging position-ordering issues in drag-and-drop interfaces
invisible in the logs.

**Complexity:** Low — add `Log::error(...)` calls.

**Dependency:** None.

---

### Turbo Stream Response Pattern — Standardization

**What:** `AppSettingsController` uses the full turbo-stream pattern (read flash session,
clear it, render turbo-stream response). Other controllers that perform modal-based
actions (`OfficersController::assign()`, `OfficersController::release()`, etc.) use
`redirect($this->referer())` which is safe for full-page use but breaks flash rendering
in Turbo Frame contexts.

**Why it matters:** As the UI matures and more actions move into modals (which operate
inside Turbo Frames), the redirect pattern will silently swallow flash messages.

**Complexity:** Medium — extract a shared method (or AppController hook) for
modal-action responses that detects `Turbo-Frame` headers and returns a stream vs. a
redirect accordingly.

**Dependency:** Should be done before adding more modal-based workflows.

---

### `activeWindowTabs.php` vs `turboActiveTabs.php` — Two Tab Systems

**What:** Two different tab element files exist with overlapping concerns.
`activeWindowTabs.php` renders tables inline (eager, no Turbo). `turboActiveTabs.php`
uses lazy Turbo Frames per tab. Both exist in production use across different
parts of the codebase.

**Why it matters:** Maintenance overhead, inconsistent UX (some tabs load eagerly, some
lazily), inconsistent empty state behavior.

**Complexity:** Medium — audit all `activeWindowTabs` usages and migrate to
`turboActiveTabs` where the data is already served server-side in the tab format.

**Dependency:** The migrations are safe but tedious; requires per-usage testing.

---

## Anti-Improvements to Avoid

Things that sound good but would add maintenance burden without proportionate benefit
in a maintenance-mode codebase.

### Profile Photos in Grid Columns

**Why avoid:** Adds a new grid column type (`image`), requires permission-checking logic
in the grid renderer (only show photo if viewer has `viewPii` or photo is public),
increases page weight on list views, and creates a new code path to test. The member
detail view already shows the photo.

**What to do instead:** Keep photos on the detail view. If quick identification is
needed at officer check-in, the mobile card QR code workflow already solves that use case.

---

### Real-Time Grid Refresh Without Full Frame Reload

**Why avoid:** The Turbo Frame pattern loads grid data lazily on first render and on
filter/sort/page changes. Adding WebSocket or polling-based auto-refresh to "keep the
grid current" would require a significant infrastructure addition (WebSockets or SSE)
incompatible with the Queue plugin's job model. The data in KMP grids (members, officers,
warrants) changes slowly.

**What to do instead:** Manual refresh (the existing Turbo Frame reload) is sufficient.
Document that grids show data as of page load.

---

### In-Page Award Recommendation Status Board Auto-Promotion

**Why avoid:** The kanban board (`board.php`) supports drag-and-drop column changes via
`kanban-controller.js`. Adding "smart" auto-promotion rules (e.g., auto-move to "Approved"
when a kingdom event is selected) would bypass the deliberate human review step. The
`$rules` JSON already allows configuring legal state transitions per award type. Adding
automation here risks promoting awards without proper officer sign-off.

**What to do instead:** Improve the board's visual indicators of which transitions are
allowed, without automating the transitions themselves.

---

### Global `phpcbf` Run for Code Style Cleanup

**Why avoid:** Already documented as an architecture anti-pattern. Running `phpcbf`
globally adds native type hints from docblocks to `BasePolicy` overridable methods
(`canAdd`, `canEdit`, etc.), breaking PHP's Liskov Substitution Principle for plugin
policy overrides. This has broken the `WarrantsTable::afterSave()` and
`ServicePrincipalPolicy::canAdd()` in the past.

**What to do instead:** Fix PHPCS violations manually, only in files being changed.
Use `composer run-script cs-check` scoped to changed files only.

---

### Collapsing the Two Tab Systems Into One Under Turbo Drive

**Why avoid:** Enabling Turbo Drive (`Turbo.session.drive = true`) to unify navigation
would be a significant architectural change. Turbo Drive was explicitly disabled
(`Turbo.session.drive = false`). Enabling it would require auditing every page for
Turbo Drive compatibility (CSRF meta tag placement, `data-turbo="false"` on links that
must do full navigations, flash message rendering, etc.).

**What to do instead:** Keep Drive disabled. Consolidate the two tab elements
(`activeWindowTabs` → `turboActiveTabs`) using Turbo Frames, which is safe within the
current architecture.

---

### Privacy Controls as a New Plugin

**Why avoid:** The `Member::publicData()` TODOs suggest privacy field configuration
might warrant its own management UI. Building a full Privacy plugin (settings UI,
per-field toggles, API for external consumers) is new-feature work outside the
maintenance-mode scope.

**What to do instead:** Implement privacy field configuration using the existing
`app_settings` infrastructure with a `Member.Privacy.*` key namespace. Expose it
through the existing `AppSettings` admin interface. No new plugin needed.

---

## Feature Dependencies

```
AppSettings caching → fix first, reduces DB load for every improvement that reads settings
Turbo stream standardization → should precede any new modal-based save actions
Privacy TODO completion → blocks Officers::autoComplete audit and publicData correctness
Membership check re-enablement → blocked externally on reliable membership import data
```

---

## Maintenance Priority Recommendation

**Fix first (high impact, low effort):**
1. Timezone consistency — grep `i18nFormat` in templates, convert to `TimezoneHelper`
2. Member status badges — use existing badge column type in `MembersGridColumns`
3. Empty state messaging — differentiate filtered vs. empty in `dataverse_table.php`
4. AppSettings caching — request-scoped static cache in `StaticHelpers`
5. SortableBehavior logging — add `Log::error` calls

**Fix second (medium impact, medium effort):**
6. Flash messages in Turbo Frame contexts — turbo-stream response standardization
7. Modal validation errors — propagate the Members session-patching pattern to Officers and Awards modals
8. Privacy settings completion — implement `Member.Privacy.*` app settings namespace

**Defer (higher effort, lower ROI in maintenance mode):**
9. Tab system consolidation — `activeWindowTabs` → `turboActiveTabs`
10. Confirmation modals for destructive actions — wiring to existing `delete-confirmation-controller.js`
11. Membership check re-enablement — blocked on external data reliability

---

## Sources

- Direct inspection of `/workspaces/KMP/app/templates/` (285+ PHP template files)
- Direct inspection of `/workspaces/KMP/app/src/Controller/` and `app/plugins/*/src/Controller/`
- Direct inspection of `/workspaces/KMP/app/src/Model/Entity/Member.php`
- Direct inspection of `/workspaces/KMP/app/src/Services/` (ActiveWindowManager, WarrantManager)
- Direct inspection of `/workspaces/KMP/.planning/PROJECT.md` and `.planning/codebase/ARCHITECTURE.md`
- TODO/FIXME grep across `app/src/` and `app/plugins/` (excluding vendor)
