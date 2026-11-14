# App back-arrow navigation

The shared browser navigation adapter owns document and local UI history. The app
Back arrow uses real browser history and skips the current record’s tab/filter
entries, returning to the previous document with its query and scroll restoration.
PHP no longer maintains a second `pageStack` in the session.

`NavigationParentService` supplies a same-tenant, policy-checked list link for
directly opened records. It uses the registered list action (including My Tasks
and My RSVPs), or the home page when no authorized list exists. Foreign referrers
and authentication pages never become app return destinations. The control stays
a normal accessible link when JavaScript or browser history is unavailable.

With `KMP_TURBO_ENABLED=true`, Turbo owns document visits.
`ui-navigation-service.js` preserves Turbo’s restoration identifiers and state
while tab/grid changes add local entries. Its listener runs before Turbo’s history
observer so local Back/Forward cannot trigger a competing document restoration.
With the switch off, native document navigation and Frames/Streams remain active,
and the same adapter maintains local UI history.

Detail-tab grids keep their query in the table frame while the user works. Before
a same-origin record link leaves the page, or a native form submits from that grid,
`page-context` replaces the host history entry with the live grid query and current
tab. This lets browser Back and redirect Referers restore the displayed grid.
The adapter stops Turbo’s document-history observer in fallback mode and brokers
local restoration before Turbo in Drive mode. Top-level tab
changes preserve other query parameters and the hash; nested tab
groups do not replace the host tab. Back/Forward restores selection without
reloading loaded tab frames. The profile alias retains the query when redirecting
to the member record. See [Hotwire interaction contracts](hotwire-interactions.md).
Frame endpoint identities (`frame_id`, `member_id`, `branch_id`, `gathering_id`)
stay on their own data URLs so reloading the host cannot override sibling grids.
Parameters already present in a grid's data URL take precedence over restored host
parameters, including on unmarked URLs; the remaining page, filters, and sort restore normally.
The host query carries `grid_context` with the originating grid frame ID. On
reload, only that grid receives the query; sibling grids retain their own endpoint
context and default state. The marker is omitted from data endpoint requests and
from primary grid pages. Unmarked detail URLs keep their existing query behavior.
Embedded saved-view page URLs preserve the matching `grid_context`, current tab,
and hash; frame refreshes retain their endpoint identities and omit the host marker.
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
