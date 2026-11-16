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

## Deferred execution

`DefaultWorkflowEngine` implements `WorkflowDeferredWorkInterface` for the existing
`WorkflowResume` queue task. Workers must bind the tenant before resolving the engine or
tables. The queued job and workflow state must use the same connection so both commit or
roll back together. Jobs contain instance, node, and execution-log IDs; action names,
parameters, and output connections come from the instance's pinned definition.

- A `delay` node with `duration` schedules a persistent queue job using `notbefore`.
  Durations accept non-negative integer seconds or compact units (`30s`, `30m`, `2h`,
  `2d`, `1w`), up to ten years. Context/app-setting value descriptors are resolved before
  scheduling. A duration-less delay remains a manual callback wait; `waitEvent` does not
  subscribe to events automatically. Existing unscheduled waits can still be resumed
  explicitly through the engine.
- An action with node/registry `isAsync`, or a durable action with positive `maxRetries`
  (`retryCount` is the legacy alias), runs in the queue. `maxRetries` counts retries after
  the first attempt. Each failed attempt has its own execution log. Retries wait 1, 2, 4,
  ... seconds, capped at one hour, using queue scheduling rather than worker sleeps.
  Retryable actions therefore become asynchronous even when `isAsync` is absent.
- Each deferred action attempt executes in a database savepoint. An exception or a result
  with `success: false` rolls back that attempt's database writes and context updates.
  The failure log and next attempt/job then commit together. Once retries are exhausted,
  the action follows its connected `error` output, or marks the instance failed.
- Successful deferred action results and context updates survive failures in downstream
  continuation. A continuation failure marks the instance failed without retrying the
  completed activity. Queue retries for infrastructure failures are separate from action
  retry counts. Ephemeral workflows keep their synchronous retry behavior; durable
  automatic retries currently apply to action nodes.
- Instance row locks and execution-log IDs serialize delivery and make completed,
  cancelled, or stale jobs harmless. Early deliveries schedule another job for the saved
  due time. Queue jobs are acknowledged after work commits; a lost acknowledgement does
  not rerun a completed attempt. Direct `resumeWorkflow()` cannot skip a queued action.
  Pre-upgrade async jobs still execute, using the saved definition rather than their
  embedded action/config payload.
- External HTTP/email/provider effects cannot be rolled back by a database savepoint.
  Actions that perform external effects must tolerate redelivery after an interrupted
  transaction. These guarantees do not imply exactly-once external execution.

The current `forEach` iterate path cannot suspend. Its validator and runtime reject
asynchronous or retryable durable actions inside that path. Use separate child workflows
when iterations must wait. Run the ordinary tenant queue worker to deliver timers and
retries; no new cron entry or database migration is required.

Regression coverage lives in
`tests/TestCase/Services/WorkflowEngine/DeferredWorkflowExecutionTest.php` and
`tests/TestCase/Queue/Task/WorkflowTaskTest.php`.

## Source map

- `config/routes.php`
- `src/Controller/WorkflowDefinitionsController.php`
- `src/Controller/WorkflowInstancesController.php`
- `src/Controller/ApprovalsController.php`
- `src/Policy/*Workflow*Policy.php` and `src/Policy/ApprovalsControllerPolicy.php`
- `src/Services/WorkflowEngine/DefaultWorkflowApprovalManager.php`
- `src/Services/WorkflowEngine/WorkflowDeferredWorkTrait.php`
- `src/Queue/Task/WorkflowResumeTask.php`
