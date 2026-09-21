# Grid email subscriptions

Members can choose **Email this view** in My Approvals, My To-Dos, Warrant Rosters,
and the main Bestowals grid. The selected view, filters, search, and sorting are
retained. A subscription can run daily, every three days, or weekly; its first
check is due after that interval. The scheduler checks due subscriptions every
15 minutes. Empty results are skipped. Emails contain up to 50 visible rows and
a link to the full view. **Email subscriptions** on the member's own profile
lists active/stopped subscriptions and lets the owner cancel them.

**Send me a sample** uses the currently applied view settings and sends immediately
to the signed-in member's account email, without saving a view, adding a
subscription, or changing any existing delivery dates. An empty sample explains
why scheduled emails would be skipped. Samples use the same report authorization
and mailer as recurring delivery and are limited to five requests per member per
tenant in a five-minute window.

## Kingdom email customization

**Email Templates → Grid Email Summary** controls the subject, Markdown/HTML body,
and plain-text body for both samples and recurring summaries. **App Settings →
Email.GridSubscriptionTemplate** selects the template by slug (default:
`grid-email-summary`). A kingdom can edit the default or create another active
template and put its slug in this setting. No workflow is involved.

The deployment migration creates missing defaults for every tenant, including
POC and newly provisioned kingdoms, and preserves existing settings/templates.
No manual template installation is needed. Rollback retains these tenant-owned
records. Missing, inactive, or invalid selected templates fail delivery visibly
and follow normal scheduled-job retry handling; there is no hard-coded fallback.

Available variables are listed in the template editor. Use `resultsTable` in the
Markdown body for the escaped table and `resultsText` in the plain-text body.
`summaryName`, `gridLabel`, `rowCount`, `rowLimit`, `viewUrl`, `manageUrl`,
`siteTitle`, and `siteAdminSignature` provide labels, counts, links, and branding.
`isSample`, `hasRows`, and `isEmpty` support conditional wording. Templates only
receive the currently authorized, bounded report; editing the email cannot add
columns or bypass data permissions. **Send me a sample** previews the currently
selected template and current view together.

## Delivery and access

Settings live in the tenant's `grid_subscriptions` table. The platform schedule
`grid-subscriptions` runs `grid_subscriptions_enqueue` for active tenants; the
single-tenant Docker scheduler runs the same command. The command claims up to
50 due rows and queues `GridSubscription` tasks containing only a subscription
ID and a random claim token. Claims expire after one hour to recover abandoned
work. Stale tokens and jobs whose subscription was deleted are harmless.

The task reloads the member and invokes the registered read-only grid action in
an isolated controller container with a memory-only session. Normal controller
authorization, restore locks, row scopes, selected columns, and cell formatting
remain in force. The current grid output is converted to display text and sent
immediately by the claimed task, not stored in another email job. Account
ineligibility, deleted/inaccessible saved views, and denied grid access stop the
subscription. Reduced row scope is reflected in each report; an empty personal
queue skips delivery. Transient failures use normal queue retries.

A row lock serializes cancellation and delivery. A cancellation takes effect
before the next delivery starts; an email already being sent cannot be recalled.
As with other SMTP delivery, transport success followed by database/process
failure can cause a duplicate on retry. No exactly-once transport guarantee is
claimed. Links retain the tenant origin resolved when the subscription was made.
Recipients always use the member's current account email address.

## Extending supported grids

`GridSubscriptionRegistry` is a server-owned allowlist. Plugins register their
own grid key, label, read-only data route, and page route at bootstrap. Review
both authorization and field visibility before adding a grid. Data actions must
return `data`, `columns`, `visibleColumns`, `gridKey`, and `gridState` view variables
and must work without a persistent browser session. They must respect the
standard pagination limit. Never accept a route, callback, SQL, recipient, or
origin from the subscription request body.

Run the subscription HTTP/service tests, grid JavaScript tests, and the local
browser acceptance check when changing this workflow. Test cancellation,
permission loss, tenant boundaries, empty results, and retries as well as email
content. Browser/queue acceptance must use synthetic local recipients and
Mailpit; do not trigger production emails for verification.
