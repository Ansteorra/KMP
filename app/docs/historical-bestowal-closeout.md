# Historical Ansteorra Bestowal Closeout

## Purpose

`awards close_historical_bestowals` is a one-time, manifest-scoped remediation
for Ansteorra recommendations that were historically bestowed but remain open
in KMP. It uses the application closeout path so the Given action item,
bestowal, linked recommendation, and audit records remain consistent.

The command does not create or change court assignments, agenda entries,
gatherings, scrolls, regalia, optional checks, workflow executions, or
notifications.

Do not replace this operation with direct SQL or a migration. The command is
manually invoked, defaults to a dry run, validates a production fingerprint for
every listed record, and requires explicit apply confirmation.

## Canonical manifest

The version-controlled manifest is:

```text
plugins/Awards/config/remediations/2026-08-ansteorra-historical-given.json
```

Its source workbook SHA-256 is:

```text
f676bc5b3d1207697573bd4a4f441df47b0bfa13a7143ad957dc47233fcc11df
```

The canonical manifest SHA-256 is:

```text
7ae9fa60c9ea59dbc3ea24575ed9cd43cd93a3fb6264595f28883c82b6e4bc4b
```

Verify the digest from `app/` before every dry run and apply:

```bash
sha256sum plugins/Awards/config/remediations/2026-08-ansteorra-historical-given.json
```

The manifest accounts for every source row:

| Disposition | Count | Command behavior |
| --- | ---: | --- |
| `apply` | 249 | Validate and close through the application workflow |
| `hold` | 6 | Validate only; never mutate |
| `already_given` | 17 | Validate the existing completed state; never mutate |
| `separate_repair` | 1 | Validate only; the record has no bestowal |
| Total | 273 | Must match before any write is allowed |

The six hold recommendation IDs are `459`, `1136`, `1216`, `1792`, `1869`,
and `2298`. Recommendation `1` is the separate repair. These records are
controls and are intentionally outside this remediation.

For apply records, `historicalGivenDate` comes from the workbook's corrections
and comments when that field contains a date; otherwise it comes from the OP
award date. A corrections date overrides a stale OP match. In particular,
recommendations `2198`, `2164`, `2163`, `2291`, and `2284` use `2026-04-11`.
Non-apply records have no remediation date because the command cannot change
them.

Each `expected` object is a read-only production fingerprint. Names are not
stored; their exact UTF-8 database values are represented by SHA-256 hashes.
Any mismatch in identity, award, gathering, linkage, lifecycle, Given action
item, or completion configuration is drift and prevents apply.

## Release requirements

Run the command only after this change has passed the normal pull-request,
POC, and production promotion controls. Use the exact image that completed
those controls. The runtime database connection must already target the
Ansteorra production tenant; `--tenant ansteorra` is a safety assertion, not a
database router.

Before the production dry run:

1. Create and verify a fresh recoverable production backup.
2. Select an active or membership-verified adult production member ID to be
   recorded as the audit actor.
3. Select a durable release, ticket, or change-management reference.
4. Record the release artifact, backup reference, actor, and change reference
   in the controlled change record.

Do not put credentials, connection strings, or other secrets in command output
or the change record.

## Dry run

Run from `app/`. Dry run is the default and makes no database changes:

```bash
bin/cake awards close_historical_bestowals \
  --tenant ansteorra \
  --manifest plugins/Awards/config/remediations/2026-08-ansteorra-historical-given.json \
  --expect-manifest-sha256 7ae9fa60c9ea59dbc3ea24575ed9cd43cd93a3fb6264595f28883c82b6e4bc4b \
  --expected-apply-count 249 \
  --actor-id MEMBER_ID \
  --change-reference CHANGE_REFERENCE
```

The initial production dry run must exit successfully and report:

```text
total: 273
apply: 249
hold: 6
alreadyGiven: 17
separateRepair: 1
actionable: 249
alreadyApplied: 0
changed: 0
drift: 0
```

Stop if the digest, counts, actor, tenant, any expected fingerprint, or any
record state differs. Investigate and review a manifest change through the
same controlled release process; do not weaken a guard at the console.

Repeat the dry run after the backup is confirmed and immediately before apply.

## Apply

Apply uses the same arguments plus both write guards:

```bash
bin/cake awards close_historical_bestowals \
  --tenant ansteorra \
  --manifest plugins/Awards/config/remediations/2026-08-ansteorra-historical-given.json \
  --expect-manifest-sha256 7ae9fa60c9ea59dbc3ea24575ed9cd43cd93a3fb6264595f28883c82b6e4bc4b \
  --expected-apply-count 249 \
  --actor-id MEMBER_ID \
  --change-reference CHANGE_REFERENCE \
  --apply \
  --confirm ansteorra-historical-given-249
```

The service locks and revalidates the records before writing. It completes the
open Given action item under the selected actor, preserves the manifest's
historical date, finalizes the bestowal, synchronizes the recommendation to
`Closed / Given`, and writes the corresponding audit records. The operation is
atomic: drift or a failed write rolls back the whole apply set.

Capture the complete command output. A successful process exit is required;
do not infer success from a partial summary.

## Post-apply verification

Run the same dry-run command again. A clean idempotence check must report:

```text
total: 273
apply: 249
hold: 6
alreadyGiven: 17
separateRepair: 1
actionable: 0
alreadyApplied: 249
changed: 0
drift: 0
```

Also retain read-only reconciliation evidence showing that:

- all 249 apply bestowals are `given` with their manifest dates;
- their linked recommendations are `Closed / Given` with the same dates;
- their Given action items are completed by the selected actor;
- action-item and recommendation-state audit records exist;
- the 17 already-given, six hold, and one separate-repair controls did not
  change; and
- no court, agenda, gathering, scroll, regalia, optional-check, workflow, or
  notification records were created by the remediation.

Archive the manifest digest, release artifact, command output, backup
reference, actor, change reference, and reconciliation report together.
