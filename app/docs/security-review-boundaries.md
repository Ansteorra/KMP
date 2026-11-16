---
layout: default
title: Security review boundaries
---

# Security review boundaries

These contracts address the actionable 2026-11-10 static review. They do not enable
Turbo Drive or live delivery, change applied migrations, or deploy a release.

## Approval reads

Approval details require entity authorization before context rendering, including response
comments and requester information. Current eligible pending approvers and members with a
recorded response may read the approval. Candidate discovery requires current pending
eligibility; historical access does not permit discovery. Reassignment discovery remains
administrator-only through the existing BasePolicy exception. Requesters and unrelated
members receive a generic 403. Missing records return 404. Eligibility is evaluated against
the current tenant on every request; reassignment revokes the previous assignee immediately.
Service-principal IDs cannot stand in for member IDs.

## Tenant mail configuration

Platform Admin validates email changes in tenant creation, registry edits, and configuration
edits before writing or queuing provisioning. Changed email settings require typed
`EMAIL <slug>` confirmation, an audited reason of at least ten characters, and a fresh,
atomically consumed TOTP. An email change rolls back if its audit event cannot be recorded.
Unchanged email settings do not require step-up for unrelated edits.

Only exact tenant-local mail names are accepted (optionally prefixed by `secret://` or `db://`):

| Provider | Allowed names under `tenant.<slug>.` |
| --- | --- |
| SMTP | `smtp-password`, `email.smtp_password` |
| SendGrid | `email-api-key`, `sendgrid-key`, `email.sendgrid.api_key` |
| Resend | `email-api-key`, `resend-key`, `email.resend.api_key` |
| Azure Communication Services | `acs-connection`, `email.azure.connection_string` |

API endpoints are blank (provider default) or exactly the provider's mail URL:
`https://api.sendgrid.com/v3/mail/send`, `https://api.eu.sendgrid.com/v3/mail/send`, or
`https://api.resend.com/emails`. Cross-provider endpoints, custom hosts, query strings,
raw environment references, platform credentials, and other tenants' names are rejected
before any secret lookup. SMTP hosts remain an explicit privileged configuration choice
with step-up; network egress policy remains an operator responsibility.

The runtime repeats validation for existing stored overrides. Invalid configurations use
a rejecting transport: unrelated tenant requests remain usable, but mail cannot silently
fall back to platform credentials. Operators must inventory existing custom overrides,
provision approved tenant-local mail references, and correct incompatible settings before
rollout. Platform default remains available for shared platform mail. Tenant scope exit
restores the prior mail configuration, including after rejection.

## Tenant metadata freshness

Host maps expire after thirty seconds, even if the underlying cache retains an entry longer.
A versioned envelope prevents reuse of old long-lived maps. Transactional reads never publish
uncommitted metadata. Lifecycle and configuration invalidation runs after the outer platform
transaction commits; rolled-back changes publish nothing. Another replica with a local file
cache may retain the old mapping/status for at most thirty seconds. Use shared Redis where
immediate cross-replica invalidation is required. No Redis/session migration is performed here.

## Reverse proxy trust

`TRUST_PROXY=true` alone grants no trust. Generic deployments must set `TRUSTED_PROXY_IPS`
to explicit proxy IPs or CIDRs; empty, malformed, and `/0` entries do not grant trust.
The client address is the first untrusted hop walking `X-Forwarded-For` from the right.
Only a trusted direct peer may supply TLS metadata. Alternate client-IP headers and forwarded
hostnames never select a tenant or client identity.

Azure templates set `KMP_PROXY_MODE=azure-container-apps`. This mode additionally requires
the runtime's `CONTAINER_APP_NAME` and a private direct ingress peer. Azure HTTP ingress
appends the authoritative client address on the right; earlier entries remain untrusted.
See [Microsoft's ingress header contract](https://learn.microsoft.com/en-us/azure/container-apps/ingress-overview#http-headers).
Do not enable this mode for raw TCP ingress, arbitrary reverse proxies, or untrusted direct
network access. Restrict direct application access to the managed ingress. Rate limits and
audit records consume CakePHP's normalized `clientIp()` after this middleware.

## Regression checks

Approval HTTP/policy tests cover same-branch and cross-branch denial, assigned approvers,
respondents, administrators, reassignment, missing records, and service-principal collisions.
Mail tests cover all edit routes, fresh/replayed MFA, secret lookup denial, actual delivery
rejection, provider endpoints, and scope restoration. Cache tests include independent
PostgreSQL connections for commit/rollback and host remapping. Proxy tests cover IPv4/IPv6,
spoofed chains, distinct clients behind a proxy, and disabled/unconfigured trust.
Authentication tests cover account lockout and CSRF-protected logout without changing GET state.

Run the suites on isolated PostgreSQL databases and the browser checks on the isolated local
tenant stack. See [authentication security](authentication-security.md) and the existing
[security remediation register](security-remediation-log.md). Production acceptance remains
part of the established release workflow; passing local checks is not a production release.
