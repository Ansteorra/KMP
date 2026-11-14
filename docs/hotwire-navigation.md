---
layout: default
---
[← Back to UI Components](9-ui-components.md) | [← Back to JavaScript Development](10-javascript-development.md) | [← Back to Table of Contents](index.md)

# Hotwire navigation

KMP uses Turbo Drive for audited authenticated navigation when
`KMP_TURBO_ENABLED=true`, with Frames/Streams as the fallback. Live delivery has
an independent `KMP_LIVE_UPDATES_ENABLED` switch. Both default off until the
release gates pass. Authentication, identity changes, downloads, public consent,
complex workflow editors, platform administration and offline/mobile layouts
retain native boundaries. See the [runtime contracts](https://github.com/Ansteorra/KMP/blob/main/app/docs/hotwire-interactions.md).

## Choose the navigation mechanism

| User interaction | Mechanism | Browser URL |
| --- | --- | --- |
| Open a compatible page | Turbo document visit; native fallback when disabled | Shared browser history |
| Filter, sort, or page a Dataverse grid | Reload the inner `{frameId}-table` frame | Shared navigation adapter preserves local history |
| Load a detail tab | Lazy tab frame | Detail-tabs controller preserves `?tab=` |
| Submit a modal form | Turbo-enabled form and Turbo Stream response | Remains on the originating page |
| Refresh one changed grid record | Replace or remove its row with a Turbo Stream | Remains unchanged |
| Refresh an entire grid result | Invalidate the inner table once using its live query | Remains unchanged |
| Refresh larger dependent details | Guarded morph preserving loaded frames and drafts | Remains unchanged |
| Observe another user’s change | Authorized SSE invalidation followed by a fresh scoped read | Remains unchanged |

Use a frame when one bounded region has a stable destination. Use a stream when
a successful server mutation needs to coordinate several regions, such as a
modal, flash messages, and a grid row. A normal redirect remains the clearest
choice for a full-page workflow.

## Frame contracts

Keep frame IDs stable because templates, controllers, and tests use them as an
interface:

- Dataverse grid shell: `{resource}-grid`
- Dataverse grid results: `{resource}-grid-table`
- Grid row: `{resource}-grid-row-{primaryKey}`
- Modal: a purpose-specific ID such as `editMemberQuick`
- Detail tab: `{tabId}-frame`

The outer grid frame owns the toolbar and controls. Only the inner `-table`
frame is replaced for filter, sort, pagination, export preparation, or modal
refreshes. `App\KMP\GridRowDomId` derives row IDs from the table-frame ID; do
not recreate that naming logic in a controller or plugin.

Links rendered inside a frame that should open a complete record page must use
`data-turbo-frame="_top"`.

## Preserve page context

`grid-view-controller` uses `ui-navigation-service.js` to write grid state and
restore the inner frame on local Back/Forward. The adapter preserves Turbo history
state and prevents competing document refreshes for local tab/filter entries.
Native paginator links synchronize the displayed page back to that state.
Returning from a record page therefore restores pagination together with
search, filters, sorting, and rows per page. A refresh that loses its requested
page uses the last available page and synchronizes the effective page.

Modal forms opened from a grid include a hidden `page_context_url`. The
`page-context` controller keeps it synchronized with
`window.location.pathname + window.location.search`. On POST, the server uses
that value to preserve the current page, filters, search, sort, and row count in
a refresh URL.

`TurboResponseTrait` accepts only a relative path and query. Absolute,
protocol-relative, and newline-containing values are rejected. Treat page
context as navigation state, never as authorization evidence.

## Modal form workflow

A grid-backed modal follows this sequence:

1. The index template provides an empty Turbo Frame inside a Bootstrap modal.
2. A frame-targeted GET renders the form inside that frame.
3. The form sets `data-turbo="true"`, attaches `turbo-modal`, and submits through
   `submit->turbo-modal#submitAsTurboStream`.
4. Turbo owns the compatible submission. With Drive disabled, the shared
   coordinator sends the same-origin request with CSRF and the stream media type.
   Exactly one handler submits and renders the response.
5. On success, the controller action authorizes and saves, then returns a stream
   that closes the modal and synchronizes the row or table.
6. On validation failure, return a 422 stream that reloads the edit frame or
   renders the errors. Do not close the modal.

Forms expecting streams explicitly opt in with `data-turbo="true"`. Successful
HTML mutations redirect with 303; validation returns 422 with values and errors.
The dialog coordinator resolves confirmation only after Bootstrap has closed it
and restores focus after affected content finishes updating.

## Server response helpers

Controllers that support partial updates use `App\Controller\TurboResponseTrait`:

| Helper | Result |
| --- | --- |
| `wantsTurboStreamRequest()` | Checks the Turbo Stream media type in `Accept` |
| `getPageContextUrl()` | Reads and validates the posted relative page context |
| `buildGridDataUrlFromPageContext()` | Adds the preserved query to a grid-data route |
| `matchesGridIndexPath()` | Confirms that the origin is a supported grid route |
| `withPageContextQuery()` | Runs row resolution with the preserved grid query |
| `renderTurboReplaceGridRow()` | Replaces a still-visible row |
| `renderTurboRemoveGridRow()` | Removes a row that no longer matches the view |
| `renderTurboCloseModal()` | Closes the modal and reloads the table frame |
| `renderTurboReloadFrame()` | Reloads a form frame, normally after invalid input |
| `renderTurboFlashOnly()` | Updates flash messages without forcing navigation |

The stream elements live in `app/templates/element/`, including
`turbo_sync_grid_row.php`, `turbo_close_modal.php`, and
`turbo_reload_frame.php`.

## Row synchronization and modal refreshes

For a single-record save, resolve the record through the same query, scopes,
filters, computed fields, and enrichment used by `gridData()`:

- If the row still belongs in the current result, render
  `dataverse_table_row.php` and replace its stable DOM ID.
- If the record no longer matches the selected view or filters, remove the row.
- If the operation changes several records, the grid is embedded in an
  unsupported context, or the row cannot be reproduced safely, reload the
  inner table frame.

Row synchronization is an optimization after authorization and persistence. It
must not introduce a less-scoped query than the normal grid endpoint.

Shared modal clients advertise `X-KMP-Grid-Reconcile: table`; compatible server
helpers invalidate the table directly, avoiding a discarded row query/render.
Older row replacement/removal streams also become one table reload. It also carries the live
frame query into table replacement streams. This refreshes ordering, counts,
filter membership, and pagination together while retaining the user's place.
Focus restoration waits for the refreshed frame and uses a surviving control
when the edited row is no longer visible. Server row helpers remain available
for other stream consumers.

## Full-page and Back navigation

The app Back arrow now uses the same browser history as Turbo. It skips the
current record's local tab/filter entries and restores the preceding document's
query and scroll. PHP no longer stores a separate `pageStack`.

For directly opened records, `NavigationParentService` supplies an authorized,
same-tenant list destination. The Back control remains an ordinary accessible
link when JavaScript or browser history is unavailable. Keep local tab and grid
history in `ui-navigation-service.js`; never replace Turbo's restoration state.

Cached authenticated documents remain hidden until `/navigation/context` validates
tenant, identity, impersonation, permission generation, release and runtime flags.
Context changes clear snapshots and subscriptions. Sensitive form values and
transient dialogs/feedback stay out of snapshots.

## Multi-tenant and security boundary

The request host selects the tenant before controller code runs. Frame and
stream URLs must therefore be generated by CakePHP or remain same-origin
relative URLs; never copy a URL from one tenant host into another tenant's UI.
Every frame endpoint and stream mutation still requires normal authentication,
authorization, branch/tenant scoping, CSRF handling, and restore-lock checks.

A Turbo Stream target is only a DOM address. It is not proof that the request
may read or mutate the represented record.

## Stimulus lifecycle and accessibility

Frames can replace controller-owned DOM at any time. Register listeners in
`connect()`, remove them in `disconnect()`, and avoid retaining element
references across frame replacement. Use Bootstrap's modal API and existing KMP
accessibility utilities so focus returns to the opener and async success/error
messages are announced. Keep labels, error associations, focus order, and
keyboard operation intact in every frame state.

Use a full reload only when the whole-page lifecycle genuinely owns the state.
For a grid, modal, or tab interaction, update the smallest stable frame or emit
a stream.

## Verification

Run focused JavaScript tests from `app/`:

```bash
npm run test:js -- --runInBand \
  tests/js/controllers/grid-view-controller.test.js \
  tests/js/controllers/page-context-controller.test.js \
  tests/js/controllers/turbo-modal-controller.test.js
```

For a browser flow, use the repository lane wrapper so BDD generation and the
one-time database reset happen consistently:

```bash
bash bin/run-playwright-lane.sh uat --grep @hotwire
```

Run `npm run test:ui:journey` when the change intersects the curated end-to-end
journey, including the shipped Awards grid flow. Run `npm run test:ui` for the
full UAT lane when shared layouts, frame infrastructure, or migrations change.
Playwright lanes mutate shared state, so run only one lane at a time.

## Common failures

| Symptom | Check |
| --- | --- |
| Form redirects to a full page | Form is inside the target frame and has `data-turbo="true"` |
| Server returns HTML instead of a stream | Request sends `Accept: text/vnd.turbo-stream.html` |
| Modal closes on invalid input | Failure path returns 422 and reloads/renders the form frame |
| Filters disappear after save | `page_context_url` is present and the refresh URL preserves its query |
| Wrong row is replaced | Table frame and `GridRowDomId` follow the shared naming contract |
| Controller works once | Event listeners and Bootstrap handlers are cleaned up in `disconnect()` |
| Cross-tenant link appears | URL was hard-coded or copied instead of generated for the current host |

## Related pages

- [Dataverse Grid system](9.1-dataverse-grid-system.md)
- [UI components](9-ui-components.md)
- [JavaScript development](10-javascript-development.md)
- [Frontend architecture reference](10.1-javascript-framework.md)
