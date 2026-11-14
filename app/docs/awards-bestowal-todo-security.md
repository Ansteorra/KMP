# Bestowal To-Do security model

Awards bestowals, court work, and materialized Action Items are tenant data. Within a tenant,
visibility is scoped from the owning award's `branch_id`; the recipient's membership branch
and the gathering's host branch do not independently grant access.

The baseline permission and role mappings are installed by Awards migrations, principally
`20260714203000_HardenBestowalTodoSecurity.php`. Workflow synchronization is added separately
by `20260818120000_AddAwardWorkflowSynchronizationPermission.php` and template-scoped sync by
`20260820130000_AddTemplateScopedBestowalTodoSync.php`. Treat migrations and policies as the
executable contract when this summary differs.

## Scope rules

- Crown and Principality operational permissions use `Branch Only`.
- Baronial operational permissions use `Branch and Children`.
- When `KMP.RequireActiveWarrantForSecurity` is enabled, the relevant role assignment must
  have active membership and a current warrant.
- Agenda permissions may cover the gathering's host branch or follow award ownership through
  existing bestowals or scheduled award-giving activities inside their scope. An empty agenda
  can be prepared through an eligible scheduled activity; merely attaching an activity does
  not grant access. See [Gathering activities](gathering-activities.md).
- Agenda access does not grant bestowal read access. Builder and print check each bestowal's
  normal `view` permission. Restricted scheduled items retain placement and timing under
  `Court Item  (details blocked by security)`, with all bestowal details and item notes removed.
  Restricted unassigned bestowals are excluded from every add-item and roaming list; direct
  bestowal placement requests also require bestowal placement permission. Read permission alone
  does not put a bestowal in an Eligible Bestowals list. Restricted placeholders
  retain keyboard ordering controls but expose no detail-edit modal or award-derived move options.
- Completing or reopening a To-Do is controlled by the Action Item assignee resolver.
  Bestowal administration does not bypass assignment eligibility.
- All lookups execute on the already resolved tenant connection. Never accept a tenant or
  branch scope from the request without policy-derived validation.

## Event award delegation

Open **Event Award Delegation** from a gathering's Award Bestowals tab or court agenda.
Granting, history, and revocation stay inside a keyboard-accessible modal; closing it returns
focus to its opener. Reopening refreshes the history, and denied frame loads show safe feedback.
Select an awarding group and another verified member. The grant covers that exact
award-owning branch and gathering, including unplaced bestowals already assigned to the
gathering, court agendas, notes the grantor may read, and the grantor's eligible To-Dos.
It remains active until explicitly revoked; event dates do not expire it.

Delegates can also attach, describe, remove, and schedule award-giving activities configured
for awards belonging to that exact granting group. The activity's assignment rules must match
the grantor's current native permissions in the granting group's branch. This lets a Crown
delegate prepare a Kingdom Court at a local event without relying on the event host's branch.
Native award staff use that same awarding-group context when the event already contains
their group's bestowals or eligible scheduled award business, or they have made an active
delegation for that event and group. A Crown does not need to delegate to themselves to
prepare the court they are authorizing someone else to manage.
Activities must be attached before they can be scheduled, and both the stored and replacement
activity are checked on edits. General gathering editing, catalogue/rule editing, unrelated
activities, and generic Other schedule entries are not included. Existing template, waiver,
and schedule dependency restrictions still apply. The core policy obtains this extension
through `GatheringActivityDelegationRegistry`; native-only checks prevent delegation chains.

Activity access opens Activities and Schedule alongside any independently authorized plugin
tabs. It does not expose private Staff, Location management, or Record details tabs. Those
require native gathering view/edit or steward access through `GatheringPolicy::canViewManagement`.
Public event information and explicitly public contacts remain in Overview.

Granting requires the grantor's own native bestowal view permission for the awarding
group. Effective access is always the intersection of the event/group grant and the
grantor's current native permissions, membership/warrant rules, and task assignment.
No roles, permissions, warrants, or office appointments are copied to the recipient.
Losing native authority or either account becoming unavailable stops effective access.
The recipient's existing native rights continue independently.

