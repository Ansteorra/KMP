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
- Coordinated quick-edit clients (`X-KMP-Grid-Reconcile: table`) receive direct current-table invalidations on success and 422 feedback without replacing their submitted form on failure. Keep legacy row/reload contracts for older clients. See `app/docs/hotwire-interactions.md`.
- Recommendation feedback approval context uses the registered `AwardsFeedback` renderer.
- Recommendation approval synchronization is scoped to one approval process from its detail page. Candidate discovery and restarts run in bounded tenant queue jobs; the browser only enqueues and reads progress.
  Durable runs/items record per-recommendation results, duplicate requests reuse an active run, and worker retries
  cannot repeat committed restarts. Authorization and configuration are rechecked during processing; interruptions
  remain visible and a new synchronization can resume eligible remaining work. Unrelated and current records are excluded. For each outdated recommendation, all
  active runs, workflow instances, and pending gates are audit-cancelled with `approval_process_restarted`, then exactly
  one new existing-recommendation workflow starts from the selected current process. Historical responses remain on
  cancelled gates but never count toward or copy into the replacement run. The cancellation and replacement are atomic
  per recommendation, and one failure does not roll back other recommendations. Closed, approved, bestowal-owned,
  deleted, grouped-child, or otherwise ineligible recommendations are not restarted. Synchronization itself never
  approves a recommendation or creates a bestowal; after replacement the recommendation is current and repeat synchronization skips it.
  A grouped child is excluded while the group head remains eligible. Removing or ungrouping a child during the head's
  active review restores the child's origin state and starts it at step one of its award's current approval process;
  the head's run and all remaining children stay unchanged, including a group with just one remaining child.
  Only Ungroup All or explicitly removing every child dissolves the group; cancelled child approvals remain history only. Bestowal-linked recommendations remain
  locked after approval completion.
- Registered completion providers must resolve table-owning services within the active tenant, not during plugin bootstrap. Retained bootstrap services can use a second connection and deadlock owner-locked transitions.
- Bestowal To-Do synchronization maps template items by stable `item_key`/ActionItem `source_ref`; matching history is
  preserved, removed items are audit-cancelled, and only synchronization-cancelled items may reopen automatically.
  Synchronization is launched from one template's detail page and considers only open bestowals assigned to that
  template whose stored template signature is missing or differs from its current definition. Successful initial
  materialization and synchronization store the current signature; terminal, unrelated, and already-current bestowals
  are excluded, and the action is disabled when the selected template has no outdated open bestowals.
  An assigned empty template is an authoritative zero-item process, and an explicit Required field `None` overrides
  legacy key-based defaults. Materialization, synchronization, ActionItem transitions, and finalization serialize on
  the persisted bestowal before locking its ActionItems; cancellation uses the same mutex, and finalization rechecks
  the stored terminal/gating rules while holding it. Explicit finalization selects the requested terminal snapshot and checks its assignee only after acquiring that same owner lock; the completion callback must match that terminal ID and owner. Completed/cancelled bestowals reject queued ActionItem mutations. Each ActionItem reconciliation
  is atomic, and initial materialization/backfill only targets open bestowals.
  Required-field reconciliation must use bounded passes until stable so prerequisite chains converge independent of
  template sort order. Completion events are deferred until the whole batch is stable, and definition synchronization
  must not implicitly finalize a bestowal. Templates may define multiple `is_terminal` outcome definitions, with exactly
  one `is_terminal_lead` entry point. Only the lead materializes as a To-Do; other terminal definitions supply outcome choices.
  The lead's assignee, branch, and required fields control completion for every choice. Saving a new lead atomically replaces
  the prior lead under the template lock; the first terminal definition becomes the default. Each definition's `terminal_status`
  defaults to `given`; the lead snapshots all allowed statuses as `completion_config.terminal_outcomes`. Lead selection and
  outcomes participate in the template signature. Completion requires an explicit allowed choice, recorded as
  `selected_terminal_status` in the lead's snapshot. Posted values cannot add outcomes or choose a different template's state.
  Terminal tasks require deliberate completion, override other required tasks, and atomically set the bestowal's lifecycle to the selected completion status
  while audit-closing unfinished siblings as not applicable. Their own field requirements still apply. Completed history
  remains unchanged. Ordinary completions cannot finalize while a terminal snapshot exists; templates without a terminal
  retain all-gating finalization. Explicit template sync adopts new terminal settings without finalization. Already completed
  terminal snapshots require a separate explicit finalization confirmation and outcome choice. Older snapshots retain their
  original controls until explicit synchronization; once a current lead snapshot exists, superseded completed terminal tasks
  remain history and cannot finalize or reopen. Only `open` bestowals belong in active queues; all statuses other than `open` and
  `cancelled` are completion outcomes and belong in Completed views. Finalized checklists are read-only.
  Authorized bestowal editors can explicitly change a completed terminal state using the completed lead's frozen
  outcomes, including Given Not Received to Given on delivery. The change serializes on the owner, rejects stale
  source states, and atomically appends an old/new-state audit note to the completed lead. It preserves the original
  close date, selected completion snapshot, To-Do statuses, existing logs, and recommendation projection.
  Ansteorra's standard templates include an optional `Given Not Received` terminal outcome with the existing Given assignee;
  other kingdoms configure their own outcomes. New definitions reach existing open bestowals only through explicit synchronization.
  Reopening field-backed scheduling tasks clears the corresponding assignment and reconciles siblings and agenda placement
  in the owner-locked ActionItem transaction. Event removal also clears court placement; court removal retains the event.
  The built-in Added to Agenda task requires a gathering and completion of Event Scheduled when present, checked before
  applying court selections. Completion requires Roaming Court or an eligible scheduled activity; explicit Required field
  None retains its opt-out. My To-Dos shows unmet prerequisites with completion disabled.
  Cancelling an open bestowal requires a recorded reason and is treated as complete reconsideration. While holding the
  same bestowal mutex, cancellation audit-cancels every open To-Do, preserves completed To-Do history, clears the
  bestowal links and bestowal-owned fields, resets each standalone recommendation or group head to `In Progress /
  Submitted`, and returns grouped children to `Linked`. Consumed, superseded, and active approval runs are cancelled
  with `bestowal_cancelled`; then exactly one new existing-recommendation approval workflow is requested per approval
  scope, even when no prior rehydratable run exists. The bestowal becomes `cancelled` only if the entire cleanup and
  restart request succeeds.
