# Awards plugin guide

## Purpose

Own award domains, levels, recommendations, recommendation feedback approvals, bestowals, state logs, award workflow actions/conditions, and award-related notification variables.

## Ownership

- Parent plugin contracts live in `app/plugins/AGENTS.md`.
- `AwardsPlugin.php` registers navigation, view cells, approval context rendering, recommendation feedback listeners, console commands, settings, services, and workflow actions/conditions.
- Plugin docs are under `docs/5.2*awards*` and app-local awards redesign/workflow notes.

## Local Contracts

- Plugin path is `/awards` and supports `json`, `pdf`, and `csv` extensions.
- Migration order is `3` in `app/config/plugins.php`; it loads after Officers.
- Recommendation and bestowal state transitions belong in the dedicated transition/update/state-log services.
- Recommendation feedback approval context uses the registered `AwardsFeedback` renderer.
- Recommendation approval synchronization backfills open approval-owned records missing an active run, maps in-flight
  progress by stable approval-step key, preserves recorded responses, refreshes the pending gate and current workflow
  version, and immediately resumes a gate when its updated threshold is already met and the current approver pool is
  nonempty. Raising a threshold reopens an approved-but-not-yet-resumed gate when more responses are now required. It
  recovers an already-resolved current gate when workflow resumption was interrupted, including the original rejection
  responder, comment, and final-step gathering selection. The gathering selection is persisted atomically with the
  approval response so recovery does not depend on transient request data. Synchronization must skip closed or
  otherwise ineligible recommendations and must not revive terminal workflow instances. A resumed final gate may
  continue through the idempotent handoff and create exactly one bestowal; synchronization must never mark it Given.
  It must not rewind a current stable step or reclassify unsafe legacy records.
- Bestowal To-Do synchronization maps template items by stable `item_key`/ActionItem `source_ref`; matching history is
  preserved, removed items are audit-cancelled, and only synchronization-cancelled items may reopen automatically.
  An assigned empty template is an authoritative zero-item process, and an explicit Required field `None` overrides
  legacy key-based defaults. Materialization, synchronization, ActionItem transitions, and finalization serialize on
  the persisted bestowal before locking its ActionItems; cancellation uses the same mutex, and finalization rechecks
  gating while holding it. Given/cancelled bestowals reject queued ActionItem mutations. Each ActionItem reconciliation
  is atomic, and initial materialization/backfill only targets open bestowals.
  Required-field reconciliation must use bounded passes until stable so prerequisite chains converge independent of
  template sort order. Completion events are deferred until the whole batch is stable, and definition synchronization
  must not implicitly finalize a bestowal.
- Bulk synchronization reports may expose bounded record IDs and trusted domain skip reasons, but unexpected exception
  details belong in server logs; user-facing failures use fixed categories.
- State/status rules and plugin settings are stored in `Awards.*`, `Member.AdditionalInfo.*`, and `Plugin.Awards.*` settings.
- The `awards migrate_award_recommendations` command is registered by the plugin.

## Work Guidance

1. Do not bypass recommendation or bestowal transition services for state changes.
2. Keep feedback approvals wired through the listener, approval resolver, and context renderer.
3. Preserve CSV/PDF/JSON response expectations for award grids and exports.
4. Update docs when state machines, approval rules, or recommendation/bestowal workflows change.

## Verification

- Plugin tests: `vendor/bin/phpunit plugins/Awards/tests/TestCase`
- All plugin tests: `vendor/bin/phpunit --testsuite plugins`
- Changed PHP files: `vendor/bin/phpcs plugins/Awards/src`
- UI workflow changes: targeted awards Playwright scenarios under `tests/ui/bdd/@awards`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