Delegation is one hop: inherited rights never qualify a recipient to grant those rights
again. Someone who has separate native authority may still delegate that authority.
Only the original grantor or a super user may revoke; the grantor can revoke after losing
award authority. Revocation and replacement grants preserve historical rows, who granted
and revoked them, and their timestamps. Work records and To-Do logs name the actual actor.

Delegated edits and on-the-spot bestowals stay in the granted event and awarding group.
Recommendation administration and linking/unlinking unrelated recommendations are outside
this grant. Reads, grids, CSV, court placement, protected fields, and queued bestowal edits
recheck the relevant current permission. To-Do eligibility extends every assignment kind
through the grantor's native eligibility; materialized assignments and history are unchanged.
My To-Dos candidate queries include event grants but the live resolver still verifies each
row. The owner extension is registered through `ActionItemDelegationRegistry`, and retained
providers resolve the active tenant locator each time.

Mutation services reload the actual operator and persisted bestowal/event context inside their
transaction. This includes cancellation, finalization without a terminal task, bulk scheduling,
and court edits. Delegated bulk scheduling cannot move work to a different gathering. Native
scheduling-task assignees retain their existing task-based assignment path. Court sessions must
belong to the bestowal's current gathering; forged agenda/item combinations cannot cross agendas.
Recommendation lookup context cannot widen an event grant into recommendation authority.
Activity attachment/description/removal and schedule creation/edit/deletion also reauthorize
the actual operator inside their transactions. Automatic court agenda/section preparation
checks current read authority before creating rows; canonical bestowal placement mirroring
remains an internal consequence of the already-authorized bestowal save.

Authorization transactions hold shared locks on the active grant rows they use. Revocation
locks the grant for update, so it waits for earlier authorized transactions to finish before
returning. After that, stale forms, queued operations, and subsequent reads must reauthorize
and are denied unless the operator has independent native rights or another active grant.
Revocation cannot retract content already delivered to a browser or exported earlier.

Migration `20261105120000_CreateBestowalDelegations.php` creates the tenant-owned grant
history. Deploy it through the normal tenant migration path before serving this feature.

## Operational permissions

Each tier has five purpose-specific permissions:

| Function | Crown | Principality | Baronial |
| --- | --- | --- | --- |
| Scroll | `Crown Scroll Management` | `Principality Scroll Management` | `Baronial Scroll Management` |
| Regalia | `Crown Regalia Management` | `Principality Regalia Management` | `Baronial Regalia Management` |
| Schedule | `Crown Award Schedule Management` | `Principality Award Schedule Management` | `Baronial Award Schedule Management` |
| Court editing | `Crown Court Management` | `Principality Court Management` | `Baronial Court Management` |
| Court reporting | `Crown Court Reporter` | `Principality Court Reporter` | `Baronial Court Reporter` |

All five provide scoped bestowal read/index access. Scroll adds
`BestowalPolicy::canPrepareScrolls`; schedule adds court-scheduling and gathering-lookup
abilities; court reporting adds agenda view/print; court editing adds the agenda mutation
bundle. Do not recreate these bundles with controller conditionals—change the permission to
policy mappings and cover the migration/policy behavior.

The Regalia permission names above are installation defaults. Migration
`20261105011000_AllowRegaliaPermissionRenaming.php` clears their `is_system` flag so
tenant administrators can rename them. Existing role, policy, template, and Action Item
references retain the same permission IDs. Development seeds still look up the default
names and should run before local renaming. The migration is irreversible because renamed
permissions can no longer be safely identified by their original names.

## Administrative permissions

| Permission | Scope and purpose |
| --- | --- |
| `Can Administer Bestowals` | Branch-and-children bestowal read/edit, state, cancellation, scheduling, scroll, and ad-hoc creation |
| `Can Administer Court Agendas` | Branch-and-children court agenda management |
| `Can Manage Bestowal To-Do Templates` | Global template and template-item administration |
| `Can Synchronize Award Workflows` | Global access to synchronize outdated recommendation runs and open bestowal To-Dos; does not grant general process/template editing |

Synchronization requires active membership and a current warrant when warrant enforcement is
enabled. The migrations preserve established administrator access through explicit mappings,
but template administration and general Awards administration remain separate abilities.

