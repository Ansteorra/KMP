# 2026-02-10: Documentation Accuracy Review

**Requested by:** Josh Handel

## Who Worked

- **Kaylee** — Backend docs (architecture, core modules, services)
- **Mal** — Plugin docs (all plugin documentation)
- **Wash** — Frontend/UI docs (JavaScript, asset management, UI components)
- **Jayne** — Testing, setup, deployment, and misc docs

## What Was Done

Full team documentation review: all 96 docs in `/docs/` verified against actual codebase.

| Reviewer | Docs Reviewed | Docs With Issues | Notes |
|----------|--------------|-----------------|-------|
| Kaylee | 25 | 9 | Architecture, core modules, services |
| Mal | 36 | 8 | Plugin documentation |
| Wash | 14 | 10 | 39 individual inaccuracies found |
| Jayne | 20 | ~10 | Critical testing doc errors found |
| **Total** | **96** | **~30** | |

## Key Findings

### Worst Offenders

1. **10.4-asset-management.md** — 10 inaccuracies (wrong output filenames, wrong KMP_utils implementations, non-existent npm scripts, SCSS vs CSS mismatch)
2. **7-development-workflow.md & 8-development-workflow.md** — Duplicates with wrong test suite names, wrong base class patterns, non-existent methods/scripts
3. **5.7-waivers-plugin.md** — Severely outdated plugin structure (missing controllers, entities, tables, policies, JS controllers; includes non-existent files)
4. **7.3-testing-infrastructure.md** — Wrong constants (KINGDOM_BRANCH_ID says 1, actual 2; TEST_BRANCH_LOCAL_ID says 1073, actual 14), wrong test suite names, recommends deprecated trait

### Critical Patterns Found Across Docs

- Session timeout inconsistency: some docs say 30 min (correct), others say 240 min (wrong)
- PHP version references range from 8.0 to 8.3 (actual: 8.3)
- Multiple docs reference non-existent commands (`bin/cake security generate_salt`, `StaticHelpers::logVar`, `npm run lint`)
- Test suite names wrong in multiple docs (`unit`/`integration` vs actual `core-unit`/`core-feature`)
- 8-development-workflow.md is a complete duplicate of 7-development-workflow.md

### Accurate Areas

- Timezone handling docs (10.3.x series) — fully accurate
- Activities plugin docs (5.6.x series) — accurate
- Awards policy docs (5.2.5–5.2.15) — accurate
- Docker development docs — accurate
- Seed documentation, active window sync, youth age-up — accurate

## Decisions Made

- All 4 agents submitted review findings to decisions inbox
- Jayne also submitted skipped test triage decisions
- Kaylee submitted BasePolicy array fix decision

## Outcomes

- 96 docs reviewed, ~30 have substantive inaccuracies
- Findings documented in decisions inbox for future fix prioritization
- No doc corrections made in this session — review only
