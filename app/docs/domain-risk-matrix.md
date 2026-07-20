# KMP Domain Risk and Coverage Matrix

Use this matrix with [`testing-suite.md`](./testing-suite.md) when deciding which lower-layer tests and Playwright coverage must exist before review, UAT, and production promotion.

This inventory is based on the current core controllers, tables, services, routes, and enabled plugins in `app/config/plugins.php`. It intentionally focuses on KMP product domains and operational integrations, not third-party vendor packages. The commented-out `Template` plugin is excluded because it is not part of the active product surface.

## Gate Legend

| Gate | Meaning | PR expectation | UAT / release expectation |
| --- | --- | --- | --- |
| `P0` | Release-blocking critical path | Run all affected lower-layer suites and the listed critical-path Playwright coverage before review. | Run the full touched workflow on the exact UAT candidate SHA and repeat it as post-deploy smoke. |
| `P1` | High-value path with material regression risk | Run all affected lower-layer suites; add Playwright whenever the change crosses pages, personas, approvals, or plugin/core boundaries. | Run the touched workflow end to end in UAT and keep it in release signoff if it is part of the release scope. |
| `P2` | Localized or operationally important but non-primary path | Targeted lower-layer coverage is usually enough for PRs; browser coverage is only needed when UI behavior is the thing being changed. | Run focused smoke or exploratory validation only when the area changed. |

When a change touches multiple rows, use the **highest** gate and keep the coverage from every touched row.

## Core Domains

| Domain | Risk | Primary risks | Critical paths to prove | Required lower-layer coverage | Required E2E coverage | Suggested gate |
| --- | --- | --- | --- | --- | --- | --- |
| Member identity and account lifecycle | High | Login failures, brute-force/lockout regressions, broken password recovery, invalid registration or verification state, stale quick-login setup | Login/logout, forgot/reset password, registration handoff, verification queue actions, quick-login setup/reset when touched | PHPUnit unit coverage for authentication, registration, quick-login, token/lockout rules; PHP feature coverage for `MembersController` auth, reset, register, verify, and redirect behavior | Playwright for success/failure login flows and any changed browser-visible recovery or registration path | `P0` |
| Member records, branches, profiles, and notes | Medium | Data corruption, duplicate identity data, branch scoping leaks, wrong member visibility, import/edit regressions | Member create/edit/view, public vs authenticated profile boundaries, branch membership views, verification queue details when touched | PHPUnit unit coverage for table/service validation and search/filter rules; PHP feature coverage for CRUD, imports, search, and authorization boundaries | Playwright when profile editing, verification review, or branch/member navigation changes materially | `P1` |
| Roles, permissions, service principals, and impersonation | High | Authorization leaks, stale privileges, bad role timing windows, token issuance/revocation bugs, impersonation not restoring identity | Assign/revoke member roles, permission-scoped access, service principal create/token regenerate/revoke, impersonate/stop impersonating | PHPUnit unit coverage for policy/service permission resolution and token rules; PHP feature coverage for 403/200 boundaries, role changes, token lifecycle, and impersonation session behavior | Playwright for admin permission-boundary journeys and impersonation flows when browser behavior changes; API/service-principal paths can stay lower-layer unless UI is the regression surface | `P0` |
| Workflow engine and approvals | High | Invalid state transitions, lost approvals, bad approval-token handling, workflow publish/migration regressions, audit trail gaps | Trigger event -> workflow instance -> approval created -> approve/reject/reassign -> final state and history | PHPUnit unit coverage for workflow services, registries, state handling, trigger dispatch, and approval managers; PHP feature coverage for workflow definition, instance, and approvals endpoints | Playwright for any user journey that depends on approvals, including token/email entry points when touched | `P0` |
| Warrants, rosters, and warrant periods | High | Unauthorized access due to bad warrant state, date-window bugs, roster approval miscounts, revoke/deactivate failures | Pending roster/request -> approval -> active warrant -> revoke/expire; warrant period configuration; warrant listing filters when touched | PHPUnit unit coverage for warrant manager/date logic and roster business rules; PHP feature coverage for warrant/roster controllers, exports, and authz | Playwright for any changed issue/approve/revoke path, especially when officer or approval workflows feed it | `P0` |
| Gatherings, attendance, staffing, and calendar | Medium | Wrong event state, broken attendance counts, schedule/staff regressions, bad public/private event visibility, calendar/feed failures | Create/edit/cancel gathering, add activities, manage staffing/attendance, calendar/feed access, clone/schedule flows when touched | PHPUnit unit coverage for schedule, clone, and attendance services; PHP feature coverage for controllers, filters, public endpoints, and authorization | Playwright for steward/member event journeys when UI flow, attendance capture, or calendar interactions change | `P1` |
| Operational admin, reports, and communications | Medium | Broken exports, bad email template rendering, unsafe admin actions, missing backup/report health, config regressions that affect release readiness | App settings, reports/CSV/PDF entry points, email template edit/render paths, health/admin screens touched by the change | PHPUnit unit coverage for renderers/helpers/services; PHP feature coverage for admin/report routes, template save/preview, and permissions | Usually no dedicated Playwright beyond admin smoke unless the change is specifically a complex browser workflow | `P2` |

