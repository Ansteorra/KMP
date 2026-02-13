# Session: 2026-02-12 — Badge Count Fix

**Requested by:** Josh Handel
**Agent:** Kaylee

## What was done

Fixed two bugs in `GatheringWaiversTable::countGatheringsNeedingWaivers()`:

1. **Permission mismatch:** Changed permission action from `'needingWaivers'` to `'uploadWaivers'` to match the list view controller.
2. **Date filter inversion:** Changed from showing future/ongoing gatherings to only past gatherings (`end_date < today`), so the badge reflects events that are over but still need waivers uploaded.

## Outcome

Badge count now matches the list view — a strict subset showing actionable past gatherings.

## Decisions

- See `decisions.md`: "Badge count query changed to past-only gatherings"
