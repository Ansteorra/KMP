# Managed Platform Penetration Test Scope and Evidence Checklist

This checklist defines the third-party penetration test package required before managed multi-tenant production launch. It is a planning and evidence checklist only; it does not assert that a penetration test has already happened.

[← Back to Deployment Guide](README.md) | [Launch Readiness Gate](launch-readiness-gate.md) | [Security Regression Checklist](security-regression-checklist.md)

## Required test objective

Validate that the managed KMP platform resists tenant isolation bypass, account compromise, privilege escalation, data exposure, and operational-control-plane abuse before a production tenant is activated.

The test must be performed by an approved independent tester or security team with written authorization, a signed rules-of-engagement document, and a documented test window. Production customer data must not be targeted unless counsel, security leadership, and the affected customer explicitly approve it.

## In-scope surfaces

| Surface | Minimum coverage | Evidence required |
|---------|------------------|-------------------|
| Tenant web application | Authentication, authorization, CSRF, XSS, SQL injection, IDOR, file/document access, session handling, password reset/TOTP flows | Test plan, executed cases, affected routes, findings, retest results |
| Tenant isolation | Host-based tenant resolution, database-per-tenant boundaries, tenant document storage boundaries, tenant-scoped cache/session behavior | Attempts to access tenant B data from tenant A, query/API/browser evidence, negative results |
| Platform admin app | Isolated ingress, external identity gate, allowed-email enforcement, read-only posture, audit logging, redaction | Route inventory, auth bypass attempts, header spoofing tests, audit event samples |
| Tenant provisioning and migrations | Tenant creation, migration canary, release manifest compatibility, rollback marker behavior | Non-production command output and evidence links |
| Backups/restores | Backup metadata, restore drill planning, destructive restore guardrails, object URI redaction | Backup/restore evidence references only; no backup secrets or raw data |
| Secrets and encryption | Secret-store access patterns, wrapped-key handling, KEK escrow process, log redaction | Configuration review notes, redaction proof, no plaintext key material |
| WORM audit | Audit event integrity, hash-chain continuity, immutable mirror controls, fail-closed behavior | Continuity check results and immutable storage control-plane screenshots |
| APIs and background jobs | Queue/process admin routes, scheduled jobs, health/readiness endpoints, error handling | Authz tests, failed-job redaction, abuse-case notes |
| Infrastructure configuration | TLS, security headers, ingress segmentation, network restrictions, storage/database RBAC | Configuration screenshots or exported policy summaries |

## Out-of-scope unless separately approved

- Denial-of-service or load testing against shared infrastructure.
- Social engineering, phishing, or physical access attempts.
- Destructive database restores, production tenant modification, or data exfiltration.
- Access to plaintext KEKs, Shamir shares, recovery codes, passwords, tokens, or full database exports.

## Preconditions before testing starts

- [ ] Written authorization and rules of engagement approved by Platform Owner, Security Lead, and counsel if needed.
- [ ] Test environment, tenant slugs, hostnames, and accounts are documented.
- [ ] No real customer production data is present unless explicitly approved.
- [ ] Current release version, commit SHA, image digest, release manifest, and migration version are recorded.
- [ ] Two-tenant POC or equivalent host-resolution smoke evidence is attached.
- [ ] Tenant migration canary and nightly migration drill evidence is attached.
- [ ] Backup freshness, non-destructive restore drill, WORM audit continuity, and DR preflight evidence are attached.
- [ ] Platform admin and tenant admin test accounts have least privilege needed for the assessment.
- [ ] Monitoring, incident response, and test-stop contacts are staffed during the window.

## Evidence package

Store evidence in the release security ticket or approved evidence repository. Do not paste secrets, raw customer records, database exports, object SAS URLs, wrapped DEKs, plaintext KEKs, or Shamir shares.

| Evidence item | Owner | Required before launch? | Go/no-go rule |
|---------------|-------|-------------------------|---------------|
| Signed scope and authorization | Security Lead | Yes | No test without approval |
| Route/API/surface inventory | Platform Lead | Yes | Missing critical surface blocks launch |
| Tool versions and methodology | Tester | Yes | Must be reproducible enough for retest |
| Finding report with severity and affected tenant/surface | Tester | Yes | Critical/High findings block launch unless formally risk-accepted |
| Retest evidence for remediated findings | Security Lead | Yes for Critical/High | No launch until retest passes or risk acceptance is signed |
| Tenant isolation negative-test evidence | Tester | Yes | Any confirmed cross-tenant exposure is no-go |
| Platform admin gate evidence | Security Lead | Yes | Header spoofing or normal-tenant reachability is no-go |
| Secret/log redaction evidence | Platform Lead | Yes | Plaintext secrets in logs or pages are no-go |
| WCAG/security UI regression evidence | QA Lead | Yes when UI changed | Blocking accessibility/security regressions are no-go |
| Residual risk decision record | Platform Owner | Yes | Must list owner, expiry, mitigation, and customer impact |

## Minimum finding disposition

| Severity | Launch posture |
|----------|----------------|
| Critical | No-go until fixed and retested. |
| High | No-go unless Platform Owner and Security Lead sign a time-bound risk acceptance and customer impact is understood. |
| Medium | Owner and due date required before launch; Security Lead decides whether it blocks pilot expansion. |
| Low/Info | Track in backlog or hardening issue with owner. |

## Retest and closure

- [ ] All Critical findings are closed and retested.
- [ ] High findings are closed and retested, or have explicit time-bound risk acceptance.
- [ ] No tenant isolation, platform admin gate, WORM audit, backup, or secret-handling finding remains unresolved without written go/no-go approval.
- [ ] Findings that changed code include relevant PHPUnit/Jest/PHPCS/PHPStan or manual evidence.
- [ ] Final report is linked from [Launch Readiness Gate](launch-readiness-gate.md) evidence.
