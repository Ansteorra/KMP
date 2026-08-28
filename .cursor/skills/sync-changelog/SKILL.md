---
name: sync-changelog
description: Builds or refreshes the canonical KMP release section in app/CHANGELOG.md from source-checked git history.
---

# Sync the KMP changelog

Read and execute `.github/prompts/sync-changelog.prompt.md` in full.

Inspect commits and relevant diffs, include meaningful user-visible fixes as well as features, consolidate duplicates, and preserve the established user-facing format. For a release candidate, the canonical heading is `## KMP <version without leading v> — <Month Day, Year>`. Do not tag, deploy, publish, or move branches as part of a changelog-only request.
