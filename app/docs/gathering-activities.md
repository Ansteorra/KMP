# Gathering activities

`GatheringActivity` is the shared catalogue for gathering activities. It is separate
from the Activities plugin's member authorization types. The catalogue owns appearance,
waiver associations, and assignment permissions. Gathering links own descriptions and
ordering; scheduled entries own times, titles, and registration details.

## Catalogue editing

The Gathering Activities grid has **View** and **Edit** actions. **Edit** and
**Add Gathering Activity** open the same modal for the name, description, display order,
activity type, colors, catalog icon, and uploaded artwork. Successful saves refresh
the grid while preserving its current search, filters, sorting, and page. Validation
errors keep the dialog open with entered values and focus the invalid setting.

**View** opens the activity details for assignment permissions, waiver associations,
and award configuration provided by plugins. The details page's **Edit** action uses
the same modal and returns to the current details tab after saving. Direct add/edit
URLs remain available as full-page forms.

## Assignment and scheduling

The activity's **Can be assigned by** tab follows the Role **Permissions** pattern:
assigned permissions appear in a table with scope and requirements; **Add Permission**
opens the shared searchable lookup modal and each row has a Remove action. Already
assigned permissions are excluded from the lookup. Removing the last permission restores
gathering-editor/steward access. Each action changes only that one rule.

Any one current grant covering the gathering's branch is sufficient. Existing role dates, membership,
warrant requirements, and Global / Branch Only / Branch and Children scopes still apply.

