# Gathering activities

`GatheringActivity` is the shared catalogue for gathering activities. It is separate
from the Activities plugin's member authorization types. The catalogue owns appearance,
waiver associations, and assignment permissions. Gathering links own descriptions and
ordering; scheduled entries own times, titles, and registration details.

## Assignment and scheduling

The activity's **Can be assigned by** tab follows the Role **Permissions** pattern:
assigned permissions appear in a table with scope and requirements; **Add Permission**
opens the shared searchable lookup modal and each row has a Remove action. Already
assigned permissions are excluded from the lookup. Removing the last permission restores
gathering-editor/steward access. Each action changes only that one rule.

Any one current grant covering the gathering's branch is sufficient. Existing role dates, membership,
warrant requirements, and Global / Branch Only / Branch and Children scopes still apply.

- With no configured permissions, gathering editors and stewards manage the activity.
- With configured permissions, a matching grant is required, including for gathering editors.
- A matching grant provides access to the gathering details needed to attach, describe,
  remove, and schedule that activity. It does not grant general gathering editing,
  unrelated activity access, generic **Other** scheduling, or permission to edit the catalogue.
- Schedule edits check both the persisted activity and the requested replacement activity.
  Typed schedule entries must reference an activity attached to that gathering.
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

## Appearance

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

## Verification

Run the gathering/activity controller tests, activity assignment policy test,
presentation/public asset service tests, and appearance/mobile-calendar JavaScript
tests. Browser checks should cover keyboard icon selection, contrast validation,
upload/replace/remove, assignment rule changes, and anonymous direct asset access with
cache/CORS headers. Build Vite assets after changing icon catalogue imports or styles.
