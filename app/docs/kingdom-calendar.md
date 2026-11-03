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
- groups them by the event's timezone;
- supports `activities[]=<id>` filtering and derives options from upcoming published events;
- supports `royal_progress[]=<snapshot>` filtering by royal office and represented branch;
- displays branch, location, activity/circle metadata, cancellation state, event and calendar
  links, and royal-progress snapshots;
- renders the `public_event` layout with `templates/Gatherings/public_calendar.php` and
  `assets/css/gatherings_public.css`.

An order circle is a normal gathering activity with `is_circle = true`; names are not used to
infer the flag at runtime.

## Feed and calendar downloads

`GET /gatherings/feed` returns a cacheable multi-event iCalendar document for published
events from 30 days ago through two years ahead. It accepts supported grid filters under
`filter[column][]`, emits an `ETag`, and returns `304` for a matching `If-None-Match`.
Calendar subscriptions must retain the tenant hostname.

Per-event downloads use the gathering public ID. Unauthenticated access is allowed only when
the event is published or its public landing page is enabled; preserve the controller's
server-side visibility check when changing download links.

## Royal progress

Royal progress is metadata on `gathering_attendances`, not an activity type. The table method
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
