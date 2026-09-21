# Repairing migrated activity authorization workflows

The repair body is `plugins/Activities/config/repairs/migrated-authorizations.sql`.
It runs directly in a PostgreSQL console against either KMP 1.5.9 or 1.5.10; no
application deployment, schema migration, or new CLI command is required.
Production-specific manifests and generated SQL bundles are operator artifacts,
not repository data.

## Scope and evidence

The reviewed operation contains exactly 46 workflow/authorization pairs: 44 waiting
approvals whose legacy action context needs correction and two completed workflows
with an approved gate but failed activation. The two completed records each require
one original approval response. The manifest pins workflow, authorization, approval,
response, member, activity, and approver IDs, the response timestamp, the activity
term in months, and any predecessor's original dates. Never build this manifest
from a broad update selecting every completed workflow.

For the 44 waiting workflows, only the legacy compatibility field
`context.nodes["validate-request"].result.authorizationId` and additive repair
metadata change. Existing context, approval counts, responses, active nodes,
workflow versions, and workflow state remain intact. That field is read by the
existing 1.5.9 approval and denial actions.

For the two completed workflows, authorization status becomes Approved and
`approval_count` comes from the approved gate. The effective start is the original
approval response timestamp, truncated to seconds to match ActiveWindow's stored
precision; expiration adds the reviewed activity term in months. Approval identity,
comments, and timestamp remain in the original response. The authorization table
has no `approved_by` column: the audit entry links the original response and
approver rather than inventing an actor field or replacing an approver with the
operator.

An explicitly reviewed active renewal predecessor becomes Replaced, ending one
second before the historical approval time, with the original final approver as
`revoker_id`, matching ActiveWindow replacement semantics. Unrelated historical
records remain unchanged. This targeted operation only supports activities with
no granted role and authorizations without existing role effects. It refuses
changed roles, terms, additional overlapping records, or ambiguous approval data;
those cases need a new reviewed repair, not weaker guards.

## Run from a SQL console

1. Use the target tenant's database and a recoverable database backup. Verify no
   restore is active in the platform operations screen; the restore lock is held
   in shared application cache and cannot be checked from SQL alone. Do not run
   the repair during a restore or tenant migration.
2. Open the generated **preview.sql** and execute the entire file on one connection.
   It starts `BEGIN READ ONLY`, sets the exact manifest and timeouts, executes the
   repair body with apply disabled, and commits the read-only transaction. Expect
   `waiting=44, approved=2, already_repaired=0` and review the two original approver
   IDs, dates, and predecessor effects. The preview performs no writes.
3. Execute the separately generated **apply.sql** only after reviewing that output.
   It uses the same manifest, one transaction, short lock and statement timeouts,
   and apply enabled. Approval, workflow, response, activity, and authorization
   rows are locked; the full manifest is validated before mutations. Unexpected
   state or any write failure aborts all changes. If the console leaves a failed
   transaction open, execute `ROLLBACK` before continuing. Never run selected
   statements independently or remove the transaction wrapper.
4. Check the two authorizations' status and dates, the predecessor's replacement
   date, and the 44 legacy context IDs. Approval responses and original failed
   execution logs must still exist. No notification or workflow replay is sent.
5. Repeat the preview. Before any intervening workflow activity, it reports
   `waiting=0, approved=0, already_repaired=46`. Reapplying skips marked records and
   does not duplicate activations or audit rows.

The required wrapper is:

```sql
BEGIN READ ONLY; -- Use BEGIN for the separately reviewed apply file.
SET LOCAL lock_timeout = '5s';
SET LOCAL statement_timeout = '30s';
SET LOCAL timezone = 'UTC';
SET LOCAL search_path = public;
SELECT set_config('kmp.authorization_repair_manifest', '<reviewed JSON manifest>', true);
SET LOCAL kmp.authorization_repair_apply = 'off'; -- 'on' only in the apply file.
-- Include the complete migrated-authorizations.sql body here.
COMMIT;
```

The manifest structure is `{"database":"target_database","workflows":[...]}`.
Each entry has `workflow_id` and `authorization_id`; exactly two additionally have
an `approval` object containing `approval_id`, `response_id`, `member_id`,
`activity_id`, `approver_id`, `responded_at`, and `term_months`. A renewal approval
also has `predecessor` with `id`, `start_on`, and `expires_on`. These are reviewed
expected values, not values to overwrite into approval responses.

## Audit and recovery

Each repaired workflow receives one `authorization-id-repair-2026-09` execution
log with pre-repair context and authorization data, approval provenance, and any
predecessor snapshot. Its context also receives a repair marker with database
operator identity and timestamp. The original activation failure and notification
history remain unchanged; the new log explains the later correction.

The operation commits as a unit. A post-commit reversal requires a separate
review against current state and the stored before-images, especially if any of
the 44 approvals have progressed. Do not blindly restore old context or remove
legitimate approvals made after repair.

## Verification

`vendor/bin/phpunit plugins/Activities/tests/TestCase/Services/MigratedAuthorizationRepairTest.php`
exercises the SQL against temporary copies of real PostgreSQL table schemas. It
covers preview, waiting-first manifests, activation dates and counts, predecessor
closure, preserved responses, repeat execution, wrong database, changed evidence,
changed workflow state, role changes, and rollback after an audit write failure.