- Bulk synchronization reports may expose bounded record IDs and trusted domain skip reasons, but unexpected exception
  details belong in server logs; user-facing failures use fixed categories.
- State/status rules and plugin settings are stored in `Awards.*`, `Member.AdditionalInfo.*`, and `Plugin.Awards.*` settings.
- Recipients do not need member accounts. Bestowal creation copies `member_sca_name` from the approved recommendation.
  Award changes preserve the assigned gathering. Warn inline when the proposed award/date filter makes it
  ineligible; the current selection's presence in the picker is not eligibility evidence. The read-only
  check must authorize the actual bestowal and proposed delegation scope and must never mutate assignments.
  Check the selected court session independently against the proposed award/activity link and gathering;
  warn on incompatibility without clearing the court or gathering. Explicit court removal retains the gathering.
  Use `Bestowal::getRecipientName()` for display, reports/exports, agendas, print, and generated herald/notification text:
  prefer a nonblank loaded member name, then the stored bestowal name. Only missing names use the unknown placeholder.
- Recommendation display follows the same current-member/stored-name rule through
  `Recommendation::getRecipientName()`. Keep submitted snapshots and identity/OP links unchanged;
  eager-load members for current-name display, searching, and sorting. Bestowal grids and CSV
  report the actual award and specialty rather than substituting recommendation values.
- The Submitted By Me recommendation grid offers text search only; use its dedicated column metadata to keep dropdown and date filters unavailable.
- Gathering activity attachment, descriptions, removal, and scheduling use the core activity's **Can be assigned by**
  configuration. Matching permissions are evaluated in the gathering's branch and grant access to that activity only,
  including scheduled entries created by other members. Unconfigured activities require gathering-edit access.
  The court migration seeds existing Crown, Principality, Baronial, and court-schedule permissions into those rules;
  no separate court-only attachment or schedule authorization remains. Awards catalog eligibility still determines
  whether an activity supports award presentations and agendas. Bestowal and agenda content retains Awards permissions.
  See `app/docs/gathering-activities.md` for the shared contract.
- Each scheduled award-giving activity occurrence has its own agenda section regardless of its name or court level.
  Builder and print preparation discover eligible sessions without requiring bestowals first. New sections reject
  ineligible activities and duplicate sessions; bestowal placement remains specific to the award/activity link.
  Display current session details in the gathering's timezone and preserve existing script content and section names.
  Authorize new agenda placeholders with their gathering attached. Scoped agenda permissions can follow award ownership
  through existing bestowals or scheduled award-giving activities, including empty agendas hosted by another branch;
  unscheduled activities and out-of-scope awards do not grant access.
- Agenda builder and print must separately check each bestowal's normal `view` permission.
  Restricted scheduled items show `Court Item  (details blocked by security)` with placement
  and timing only; remove bestowal data, item notes, and derived award details from the view model.
  Exclude restricted unassigned bestowals from all candidate lists and require bestowal view
  permission on direct bestowal placement posts. Agenda access alone never grants bestowal visibility.
  Eligible Bestowals additionally requires placement authority; read-only items are never candidates.
- Event award delegation is scoped to one gathering and exact award-owning branch and lasts until revoked.
  Native bestowal view authority permits granting; inherited rights never authorize another grant.
  Evaluate the grantor's current native permissions and native To-Do assignment on each operation;
  do not copy roles or merge delegated privileges into the global identity. The grantor or super user
  may revoke while preserving grant history. Retain the actual operator in all work and To-Do audit fields.
  Delegated edits/creation cannot cross event or awarding-group boundaries or administer recommendations.
  Recheck actual actor and persisted owner context inside mutation transactions, including cancellation,
  legacy finalization, bulk scheduling, and court edits. Grant reads inside a transaction hold shared
  locks so successful revocation follows completion of earlier authorized writes. Scheduled court
  activities must belong to the bestowal's gathering. Keep delegation management inside its modal.
  Eligible award-giving activity attachment and scheduling use `GatheringActivityDelegationRegistry`.
  Require an award/activity link for the exact granting branch and evaluate the activity's assignment
  rules against the grantor's native permissions in that branch, even at a locally hosted event.
  Native award staff use the same context when the event has their group's bestowals, eligible
  scheduled award business, or an active delegation they made. Do not require self-delegation
  or grant private core gathering tabs merely to expose court preparation tools.
  Never expand this into general gathering, catalogue, unrelated activity, or Other schedule access.
  My To-Dos uses the registered owner delegation provider for candidates and final eligibility; retained
  providers must obtain the current tenant locator on every call. See `app/docs/awards-bestowal-todo-security.md`.
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
