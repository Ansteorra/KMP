---
description: Build or refresh the canonical KMP release section in app/CHANGELOG.md from source-checked git history
---

# Sync the KMP changelog

Update `app/CHANGELOG.md` with concise, user-facing release notes. The changelog is displayed in the application and its selected release section is used verbatim for GitHub Release notes.

## Inputs

Honor `--dry-run`, `--since COMMIT`, and `--since-date YYYY-MM-DD` when supplied. Otherwise read the current changelog markers and existing release sections to determine the review boundary. Confirm the candidate commit explicitly when preparing a release.

## Workflow

1. Read `AGENTS.md`, `app/AGENTS.md`, `app/CHANGELOG.md`, and `.github/skills/release-deploy/SKILL.md`.
2. Resolve the start and end commits, then inspect both commit messages and relevant diffs. A vague message such as `fix` is not a reason to exclude a real user-visible correction.
3. Include user-visible features, fixes, security improvements, administrator capabilities, migrations with operational impact, and meaningful compatibility changes.
4. Exclude internal refactors, tests, tooling, and dependency churn unless they materially affect users or operators.
5. Consolidate related commits and remove entries already represented in the changelog.
6. Write from the user's perspective without exposing implementation details, vulnerabilities, credentials, tenant data, or unreleased secrets.
7. For a release candidate, create one canonical section headed exactly:

```markdown
## KMP <version without leading v> — <Month Day, Year>
```

8. Keep entries inside that section in the project's established format. Update sync markers only after the reviewed range is correct.
9. Re-read the exact section as a standalone set of release notes and show a concise summary of the commit range and entries created.

## Release contract

Do not publish, tag, deploy, or move branches as part of a changelog-only request. During a release, the canonical section must already exist in the candidate commit before POC validation; the release workflow checks that GitHub Release notes match it exactly.
