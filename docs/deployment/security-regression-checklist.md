# Managed Platform Security Regression Checklist

Run this checklist for every release candidate and for any hotfix that touches authentication, authorization, tenant resolution, platform admin, storage, backup/restore, audit, migration, or customer-facing UI. It complements the required external penetration test scope; it does not replace an independent assessment.

[← Back to Deployment Guide](README.md) | [Launch Readiness Gate](launch-readiness-gate.md)

## Required automated checks

| Check | Command or evidence | Owner | Blocking rule |
|-------|---------------------|-------|---------------|
| PHPUnit focused security/platform tests | `composer test` or targeted test class output | Developer/QA | New failures block release |
| JavaScript tests | `npm run test:js` when JS changed | Developer/QA | New failures block release |
| Build | `npm run dev` or production build command used by CI | Developer/QA | Build failure blocks release |
| PHPCS changed PHP | `vendor/bin/phpcs` on changed PHP or `bin/verify.sh` | Developer | New style errors in changed PHP block release |
| PHPStan/static analysis | `vendor/bin/phpstan analyse` or `bin/verify.sh` | Developer | New relevant errors block release |
| Dependency audit | Composer/npm audit or approved scanner result | Security Lead | Critical exploitable dependency blocks release |
| Trust docs presence | `bash bin/trust_readiness_check.sh` from `app/` | Platform Lead | Missing required trust doc blocks launch review |

## Tenant isolation regression

- [ ] Host-based tenant resolution rejects unknown hosts and maps known hosts to exactly one active tenant.
- [ ] Tenant A cannot access Tenant B records through IDs, slugs, query parameters, cached values, uploads, backups, restore-drill rows, jobs, trust evidence, or admin pages.
- [ ] Queries and services derive tenant scope from server-side tenant context, not user-supplied tenant IDs.
- [ ] Tenant-scoped storage paths/containers do not expose other tenant objects.
- [ ] Cache, session, queue, and audit keys include tenant context where needed.
- [ ] Missing tenant context fails closed.

## Authn/authz regression

- [ ] Anonymous users cannot reach authenticated tenant or platform admin routes.
- [ ] Tenant users cannot reach platform admin routes.
- [ ] Platform admin routes require the external identity gate and allowed operator status.
- [ ] Spoofed platform admin headers are stripped or rejected by ingress before app trust.
- [ ] CSRF protection remains active for state-changing forms.
- [ ] Password reset, TOTP/MFA bootstrap, impersonation, and logout flows do not weaken session boundaries.

## Data handling and secrets regression

- [ ] Logs, exceptions, flash messages, API responses, and templates do not include passwords, tokens, connection strings, SAS URLs, wrapped DEKs, plaintext KEKs, Shamir shares, recovery codes, or raw job errors.
- [ ] Backup object URIs and storage details are redacted from tenant-visible pages.
- [ ] Evidence packages contain links and checksums, not raw database exports or customer-private records.
- [ ] Secret-store and KEK escrow code paths fail closed when configuration is incomplete.
- [ ] WORM audit writes are preserved for platform admin, tenant provisioning, backup, restore, migration, and break-glass actions.

## Backup, restore, DR, and migration regression

- [ ] Tenant backup command records retention, hash, status, and redacted errors.
- [ ] `tenant restore_drill` remains non-destructive by default and requires explicit destructive confirmation before real restore.
- [ ] Platform metadata restore precedes tenant restore in runbooks and drills.
- [ ] Tenant migration canary and nightly migration drill remain gated against accidental production use.
- [ ] Release manifest compatibility blocks unsupported schema rollouts.
- [ ] Rollback marker/PITR evidence is captured before tenant migrations or pilot imports.

## Customer-facing UI and WCAG 2.2 AA regression

For changed pages, modals, dashboards, trust cards, admin pages, and forms:

- [ ] Semantic landmarks/headings describe the page structure.
- [ ] All interactive elements are keyboard reachable and operable without pointer-only actions.
- [ ] Visible focus indicators are not removed or obscured.
- [ ] Form controls have programmatic labels, validation messages, and error summaries where appropriate.
- [ ] Status is conveyed with text/icons in addition to color; color alone is never the only signal.
- [ ] Text and meaningful icon contrast meet WCAG 2.2 AA thresholds.
- [ ] Dynamic updates announce important status changes or preserve focus predictably.
- [ ] Tables/cards preserve reading order and responsive behavior at mobile, tablet, and desktop widths.
- [ ] Links and buttons have accessible names that make sense out of context.
- [ ] Security-sensitive confirmation dialogs identify the tenant/action and require deliberate confirmation.

## Manual smoke pack

- [ ] Login and logout as tenant admin, normal member, and platform admin test users.
- [ ] Verify a tenant admin cannot access another tenant by changing host, slug, query string, or object ID.
- [ ] Verify platform admin read-only pages do not show DB passwords, DB roles, object URIs, wrapped keys, raw errors, or tenant-private details beyond approved summaries.
- [ ] Create or view a record that writes audit events; confirm audit/WORM continuity evidence.
- [ ] Run backup/restore-drill commands in a safe environment and verify no destructive action occurs by default.
- [ ] Review browser console/network output for leaked secrets or cross-tenant payloads.

## Evidence to attach

- Test command output or CI links.
- Screenshots or notes for WCAG 2.2 AA checks on changed UI.
- Tenant isolation negative-test results.
- Security finding retest links.
- Risk acceptances with owner, expiry, and mitigation.
