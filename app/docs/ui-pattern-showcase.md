# Shared UI pattern showcase

`/ui-patterns` is an authenticated design-review surface for the full
application. It exercises six page families with fictional examples of existing
KMP workflows. It is not a replacement for those workflows or a production
permission model.

The showcase uses the real tenant branding, authorized application navigation,
back stack, Bootstrap components, KMP icon catalog, and frontend pipeline. It is
deliberately reached by direct link while the design is being reviewed. Its CSS
is a separate Vite entry loaded only by the showcase layout; existing application
screens retain their current presentation.

## Examples and review controls

Use **Examples & states** to select a workflow and typical, empty, needs-attention,
or limited-access situation. The next-example arrow cycles through the six families.

| Family | Example | Interactions |
| --- | --- | --- |
| Collection | Dataverse gathering list | Real saved tabs/defaults, combined filters, search, column visibility/order, sort, pagination, cards/table, selection, CSV download |
| Record | Branch, member, or role | Visible facts, related sections, local edit, permission grid and Add lookup dialog |
| Reader | Oakmere Winter Court | Schedule, activities, Royal Progress, local RSVP/edit, connected court workspace |
| Focused task | Gathering waiver submission | Validated steps, local file or sample selection, attestation, completion feedback |
| Workspace | Kingdom and Baronial court agendas | Switch court, add eligible examples, reorder with keyboard-operable buttons |
| Dashboard | Waiver operations | Branch scope, distinct missing/lost/ready-to-close exceptions, contextual links |

The connected gathering examples share the same name and date. Unrelated sample
records are not linked to that gathering. Gathering, record, waiver, and agenda
edits are simulated in memory and reset on navigation. File selection displays only the selected name;
the showcase does not upload, read, or store the document. The controller exposes
only GET and rejects writes. Personal grid views use the existing authenticated
`GridViews` APIs and persist under separate `UiPatterns.collection.main` and
`UiPatterns.branch.gatherings` grid keys. They never change ordinary gathering
views or gathering records. Application navigation outside the examples opens
the real app and keeps its normal authorization.

Review-state labels illustrate capability differences, but are not authority to
perform a real action. A future adoption must preserve separate PII, membership
verification, branch roster, activity-assignment, court, and other domain checks.
The role lookup adds a permission with its defined scope; it does not let the
role redefine permission scope. Member role assignments supply the branch.

## Full-app mobile contract under review

- Keep application navigation available through a Bootstrap drawer. Account and
  configured header links share an options menu rather than overflowing the header.
  The phone header omits the supplemental “Full app” label and keeps fixed gutters
  so enlarged text does not crowd the navigation and account controls.
- Give navigation icons, labels, and counts separate slots. Wrapped labels stay
  aligned with their first line; counts remain visible at the right. Distinguish
  section headings with weight, background, and spacing. Preserve full labels
  instead of truncating them or shrinking their type to fit.
- Use the same page identity, meaningful status, history Back, and action hierarchy
  on desktop and phone. Preserve the existing circled Back icon and originating URL.
- Let title and action groups move onto separate rows when space is tight. Keep
  page headings readable and wrap at word boundaries. The phone review toolbar
  gives the example title and navigation controls their own rows.
- Keep search and filters accessible; disclose secondary display controls on
  phones. Keep saved tabs, their options menus, and Save view visible. Show selection
  review when records are selected, with current-page scope.
- Offer cards and a separately scrollable comparison table. Do not force a loss of
  sorting, selection, or columns simply because the viewport is narrow.
  Card and record-summary fields reflow into one column when enlarged text needs more space.
  Sort through the table's column headers; cards retain that order. Do not add a
  redundant sort dropdown alongside the column controls.
- Keep field controls at readable sizes and touch controls at least 44px in the
  phone layout. Preserve keyboard focus, native labels, modal focus return, and
  status announcements. Scroll section tabs fully into view when they receive
  focus or become active, without moving the page vertically.
- Keep icon-only and dismiss controls square, with explicit dimensions that
  cannot be stretched by general button rules. Let button labels wrap within
  their own space and move action groups onto another row when necessary.
- Use drawers for secondary workspace tools, retaining court selection and
  non-drag reordering on the working surface.
- Keep task actions in a sticky footer that reserves its own layout space, respects
  the safe-area inset, and wraps at narrow widths. Validate before advancing and
  focus the new step or completion heading.
- Preserve distinct domain states: lost is not uploaded, placing an award on an
  agenda is not presenting it, and record visibility is not edit access.

This is a responsive full-app design exercise. It does not expand the existing
offline capability or change the mobile quick-access application. Existing device
trust, impersonation, session, and restore-lock handling remain active.

## Ownership and composition

| File or directory | Responsibility |
| --- | --- |
| `src/Controller/UiPatternsController.php` | GET-only entry; whitelisted family, record, and scenario selection |
| `src/Policy/UiPatternsControllerPolicy.php` | Authenticated access to fictional examples |
| `src/Services/UiPatternGridService.php` | Synthetic gathering query results using isolated, member-owned Dataverse preferences |
| `src/KMP/GridColumns/UiPatternGatheringsGridColumns.php` | Sample column/filter metadata and system views |
| `templates/layout/ui_showcase.php` | Candidate full-app shell using the existing navigation registry/cell |
| `templates/UiPatterns/index.php` | Review controls, scenario selection, shared live feedback |
| `templates/element/uiPatterns/pageHeader.php` | Shared title, context, Back, status, and supplied action presentation |
| `templates/element/uiPatterns/gatherings_grid.php` | One Dataverse surface shared by the collection and branch record's Gatherings section |
| `templates/element/uiPatterns/*.php` | Explicit synthetic examples, not domain queries or plugin integrations |
| `assets/css/ui-patterns.css` | Scoped candidate tokens and responsive patterns |
| `assets/js/controllers/ui-patterns-controller.js` | Local record, wizard, agenda, and dashboard behavior |
| `assets/js/controllers/ui-pattern-grid-controller.js` | Responsive presentation, quick search, and selection summary delegated to the real grid controller |

