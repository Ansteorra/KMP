# Kingdom Calendar: Publishing Workflow & Royal Progress

This document covers the public kingdom calendar feature set (issues #58–#64):
who can publish events, how the public calendar behaves, and how royal
progress is modeled.

## Publishing workflow (issue #58)

Gatherings carry an explicit publish flag that controls whether they appear on
the **public** kingdom calendar and iCal feed:

- `gatherings.published` (bool) — on the public calendar when true
- `gatherings.published_by` / `published_on` — audit stamp, set by the publish
  action and cleared on unpublish

Publishing is a **kingdom-level privilege**, deliberately separate from branch
gathering management so local groups cannot publish kingdom events before
dates are secured:

- Policy: `App\Policy\GatheringPolicy::canPublish`
- Permission: **"Can Publish Gatherings to Kingdom Calendar"** (created by
  migration `AddPublishGatheringsPermission`). It is granted to **no roles by
  default** — assign it to the Kingdom Seneschal / Calendar Deputy roles
  through the role management UI. Super users can always publish.
- UI: Publish/Unpublish buttons on the gathering view page
  (`templates/Gatherings/view.php`), shown only to users passing `canPublish`.
- Endpoint: `POST /gatherings/publish/{id}?publish=true|false`

`published` is guarded against mass assignment on the `Gathering` entity; the
only write path is `GatheringsController::publish()`.

### Relationship to `public_page_enabled`

These are independent controls:

| Flag | Controls | Who sets it |
|------|----------|-------------|
| `public_page_enabled` | The per-event public landing page (`/gatherings/public-landing/{public_id}`) | Event stewards / branch gathering managers |
| `published` | Listing on the public kingdom calendar (`/events`) and the public iCal feed | Kingdom calendar staff only |

A published event without a public landing page appears on the calendar as
plain text (no link). Per-event `.ics` downloads are public when **either**
flag is set.

## Public kingdom calendar (issues #59, #60, #63)

- Route: **`/events`** → `GatheringsController::publicCalendar()`
  (unauthenticated, read-only, list-first)
- Lists only `published = true` gatherings from today through +2 years,
  grouped by month, in each event's own timezone.
- Shows inline (no expansion needed): dates/times, host branch, location, the
  event's web link (`gatherings.website_url`, issue #59), a `.ics` download,
  a cancelled badge when applicable, and royal progress with a crown icon
  (issue #63).
- The header offers a `webcal://` subscription link to the public feed
  (`/gatherings/feed`), which applies the same `published = true` filter.
- Template: `templates/Gatherings/public_calendar.php`; styles live in
  `assets/css/gatherings_public.css` (`.kc-*` classes).

## Royal progress via RSVP metadata (issues #61, #62)

Royal progress is **not** a separate activity type. It is metadata on the
normal RSVP record (`gathering_attendances`):

- `is_royal_progress` (bool)
- `progress_office_id` — `officers_offices.id` reference (UI pre-selection
  only; intentionally no FK)
- `progress_office_name` / `progress_branch_name` — **snapshots** taken at
  RSVP time so the progress record keeps its meaning after the office holder
  changes (issue #62). The `progress_title` virtual field renders them as
  e.g. "Crown of Ansteorra".

### Which offices count as progress

Offices carry an `is_royal_progress` flag (`officers_offices.is_royal_progress`),
editable on the office add/edit forms in the Officers plugin. Flag the Crown,
Coronet, and heir offices; leave everything else off.

### How a progress RSVP is recorded

1. A member who **currently holds** a progress-flagged office opens the normal
   RSVP modal (gathering view, public landing page, or calendar). The modal
   shows a "Royal Progress" select listing their current progress-eligible
   offices (`GatheringAttendancesTable::currentProgressOfficersForMember()`).
2. On save, the controller passes `progress_officer_id` to
   `GatheringAttendancesTable::applyRoyalProgress()`, which verifies the
   officer assignment (belongs to the member, status Current, office flagged)
   and writes the snapshot. Invalid selections are rejected server-side.
3. Progress RSVPs are always shared with the kingdom
   (`share_with_kingdom = true` is forced) — public visibility is the point.
4. The mobile RSVP JSON endpoints (`mobileRsvp` / `mobileUpdateRsvp`) accept
   the same `progress_officer_id` field.

All progress fields are guarded against mass assignment; `applyRoyalProgress()`
is the only write path.

### Where progress is displayed

- `/events` — crown icon on the event row plus a "Progress: Crown of
  Ansteorra (Sca Name)" line
- Public landing page hero — "Royal Progress" banner
- Both use the snapshot fields, never live officer data.

## Embedding

The public calendar is self-contained (no app chrome, no authentication) and
can be linked or iframed from a kingdom WordPress site. Per-tenant theming is
tracked separately in issue #65.
