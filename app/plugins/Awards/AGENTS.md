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
- Recommendation approval synchronization is scoped to one approval process from its detail page. The action is enabled
  only while an eligible open recommendation assigned to that process uses an older process snapshot or published
  workflow version; unrelated and already-current processes are not considered. For each outdated recommendation, all
  active runs, workflow instances, and pending gates are audit-cancelled with `approval_process_restarted`, then exactly
  one new existing-recommendation workflow starts from the selected current process. Historical responses remain on
  cancelled gates but never count toward or copy into the replacement run. The cancellation and replacement are atomic
  per recommendation, and one failure does not roll back other recommendations. Closed, approved, bestowal-owned,
  deleted, grouped-child, or otherwise ineligible recommendations are not restarted. Synchronization itself never
  approves a recommendation or creates a bestowal; after replacement the process is current and the action is disabled.
  A grouped child is excluded while the group head remains eligible. Removing or ungrouping a child during the head's
  active review restores the child's origin state and starts it at step one of its award's current approval process;
  the head's run stays active and cancelled child approvals remain history only. Bestowal-linked recommendations remain
  locked after approval completion.
- Bestowal To-Do synchronization maps template items by stable `item_key`/ActionItem `source_ref`; matching history is
  preserved, removed items are audit-cancelled, and only synchronization-cancelled items may reopen automatically.
  Synchronization is launched from one template's detail page and considers only open bestowals assigned to that
  template whose stored template signature is missing or differs from its current definition. Successful initial
  materialization and synchronization store the current signature; terminal, unrelated, and already-current bestowals
  are excluded, and the action is disabled when the selected template has no outdated open bestowals.
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
