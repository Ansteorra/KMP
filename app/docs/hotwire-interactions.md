# Hotwire interaction contracts

KMP supports Turbo Drive behind `KMP_TURBO_ENABLED` (default false), with the
existing Frame/Stream runtime as its fallback. Live delivery has an independent
`KMP_LIVE_UPDATES_ENABLED` switch (also default false), and requires Drive. Enable
these only after the release gates below pass for the candidate.

`ui-navigation-service.js` owns document and local history coordination. Turbo
owns document visits; the adapter preserves Turbo state and brokers tab/filter
Back/Forward before Turbo’s history observer. In fallback mode it stops that
observer because disabling `session.drive` alone still allows Turbo to restore
documents on `popstate`. This integrates with the installed Turbo history API;
retain the real-browser acceptance checks when upgrading Turbo.

Use the existing `dv_grid`, `DataverseGridTrait`, `TurboResponseTrait`,
`grid-view`, `page-context`, and `turbo-modal` abstractions before adding another
fetch or reload path. See the upstream [frame reference](https://turbo.hotwired.dev/reference/frames)
and [stream reference](https://turbo.hotwired.dev/reference/streams).

## Choose an update boundary

| Change | Expected update |
| --- | --- |
| Search, sort, filter, paging, saved-view selection | Reload the grid's table frame; retain its shell and controls |
| Edit a row that can change ordering, filter membership, or totals | Refresh the current table once, retaining its live query; clamp an empty last page |
| Change display-only content with no ordering/count implications | A row stream is sufficient when the caller does not require table reconciliation |
| Change a detail summary and related list | Stream both explicit regions, or use a deliberate document redirect while preserving the detail tab |
| Change permissions, impersonation, authentication, or global workflow editor state | A document navigation can be appropriate because controls and identity-dependent content change |
| Validation or network failure | Keep the form and draft visible, provide an accessible error, and allow retry |

Do not use a whole-page stream `refresh` as a generic subgrid-save response.
A frame response must contain the matching frame ID. A stream response must target
an element present on its originating screen. Return a non-success status for
failed mutations; a redirect followed by a successful HTML document must not stand
in for validation failure on a stream form.

Grid state includes the default sort on first load, mapped to its column key and
normalized direction. Headers show both arrows for inactive columns and a single
arrow for the active sort. Clicking the active header reverses its direction;
another column starts ascending. Header labels and arrows stay on one line, with
horizontal scrolling in the table region when needed.

## Detail tabs and return context

`detail-tabs-controller` owns the top-level `tab` parameter. Bootstrap's
`shown.bs.tab` event synchronizes both mouse and keyboard selection. Selecting the
first tab removes only `tab`; other query parameters, the hash, and history state
survive. Browser Back/Forward selects the corresponding tab without adding history
or forcing scroll to the top. An unavailable tab falls back to the first tab by
`data-tab-order`.

Nested `detail-tabs` groups store their selection in `history.state.kmp.nestedTabs`
under the group ID (or first ordered tab ID). They create local history entries
without replacing the host record’s `tab` parameter. Local Back/Forward and cached
document restoration restore each group independently. IDs must stay stable and
unique within the document. Explicit `update-url=false` groups stay local without
adding history entries.

Turbo lazy frames load when visible. Revisiting a tab keeps its loaded content;
explicit mutation streams are responsible for refreshing affected content.
`frame.loaded` is a promise and must not be used as a boolean load-completion test.
Do not attach unconditional `reload()` calls to tab selection.

`page-context-controller` and `turbo-modal-controller` preserve the originating
grid's current query, including paging and rows per page. Embedded data endpoints
retain their own identities; the host carries `grid_context` so sibling grids do
not inherit each other's filters. The profile alias forwards query parameters to
the member record. See [navigation history](navigation-history.md).

## Modal saves

A stream modal form uses
`submit->turbo-modal#submitAsTurboStream`. `data-turbo="true"` alone does not opt a
form outside a frame into the fallback fetch lifecycle. With Drive enabled, Turbo
owns the submission and the controller coordinates only response processing,
Bootstrap transitions, feedback, reconciliation, and focus. It consumes the stream
response before Turbo’s document StreamObserver to prevent duplicate rendering.
Compatible HTML mutations redirect with 303; HTML entity-validation failures
render 422 with submitted values and the existing associated field errors. Keep AJAX request headers,
CSRF fields/tokens, normal authorization, and same-origin credentials.

The shared controller includes the clicked submit button's name/value, blocks
concurrent submissions, and leaves the dialog open until the server succeeds.
Failure flash messages are also shown inside the dialog, where users can read
them without closing it. Officer edit failures return a 422 flash stream instead
of redirecting away from the entered dates and notes.

Successful streams close the dialog through Bootstrap before changing the page.
The controller reconciles affected Dataverse rows/table streams to one table
refresh using the live frame URL. It waits for that refresh before restoring focus
to the replacement action, neighboring row, or surviving context. Preserve dialog
labels, keyboard dismissal, visible focus, live feedback, and listener cleanup.

## Notes, attendance, and schedule dependencies

Notes append only the newly saved note through `Notes/saved.php` and the shared
note element. The existing list, private-note visibility, expanded notes, and
form stay in place. The Notes controller resets the draft only after a successful
stream and focuses the new note. Validation returns 422 without replacing the
form. No host-page fetch is needed for a note save.

`page-refresh-service.js` coordinates committed attendance and schedule changes.
Templates opt regions into a topic with a stable `id` and
`data-refresh-on="attendance"` or `data-refresh-on="schedule"`; multiple topics
are space-separated. These static regions are updated with Turbo Streams using
fresh markup from an authorized AJAX GET of the current record. Only their inner
content changes, so active tab/ARIA state and unrelated drafts survive. Responses
missing a requested region are rejected before any update; a response for a
previous document is discarded, and newer refreshes supersede older region data.

Use `data-refresh-frame-on` on a dependent frame or its wrapper for grids and
calendars. The service refreshes the innermost table frame with its live source,
retaining filters, view, pagination, row count, and endpoint identities. Plugin
cells declare their own dependencies; core does not know plugin frame IDs.
Page-owned JSON lists can subscribe to `kmp:changed` and register completion with
`event.detail.waitUntil(promise)`. `data-change-context` identifies their owner
when modal markup is removed after closing.

- Attendance updates refresh the gathering's RSVP action, RSVP summary, overview,
  attendee list, count, and form controls. The next open therefore uses the correct
  add/edit/delete action. Calendar changes refresh the current calendar frame;
  reopening quick view fetches current attendance. My RSVPs refreshes its lists
  and counts and updates its saved projection without changing tabs. Online mobile
  forms use the shared stream submitter, with no second legacy submission handler.
  The existing offline queue remains separate, and public gathering forms remain
  native submissions.
- Schedule add, edit, and delete refresh the schedule content, badge, overview,
  and the Awards cell's dependent grid. The existing schedule service continues
  to clear dependent court/agenda assignments when deleting a session. Editor
  dialogs remain outside the refreshed content, so failed saves retain input.
  Dialog field IDs are distinct between add and edit forms.

Close dialogs through Bootstrap before refreshing their dependent markup, then
restore focus to the replacement control or a surviving list/tab action without
forcing scroll. Do not replace tab panes or grid shells to refresh their contents.
A refresh failure after a committed write displays **Saved** feedback and a
**Refresh details** retry that repeats reads only. Never invite another mutation
because a follow-up read failed.

## Application surface review

The implementation review covered the shared runtime and these core/plugin
interaction families. These distinctions identify migration opportunities; they
are not a claim that every action has browser coverage.

| Surface | Current behavior and experience opportunity |
| --- | --- |
| Core Dataverse grids, settings, approvals, ActionItems | Shared frame navigation and stream helpers exist. Keep all save callers aligned with live-query reconciliation. |
| Offices catalogue | One `dv_grid` owns its frame ID. Do not wrap it in another frame with the same ID or promote grid-data navigation to a document visit. |
| Member/branch officer grids | Table invalidations reconcile the live query; validation stays in the modal. Compatible HTML grants/releases use Drive redirects; permission-changing outcomes trigger fresh authorization. |
| Awards recommendations and bestowals | Index and embedded contexts have row-sync helpers and shared stream modals. Quick-edit failure helpers can reload forms through GET; retaining submitted drafts and associating field errors is a useful next improvement. |
| Activity catalogue and gathering activity editor | Frame editor loading, loading/error templates, and focus handling are established patterns to reuse. Review remaining forms that only close on `turbo:submit-start` before adopting the full shared submit lifecycle. |
| Notes on members, warrant rosters, recommendations, and bestowals | The shared Notes cell appends a saved note and resets only the successful draft. Existing creation policies and host private-note visibility remain authoritative. |
| Roles and permission membership changes | Compatible forms use Drive redirects; security invalidation clears older snapshots and refreshes identity-dependent controls. Identity switches remain native. |
| Gathering schedules | JSON mutations refresh explicit schedule/count/overview regions and the plugin-owned award grid. Deletes retain the existing court/agenda cleanup workflow. |
| Calendar attendance and My RSVPs | Stream forms refresh the relevant calendar or JSON list and counters. Gathering details refresh their attendance regions and controls; consent and offline/native paths remain separate. |
| Waiver attestation/upload | Drive attestation closes its dialog and reconciles the same screen; blocked reads offer a saved-state retry. Pending/persisted waiver details subscribe through their authorized gathering. Multipart/camera upload wizards retain native boundaries and their existing progress/offline handling. |
| Workflow designer/import/publish and offline runtime | Some full reloads synchronize global/editor/session state. Treat these as separate flows, with draft/offline/session verification, rather than automatically converting them to frame updates. |
| To-Do completion, approval response/reassignment, recommendation decisions | Stimulus initializes dialogs on document/frame insertion and cached restoration. Scoped listeners and delayed deep-link opening are cancelled on exit. Existing approval controllers and domain validation remain authoritative. |
| Gathering staff forms and member RSVP editor | Stimulus initializes each visit; obsolete or disconnected contact/form reads cannot overwrite the current selection or draft. Bootstrap owns dialog close and focus trapping. |
| Permission policy disclosures | Native keyboard buttons drive Bootstrap collapse, with matching controlled-region IDs, synchronized icons and labeled policy switches. |

Runtime templates have no page-load-only initializer outside the deliberate native
Table Admin SQL tool. That tool retains native navigation because it executes raw
maintenance queries outside ordinary mutation hooks. The unreferenced
`timezone_examples` element is documentation/demo content, not an application
screen. The remaining inline default-layout script only sets the app URL root;
grid/bestowal/autocomplete scripts are inert JSON data. A Jest compatibility guard
rejects new `DOMContentLoaded` initialization in active core/plugin templates.

Shared modal requests advertise `X-KMP-Grid-Reconcile: table`. Officer and bestowal
saves return an explicit table invalidation for these clients, avoiding the
intermediate row query/render that the client would discard. The shared coordinator
refreshes that table once using its live query, retaining ordering, counts and
empty-page correction. Older clients retain the row-response contract. Reserve
true row-only updates for mutations whose dependency set is known.

Heavy frontend controllers/services are eagerly imported through
`controllers-entry.js`. Dynamic loading of expensive editor, PDF, and recognition
features is a separate bundle-performance opportunity and needs a focused review
of their global registration and offline dependencies.

## Verification

Run `npm run test:js` for tab/history, page context, grid, and modal coverage.
Run `npm run dev` in the configured application environment before browser checks.
`node tests/ui/support/detail-tabs-browser-check.cjs` uses the running local Docker
app to check document/query preservation, Back/Forward, loaded-frame reuse,
nested tabs, modal validation, partial table refreshes, and focus return. Its modal
write endpoint is intercepted; it does not mutate application records. Server
mutation/validation behavior is covered separately by the officer controller and
workflow dispatch tests. Existing Awards Hotwire BDD scenarios cover real grid saves
and pagination changes.

`node tests/ui/support/partial-updates-browser-check.cjs` creates and removes
local synthetic fixtures and exercises real Notes, RSVP detail/list/calendar,
schedule validation/add/edit/delete, and dependent award-grid refreshes. It checks
that court assignments clear, the document/tab/query remain intact, and focus
returns to the continuing task. Run only one browser lane at a time.

For future migrations, require keyboard submission/dismissal, a reachable error
message with the draft intact, correct ARIA selection, predictable focus after
refresh, retained tab/filter/page/row count, no duplicate POST, no unnecessary
main-document request, and no stale dependent detail content.

## Snapshot, draft, and component lifecycle

Hashed Vite scripts/styles carry `data-turbo-track=reload`. A cached authenticated
snapshot remains hidden until `GET /navigation/context` validates an opaque HMAC
of tenant, host, session, identity/auth version, impersonation, permission
generation, immutable release revision (or the development asset manifest), release tag, and runtime switches. Failed, redirected, timed-out, or
changed context clears snapshots and subscriptions and reloads through normal
authentication. Browser BFCache restores repeat the check before revealing content.

`form-state-service.js` tracks user edits, protects dirty forms during morphs,
warns before leaving unsaved work, and resets the baseline only after successful
submission or explicit reset. Cached restoration retains the draft's dirty marker,
so returning with Back does not silently treat restored work as saved. Successful
local mutations and received invalidations clear older document snapshots before
they can restore stale data; authorization-only subscription/refresh POSTs do not
evict snapshots. Controller initialization and hidden context fields
are not drafts. Dependent refreshes update clean summaries immediately and defer
dirty regions with an accessible **Refresh details** action. Reset/success retries
those reads without repeating the mutation. Hidden tab regions are marked stale
and refreshed when opened; loaded current tabs do not reload.

`bootstrap-lifecycle-service.js` disposes instances before document/frame removal
and snapshot caching, clears transient dialog state, and initializes tooltips
after document and frame rendering. Controllers still own their listeners,
requests, timers, and observers. `frame-request-lifecycle-service.js` installs
before Turbo upgrades eager frames, cancels obsolete reads, and buffers finite
frame HTML before handing it to Turbo. This keeps cancellation after headers
inside the request error boundary, including Firefox native navigation.
`modal-navigation-service.js` coordinates ordinary HTML form redirects with
Bootstrap and retains validation errors/drafts inside an open dialog. Render
wrappers preserve the authenticated snapshot validation pause. View transitions
honor reduced motion.
Background table refreshes use a morph stream inside the existing frame, retaining
focusable elements where possible and choosing a surviving grid/tab control if a
focused row disappears. Removed Bootstrap nodes are disposed; retained popovers
rebuild their content only when morphed attributes change. Frame renderers settle
without painting when a draft begins during their request, rather than leaving a
paused renderer that can block later work.

## Authorized live invalidation

`POST /live/subscriptions` resolves registered read contexts and runs their normal
resource policy. It issues a 120-second RS256 token bound to the exact Origin,
tenant, authorized topics, and navigation context. Browser fetch streams send the
token in the Authorization header, never a URL, to the independent Node SSE
gateway. Apache/PHP does not hold persistent browser connections.

`UiUpdateRegistry` owns core metadata; loaded plugins supply
`Services\UiUpdateProvider` implementations. Providers resolve tenant tables at
use time. The mutation behavior records invalidation metadata on the domain
connection, including old/new ownership and bounded transitive dependencies. A
rollback leaves no event. After commit, publishing uses Redis fanout, bounded
per-tenant replay, and event-ID deduplication. Failed deliveries remain in the
tenant-local outbox with retry delay; tenant worker drains retry them. Delivered
rows expire after one day in bounded batches. Administrative imports do not emit
events; workers can still dispatch existing events. Bulk ORM operations require
explicit invalidation because they bypass behavior callbacks. `UiBulkMutation`
provides that path for registered bulk updates/deletes: it locks the matched IDs
in a consistent order, mutates that fixed set, and records the old and new owners
in bounded batches on the same transaction. Optimistic version/status conditions
remain part of the initial locked selection. Approval counters, feedback expiry,
warrant replacement, and bestowal template synchronization use it. Authentication
and passkey counters retain their native identity lifecycle; administrative
imports do not emit live events.

The gateway filters tenant/topics and emits invalidations, never private rendered
HTML. Content reads always return to CakePHP authorization and tenant/branch
scopes. `POST /ui/refresh` accepts only registered read contexts and rejects
arbitrary route/template selection. Registered Notes refreshes render the existing
Notes list element directly, repeating the owning screen’s private-note policy.
Other registered contexts request reconciliation through their authorized read
templates. Screens
without declared regions use a guarded morph refresh or current-query grid reload.
The guarded refresh morphs the main content while retaining loaded frame elements,
then reloads each visible leaf frame once using its current query. Hidden loaded
frames are marked stale and reconciled when opened. An editor begun during the
read prevents painting that response. Mixed signals still update declared clean
summaries while an unrelated draft blocks the broader refresh.

Catalogue providers declare `invalidatesScreens` dependencies for read contexts
that display their labels and configuration. A subscription can therefore include
an office/activity/award catalogue channel without expanding access to other
members' record channels. Signals contain no catalogue HTML; the resulting read
repeats the owning screen's authorization. Plugins contribute these dependencies
through their providers rather than adding plugin rules to core controllers.
Catalogue channels are separate from owner-list channels: adding a Note or changing
attendance refreshes the relevant owner/detail/list, without announcing an unrelated
member or gathering label change to every catalogue-dependent screen.

The client batches signals, discards obsolete reads, preserves dirty work, renews
subscriptions before expiry, reconciles replay gaps, and uses bounded visible-region
polling during outages. Permission invalidation clears the cache and reloads.
The shared browser/capacity transport jitters lease renewal and retains the current
stream until the replacement is authorized and connected. Complete-event cursors
use full-precision stream IDs; duplicate events and obsolete streams cannot move
them backward. Replay gaps clear an ahead cursor before reconciliation. Relative
lease lifetime avoids depending on the user's device clock.
Rejected, missing, or redirected subscription/content reads also invalidate the
old session view immediately. Failed content reads retain their signals and retry
at a bounded interval; finishing/resetting an editor or using **Refresh details**
reconciles deferred reads.
Mutation IDs correlate requests with committed events. Actor events still reconcile:
a lost response or a partially updated pane must not cause a committed change to
be ignored. Repeated invalidations are coalesced.
See [live deployment contract](../../deploy/azure/hotwire-live.md).

## Release gates and deliberate native boundaries

Authentication/MFA/passkeys, impersonation and identity changes, downloads/exports,
public consent/event pages, platform administration, Table Admin SQL maintenance, workflow definition editors,
multipart/camera upload wizards, and offline/mobile application layouts retain native document boundaries. These
flows require fresh identity, download semantics, consent submission, or separate
editor/offline lifecycle verification. They still retain supported Frames/Streams.
Prefetch stays disabled until destinations have measured inexpensive GET baselines.

The candidate requires all PHPUnit suites, Jest, PHP/static/template lint, docs
and icons, development/production assets, gateway unit/real-Redis tests, Azure
contracts, sequential Chromium/Firefox/WebKit UAT, and a separate destructive
platform lane. Browser acceptance includes app Back, nested tabs, sibling grids,
modal validation/network recovery, draft retention, and dependent summaries.
Accessibility requires keyboard/focus/mobile/reduced-motion checks and manual
screen-reader spot checks.

Capacity acceptance is 30 minutes with 500 distinct authenticated live sessions
across five synthetic tenants, at least two web and two gateway replicas, 50
ordinary requests/second and five mutations/second. Require zero unexpected
errors, healthy live p95 within two seconds, request p95 within 1.5 seconds and at
most 10% above a comparable baseline. Record query/request counts, latency,
outbox lag, reconnects, CPU/memory and connection use. Tie results to the exact
candidate commit and immutable images, then validate that candidate in POC.
Unavailable or failed gates remain outstanding and prevent release-readiness
claims or app-wide switch enablement. Production promotion is a separate action.

### Capacity runner

`npm run test:perf:contracts` verifies the workload and acceptance logic in local
verification. `npm run test:perf:live` executes the actual HTTP/SSE workload against
an isolated Docker deployment. It requires these private environment inputs:

| Input | Contract |
| --- | --- |
| `KMP_CAPACITY_MANIFEST` | Path to a private JSON manifest with `synthetic: true`, full candidate `commit`, 500 distinct `sessions` across five tenants, and immutable `replicas.web`/`replicas.gateway` container/image pairs |
| `KMP_CAPACITY_PASSWORD` | Synthetic account password, supplied privately; never written to reports |
| `KMP_CAPACITY_OUTPUT` | Private evidence directory |
| `KMP_CAPACITY_MODE` | `baseline` for the comparable 300-second baseline; otherwise live acceptance defaults to 1,800 seconds |
| `KMP_CAPACITY_BASELINE` | Path to the comparable baseline's `capacity-report.json` for live acceptance |
| `KMP_CAPACITY_SECONDS` | Optional diagnostic duration; a shortened live run cannot pass release acceptance |

Each session provides `tenant`, `email`, two same-host `origins` for the web
replicas, its authorized `detailPath`, and optional `ordinaryPaths`. At least one
account per tenant has `writer: true` and an authorized Notes form. The manifest
also provides two `gatewayEndpoints` ending in `/events`, and `database` telemetry
metadata (`container`, local PostgreSQL `user`, five `tenants` database names).
Fixture provisioning, reset and removal belong to that isolated deployment and
must never point at shared development or customer databases.

The runner verifies running image IDs and revision labels, logs in every distinct
account, checks each shared session against both web replicas, connects the live
sessions, offers fixed request/mutation rates, and verifies committed Notes through
SSE followed by an authorized partial read. It samples gateway health/connections,
container CPU/memory and tenant outbox lag/PostgreSQL connections. Enable
`PERF_REQUEST_LOG_ENABLED=true`, `PERF_LOG_ALL_REQUESTS=true`, and sufficient
performance log retention on the isolated web replicas. A bounded opaque
`X-KMP-Performance-Run` value correlates only the measured requests with server query,
database-time, response-byte and CPU metrics. Missing metrics fail acceptance.

Reports record the candidate, image pair, workload, latencies, lost updates,
reconnects, errors, and failed gates. Keep the fixture and replica manifest stable
between baseline and live runs; reset synthetic mutation data between them.
No abbreviated run or passing contract test substitutes for the required load run.
