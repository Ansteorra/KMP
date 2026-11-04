# Public kingdom calendar

The calendar is a tenant-host public surface backed by the tenant's gatherings. It is
unauthenticated, but it is not global: `TenantResolutionMiddleware` must resolve the request
host before `GatheringsController` can query data.

## Signed-in gathering reader

The signed-in `/gatherings/view/<public_id>` page uses `Gatherings/reader.php`, a native
application layout with an Overview first: when/where, the member's RSVP, description,
schedule, activity badges, royal progress and shared contacts. It does not embed the
public landing-page element, styles or fonts. Directions, registration, calendar and
sharing links are next to the event summary. Overview anchors provide quick section access.

`view.php` supplies authorized organization tools to that layout; `view_public.php`
supplies the limited member view. Metadata lives under Record details. Plugin tabs and
details still come from the view-cell registry, using the integer record ID. Keep
existing tab IDs and modal/controller targets stable for deep links and delegated activity
permissions. The overview includes only staff flagged for public sharing; private
staffing remains in the authorized Staff tab. RSVP eligibility matches the attendance
endpoints, including existing RSVPs on cancelled events and events ending today.
The unauthenticated landing page remains a separate presentation.

For local browser acceptance without a database reset, run
`node tests/ui/support/gathering-reader-browser-check.cjs` from `app/`. Set
`KMP_READER_EVIDENCE_DIR` to keep desktop/mobile screenshots outside Git. The check
creates and cleans up synthetic members and a gathering, and exercises reader privacy,
RSVP save/reopen, organization tools, keyboard tabs, cancellation and responsive layout.

## Publication and public links

`GatheringsController::publish()` is the only normal write path for the guarded publication
fields:

- `published` controls the `/events` listing and `/gatherings/feed` subscription.
- `published_by` and `published_on` record the publisher and time.
- `public_page_enabled` independently controls the event landing page at
  `/gatherings/public-landing/<public_id>`.

Publishing requires `GatheringPolicy::canPublish` and the “Can Publish Gatherings to Kingdom
Calendar” permission; ordinary branch event management does not imply that ability. The
endpoint is `POST /gatherings/publish/<id>?publish=true|false`.

For a public event link, prefer an enabled KMP landing page, then `website_url`, then plain
text. `preregister_url` is an external link shown only while
`Gathering::is_preregistration_open` is true. Keep these decisions server-side rather than
reimplementing them in JavaScript.

## `/events`

`GatheringsController::publicCalendar()`:

- selects `published = true` gatherings whose dates overlap today through two years ahead;
- builds compact, horizontally scrollable rows using each event's timezone, grouped by month;
- shows empty weekends and repeats multi-week events on affected weekends, with a Continues label;
- keeps weekday-only events visible; a weekend belongs to the month containing its Saturday;
- supports `activities[]=<id>` filtering and derives options from upcoming published events;
- adds `q` (event/group/address), `branch`, `month` (`YYYY-MM`), and `circles=1` filters;
- supports `royal_progress[]=<snapshot>` filtering by royal office and represented branch;
- displays branch, location, activity/circle metadata, cancellation state, event and calendar
  links, and royal-progress snapshots;
- renders the `public_event` layout with `templates/Gatherings/public_calendar.php` and
  `assets/css/gatherings_public.css`.

Rows show date, event name and stored location/address, hosting group, and compact activity/progress
icons. Clicking noninteractive row content expands details; event names keep the existing public-page
then external-website link precedence. The native disclosure remains keyboard operable. Expanded rows
contain all public activities, event information, royal progress and calendar downloads. Without
JavaScript, expanded content remains available through the no-script fallback.

Activities show Royal Progress first, Order Circle second, then all remaining activities. Within
each group they sort by configured `display_order`, then name and ID; stored assignment order
is untouched. This shared order also applies to other calendar views and activity selectors. The summary shows at most four activity icons, followed by an accessible **…** button
when more exist. Royal Progress and Order Circle use their configured activity icons, without
additional generic markers. Expanded rows use the same single badge per activity and retain
royal attendance as named text. **Business** activities are administrative: omit them from
summary icons, expanded activity lists, filter choices, and overflow counts. Public event pages
also omit Business activities and their scheduled sessions, including for signed-in staff.
Filtering happens before schedule grouping, so empty days and sections are not rendered.
Internal event assignments and schedules remain available to staff.

