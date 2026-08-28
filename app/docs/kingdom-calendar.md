# Public kingdom calendar

The calendar is a tenant-host public surface backed by the tenant's gatherings. It is
unauthenticated, but it is not global: `TenantResolutionMiddleware` must resolve the request
host before `GatheringsController` can query data.

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