The legacy `Can View Bestowals`, `Can Manage Bestowals`, `Can Prepare Scrolls`, and
`Can Manage Court Schedule` names remain compatibility permissions with narrowed scope and
policy mappings. New tier roles must use the purpose-specific permissions. Import and seed
logic must resolve permissions by name, never environment-specific numeric IDs.

Gathering activity attachment and scheduled activity editing use the catalogue's
**Can be assigned by** rules, described in [Gathering activities](gathering-activities.md).
The court migration seeds existing court management permissions into those rules and removes
their legacy core gathering-policy grants. Activity access is scoped to the gathering's host
branch; bestowal and agenda content keeps the award-branch scope described above.

## Change and deployment checklist

1. Change the owning Awards migration/policy/service and add a migration contract test.
2. Preserve unrelated permissions when updating built-in roles.
3. Resolve Action Item template `assignee_source_id` from its permission name.
4. Assign roles at the branch where their scope begins and provision required warrants.
5. Run the tenant/plugin migration through the normal fleet migration path; do not patch one
   database manually.
6. Clear the tenant-aware `security` cache or restart processes after permission changes.
7. Verify allow and deny cases for each affected tier, including a sibling/out-of-scope branch,
   expired membership/warrant, court read versus edit, and To-Do assignment.
8. Exercise two tenants when changing caches, jobs, import logic, or platform-driven rollout.

Development seed personas and demo warrants are local test data and must never be promoted or
used as production authorization evidence.

## Closeout troubleshooting

Completing a bestowal requires assignment to its lead terminal To-Do when terminal
definitions exist. The user completes that single To-Do and selects an allowed outcome,
such as Given or Given Not Received. The lead's assignee and required fields apply to
every choice; outcome-only definitions do not create extra To-Dos or grant authority.
The service rechecks the lead snapshot, its assignment, and the chosen outcome under
the bestowal lock. A task or outcome from another bestowal cannot be selected.
A denial identifies that task and directs staff to an awards administrator. Check the
task's assignee configuration, the member's active role and branch scope, and any required
membership or warrant. General bestowal administration does not replace task assignment.
Do not remove assignment checks to resolve an account-specific access problem.

Notes entered when completing or reopening a To-Do stay in `action_item_logs.note`. The bestowal's
To-Dos tab and quick checklist show nonblank transition notes under their task, with the transition,
author, and date displayed in the viewer's timezone. These are separate from the bestowal's Notes
tab. Reading notes uses the bestowal's existing view authorization and only loads logs for its
displayed To-Dos; prior notes remain visible after reopening or finalization. Note text is escaped
and line breaks are preserved. No notes are copied or rewritten when viewed.
Completion from the detail checklist and quick checklist prompts for an optional multiline note.
Cancel leaves the task unchanged and restores focus to its completion control. In My To-Dos,
switching tasks or successfully saving clears the previous note; a failed save keeps the current
note available for retry. Blank notes remain optional.

Gathering-link visibility evaluates activity access as one batch. Catalogue rules, award/activity
links and event grants are read once per evaluation, while native grantor permissions and branch
scope remain authoritative. Batch providers retain no decisions across evaluations or tenant
changes, and transactional grant reads retain their shared locks.

## Schedule to agenda

Changing the award or award type in the edit dialog refreshes eligible gathering choices
without clearing the existing gathering assignment. The displayed gathering and submitted
`gathering_id` must stay synchronized; lookup refreshes retain the current selected ID.
The edit dialog separately checks current eligibility using the proposed award and date filter,
without the picker's always-included selected option. An ineligible or cancelled gathering shows
an accessible inline warning while preserving the assignment and allowing Save. The warning clears
when an eligible selection is made or the gathering is cleared. Failed checks report uncertainty;
older responses cannot overwrite a newer selection. The read-only check requires edit authority
on the persisted bestowal and validates the proposed event/group against the same delegation scope.
The same check independently validates the selected court session against the proposed award's
activity links and gathering. An incompatible session shows its own accessible warning even when
the gathering remains eligible. Neither assignment is silently cleared. Choosing an eligible session,
Roaming Court, or no session clears the court warning; clearing only the court retains the gathering.