An event award delegation additionally permits eligible award-giving activities for the delegated
awarding group at that one gathering. Its assignment rules are checked against the grantor's native
permissions in the awarding group's branch, allowing a Kingdom Court at a local event. This access
ends on revocation or loss of the grantor's authority and cannot be passed onward. It does not
include unrelated activities, Other schedule entries, catalogue editing, or general gathering editing.
Native award staff can prepare the same eligible activities when their group's bestowals,
eligible scheduled business, or their own active delegation establishes context for that event.
See [Event award delegation](awards-bestowal-todo-security.md#event-award-delegation).

- With no configured permissions, gathering editors and stewards manage the activity.
- With configured permissions, a matching grant is required, including for gathering editors.
- A matching grant provides access to the gathering details needed to attach, describe,
  remove, and schedule that activity. It does not grant general gathering editing,
  unrelated activity access, generic **Other** scheduling, or permission to edit the catalogue.
- Activity-only access shows Activities and Schedule. Private Staff and Record details
  require independent native gathering view/edit or steward access; public contacts remain in Overview.
- Schedule edits check both the persisted activity and the requested replacement activity.
  Typed schedule entries must reference an activity attached to that gathering.
- Actor-driven activity and schedule services repeat authorization in the write transaction,
  holding any active delegation grant until commit so revocation cannot race a stale form.
- Template assignments, gathering type/branch changes, and cloning check the same rules.
  Remove scheduled entries before removing the activity. Existing non-removable template
  and waiver constraints continue to apply.
- Superusers retain the shared policy framework's administrative override.

The Awards migration copies existing court delegation into these rules and removes the
old gathering-policy grants. Court types use the same activity controls and policy as
other types. Awards catalogue eligibility still activates award presentations and court
agenda features; configuring assignment permission alone does not enable those features
or grant access to protected bestowal/agenda content. Deleting a scheduled court continues
to release court assignments through the existing Awards workflow.

## Award-giving agendas

Every scheduled occurrence of an activity linked to an award in the Awards catalogue
gets its own court agenda section, even before any bestowals are assigned. This applies
to kingdom and baronial courts and to activities with other names. An eligible activity
without a scheduled occurrence does not create a section. Both the builder and print
view discover newly scheduled sessions; printed session details and builder labels use
the current schedule and the gathering's timezone. Existing section names, notes, and
script items are retained when the schedule changes.

The builder keeps one court in focus with a compact court selector and numbered script
rows. Choose a court, then **Show court** to load it. Use the adjacent arrow buttons
or drag a row to reorder; saved moves retain keyboard focus and update the row numbers.
**Details** reveals timing hints, metadata, court moves, and removal actions. **Edit**
opens the notes and print-options dialog. Adding eligible bestowals and interjections
uses the tools beside the script on desktop and the **Add to court** drawer on smaller
screens. Court scheduling help is available as a disclosure above the script. These
controls preserve the existing agenda and bestowal permissions; ordering never completes
a bestowal.

Both printer-ready agenda versions (including browser PDF exports) order court
sections by their scheduled start time, earliest
first across the entire event. Simultaneous sessions retain their existing order;
unscheduled and roaming sections follow scheduled sessions in their existing order.
This changes only export presentation, preserving saved section and script-item order.
The printouts show the event date range, each scheduled court's local date as
`MM/DD/YYYY`, and its current scheduled start
and end times together in each court heading, with timezone abbreviations and no
repeated schedule subtitle. Sessions spanning local dates show both dates;
sessions without an end time show only the start. The Award /
Action column shows the included bestowal specialty as **Class**. Generated recipient,
award, specialty, and call summaries are removed from printed herald notes; authored
instructions are retained without changing stored notes. The bestowal's current
**Call into court** value appears once per row. Empty and `Not Set` notes are omitted,
and reasons already present in Noble notes appear only once. Print formatting does
not alter stored notes or field permissions. Individual minutes, timing hints,
lifecycle state, and blank Hand notes areas are omitted. Each row has an **NP**
(Not Present) checkbox for use during court; checks are local to the printed page
or browser view and do not update bestowal attendance or status.

The builder offers **Printer Ready — Full** first and **Printer Ready — Heralds**
second. Full retains Court Notes and runtime estimates. Heralds includes Order,
Recipient / Block, Award / Action (including Class), Notes, and NP columns. Its Notes
column contains one **Call into court** field and the herald instructions labeled
**Notes**. Projected runtime, runtime warnings, and minutes in court headings are
omitted from Heralds. Both retain event dates and scheduled court time slots and
use the same agenda and bestowal permissions. The existing print URL defaults to
Full; `?version=heralds` selects the slimmer version. Herald notes use the same cleaned,
escaped text and field permissions in both versions. Crown notes, recommendation
reasons, counts, and original-award summaries remain exclusive to Full Court Notes.

After existing notes and reasons, Full Court Notes show the linked recommendation
count, including the group head and all children, when the viewer can access
recommendation details. If any recommended
award differs from the bestowal's current award, Court Notes also list each original
award once under **Original recommendations were for** (singular for one submission).
The Award / Action column continues to show the current bestowal award. These notes
are omitted for ad-hoc bestowals with no recommendations, security-blocked items,
and viewers without access to Crown fields. The Heralds layout omits Court Notes.

The agenda identifies award-giving activities that are attached under **Activities** but
have no scheduled occurrence. Staff who can view the gathering get a direct **Schedule**
link; others are directed to the gathering organizer. With no eligible section, the agenda
also explains how to add an activity and check its award-catalogue eligibility. Creating an
agenda shell before scheduling remains supported; roaming presentations remain available.

New sections require award eligibility and cannot duplicate a scheduled session's
existing section. Award placement and move choices still require that specific award
to be linked to the session's activity type; eligibility for another award is insufficient.
Existing bestowal assignments continue to expose legacy sessions for historical continuity.

Recipients without accounts retain the name copied from the approved recommendation
into the bestowal. Bestowal screens, reports/exports, agenda choices and script items,
printed agendas, and generated herald/notification text use the same name fallback:
a nonblank linked member name, then the name stored on the bestowal. An account is not
required to display a recipient's name, and names remain escaped in HTML and accessible labels.

Agenda authorization checks the gathering's branch even before the agenda is persisted.
Staff with scoped agenda permissions may also prepare a gathering hosted elsewhere when
an assigned bestowal or a scheduled activity supports an award in their permission scope.
An unscheduled activity or an award outside that scope does not grant this access.

## Activity types

**Activity Type** is a required dropdown in both add/edit forms and the shared modal,
and a sortable, searchable, filterable column in the activity grid. Its fixed choices are
**Order Circle**, **Royal Progress**, **Court**, **Business**, **Martial**,
**Arts & Sciences**, **Youth**, **General**, and **Other**. New activities default to
**General**. The model rejects unlisted or empty values.

The activity-type migration converts existing order-circle flags to **Order Circle** and
all other activities to **General** before removing the flag. The order-circles filter uses
the selected type, independently of the activity name. Public calendar rows use the configured
activity icon once, including for circles and royal progress. **Business** activities remain
available internally and are omitted from public calendar activity lists and filter choices.
Manage Royal Progress by attaching activities of type **Royal Progress** to gatherings.
RSVPs and office assignments do not determine Royal Progress.

## Activity display order

Each gathering activity has a **Display order** field on its add, edit and detail pages and in
the activity grid. It is a nonnegative integer (default **100**). Activity lists show
**Royal Progress**, then **Order Circle**, then all remaining activities. Within each of
these groups, lower display orders appear first; ties sort alphabetically, then by ID.
This applies to calendars, event and template details, award associations, selectors, and
the default activity grid. Public calendar filters retain their Activity Type sections.

`GatheringActivityOrderingService` owns the shared comparator and SQL order. The
`forDisplay` finder orders catalog queries; `OrderedGatheringActivitiesTrait` orders loaded
associations and serialized output. Partial activity selections must include `activity_type`
and `display_order`. Persisted assignment order and chronological schedules remain intact.

The public summary displays four activity icons before **…**; expanding the row shows all
public activities. **Business** activities and their scheduled sessions are hidden from the public
calendar and public event pages; administrative views retain them. See
[Public kingdom calendar](kingdom-calendar.md) for filtering and continuation rows.

## Appearance

The Gathering Activities table includes an **Icon** column showing the configured catalog
icon or uploaded artwork using the shared compact renderer, colors and accessible name.

Editors choose an icon from the unified KMP catalog or upload a PNG/WebP no larger than 128 × 128 pixels
and 512 KB. The server checks decoded dimensions and format, then re-encodes PNG with
alpha transparency and without uploaded metadata. Compact calendars tint the alpha mask
with the foreground color. Larger details show the original colors. A transparent
background produces the clearest compact icon; an opaque image produces an opaque mask.

Both color pickers require at least 4.5:1 contrast for readable activity labels. Names
remain visible in detailed views and accessible labels identify compact icons. The
searchable picker uses native buttons with selected state and a bounded initial result
set. Catalog metadata loads when Browse or Search is used. See [KMP icons](icons.md)
for the shared rendering contract, source selection, compatibility and update workflow.

## Public artwork storage

`PublicAssetService` publishes explicitly public, normalized artwork independently of
private Documents. Keys are `images/<tenant hash>/<content SHA-256>.png`. Identical
content is deduplicated within a tenant, while replacement creates a new URL. Assets
remain anonymously readable after removal/replacement so cached pages keep working.
Do not publish private documents or personal images through this service.

`PublicAssets` configuration supports:

| Setting | Purpose |
| --- | --- |
| `PUBLIC_ASSETS_ADAPTER=local` | Write under `webroot/public-assets`; Apache serves files directly |
| `PUBLIC_ASSETS_ADAPTER=azure` | Write to a separately provisioned public Azure container using existing Azure storage credentials |
| `PUBLIC_ASSETS_BASE_URL` | Absolute public object-store/CDN origin and optional prefix; required for Azure, optional locally |
| `PUBLIC_ASSETS_AZURE_CONTAINER` | Dedicated public container, default `public-assets` |

For local hosting, persist and share `webroot/public-assets` across app replicas and
deployments, or configure Azure/CDN storage. The directory is excluded from Git. Local
URLs default to an absolute `/public-assets/` URL on the request host. A configured base
URL must map directly to the storage root; PHP does not proxy these requests.

Provision public container access, runtime write permission, and CDN routing outside
request handling. Azure runtime uploads never create containers or change public access.
Allow cross-origin GET/HEAD from app origins (or `*` for this intentionally public store)
so CSS masks work from a CDN. Serve `Content-Type: image/png` and
`Cache-Control: public, max-age=31536000, immutable`. Apache sets the same cache policy,
CORS, and `nosniff` for local hashed artwork. Keep these headers when serving through
another web server. Never apply immutable caching to missing/error responses.

Immutable orphan files are intentionally retained; any future cleanup must account for
cached pages and database/tenant restores. Storage failures reject the appearance save
and show an upload error. Private document authorization and storage are unaffected.

## Development catalogue seeding

`DevLoadGatheringActivitiesSeed` and rolling calendar examples share
`GatheringActivitySeedService`. Seed normal activity names and reuse their IDs; do not
create separate demo/test activity catalogues. Fill missing types and create absent entries
with catalogue defaults while preserving display order and uploaded artwork. Running the
normal development seed explicitly reapplies catalogue icon and color defaults: Laurel Circle uses
`laurel-wreath`, Pelican Circle uses `pelican-vulning`, and Chivalric Circle uses
`chain-necklace`. Additional **Order Circle** entries and icons are:

| Activity | Icon |
| --- | --- |
| Defense Circle | `master-of-defense` |
| Order of the Mark Circle | `order-of-the-mark` |
| Golden Lance Circle | `lance` |
| Iris of Merit Circle | `rainbow` |
| Star of Merit Circle | `ansteorran-star` |
| Blades of Merit Circle | `single-rapier` |
| Centurion Circle | `phoenix` |
| Arc d'Or Circle | `archery` |

Existing normal naming variants are reused, including Blade/Blades of Merit and Arc De Or.
The rolling Crown Court & Feast example attaches these circles for public calendar checks.
Courts, Bardic, Equestrian, Thrown Weapons, and other seeded activities
use their corresponding catalogue subjects. Automatic calendar-date refreshes preserve
subsequent icon and color edits. Use `bin/cake seed_public_calendar --refresh-activity-icons`
for updated icons or `bin/cake seed_public_calendar --refresh-activity-colors` for updated colors
in the local tenant. The flags are independent and preserve uploaded artwork and its colors.

Seed palettes use dark foregrounds on pale backgrounds with at least 4.5:1 contrast. Royal
Visit, Kingdom Court, Chivalric Circle, and Golden Lance use gold; Laurel and archery use green;
Pelican and Centurion use crimson; Defense and Blades of Merit use blue; Iris uses purple;
Star of Merit uses black on gold. Other activities use related gold, green, crimson, blue,
purple, teal, amber, or slate palettes. These are editable presentation defaults. Icon shapes
and activity names continue to identify activities independently of color.
Known obsolete prefixed fixtures are consolidated with their references preserved. See
[Rolling development demo events](kingdom-calendar.md#rolling-development-demo-events).

## Verification

Run the gathering/activity controller tests, activity assignment policy test,
presentation/public asset service tests, and appearance/mobile-calendar JavaScript
tests. Browser checks should cover keyboard icon selection, contrast validation,
upload/replace/remove, assignment rule changes, and anonymous direct asset access with
cache/CORS headers. Run `node tests/ui/support/gathering-activity-modal-browser-check.cjs`
against the local Docker stack for modal add/edit, validation, grid state, and mobile/keyboard checks.
For court builder layout changes, run
`node tests/ui/support/court-agenda-browser-check.cjs` against the local Docker stack
to check saved ordering, notes, court selection, mobile additions, and keyboard focus.
Build Vite assets after changing icon catalogue imports or styles.
