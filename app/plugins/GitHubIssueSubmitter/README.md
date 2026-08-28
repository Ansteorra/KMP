# GitHubIssueSubmitter plugin

GitHubIssueSubmitter is an enabled KMP utility plugin that renders the footer's
feedback modal and creates issues in a configured GitHub repository. It is part
of the application and is not installed independently.

## Request flow

When `Plugin.GitHubIssueSubmitter.Active` is `yes`, the
`GitHubIssueSubmitter.IssueSubmitter` cell renders an anonymous form. It posts
to the plugin's `/git-hub-issue-submitter` route scope; the submit action:

1. permits unauthenticated access and intentionally skips policy authorization;
2. rate-limits the client IP to five attempts per hour through the tenant-aware
   cache;
3. HTML-escapes the submitted title and body;
4. sends the issue to the configured owner/project with `web` and selected
   feedback labels; and
5. returns the created issue number and URL.

The feature message must tell users that submissions become GitHub issues and
must not contain personal information or support requests.

## Configuration and tenant scope

The plugin reads these application settings:

| Setting | Purpose |
| --- | --- |
| `Plugin.GitHubIssueSubmitter.Active` | Per-tenant feature visibility |
| `Plugin.GitHubIssueSubmitter.PopupMessage` | Text shown above the form |
| `KMP.GitHub.Owner` | Repository owner |
| `KMP.GitHub.Project` | Repository name |
| `KMP.GitHub` → `Token` | GitHub API credential expected by the controller |

At request time, application settings and the rate-limit cache resolve in the
current tenant context. The controller currently expects the credential in
`KMP.GitHub` application settings; never commit, log, or display it. Moving
this token into the managed database-backed platform secret store requires a
code change because this controller does not read that store today.

## Current security boundary

This is a deliberately anonymous public endpoint. The IP rate limit is a basic
abuse control, not identity verification. The controller currently trusts the
posted feedback label and can return GitHub's upstream error message to the
browser. Treat server-side label allowlisting and fixed client-safe upstream
errors as open hardening work; do not document them as implemented.

Changes must preserve CSRF middleware behavior, bounded outbound timeouts,
credential redaction, and the no-PII warning.

## Development

Run from `app/`:

```bash
vendor/bin/phpunit plugins/GitHubIssueSubmitter/tests/TestCase
npm run test:js
```

The current PHP suite covers the request policy; controller changes need focused
HTTP tests in addition to the JavaScript controller tests.

See [GitHub issue submitter documentation](../../../docs/5.4-github-issue-submitter-plugin.md).
