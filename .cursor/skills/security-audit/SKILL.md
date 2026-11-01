---
name: security-audit
description: Performs an authorized, tenant-aware KMP security review with safe static analysis and explicitly scoped dynamic testing.
---

# KMP security audit

Read and follow `.github/skills/security_audit/SKILL.md`. It is the canonical audit workflow.

Before any active testing, establish the exact commit, environment, tenant/platform hosts, accounts, rate limits, data-handling rules, and stop conditions. KMP uses host-resolved tenants plus a separate platform connection; PostgreSQL is the only supported platform database across local development, CI and managed hosting. Never run scanners, brute-force loops, resets, or destructive payloads without explicit authorization, and never print secrets or real tenant/member data.
