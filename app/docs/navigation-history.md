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
