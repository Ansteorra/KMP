---
name: "badge-list-consistency"
description: "Badge/notification counts must use identical permissions and be a subset of the list view they link to"
domain: "backend-ux-consistency"
confidence: "low"
source: "earned"
---

## Context
When a badge (notification count) links to a list view, the badge query and the list view query must stay in sync. If the badge counts items the list doesn't show (or vice versa), users see a number that doesn't match what they click into — a confusing and trust-eroding UX bug.

## Patterns

### Permission Action Alignment
The badge count method and the list view action MUST use the same permission check. If the list view gates on `'uploadWaivers'`, the badge must also gate on `'uploadWaivers'`, not a similar-sounding `'needingWaivers'`.

```php
// Badge count (model method)
$branchIds = $member->getBranchIdsForAction('uploadWaivers', 'Waivers.GatheringWaivers');

// List view (controller action) — MUST match
$branchIds = $currentUser->getBranchIdsForAction('uploadWaivers', 'Waivers.GatheringWaivers');
```

### Badge as Strict Subset
The badge count should be a **subset** of the list view results, not an independent query. The list view may show a broader set (e.g., past + upcoming items), but the badge should highlight the urgent/actionable subset (e.g., past items needing attention).

If the badge counts items the list doesn't show, users will never find what the badge is pointing at. If the list shows items the badge doesn't count, that's fine — the badge is an attention signal, not a total count.

### Shared Query Logic (Ideal)
When possible, extract the core query conditions into a shared method (on the Table class) that both the badge and list view call, with the badge adding stricter filters on top.

```php
// Table class
public function findNeedingWaivers(Query $query, array $options): Query {
    // Base conditions shared by badge and list
}

// Badge: adds ->where(['end_date <' => $today])
// List: uses as-is or adds broader date range
```

## Anti-Patterns
- **Different permission actions** between badge and list — guarantees mismatched counts
- **Independent query construction** — badge and list evolve separately and drift apart
- **Badge counts more than list shows** — users can't find the items the badge references

## When to Use
Any time you implement a notification badge, count indicator, or summary number that links to a detail/list view. Verify on implementation AND on any subsequent changes to either the badge or the list query.
