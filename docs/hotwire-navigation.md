---
layout: default
---
[← Back to UI Components](9-ui-components.md) | [← Back to JavaScript Development](10-javascript-development.md) | [← Back to Table of Contents](index.md)

# Hotwire navigation

KMP uses Turbo Frames and Turbo Streams for explicit partial updates. Turbo
Drive is disabled in `app/assets/js/index.js`, so ordinary links and forms keep
normal browser navigation unless a template opts into Turbo.

## Choose the navigation mechanism

| User interaction | Mechanism | Browser URL |
| --- | --- | --- |
| Open a normal page | Plain link or `data-turbo-frame="_top"` | Navigates normally |
| Filter, sort, or page a Dataverse grid | Reload the inner `{frameId}-table` frame | Grid controller preserves state with `pushState` |
| Load a detail tab | Lazy tab frame | Detail-tabs controller preserves `?tab=` |
| Submit a modal form | Turbo-enabled form and Turbo Stream response | Remains on the originating page |
| Refresh one changed grid record | Replace or remove its row with a Turbo Stream | Remains unchanged |
| Refresh an entire grid result | Replace the inner table frame with a lazy `src` | Remains unchanged |

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

`grid-view-controller` writes grid state to the address bar and updates the
inner frame's `src`. It also restores the frame on `popstate`, so Back and
Forward navigate grid state without enabling Turbo Drive.

Modal forms opened from a grid include a hidden `page_context_url`. The
`page-context` controller keeps it synchronized with
`window.location.pathname + window.location.search`. On POST, the server uses
that value to preserve the current filters and sort in a refresh URL.

`TurboResponseTrait` accepts only a relative path and query. Absolute,
protocol-relative, and newline-containing values are rejected. Treat page
context as navigation state, never as authorization evidence.

## Modal form workflow

A grid-backed modal follows this sequence:

1. The index template provides an empty Turbo Frame inside a Bootstrap modal.
2. A frame-targeted GET renders the form inside that frame.
3. The form sets `data-turbo="true"`, attaches `turbo-modal`, and submits through
   `submit->turbo-modal#submitAsTurboStream`.
4. `turbo-modal-controller` sends a same-origin request with the CSRF token and
   Turbo Stream `Accept` header.
5. On success, the controller action authorizes and saves, then returns a stream
   that closes the modal and synchronizes the row or table.
6. On validation failure, return a 422 stream that reloads the edit frame or
   renders the errors. Do not close the modal.

Turbo Drive being disabled does not disable frames or streams. It does mean that
a form expecting a stream must opt in with `data-turbo="true"`.

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

## Prefer targeted row synchronization

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

## Full-page and Back navigation

KMP has two distinct history mechanisms:

- `pageStack` stores the full-page GET trail used by the server-side back button.
- Grid and tab controllers store local UI state in the browser URL.

Do not use `pageStack` as grid state, and do not enable Turbo Drive to repair a
Back-button problem. Diagnose the appropriate history owner.

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