The document owns vertical calendar scrolling; the table wrapper only scrolls horizontally
on narrow screens and never shows a separate vertical scrollbar. The filter bar stays pinned
to the top as the document scrolls underneath. Its opaque
background and stacking order keep controls and open pickers above the event rows. Keyboard
focus in the calendar is revealed below the bar. At short viewport heights, the filter bar
is capped at 60% of the viewport and can scroll internally, leaving the event list reachable.

The **Activities & progress** filter starts collapsed and opens a picker up to 600px wide with
two columns of choices within each type at viewport widths of 600px and above. Phones use one
column. The searchable choice area remains capped at 240px. Each activity shows its configured icon or uploaded artwork and colors beside
its name. A border and checkmark indicate selection; keyboard focus has a separate outline.
Native checkbox semantics and form values remain available to keyboards, screen readers, and
JavaScript-free browsing, while the checkbox itself is visually hidden. Options are grouped by
**Activity Type**, with **Royal Progress** and **Order Circle**
first and expanded by default; other types start collapsed. All type headings are native
keyboard-operable disclosures. Each heading shows its selected count. Within each type, activities follow their configured
display order. The Royal Progress group also contains **Any Royal Progress** and published royalty
snapshots; Order Circle includes **Any order circle** and individual order activities. These retain
the existing attendance, circles, and activity query parameters and combined-filter behavior.
Searching matches activity names, type headings, and royal office names, hides empty groups, and
preserves checked choices. Searching opens matching groups; clearing the search restores each
group’s previous expanded/collapsed state. The summary counts selections across all groups.
Selected activity, circle, and royal progress names remain visible beneath the controls, even
when the picker is closed or search hides their options. The names are also server-rendered
for JavaScript-free browsing. The selected-name list is capped at 90px to keep the pinned
filters compact. Changing a selection announces that **Apply filters** is required; reverting
to the applied selection clears the reminder. Filters submit
explicitly and work with native form navigation. The month selector keeps the full two-year horizon accessible without adding a long
month-navigation rail. Empty rows under an active filter say **No matching events**, so they do
not imply a booking date is available. Month totals count distinct events within that month;
the page total counts distinct visible events across continuation rows.

`PublicCalendarService` owns date bucketing, continuation rows and activity ordering. Do not put
those rules in templates or reimplement the server-owned public event URL decisions in JavaScript.

An order circle is a normal gathering activity with **Activity Type → Order Circle**; names are not used
to infer the type at runtime.

## Rolling development demo events

`bin/cake seed_public_calendar --host kmp.localhost` creates or refreshes six published
events with ordinary names using the normal activity catalogue. Examples include Crown attendance,
Laurel and Pelican circles, every activity type, more than four activities on a row,
a ten-day event, and gaps between weekends. Dates are relative to the next Friday in
America/Chicago. Event pages are public; titles and descriptions have no demo/test labels.
Examples are distributed deterministically across existing local groups, rather than the
administrator's default group. Later changes to an example's host group are preserved.
Royal attendance snapshots name the kingdom, independently of the event's hosting group.

The local Docker scheduler runs this refresh on startup and every 24 hours, keeping the
fixtures current while development services run. It uses the default `kmp.localhost`
tenant. Set `KMP_PUBLIC_CALENDAR_DEMO=false` when starting Compose to disable automatic
refresh. The command requires debug mode, a `.localhost` tenant host, an active tenant,
and a local PostgreSQL server; it never seeds a production deployment.

The event refresh tracks its six events by public ID in the tenant setting
`Development.PublicCalendar.EventIds`. It adopts older `[Calendar Demo]` event names and
removes that prefix in place, preserving IDs and public URLs. Later title edits do not
break refreshes or create duplicates. The daily refresh updates dates and publication
state and creates missing activity links. Other events are untouched.

`GatheringActivitySeedService` is shared by the normal development activity seed and the
calendar examples. It reuses normal names (including existing Archery/Rapier naming variants),
creates missing catalogue entries, and fills unclassified activity types. Existing normal
IDs, display order, descriptions, uploads, and configuration are retained. Daily refreshes
preserve configured icons and colors. Running the normal activity seed reapplies icon and
color defaults. Pass `--refresh-activity-icons` or `--refresh-activity-colors` to
`seed_public_calendar` to independently refresh either setting for catalog icons. Uploaded
artwork and its colors are preserved. Color defaults use readable foreground/background
pairs, including green Laurel, crimson Pelican, blue Defense, and gold Crown icons.
The known `[Calendar Demo]` and `[Local icon test]` activity duplicates are renamed in place
when no normal entry exists, or consolidated into the existing normal entry. Event links,
schedules, templates, award eligibility, waivers, and assignment permissions are retained;
duplicate links combine required flags and custom descriptions. Other custom activity names
are untouched. Neither event names nor activity names need demo/test markers.
Use the command again after a development database reset, or restart the local scheduler.