## Plugin and Integration Domains

| Domain | Risk | Primary risks | Critical paths to prove | Required lower-layer coverage | Required E2E coverage | Suggested gate |
| --- | --- | --- | --- | --- | --- | --- |
| Activities plugin: catalog and authorizations | High | Bad authorization request lifecycle, broken renew/retract/revoke rules, mobile card/API regressions, plugin/core permission mismatches | Request, renew, retract, revoke, and display authorizations on member/card surfaces | PHPUnit unit coverage for authorization managers, policies, and table rules; PHP feature coverage for controller actions, mobile/API responses, and workflow dispatch | Playwright whenever member request -> approval outcome -> card/profile visibility is affected | `P1` |
| Officers plugin: offices, departments, and assignments | High | Incorrect hire/release behavior, wrong office eligibility, missing downstream warrant or permission effects, reporting regressions for active officers | Hire/replace/release officer, office/department CRUD tied to assignments, roster/report visibility | PHPUnit unit coverage for officer managers and workflow/warrant integration rules; PHP feature coverage for hire/release/report controllers and authorization | Playwright for any changed hire/release/replacement path because it crosses authority, workflow, and warrant boundaries | `P0` |
| Awards plugin: recommendations and state transitions | High | Broken recommendation submission, invalid state transitions, wrong visibility of hidden states, reporting/export regressions, missing workflow dispatch | Public or authenticated recommendation submission, review/update, approval/disposition, member/gathering recommendation views | PHPUnit unit coverage for state rules, query/update/transition services, and table validation; PHP feature coverage for submission, grid filters, state changes, and authz | Playwright for any changed submit -> review -> disposition flow and for browser-visible permission splits | `P1` |
| Waivers plugin: requirements, submissions, and compliance | High | Missing required waivers, wrong gathering/activity attachment, upload/closure regressions, bad branch-based visibility, compliance state not updating | Configure waiver types, attach requirements to activities/gatherings, submit/upload waiver, verify gathering compliance and closure behavior | PHPUnit unit coverage for waiver rules/services and any requirement-mapping logic; PHP feature coverage for controllers, auth callbacks, and requirement visibility | Playwright whenever a changed flow blocks attendance or changes steward/member waiver submission behavior | `P0` |
| Queue and scheduled job administration | Medium | Stuck jobs, duplicate execution, missed workflow resume/deadline jobs, unsafe admin retry/delete behavior | Enqueue/run/retry/cancel job, queue process visibility, workflow resume/deadline tasks when touched | PHPUnit unit coverage for queue tasks and scheduling logic; PHP feature or CLI/integration coverage for queue admin routes and retry/cancel behavior | Browser E2E is usually unnecessary; use it only if queue administration UI itself changes substantially | `P1` |
| GitHub issue submission integration | Low | Permission leaks, broken outbound payload generation, bad operator error handling | Create issue from the KMP UI and verify success/failure handling when touched | PHPUnit unit coverage for payload/build rules and policy checks; PHP feature coverage for controller and permission boundaries | No dedicated Playwright by default; add one only if a user-facing submission flow changes materially | `P2` |

## Release Smoke Baseline for Touched `P0` Domains

If a release includes changes in any `P0` row, UAT and post-deploy smoke should include the touched domain's critical browser path plus these shared checks when relevant:

1. A seed user can authenticate and reach the application shell.
2. Any changed approval-producing flow creates or updates the expected approval state.
3. Any changed warrant-producing flow shows the expected active/pending/revoked result.
4. Any changed plugin/core boundary still renders the expected tabs, cards, or detail pages for the intended persona.

## Using This Matrix in Future Work

1. Identify every touched domain row before implementation.
2. Add the listed lower-layer suites first.
3. Add the listed Playwright coverage for every touched `P0` row and every `P1` row whose change crosses personas, pages, approvals, or plugin/core boundaries.
4. Carry the same rows into PR notes, UAT signoff, and production promotion so the verified contract never changes between environments.
