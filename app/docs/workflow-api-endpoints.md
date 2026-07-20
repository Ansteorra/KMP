# Workflow API Endpoints

All workflow routes are scoped under `/workflows` and require model-level authorization via `WorkflowsPolicy`.

## Endpoint Reference

| Method | Path | Action | Description |
|--------|------|--------|-------------|
| GET | `/workflows` | `index` | List all workflow definitions |
| GET/POST | `/workflows/add` | `add` | Create a new workflow definition |
| GET | `/workflows/designer` | `designer` | Open workflow designer (new workflow) |
| GET | `/workflows/designer/{id}` | `designer` | Open workflow designer for existing definition |
| GET | `/workflows/load-version/{versionId}` | `loadVersion` | Load a specific workflow version into the designer |
| POST/PUT | `/workflows/save` | `save` | Save workflow definition from the designer |
| POST | `/workflows/publish` | `publish` | Publish a draft workflow version |
| GET | `/workflows/registry` | `registry` | List available node types in the workflow registry |
| GET | `/workflows/instances` | `instances` | List all workflow instances |
| GET | `/workflows/instances/{definitionId}` | `instances` | List workflow instances for a specific definition |
| GET | `/workflows/instance/{id}` | `viewInstance` | View details of a single workflow instance |
| GET | `/workflows/approvals` | `approvals` | List pending approvals for the current user |
| POST | `/workflows/record-approval` | `recordApproval` | Submit an approval or rejection decision |
| GET | `/workflows/versions/{definitionId}` | `versions` | List all versions of a workflow definition |
| GET | `/workflows/compare-versions` | `compareVersions` | Compare two workflow versions side-by-side |
| POST | `/workflows/toggle-active/{id}` | `toggleActive` | Activate or deactivate a workflow definition |
| POST | `/workflows/create-draft` | `createDraft` | Create a new draft from a published version |
| POST | `/workflows/migrate-instances` | `migrateInstances` | Migrate running instances to a new workflow version |

## Authorization

All actions are authorized at the model level via `Authorization->authorizeModel()` in the controller's `initialize()` method. Access is governed by the `WorkflowsPolicy` class.

## Route Parameters

| Parameter | Pattern | Description |
|-----------|---------|-------------|
| `{id}` | `\d+` | Workflow definition or instance ID |
| `{versionId}` | `\d+` | Workflow version ID |
| `{definitionId}` | `\d+` | Workflow definition ID for filtering |
