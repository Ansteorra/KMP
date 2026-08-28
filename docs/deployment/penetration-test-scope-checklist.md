---
layout: default
title: "Managed Platform Penetration Test Scope and Evidence Checklist"
description: "Planning checklist for tenant isolation, administration, storage, jobs, and security-test evidence."
---

# Managed Platform Penetration Test Scope and Evidence Checklist

This is a planning checklist for an authorized independent assessment. It is
not evidence that a penetration test has happened or passed.

[← Deployment and operations](README.md) |
[Launch readiness](launch-readiness-gate.md) |
[Security regression](security-regression-checklist.md)

## Current implementation baseline

Scope the test to the software and infrastructure that actually exist:

- tenant requests are resolved by host and use separate PostgreSQL tenant
  databases;
- document isolation uses tenant-specific containers/prefixes, while the Azure
  managed identity currently has storage-account-wide Blob Data Contributor;
- Platform Admin is a reserved-host surface in the same web app, protected by
  in-app password plus TOTP, account status/lockout, authorization, and a
  host-bound session;
- Platform Admin contains mutating operations and must not be described or
  tested as read-only;
- platform audit rows are hash chained in PostgreSQL;
- the Azure Blob WORM sink is not implemented and the managed template does not
  provision immutable audit storage;
- the tenant trust dashboard and public status page are roadmap items; and
- release-manifest/canary/nightly-drill commands are optional rehearsal tools,
  not steps in the active Azure deployment workflow.

If the engagement claims external WORM, escrow, private-network, recovery-region,
or public-trust controls, attach proof that those controls were separately
deployed. Otherwise record them as gaps or out of scope, not as application
features.

## Required objective

Validate resistance to tenant isolation bypass, account compromise, privilege
escalation, data exposure, and control-plane abuse before production activation.
Testing requires written authorization, approved rules of engagement, a defined
window, stop contacts, and explicit approval before touching production data.

## In-scope surfaces

| Surface | Minimum coverage | Evidence |
| --- | --- | --- |
| Tenant web app | Authentication, authorization, CSRF, XSS, injection, IDOR, file access, sessions, password reset, TOTP | Executed cases, routes, findings, retest |
| Tenant isolation | Host resolution, connection switching, cross-tenant object IDs, cache/session/queue keys, document names and access | Tenant-A-to-B negative tests and unknown-host results |
| Platform Admin | Reserved-host enforcement, password/TOTP, lockout/status, session host binding, policy checks, mutating action confirmation, audit/redaction | Host/session/auth bypass attempts and privileged-action samples |
| Provisioning/migration | Tenant creation, exact managed migration chain, recovery markers, suspended-tenant behavior; optional manifest/canary only if enabled | Non-production transcript and failure-path evidence |
| Backup/restore | Tenant `.json.gz.enc` and platform `.pgdump.enc` metadata, recovery-key export, suspension/TOTP/confirmation gates, URI redaction | References and hashes only; no archives, keys, or customer records |
| Secrets/encryption | Database secret store, master-key boundary, wrapped backup keys, rotation/failure behavior | Configuration review with plaintext material excluded |
| Audit/evidence | Database audit hash chain; external immutable sink only when separately provisioned | Audit samples plus external storage proof when claimed |
| Jobs/health | Unified three-minute worker, schedule claims, queue isolation, `/livez` versus `/health`, safe errors | Authorization, contention, redaction, and failure tests |
| Infrastructure | TLS/SNI, public ingress, PostgreSQL firewall, storage/database RBAC, Key Vault references, OIDC and protected environments | Approved configuration exports or screenshots |

## Out of scope unless separately approved

- denial-of-service or load testing against shared infrastructure;
- social engineering, phishing, or physical access;
- destructive restores or production data modification;
- exfiltration of full database exports; and
- access to passwords, tokens, connection strings, recovery keys, plaintext
  KEKs, or escrow shares.

## Preconditions

- [ ] Rules of engagement and written authorization are approved.
- [ ] Environment, tenant slugs, hosts, test accounts, IP ranges, and stop
      contacts are recorded.
- [ ] Production customer data is excluded unless explicitly approved.
- [ ] Commit, release tag, immutable image digest, and deployed migration state
      are recorded.
- [ ] Two-tenant host-resolution evidence is current.
- [ ] The active migration-chain result is attached.
- [ ] Optional manifest/canary/drill evidence is included only if that control is
      configured and claimed.
- [ ] Fresh tenant and platform backup metadata and a safe restore plan are
      attached.
- [ ] External WORM, escrow, or recovery-region evidence is attached only when
      independently verified.
- [ ] Monitoring and incident contacts are staffed.

## Evidence and disposition

Store redacted evidence in the approved restricted repository. Do not paste raw
records, exports, object URLs with credentials, keys, tokens, recovery codes, or
secret-bearing command output into tickets.

| Evidence | Launch rule |
| --- | --- |
| Signed authorization and surface inventory | Missing critical scope blocks the test/launch review |
| Finding report with severity and affected surface | Critical findings block launch |
| Critical/High retest evidence | Required after remediation; High deferral needs time-bound approval |
| Tenant-isolation negative tests | Any confirmed cross-tenant access is no-go |
| Platform Admin host/auth/session tests | Tenant-host reachability or cross-host session reuse is no-go |
| Secret and error redaction | Plaintext secret exposure is no-go |
| External-control proof | An unproven control cannot appear in customer claims |
| WCAG/security UI regression evidence | Required when assessed UI changed |
| Residual-risk record | Owner, mitigation, customer impact, expiry, and approvers required |

All Critical findings must be fixed and retested. High findings must be fixed
and retested or explicitly accepted by the Platform Owner and Security Lead.
Every other finding needs an owner and due date.
