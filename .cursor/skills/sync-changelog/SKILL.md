---
name: sync-changelog
description: Syncs app/CHANGELOG.md with meaningful user-facing changes from git history since last sync. Use when updating the release changelog or user mentions sync changelog.
---

# Sync Changelog

Update user-facing `app/CHANGELOG.md` from git history.

## Workflow

**Read and execute** `.github/prompts/sync-changelog.prompt.md` in full.

## Summary

1. Read `<!-- LAST_SYNCED_COMMIT -->` and `<!-- LAST_SYNCED_DATE -->` markers in `app/CHANGELOG.md`
2. `git log` from last sync to HEAD (no merges)
3. Include only user-facing features/improvements/security — exclude refactors, tests, CI, typos
4. Group related commits; write user-perspective titles
5. Update sync markers; add entries newest-first

Tags: `New Feature`, `Improvement`, `Security`, `Announcement`

Optional args: `--dry-run`, `--since COMMIT`, `--since-date YYYY-MM-DD`

Changelog displayed at `/pages/changelog`.
