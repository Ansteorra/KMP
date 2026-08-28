# Waivers plugin

Waivers is an active first-party CakePHP plugin for defining gathering waiver
requirements, collecting signed documents or exemptions, reviewing compliance,
and closing a gathering's waiver collection. It is application code, not a
copyable plugin template.

The published feature overview is
[Waivers plugin](../../../docs/5.7-waivers-plugin.md). This README owns the
implementation contracts developers and operators need when changing the
plugin.

## Tenant boundary

Waivers runs inside the tenant selected by the application's tenant middleware.
Its catalog, requirements, waiver records, closure records, settings, audit
fields, and associated `documents` rows therefore live in the current tenant
database. The tables intentionally do not carry a second `tenant_id` column.

The plugin is enabled in [`app/config/plugins.php`](../../config/plugins.php)
with migration order 4. Provisioning and release migrations must run the core
and plugin histories for every applicable tenant:

```bash
bin/cake tenant migrate --all --include-suspended --fail-fast
```

Do not migrate only the default database in a managed multi-tenant deployment.
Do not retain a table instance, entity, document response, or generated URL
across tenant-context changes.

Managed Azure document storage is tenant-aware. `DocumentService` resolves an
explicit `tenant_config.documents.blob_container`/`container` and optional
`blob_prefix`/`prefix` when present. Otherwise it derives the container from
`Documents.storage.azure.containerPrefix` plus the tenant slug. Document
metadata remains in the same tenant database as the waiver. Local and S3
adapters use their configured storage scopes; a deployment using either must
provide equivalent tenant isolation.

## Domain model

| Model/table | Responsibility |
| --- | --- |
| `WaiverTypes` / `waivers_waiver_types` | Tenant-local catalog: name, description, active flag, template reference, retention policy, and allowed exemption reasons. Names are case-insensitively unique on PostgreSQL. |
| `GatheringActivityWaivers` / `waivers_gathering_activity_waivers` | Soft-deletable requirement linking a gathering activity to a waiver type. This is global catalog data within one tenant and requires unscoped authorization to mutate. |
| `GatheringWaivers` / `waivers_gathering_waivers` | One gathering-level uploaded document or exemption. A record is not linked to an individual activity or member. |
| `GatheringWaiverClosures` / `waivers_gathering_waiver_closures` | One gathering's ready-to-close or closed state and the members who performed those actions. |
| Core `Documents` | Storage metadata, SHA-256 checksum, adapter, object path, uploader, and conversion metadata for uploaded PDFs and previews. |

Required waiver types are the distinct requirements of all activities selected
for a gathering. A valid upload or exemption satisfies the type at the
gathering level. Declined and soft-deleted records do not satisfy compliance.

## Runtime flow

1. An authorized catalog manager creates an active waiver type and assigns a
   retention policy and optional exemption reasons.
2. An authorized catalog manager links that type to one or more gathering
   activities through `GatheringActivityService`.
3. A branch-authorized user or a steward for the gathering submits one or more
   images/PDFs. Cancelled gatherings are rejected. Closed collections reject
   ordinary uploaders; a user with `closeWaivers` permission may continue
   managing the collection.
4. `WaiverFileService` converts or merges the submitted pages into one PDF,
   creates the waiver row, stores the document through `DocumentService`, then
   links the two. Failures compensate by removing newly created records/files.
5. If a waiver type defines exemption reasons, the user can record one
   gathering-level attestation instead of a document. The reason must be one of
   that type's configured values.
6. Gathering editors/stewards can mark a collection ready to close. The
   controller and state service define close, reopen, and time-bounded decline
   paths; current persistence gaps are listed below.

Closing is workflow-driven: the controller dispatches
`Waivers.CollectionClosed` and the configured workflow is responsible for the
state transition. Mark-ready, reopen, and decline mutate through
`WaiverStateService` and then dispatch their corresponding events. Preserve
this distinction when changing controllers or workflow definitions.

## Upload and retrieval contracts

Signed-waiver submissions accept mixed raster images and PDFs and produce one
stored PDF under the `waivers` document subdirectory:

- Images are decoded server-side with GD. JPEG, PNG, GIF, BMP, WBMP, and WEBP
  are supported when the PHP build supports them. Image content is inspected
  with `getimagesize()`, limited to 20 megapixels, and downscaled to a
  2,000-pixel long edge before PDF encoding.
- PDFs and converted images are processed in upload order. Unsupported PDF
  compression may cause individual PDFs to be skipped; the result returns a
  warning when at least one page was stored.
- The upload UI reports the smaller of PHP's `upload_max_filesize` and
  `post_max_size`. `DocumentService` independently enforces
  `Documents.maxFileSize` on the final stored PDF (50 MiB by default).
- Stored filenames are generated rather than derived from user paths.
  `DocumentService` sanitizes relative object paths, records a SHA-256 checksum,
  and stores the adapter used so later reads use the same backend.
- A first-page preview is stored beside the PDF when conversion can produce
  one. Preview failure does not invalidate an otherwise successful upload.

Controllers must authorize the waiver entity before download, inline display,
preview, deletion, or mutation. They then delegate storage access to
`WaiverFileService`/`DocumentService`. Do not expose storage object URLs or
build filesystem paths in controllers or templates. The local adapter applies
real-path containment checks; remote adapters read through Flysystem.

