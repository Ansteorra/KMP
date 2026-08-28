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
- A Court Management or Court Reporter permission may access a gathering agenda only when it
  contains a bestowal for an award inside that permission's branch scope.
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

## Source and test map

- `plugins/Awards/config/Migrations/20260714203000_HardenBestowalTodoSecurity.php`
- `plugins/Awards/config/Migrations/20260818120000_AddAwardWorkflowSynchronizationPermission.php`
- `plugins/Awards/src/Policy/BestowalPolicy.php`
- `plugins/Awards/src/Policy/BestowalsTablePolicy.php`
- `plugins/Awards/src/Policy/CourtAgendaPolicy.php`
- `plugins/Awards/src/Services/BestowalTodoAssigneeResolver.php`
- `plugins/Awards/tests/TestCase/Config/HardenBestowalTodoSecurityTest.php`
