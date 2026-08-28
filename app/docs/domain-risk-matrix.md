# Domain risk and coverage matrix

Use this matrix with [`testing-suite.md`](./testing-suite.md) to select proportional
verification. When a change touches multiple rows, use the highest gate and keep the coverage
from every row.

| Gate | Meaning | Expected evidence |
| --- | --- | --- |
| `P0` | Release-blocking trust or critical path | Targeted lower-layer tests, the complete affected browser/CLI journey, and UAT/post-deploy smoke on the release candidate |
| `P1` | Material product regression risk | All affected lower-layer suites; browser coverage when the change crosses pages, personas, approvals, or plugin/core boundaries |
| `P2` | Localized behavior or operator tooling | Focused lower-layer coverage; browser verification when UI behavior is the subject of the change |

## Platform and isolation boundaries

| Domain | Gate | Primary failure modes | Minimum coverage |
| --- | --- | --- | --- |
| Tenant host resolution and connection lifecycle | `P0` | Unknown/inactive/behind tenant accepted, platform failure not closed, connection or table locator leaked between requests | Middleware status cases; `TenantConnectionManager` enter/restore and failure cleanup; two-host positive/negative isolation journey |
| Platform identity and tenant identity | `P0` | Authentication domains mixed, session/host boundary weakened, platform action reachable by tenant identity, service-principal scope bypass | Policy and authentication tests for each identity type; host-bound platform and tenant browser checks; lockout/TOTP/token cases when touched |
| Tenant provisioning and lifecycle | `P0` | Partial database/secret creation, activation before schema readiness, illegal transition, destructive operation against wrong tenant | Command/service failure rollback, advisory-lock/conflict cases, provisioning browser lane, explicit tenant selector and confirmation tests |
| Fleet migrations and schema state | `P0` | Tenant skipped, unexpected migration accepted, suspended tenant omitted unintentionally, release proceeds with schema drift | Migration catalog unit tests; active and suspended fleet cases; fail-fast/continue behavior; exact candidate deployment evidence |
| Secrets, backups, and restores | `P0` | Secret disclosure, wrong tenant key, unencrypted artifact, restore into active/wrong tenant, unusable backup | Redaction and secret-chain tests; encryption/wrapping tests; tenant and platform restore drills; negative selector/confirmation cases |
| Central jobs, tenant queues, and schedules | `P0` when mutating data; otherwise `P1` | Job executes without tenant context, duplicate authority, suspended tenant processed, retry crosses tenant | Context-capture/restore, idempotency, lease/retry, active-tenant selection, and queue/schedule integration tests |
| Shared documents, cache, mail, and telemetry | `P0` for isolation; otherwise `P1` | Cross-tenant object/cache key, wrong tenant sender, PII/secret in logs or metrics | Two-tenant key/path assertions, mail configuration restoration, privacy-safe telemetry tests |

A tenant-domain test using the ordinary `test` datasource does not prove isolation. Any change
to a platform/tenant boundary needs a test that names both sides of that boundary.

## Product domains

| Domain | Gate | Critical behavior to prove |
| --- | --- | --- |
| Member authentication, recovery, registration, and verification | `P0` | Success and failure paths, token/lockout rules, authorization and redirect behavior |
| Roles, permissions, impersonation, and service principals | `P0` | Grant/revoke timing, 403/200 boundaries, token lifecycle, original identity restoration and audit |
| Workflow engine and approvals | `P0` | Trigger to instance to eligible approval response to terminal state; concurrency, tokens, reassignment, and history |
| Warrants and rosters | `P0` | Request/roster approval, active window, revoke/expire, and downstream authorization effects |
| Gatherings, attendance, staffing, calendar, and documents | `P1` | Public/private visibility, create/edit/cancel, attendance/staffing, feed/download, document scope |
| Member/branch records, profiles, notes, imports, and reports | `P1` | Branch scope, validation, search/import behavior, public/authenticated visibility |
| App settings, templates, exports, and operational admin UI | `P2`, promoted when touching a trust boundary | Permission checks, safe rendering, output contract, destructive-action confirmation |

## Plugin domains

Only loaded product plugins belong in release coverage. `Template` is a development skeleton
and is not an active surface.

| Domain | Gate | Critical behavior to prove |
| --- | --- | --- |
| Activities authorizations | `P1`, or `P0` when authorization is granted/revoked | Request, renew, retract, revoke, workflow outcome, member/card visibility |
| Officers assignments | `P0` | Hire/replace/release, office eligibility, permissions, warrants, reports |
| Awards recommendations and bestowals | `P1`, or `P0` for approval/visibility changes | Submit, scoped review, feedback/decision state, court/bestowal completion, hidden-state access |
| Waiver requirements and submissions | `P0` | Requirement attachment, member/steward submission, upload/document scope, compliance and closure |
| Queue administration | `P1` | Enqueue/run/retry/cancel behavior and permission boundary; use browser coverage only when its UI changes |

## Applying the matrix

1. Name every affected row in the work item or review notes.
2. Cover business rules and failure branches at the lowest useful layer.
3. Add HTTP tests for routes, policies, serialization, redirects, and form handling.
4. Add Jest for changed browser logic and Playwright for integrated `P0` paths or cross-persona
   `P1` paths.
5. Carry the same critical-path assertions through POC/UAT and post-deploy smoke. Do not swap
   the candidate commit or image after validation.