### Current security limitations

Keep these limits explicit when assessing or changing the upload surface:

- File extension and client-reported MIME select the mixed-upload path.
  Images receive content decoding, but a single PDF can reach the merge path
  without `PdfProcessingService::validatePdf()` being called first.
- Waiver-type template uploads call `DocumentService` directly and currently
  enforce the `.pdf` extension and size limit, not server-verified PDF content.
- There is no antivirus or content-disarm scan in this plugin.
- External template URLs are stored without a host allowlist. Only curated,
  trusted URLs should be configured.

Do not describe client MIME values, the PDF extension, or checksum recording as
malware validation. If the upload boundary is broadened, add server-side content
verification and tests before updating this contract.

## Retention and deletion

A waiver type stores JSON with one of these anchors:

```json
{"anchor":"gathering_end_date","duration":{"years":7,"months":0,"days":0}}
```

`upload_date` is also supported. The calculated `retention_date` is copied onto
each upload or exemption and does not change when the waiver type is edited.
That snapshot is the record's disposal eligibility date, not proof that
disposal occurred.

The plugin currently has no scheduled purge that marks or deletes records when
`retention_date` passes. `Waivers.IsPastRetentionDate` is available as a
workflow condition, but the condition performs no mutation. An approved
implementation and workflow would need to provide any retention action.

The controller's delete path is restricted to records whose persisted
`status` is `expired` and deletes the document before soft-deleting the waiver.
Changing these rules requires a retention, audit, restore, and legal review.

### Current lifecycle gaps

Do not rely on these paths until their persistence contracts are aligned and
covered by regression tests:

- `permanent` validates on a waiver type and calculates a null retention date,
  while `GatheringWaivers.retention_date` is required.
- `GatheringWaiversTable` validation accepts `pending`, `active`, and
  `deleted`, while the delete controller requires `expired` and no scheduled
  transition currently persists that value.
- `WaiverStateService::decline()` assigns `status = declined`, which is not
  in the table validator's accepted status list.
- Uploaded waiver-type templates are written through `DocumentService`, but
  the `WaiverTypesTable` `Documents` association is currently disabled; the
  uploaded-template download path should not be treated as operational.

## Authorization and visibility

- Normal entity/table permissions use the project's `BasePolicy` contracts.
- `GatheringWaiver::getBranchId()` derives scope from the gathering's hosting
  branch.
- Gathering stewards can view and upload for gatherings they steward even
  without a branch grant, but closure checks still apply.
- Waiver-type requirements are catalog-wide within a tenant. Adding/removing
  them requires a global (unscoped) permission rather than a branch-scoped one.
- Dashboard, search, calendar, grid, download, preview, inline-PDF, decline,
  type/activity correction, and closure actions all pass through authorization.
- Dashboard services receive the caller's permitted branch IDs. Keep those
  filters on every query and aggregation.

The plugin contributes navigation entries for uploaded waivers, the dashboard,
waiver types, and gatherings needing waivers; gathering/activity tabs through
`ViewCellRegistry`; and a mobile “Submit Waiver” item. Register additions
through the existing providers rather than core templates.

## Settings

| Key | Current use |
| --- | --- |
| `Plugin.Waivers.Active` | Enables navigation/view-cell providers through `StaticHelpers::pluginEnabled('Waivers')`. Default: `yes`. |
| `Waivers.ComplianceDays` | Number of days after a gathering before dashboard compliance is considered late. Default: `2`. |
| `Waivers.configVersion` | Internal settings-initialization version. |

`Plugin.Waivers.ShowInNavigation` and
`Plugin.Waivers.HelloWorldMessage` are initialized legacy keys with no Waivers
runtime reader. Do not build new behavior on them without first defining and
testing the contract.

## Extension points

- State changes: [`WaiverStateService.php`](src/Services/WaiverStateService.php)
- Upload/storage orchestration:
  [`WaiverFileService.php`](src/Services/WaiverFileService.php)
- Dashboard queries:
  [`WaiverDashboardService.php`](src/Services/WaiverDashboardService.php)
- Mobile gathering selection and exemptions:
  [`WaiverMobileService.php`](src/Services/WaiverMobileService.php)
- Navigation and cells:
  [`WaiversNavigationProvider.php`](src/Services/WaiversNavigationProvider.php)
  and [`WaiversViewCellProvider.php`](src/Services/WaiversViewCellProvider.php)
- Workflow triggers/actions/conditions:
  [`WaiversWorkflowProvider.php`](src/Services/WaiversWorkflowProvider.php)

Keep business rules in these services, authorization in policies, storage in
`DocumentService`, and rendering in templates.

## Verification

Run commands from `app/`:

```bash
vendor/bin/phpunit plugins/Waivers/tests/TestCase
vendor/bin/phpcs plugins/Waivers/src
```

Run `npm run dev` when plugin JavaScript, CSS, or Vite wiring changes. Use the
broader plugin or app verification lane for cross-plugin, workflow, document
storage, tenancy, or migration changes.

For documentation-only changes, verify Markdown links/fences and inspect the
scoped diff.
