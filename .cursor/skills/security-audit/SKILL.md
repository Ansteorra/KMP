---
name: security-audit
description: Performs static and dynamic security audits on KMP (SQL injection, XSS, auth, IDOR, headers, dependencies). Use for security review, penetration testing, or vulnerability assessment.
---

# KMP Security Audit

**Stack**: CakePHP 5.x, Stimulus, MySQL · **URL**: `http://localhost:8080` · **App**: `app/`

## Test users

| Email | Role |
|-------|------|
| admin@amp.ansteorra.org | Super admin |
| iris@ampdemo.com | Basic user |
| bryce@ampdemo.com | Local Seneschal |
| eirik@ampdemo.com | Kingdom Seneschal |

Password: `TestPassword`

## Phases

1. **Static analysis** — grep for raw SQL, unescaped output, missing auth, file uploads, secrets
2. **Dynamic testing** — curl auth enumeration, SQLi/XSS probes, CSRF, path traversal, headers
3. **Automated scanners** — `composer audit`, `npm audit`, nikto/nuclei if installed
4. **CakePHP-specific** — debug mode, FormProtection, ORM vs raw queries

Reports: `security-reports/` at repo root.

## Report format

| Severity | Category | Location | Description | Remediation |
|----------|----------|----------|-------------|-------------|

Severity: CRITICAL > HIGH > MEDIUM > LOW > INFO

## Full workflow

Complete grep commands, curl tests, and CakePHP checks: `.github/skills/security_audit/SKILL.md`

Also see `docs/deployment/penetration-test-scope-checklist.md` and `docs/deployment/security-regression-checklist.md`.
