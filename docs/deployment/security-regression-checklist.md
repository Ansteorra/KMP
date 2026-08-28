---
layout: default
title: "Managed Platform Security Regression Checklist"
description: "Regression checklist for tenant isolation, authorization, secrets, recovery, and accessibility."
---

# Managed Platform Security Regression Checklist

Run the relevant sections for every release candidate and for hotfixes that
touch authentication, authorization, tenant resolution, Platform Admin,
storage, secrets, backups, audit, migration, jobs, or customer-facing UI. This
checklist complements—not replaces—an independent assessment.

[← Deployment and operations](README.md) |
[Launch readiness](launch-readiness-gate.md)

## Baseline to test

Platform Admin is an in-app, reserved-host control surface with password plus
TOTP, lockout/status checks, authorization, and host-bound sessions. It is not a
separate app, does not trust an upstream identity header, and is not read-only.

The database audit chain is implemented. Azure immutable/WORM storage, a
recovery region, KEK escrow ceremonies, tenant trust pages, and a public status
page are not supplied by the current deployment. Test an external control only
when deployment evidence shows it exists.

## Automated checks

Run from `app/` unless a command says otherwise.

| Check | Command/evidence | Blocking rule |
| --- | --- | --- |
| PHP behavior | `composer test` or focused PHPUnit output | New relevant failure blocks |
| JavaScript | `npm run test:js` when JS changed | New failure blocks |
| Frontend bundle | `npm run dev` for development build; CI production-build result for release | Failure blocks |
| PHP style/static analysis | changed-file `vendor/bin/phpcs` and `vendor/bin/phpstan analyse --no-progress`, or `bash bin/verify.sh` | New relevant error blocks |
| Trust-doc contract | `bash bin/trust_readiness_check.sh` | Missing required document/topic blocks review |
| Azure runtime contract | from root: `bash deploy/azure/test-runtime-contract.sh` | Contract drift blocks managed deployment |
| Dependency/security scan | Approved Composer/npm and image-scanner evidence | Exploitable Critical finding blocks |

## Tenant isolation

- [ ] Known hosts resolve to exactly one active tenant; unknown or ambiguous
      hosts fail closed.
- [ ] Tenant A cannot access Tenant B records through IDs, slugs, APIs, cached
      data, jobs, backups, documents, or admin pages.
- [ ] Services derive scope from server-side tenant context, not a supplied
      tenant ID.
- [ ] Tenant database switching is reset safely across requests/jobs.
- [ ] Cache, session, queue, storage, and audit keys include tenant context where
      required.
- [ ] Logical storage container/prefix boundaries are enforced even though the
      Azure identity has account-wide data-plane rights.

## Authentication and authorization

- [ ] Anonymous and tenant users cannot reach Platform Admin routes.
- [ ] Platform Admin rejects non-allowed hosts before authentication.
- [ ] Password, TOTP, account-status, lockout, recovery, and logout flows fail
      safely.
- [ ] A Platform Admin session created on one allowed host cannot be replayed on
      another host or a tenant host.
- [ ] Privileged read and mutating actions enforce policy, CSRF, deliberate
      confirmation/reason, and TOTP where the operation requires it.
- [ ] Password reset and impersonation do not cross tenant/session boundaries.
- [ ] The application does not trust spoofed identity headers.

## Secrets, backups, audit, and recovery

- [ ] Managed configuration uses the database secret store and the expected
      master-key boundary; missing key/store configuration fails closed.
- [ ] Logs, errors, flash messages, APIs, and templates omit passwords, tokens,
      connection strings, object credentials, raw job errors, recovery keys,
      plaintext KEKs, and customer records.
- [ ] Tenant backups identify `backup_type=json` and `.json.gz.enc`; platform
      backups identify `backup_type=pg_dump` and `.pgdump.enc`.
- [ ] Tenant restore requires the documented status, TOTP, reason, and
      destructive confirmation gates.
- [ ] `tenant restore_drill` is non-destructive by default; destructive mode is
      exercised only in an approved disposable target.
- [ ] A pre-migration marker is treated as a logical backup, not provider PITR.
- [ ] The managed deployment runs the exact ordered migration chain and includes
      suspended tenants.
- [ ] Optional release-manifest or nightly-drill behavior is tested only when an
      environment deliberately configures it.
- [ ] Database audit hash chaining is verified.
- [ ] WORM retention/continuity is asserted only when an external immutable sink
      and storage policy are independently verified.

## WCAG 2.2 AA and UI security

For changed pages, forms, modals, grids, navigation, and asynchronous states:

- [ ] Semantic landmarks/headings and programmatic labels are correct.
- [ ] Every interaction is keyboard operable with visible, unobscured focus.
- [ ] Errors and async status are announced and focus order remains predictable.
- [ ] Meaning does not rely on color alone; contrast meets WCAG 2.2 AA.
- [ ] Links/buttons have meaningful accessible names.
- [ ] Responsive layouts preserve reading and interaction order.
- [ ] Destructive confirmations identify the tenant and exact action.
- [ ] Privileged pages redact secrets, object credentials, raw errors, and
      other-tenant details.

## Manual smoke and evidence

- [ ] Login/logout as a tenant admin, member, and Platform Admin test account.
- [ ] Attempt cross-tenant access by host, slug, query, and object ID.
- [ ] Exercise one privileged read and one privileged mutation.
- [ ] Create a platform audit event and verify its database chain; verify
      external WORM evidence separately if claimed.
- [ ] Plan a restore drill in a safe environment and confirm the default makes
      no data changes.
- [ ] Review browser console/network traffic for secrets and cross-tenant data.
- [ ] Attach CI output, negative-test results, UI accessibility notes, finding
      retests, and any time-bound risk acceptance.
