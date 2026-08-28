---
name: security-audit
description: Perform an authorized, tenant-aware KMP security review using safe static analysis and explicitly approved dynamic testing.
---

# KMP security audit

Read `AGENTS.md`, `app/AGENTS.md`, the applicable subtree guides, and the deployment security checklists before testing.

## Scope and authorization

Establish in writing:

- target commit, environment, hostnames, and allowed test accounts;
- whether testing is static only or includes active requests/scanners;
- permitted rate, time window, data handling, and stop conditions;
- whether tenant, cross-tenant, and platform-admin surfaces are all in scope.

Do not run dynamic probes, brute-force loops, destructive payloads, dependency upgrades, database resets, or external scanners without explicit authorization. Never test production by inference from a general “audit” request.

## Architecture context

- KMP is CakePHP 5 on PHP 8.4 with Stimulus, Bootstrap, Vite, and targeted Turbo Frames; Turbo Drive is disabled.
- Local Docker Compose and deployment use PostgreSQL. The dev container retains MariaDB compatibility tooling, so review database-specific code rather than assuming a single engine.
- Tenant resolution is hostname-based. Audit `TenantResolutionMiddleware`, `TenantConnectionManager`, `TenantAwareCache`, tenant document storage, queues, backups, and platform jobs for isolation.
- Platform registry and fleet operations use a separate platform connection. Verify tenant requests cannot fall back to or influence platform scope.
- Local examples are `kmp.localhost:8080`, `kmp2.localhost:8080`, and `platform.kmp.localhost:8080/platform-admin`. Recheck current seeds before using accounts; the shared test password is disposable local/POC data only.

## Review phases

1. **Static trust-boundary review**: authentication, authorization policies/scopes, IDOR, tenant connection selection, cache keys, background jobs, document paths, restore locks, impersonation, CSRF/FormProtection, validation, escaping, uploads, command execution, SSRF, deserialization, secrets, logging, and cryptography.
2. **Dependency and configuration review**: run `composer audit` and `npm audit` only when network and lockfile access are approved; inspect debug, cookies, session, proxy, CORS, headers, storage, and environment handling without printing secret values.
3. **Targeted tests**: prefer existing PHPUnit, Jest, and Playwright coverage. Add tests that prove cross-tenant denial and platform/tenant connection separation where gaps exist.
4. **Dynamic validation**: only against the authorized host and test data. Use rate-limited, non-destructive requests and preserve CSRF/session mechanics; raw curl examples are not proof of an authorization flaw.
5. **Report and retest**: record evidence, affected boundary, exploit preconditions, tenant impact, remediation, and a safe regression test.

Use `rg` rather than copying broad grep recipes. Store sanitized reports under the repository's ignored `security-reports/` directory unless the user selects another protected location.

## Severity

Base severity on demonstrated impact and reach: confidentiality, integrity, availability, tenant breakout, platform compromise, authentication requirements, and exploitability. Distinguish confirmed findings from hypotheses and tool output.

See `docs/deployment/penetration-test-scope-checklist.md` and `docs/deployment/security-regression-checklist.md` for environment-specific controls.
