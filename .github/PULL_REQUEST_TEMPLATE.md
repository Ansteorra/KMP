## What changed

Summarize the user-visible or architectural outcome and link the issue or specification it addresses.

## Verification

List the exact commands and manual checks run. Choose the narrowest useful checks from `AGENTS.md` and `app/AGENTS.md`; use `cd app && bash bin/verify.sh` for cross-cutting changes when practical.

- [ ] Relevant PHPUnit, Jest, build, PHPCS, PHPStan, or Playwright checks pass
- [ ] New or changed behavior has appropriate regression coverage
- [ ] Generated files, secrets, and unrelated formatting are not included

## Architecture and safety

- [ ] Authorization uses project policies/scopes and does not bypass restore locks, impersonation logging, CSRF, or security-token handling
- [ ] Tenant-scoped behavior was checked for cross-tenant isolation; platform operations remain on the platform connection
- [ ] Plugin behavior stays inside its plugin and integrates through the established registries
- [ ] Migrations are reversible where supported and were added in the owning core or plugin migration directory
- [ ] User-facing changes meet WCAG 2.2 Level AA expectations, including keyboard operation, focus, labels, announcements, contrast, and non-color cues

## Documentation and release impact

- [ ] Owning documentation and the applicable `AGENTS.md` chain are current
- [ ] `app/CHANGELOG.md` was updated when this is a release candidate or materially user-facing change
- [ ] Deployment or configuration implications are called out below

## Notes for reviewers

Highlight risky paths, tenant/host combinations, migration effects, operational steps, and anything not verified locally.