The built-in Added to Agenda check requires an assigned gathering and a completed
Event Scheduled check when that check exists. My To-Dos displays the missing
prerequisite and disables completion until scheduling is ready. The server checks
the prerequisite before applying a submitted court assignment, including direct posts.
After scheduling, completion requires Roaming Court or an eligible scheduled court
activity; choosing the latter places the bestowal on that court's agenda. A check
without a court assignment stays open. An explicit template Required field of None
continues to opt out of the built-in field requirements.

The shared completion modal disables inactive gathering and court controls and
removes their required state. Resetting a hidden gathering picker must preserve
that state so native validation can focus only visible fields. Switching tasks or
reopening a check clears the prior selection and restores the active field's state.

## Award and recipient display

Bestowal award columns show the actual award, level, and specialty, including when those
differ from the recommendation. CSV exports use plain text with the existing spreadsheet
formula protection. Agenda specialty details honor the item's inclusion setting.

The gathering Bestowals tab includes a separate **Specialty** column by default,
using the bestowal's current value. It is also available in the main grid's column
chooser, supports sorting and search, and exports with the selected columns. When
shown separately, specialty is not repeated in the Award cell. Both grids show a
small exclamation icon if any linked recommendation (including the primary) has a
different award ID. Hover text and screen-reader text identify it as **Award differs
from recommendation**; no visible badge or explanatory note is added. This comparison
does not substitute recommendation awards or specialties for the current bestowal
values, and does not change saved notes.
Matching awards and ad-hoc bestowals without linked recommendations have no flag;
specialty differences alone do not trigger it. CSV award cells retain the award
difference flag as plain text.

Recommendation and bestowal recipient display prefers the current linked member name and
falls back to the stored name for recipients without accounts. Recommendation grids can be
searched by the current or submitted name and sort by the displayed name. Reading a screen
or exporting data never rewrites the stored submitted name, identity links, or OP metadata.

My To-Dos shows the recipient, award, and specialization even before a gathering is
assigned, on desktop and mobile. Missing gathering, date, host, and court details show
`Not assigned`; they do not replace the bestowal details with a generic record label.
The source links on desktop and mobile require current bestowal view permission.
Without that permission, the source remains plain text and the assigned To-Do remains
available. Missing or unresolved sources also render without a link.

Desktop My To-Dos offers an Open To-Dos view and an automatic tab for each distinct
title among the member's eligible open items. The tabs and rows use the live assignee
resolver, including active permission, role, warrant, and branch rules; candidate lookup
columns alone do not grant access. Completed is always the last tab, after open types
and personal saved views, so members can review their completed work. It is the only
built-in tab and the default when no eligible open items remain. Search, filters,
and pagination do not change which type tabs are
available. Table refreshes update the tabs after completion or permission changes, and
an obsolete type tab falls back to the current default. Personal saved views remain
available within the same member-scoped data boundary.

For account holders, update the existing member account through the authorized member edit
workflow; verify its OP linkage rather than creating a replacement account. For recipients
without accounts, use the authorized recommendation edit workflow before approval, or the
bestowal edit workflow once the recommendation is locked by a bestowal. Historical notes
and previously generated herald scripts remain recorded text and should be reviewed by staff
when a name or award decision changes.

## Source and test map

- `plugins/Awards/config/Migrations/20260714203000_HardenBestowalTodoSecurity.php`
- `plugins/Awards/config/Migrations/20260818120000_AddAwardWorkflowSynchronizationPermission.php`
- `plugins/Awards/src/Policy/BestowalPolicy.php`
- `plugins/Awards/src/Policy/BestowalsTablePolicy.php`
- `plugins/Awards/src/Policy/CourtAgendaPolicy.php`
- `plugins/Awards/config/Migrations/20261105120000_CreateBestowalDelegations.php`
- `plugins/Awards/src/Policy/BestowalDelegationPolicy.php`
- `plugins/Awards/tests/TestCase/Controller/BestowalDelegationsControllerTest.php`
- `tests/ui/support/bestowal-delegation-browser-check.cjs`
- `plugins/Awards/src/Services/BestowalTodoAssigneeResolver.php`
- `plugins/Awards/tests/TestCase/Config/HardenBestowalTodoSecurityTest.php`