For POC, an operator may run the same command with `--poc --host <tenant-host>` from a staging
administrative job. It requires `KMP_ENV=staging`, `KMP_ADMIN_JOB=true`, and the execution-only
opt-in `KMP_ALLOW_POC_CALENDAR_SEED=true`. Production is refused. This adds or updates only the
six tracked examples and the normal activity catalog; it does not reset the tenant database.
Use `--refresh-activity-icons --refresh-activity-colors` to apply the packaged icon settings.

## Feed and calendar downloads

`GET /gatherings/feed` returns a cacheable multi-event iCalendar document for published
events from 30 days ago through two years ahead. It accepts supported grid filters under
`filter[column][]`, emits an `ETag`, and returns `304` for a matching `If-None-Match`.
Calendar subscriptions must retain the tenant hostname.

Per-event downloads use the gathering public ID. Unauthenticated access is allowed only when
the event is published or its public landing page is enabled; preserve the controller's
server-side visibility check when changing download links.

## Royal progress

Royal attendance is stored as metadata on `gathering_attendances`. Selecting the activity
type **Royal Progress** alone does not record royal attendance. The table method
`applyRoyalProgress()` validates that the member currently holds the selected
progress-enabled office, then records `progress_office_name` and `progress_branch_name`
snapshots and forces `share_with_kingdom = true`. Public displays use the snapshots so an old
event does not change when office assignments change. Progress fields remain guarded from
normal mass assignment.

The member profile, event detail, public event, desktop calendar, and mobile calendar RSVP forms share the
Royal Progress switch and office selector. Eligibility comes from current officer assignments
whose office has `is_royal_progress` enabled, rather than a role name alone. The visible switch
defaults to **On** when creating an eligible royal's RSVP; the office selector defaults to the
first eligible assignment and lets members choose the represented office/branch when they hold
several. Turning it **Off** records an ordinary RSVP. Editing preserves the saved On/Off state,
including historical office/branch snapshots when an assignment ends or changes. Turning an
existing progress RSVP Off clears its progress metadata.

Gathering grids, desktop calendars, the mobile calendar, and `/events` offer a **Royal
Progress** filter. Choices use the saved title (for example, “Crown of Ansteorra” or
“Coronet of a Principality”), so the represented branch is independent of the event's
hosting branch. **Any Royal Progress** matches any recorded royal RSVP. Selecting several
office/branch choices matches any of them; activity and other filters still apply together.
Multiple matching RSVPs never duplicate an event. Clearing or deleting a royal RSVP removes
that match.

Month, week, list, and mobile calendars show a small gold crown badge for royal progress,
alongside the existing attendance/activity indicators. Its tooltip and accessible label list
the attending offices and represented branches; ordinary RSVPs do not add the badge.

Each snapshot filter value is a base64-encoded JSON pair `[office_name, branch_name]`; a branch may be
`null`. Grid and feed URLs carry the choices as `filter[royal_progress][]`, with `any` for
all royal RSVPs. The public calendar's subscription link retains its royal progress
selection. Office renames and changes of office holder do not rewrite existing choices.

## Theme and embedding constraints

`Plugin.PublicGatherings.CustomCSS` is the supported tenant theme override. It is appended
after the base `.kc-*` styles by the controller/template path; keep style-end breakout
protection in place.

Linking from an external site is supported. Cross-origin iframe embedding is not: every
response currently sends `X-Frame-Options: SAMEORIGIN` and CSP
`frame-ancestors 'self'`. The public layout and back-stack exclusion do not override those
headers. Do not document or depend on WordPress iframe embedding unless the application gains
a narrowly reviewed frame policy and corresponding clickjacking tests.

## Source and verification map

- `config/routes.php`
- `src/Application.php` (tenant middleware order and browser security headers)
- `src/Controller/GatheringsController.php`
- `src/Policy/GatheringPolicy.php`
- `src/Model/Table/GatheringAttendancesTable.php`
- `templates/Gatherings/public_calendar.php`
- `assets/css/gatherings_public.css`