The collection uses `grid-view-controller`, `grid_view_toolbar`, `grid_view_tabs`,
`dataverse_table`, `DataverseGridTrait`'s state builder, `GridViewService`,
`GridViewConfig`, and `CsvExportService`. The synthetic service replaces the
business query, not the shared interaction components or saved-view store.

Filter chips keep their text separate from their dismiss or lock indicator.
Same-field OR groups name the field once; mixed-field groups retain each field
label. Each OR connector belongs to the following choice so it cannot be stranded
when wrapping. Shared markup and basic styles live in `grid-view-controller.js`
and `app.css`; the showcase uses 32px dismiss controls on desktop and 44px on
phones. Removing a focused chip moves focus to a surviving chip or the Filters
control, unless the user has already moved elsewhere. Restoring a saved view
returns focus from the disappearing Restore button to its active view tab.

The shared navigation templates expose `kmp-nav-icon`, `kmp-nav-label`, and
`kmp-nav-count` slots. Only the showcase styles arrange them into columns; the
existing navigation registry, authorization, destinations, collapse IDs, and saved
expansion preferences remain the source of truth. Direct navigation links do not
display disclosure chevrons.

Dataverse owns `view_id`, `search`, `filter[field][]`, date bounds, `sort`,
`direction`, `columns`, `limit`, `page`, and `dirty` query flags. Sticky `pattern`,
`state`, `record`, and `layout` preserve the review context through grid navigation.
Record sections own `section`; dashboard scope owns `branch`. Cards and table use
one set of rows and checkboxes. The default layout responds to viewport size;
an explicit Cards/Table choice takes precedence and is retained in the URL.

Saved views remember search, filters, sort, visible column order, and page size.
Unsaved changes can be restored, saved as another tab, or written back through
the selected personal tab's Update View action. Defaults and deletion use the
normal view options menu. Multiple options within a filter use OR; different
filters combine with AND. CSV includes all matching rows, independent of pagination
or selection, with exactly the visible columns in their chosen order. Email
subscriptions are intentionally unavailable for this unregistered synthetic grid.

The branch record embeds the same component in its Gatherings tab, with a locked
Oakmere branch filter and a separate personal-view namespace. Its required branch
context is enforced on the server for results and CSV; saved views, URL edits, and
Clear all filters cannot remove it. Embedded navigation preserves the host record
and selected section while keeping grid state separate, using the existing
Dataverse frame-context behavior.

Gathering row links open `/ui-patterns/reader`, a separate document action. This
lets `page-context` promote the originating grid's live query before departure
and lets the existing app back stack retain it. The review selector still accepts
`?pattern=reader` for direct previews; connected workflows use the reader action.

Keep future examples in this isolated surface until their shared interaction and
permission contracts are accepted. Adoption should adapt the existing record
blocks, Dataverse metadata/state, helpers, and plugin registries. Do not copy the
synthetic examples or their simplified capability flags into business screens.

## Verification

From `app/`:

```bash
vendor/bin/phpunit tests/TestCase/Controller/UiPatternsControllerTest.php
vendor/bin/phpunit tests/TestCase/Services/UiPatternGridServiceTest.php
npm run test:js -- --runInBand 'ui-pattern|grid-view'
npm run dev
node tests/ui/support/ui-patterns-browser-check.cjs
node tests/ui/support/ui-pattern-grid-browser-check.cjs
node tests/ui/support/ui-pattern-navigation-browser-check.cjs
node tests/ui/support/ui-pattern-buttons-browser-check.cjs
```

The browser check uses the existing seeded local tenant without resetting it or
creating fixtures. It checks all families at 1440px, 390px, and 320px, alternate
states, return-to-filtered-results, dialogs, phone drawers, local submission, and
court tools. It rejects JavaScript errors and verifies simulated domain commands
issue no writes. The dedicated grid journey also verifies real preference saving,
reopening, updating, defaults, deletion, filter combinations, and CSV parity. It
cleans up only views it creates and preserves the prior showcase default. Set
`UI_PATTERNS_OUTPUT` to choose a screenshot/result directory;
the defaults are ignored `tmp/ui-pattern-showcase` and `tmp/ui-pattern-grid`. Use the existing
`PLAYWRIGHT_BASE_URL` and `PLAYWRIGHT_ADMIN_EMAIL` overrides when needed.

The navigation journey checks desktop, tablet, and phone widths, wrapping and
badge alignment, all six page headers, keyboard section toggles, drawer focus
return, and enlarged text. It restores navigation expansion preferences and
does not change domain records.

Screenshots and automated tests support review; they do not establish product-wide
screen-reader or contrast conformance. Future rollout still needs the relevant
domain permission, validation, offline, print, and browser regressions described
in the existing UI and accessibility guides.
