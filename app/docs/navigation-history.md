# App back-arrow navigation

`AppController::beforeRender()` records successful GET document renders after the
action has chosen the response and layout. Redirects, unsuccessful responses,
AJAX/Turbo/grid requests, non-document Fetch Metadata destinations, downloads,
non-HTML content, disabled layouts, and AJAX/Turbo layouts do not change history.
Login/logout, navbar/assets, the public calendar, and `nostack` remain excluded.
A fragment with action `index` must not clear history. Full-page Turbo visits use
Fetch Metadata destination `empty` but explicitly accept `text/html`; those remain
eligible when they render a whole page. Background HTML fetches must send the AJAX
header. Other background fetches are excluded.

The session stores at most 50 local request targets, retaining query parameters
and the request's existing base path. Reloads do not duplicate entries. Returning
to an earlier entry truncates the later entries; full `index` pages reset the list.
`pageStackVersion = 2` discards old fragment/redirect entries once, on the next
eligible page. The back button navigates the top-level page and has an accessible
name. With fewer than two app entries, it uses browser history.

When a successful document navigation leaves a grid, the same-origin Referer
can update the last history entry's query if its path matches that entry.
This retains the browser's current page, filters, search, sort, and row count
after frame navigation, so the app back arrow returns to the displayed grid.
References from another origin or an unrelated path cannot replace the entry.

Detail-tab grids keep their query in the table frame while the user works. Before
a same-origin record link leaves the page, or a native form submits from that grid,
`page-context` replaces the host history entry with the live grid query and current
tab. This lets browser Back and redirect Referers restore the displayed grid.
Frame endpoint identities (`frame_id`, `member_id`, `branch_id`, `gathering_id`)
stay on their own data URLs so reloading the host cannot override sibling grids.
The host query carries `grid_context` with the originating grid frame ID. On
reload, only that grid receives the query; sibling grids retain their own endpoint
context and default state. The marker is omitted from data endpoint requests and
from primary grid pages. Unmarked detail URLs keep their existing query behavior.
Same-frame pagination, modal links, external links, and new-tab links do not
change the host entry. Native confirmation paths notify `page-context:before-submit`
before calling `form.submit()`, which does not emit the normal submit event.
Automatic Turbo modal requests use the originating grid query as their same-origin
fetch Referer while leaving browser history unchanged. A modal trigger outside a
grid can resolve its origin from a tab panel or a containing frame with one grid.

Calendar controllers share Bootstrap dialog instances. Refreshing a calendar
frame must not dispose page-owned dialogs. Quick view closes before RSVP opens;
closing RSVP returns focus to the visible calendar trigger, not a hidden button
inside quick view. Attendance form fetches send `X-Requested-With: XMLHttpRequest`. RSVP submissions
that explicitly request Turbo streams take precedence over generic AJAX/JSON
detection. Reopening quick view refreshes attendance state from the server.

Verify with `NavigationHistoryTest`, the calendar controller Jest tests, and the
local gathering navigation browser check. Cover calendar → quick view → RSVP,
full details → RSVP → app back arrow, modal transitions, frame refreshes,
filtered calendar return URLs, and keyboard focus.

Public gathering landing pages load the limited `public-event` runtime for
Bootstrap, public gathering behavior, and accessible removal confirmations.
Turbo Drive stays disabled, and attendance forms use normal server redirects.
The app and public gathering stylesheets share `modal.css`, including scrolling
through form wrappers and keeping dialog actions reachable in short windows.
Verify this path with `node tests/ui/support/public-gathering-attendance-browser-check.cjs`
from `app/`; it creates and removes local synthetic records and covers mouse and
keyboard registration, updates, removal/cancellation, and focus return at desktop
and mobile sizes.

## Gathering schedule timing

Add and edit schedule dialogs share `gatherings/scheduleTimingFields`. Start dates
and quarter-hour time choices are in the gathering timezone. Durations offer
15-minute increments through four hours; **Other — see description** clears the
end time and leaves timing details to the description. `GatheringScheduleService`
computes the end in UTC so elapsed durations survive midnight and daylight-saving
changes. Existing gathering range rules also validate the computed end. Schedule rows
display durations in minutes so quarter-hour increments remain exact.

Editing preserves an existing off-quarter start choice and selects the stored
elapsed duration. Durations outside the picker offer **Keep current end** until
the user chooses a replacement duration. These preservation choices
do not appear in new entries. Existing datetime-based clients remain supported.

Browser acceptance: `node tests/ui/support/gathering-schedule-browser-check.cjs`.
