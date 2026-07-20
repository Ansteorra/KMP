# Bestowal To-Do security model

This document defines the least-privilege authorization model for Awards bestowal To-Dos. The configuration is installed by
`Awards/config/Migrations/20260714203000_HardenBestowalTodoSecurity.php`.

## Security boundary

Bestowal visibility and management are scoped by the owning `Awards.branch_id`. Recipient membership branch does not grant access.
The branch attached to each materialized ActionItem is also resolved from the award branch.

Court agenda authorization follows the same award scope rather than the gathering host branch. A Court Management or Court Reporter
permission can access a gathering agenda when that gathering contains at least one bestowal whose award is in the permission's branch
scope. Court Management then manages the shared agenda for that gathering.

Permission scope is expanded from the branch on the member's role assignment:

- Crown and Principality permissions use `Branch Only`.
- Baronial permissions use `Branch and Children`.
- All workflow and administrative permissions require active membership and an active warrant when
  `KMP.RequireActiveWarrantForSecurity` is enabled.
- Completing or reopening a To-Do is always controlled by the ActionItem assignee resolver. Administrative permissions do not
  bypass the configured To-Do assignee.

## Operational permissions

Each tier has five permissions:

| Function | Crown | Principality | Baronial |
| --- | --- | --- | --- |
| Scroll | `Crown Scroll Management` | `Principality Scroll Management` | `Baronial Scroll Management` |
| Regalia | `Crown Regalia Management` | `Principality Regalia Management` | `Baronial Regalia Management` |
| Schedule | `Crown Award Schedule Management` | `Principality Award Schedule Management` | `Baronial Award Schedule Management` |
| Court agenda | `Crown Court Management` | `Principality Court Management` | `Baronial Court Management` |
| Court reporter | `Crown Court Reporter` | `Principality Court Reporter` | `Baronial Court Reporter` |

Every operational permission grants scoped bestowal read access:

```text
Awards\Policy\BestowalPolicy:
  canView
  canIndex
  canGatheringBestowalsGridData
  canViewGatheringBestowals

Awards\Policy\BestowalsTablePolicy:
  canIndex
  canExport
```

Function-specific policy additions are:

| Function | Additional policies |
| --- | --- |
| Scroll | `Awards\Policy\BestowalPolicy::canPrepareScrolls` |
| Regalia | None |
| Schedule | `BestowalPolicy::{canManageCourtSchedule, canBulkAssignGathering, canGatheringsForBestowalAutoComplete, canGatheringsForBestowalBulkAutoComplete}` |
| Court agenda | Full court agenda policy bundle |
| Court reporter | Court agenda read bundle |

The court agenda read bundle is:

```text
Awards\Policy\CourtAgendasTablePolicy::canIndex
Awards\Policy\CourtAgendaPolicy::{canGathering, canPrintAgenda}
```

The full court agenda bundle adds:

```text
Awards\Policy\CourtAgendaPolicy:
  canEdit
  canImport
  canAddSegment
  canAddBlock
  canAddBestowal
  canMoveToRoaming
  canUpdateItem
  canMoveItem
  canRemoveItem
```

## Administrative permissions

| Permission | Scope | Purpose |
| --- | --- | --- |
| `Can Administer Bestowals` | Branch and Children | Scoped bestowal read/edit, state updates, cancellation, scheduling, scroll preparation, and ad-hoc creation |
| `Can Administer Court Agendas` | Branch and Children | Full court agenda bundle |
| `Can Manage Bestowal To-Do Templates` | Global | Template and template-item index, add, view, edit, and delete |

Template administration is no longer implied by `Can Manage Awards`. Roles that held `Can Manage Awards` when the migration ran
were also granted the dedicated template permission to preserve intended administrator access.

## Role configuration

The 15 `{Tier} {Function} Bestowal Todo` roles each contain exactly their matching tier permission. They do not receive the legacy
global view or scheduling permissions.

Supporting role configuration is:

| Role | Bestowal-related permissions |
| --- | --- |
| Ansteorran Crown | Five Crown operational permissions plus `Can Administer Bestowals` |
| Golden Staff | `Can Administer Bestowals`, `Can Administer Court Agendas` |
| Stable Scroll | `Crown Scroll Management` |
| Sable Scroll | `Crown Scroll Management` |
| Court Herald | `Crown Court Management` |

The migration preserves unrelated permissions on these roles.

## Retired configuration

The following permissions are not used by the tier To-Do roles:

- `Can View Bestowals`
- `Can Manage Bestowals`
- `Can Prepare Scrolls`
- `Can Manage Court Schedule`

They remain available for compatibility but are changed from Global to `Branch and Children`, require warrants, and have their
policy mappings reduced to their intended purpose.

The manually created duplicate permissions `Can View Bestowal (Branch and Children)` and
`Can Manage Bestowals (Branch and Children)` are removed. Existing role grants are migrated to the canonical scoped permissions
before removal.

## Deployment and import order

1. Run the Awards migrations.
2. Import or assign roles by permission name, never by environment-specific numeric ID.
3. Resolve template `assignee_source_id` values from the permission names.
4. Assign each role at the branch where its scope begins.
5. Create and approve active warrants for the role assignments.
6. Enable `KMP.RequireActiveWarrantForSecurity`.
7. Clear the `security` cache group or restart the application processes.
8. Verify a representative member from every tier and branch before production release.

`DevLoadBestowalTodoUsersSeed` creates time-bounded warrants for the 15 POC demo personas and for every current POC role
assignment carrying a managed bestowal permission. Those demo warrants and member assignments must not be exported to production.

## POC rollout

The scoped configuration was deployed and live-verified in POC on 2026-07-15:

- all 15 tier permissions use the documented scope, require active membership and warrants, and have the exact documented policy
  bundle;
- all 15 tier To-Do roles contain exactly their matching tier permission and no legacy broad permission;
- the duplicate manually generated permissions are absent;
- all 24 current POC role assignments carrying a managed bestowal permission have a current warrant;
- `KMP.RequireActiveWarrantForSecurity` is set to `yes`;
- while impersonating Kal Landed Nobility w Canton Demoer, the Bestowals grid returned 52 records across five awards and the
  Recommendations grid returned 95 records across six awards; every award was owned by Barony of Stargate, Barony of the
  Steppes, or Canton of Glaslyn, matching Kal's three warranted branch assignments;
- the POC tenant and platform login smoke checks and application health check passed on image version `0.0.20260715015833`.

This authorization migration does not change which template items are gating.
