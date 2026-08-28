# Workflow and approval endpoints

KMP's workflow UI is split across three controllers. Routes are declared explicitly in
`config/routes.php`; treat that file and each controller's `allowMethod()` calls as the
executable contract.

## Route ownership

| Area | Controller | Primary routes |
| --- | --- | --- |
| Definitions and designer | `WorkflowDefinitionsController` | `/workflows`, `/workflows/add`, `/workflows/designer[/<id>]`, `/workflows/save`, `/workflows/publish`, `/workflows/registry`, version, archive, draft, and migration actions |
| Running instances | `WorkflowInstancesController` | `/workflows/instances[/<definitionId>]`, `/workflows/instances/grid-data[/<definitionId>]`, `/workflows/instance/<id>` |
| Human approvals | `ApprovalsController` | `/approvals`, `/approvals/grid-data`, `/approvals/kanban-lane`, `/approvals/all`, `/approvals/record`, detail, triage, reassignment, token, eligible-approver, and mobile endpoints |

`/approvals` is the canonical approval prefix. The following `/workflows/*` approval routes
remain only for backward compatibility:

- `GET /workflows/approvals`
- `GET /workflows/approvals-grid-data`
- `POST /workflows/record-approval`
- `GET /workflows/eligible-approvers/<approvalId>`
- `GET /workflows/approval-detail/<approvalId>`

New links and client code must use `/approvals/*`. Requests to the older
`/authorization-approvals/my-queue` path receive a temporary redirect to `/approvals`.

## Authorization and request rules

Authentication, controller-level abilities, entity policies, and approval eligibility are
separate checks:

- The controller policies are `WorkflowDefinitionsControllerPolicy`,
  `WorkflowInstancesControllerPolicy`, and `ApprovalsControllerPolicy`; there is no single
  `WorkflowsPolicy`.
- Definition and instance records also use their table/entity policies.
- An approval response is accepted only after `ApprovalsController` and the workflow approval
  manager revalidate that the current member may act on the still-pending approval.
- JSON helpers that call `skipAuthorization()` perform their own current-member eligibility
  guard. Do not copy that pattern without an equivalent guard.
- Mutating endpoints restrict HTTP methods and require the normal CSRF-protected tenant web
  request. Preserve those `allowMethod()` contracts when changing routes.

All routes run inside the resolved tenant context. Workflow definitions, instances, and
approvals are tenant data; a route must never select or accept a tenant from request data.

## Source map

- `config/routes.php`
- `src/Controller/WorkflowDefinitionsController.php`
- `src/Controller/WorkflowInstancesController.php`
- `src/Controller/ApprovalsController.php`
- `src/Policy/*Workflow*Policy.php` and `src/Policy/ApprovalsControllerPolicy.php`
- `src/Services/WorkflowEngine/DefaultWorkflowApprovalManager.php`
