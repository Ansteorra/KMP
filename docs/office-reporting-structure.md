---
layout: default
---
[← Back to Officers Plugin](5.1-officers-plugin.md)

# Office Reporting Structure

KMP does not have one hard-coded kingdom organization chart. Departments, offices, reporting relationships, applicable branch types, and officer assignments are tenant data. The active tenant's records—not a diagram in this repository—are authoritative.

## Three layers

| Layer | Model | Meaning |
| --- | --- | --- |
| Category | `Officers.Department` | Groups related office definitions |
| Position definition | `Officers.Office` | Describes a role in the organization |
| Assignment | `Officers.Officer` | Places a member in an office for one branch and active window |

A Department owns many Offices. An Office can grant a core Role, require a warrant, limit assignments to one per branch, define a term length, and restrict itself to applicable branch types.

## Office relationships

`reports_to_id` identifies the office definition this office normally reports to. `deputy_to_id` identifies an office for which this is a deputy. The `Office` entity keeps them mutually consistent: assigning a deputy target also sets the reporting target; assigning an independent reporting target clears the deputy target.

The inverse associations are `DirectReports` and `Deputies`. These relationships are between office definitions, not particular people.

## Resolved assignment relationships

An Officer assignment stores the resolved coordinates:

- `reports_to_office_id` and `reports_to_branch_id`;
- `deputy_to_office_id` and `deputy_to_branch_id`.

The hire workflow calculates them from the office definition and the assignment branch. `OfficesTable::findCompatibleBranchForOffice()` finds the appropriate branch level for the target office. `OfficersTable::findEffectiveReportsTo()` walks both the office and branch hierarchies and can skip configured empty reporting levels.

This distinction matters: changing an Office definition does not make old assignment snapshots correct automatically.

## Changing the hierarchy

After changing `reports_to_id`, `deputy_to_id`, or `grants_role_id`, call `OfficerManagerInterface::recalculateOfficersForOffice()` for current and upcoming assignments. Do not bulk-update the resolved fields. The recalculation path also keeps granted roles and related lifecycle state consistent.

Before saving a hierarchy change, guard against self-reference/cycles, ensure the target applies at a compatible branch level, and consider one-per-branch and warrant requirements. Use the table/entity setters and rules rather than direct SQL.

## Authorization and tenancy

`departmentsMemberCanWork()` and `officesMemberCanWork()` combine global officer permissions with current officer positions and hierarchy. Controllers still authorize actions through policies. A reporting relationship never grants cross-tenant access, and an office/branch numeric ID has meaning only in its tenant database.

## How to inspect a tenant's chart

Use the Offices administration/grid and current officer rosters for the selected tenant. A useful diagnostic view includes department, office, reports-to/deputy target, applicable branch types, assigned branch, current officer, and effective report recipients. If a static diagram is needed for operations, generate it from that tenant's current data and label it with the tenant and export date.

## Verification

Test independent and deputy relationships, cycles/invalid targets, compatible branch selection, skipped vacancies, current/upcoming assignments, recalculation after configuration changes, role and warrant effects, authorization scope, and two tenants with different office trees.
