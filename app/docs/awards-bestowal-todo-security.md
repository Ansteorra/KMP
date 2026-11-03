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
- Completing or reopening a To-Do is controlled by the Action Item assignee resolver.
  Bestowal administration does not bypass assignment eligibility.
- All lookups execute on the already resolved tenant connection. Never accept a tenant or
  branch scope from the request without policy-derived validation.

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

Mark Given requires assignment to the final reporting To-Do when the bestowal has one.
A denial identifies that task and directs staff to an awards administrator. Check the
task's assignee configuration, the member's active role and branch scope, and any required
membership or warrant. General bestowal administration does not replace task assignment.
Do not remove assignment checks to resolve an account-specific access problem.

## Award and recipient display

Bestowal award columns show the actual award, level, and specialty, including when those
differ from the recommendation. CSV exports use plain text with the existing spreadsheet
formula protection. Agenda specialty details honor the item's inclusion setting.

Recommendation and bestowal recipient display prefers the current linked member name and
falls back to the stored name for recipients without accounts. Recommendation grids can be
searched by the current or submitted name and sort by the displayed name. Reading a screen
or exporting data never rewrites the stored submitted name, identity links, or OP metadata.

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
- `plugins/Awards/src/Services/BestowalTodoAssigneeResolver.php`
- `plugins/Awards/tests/TestCase/Config/HardenBestowalTodoSecurityTest.php`
