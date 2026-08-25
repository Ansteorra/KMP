# Awards recommendation + bestowal workflow diagrams

This document summarizes the current **implemented** workflow behavior for Awards recommendations and bestowals, based on the seeded workflow definitions and the current Awards services/policies.

## 1) End-to-end recommendation processing

```mermaid
flowchart LR
    A["Recommendation request<br/>New or Existing"] --> B["StartApprovalProcess<br/>Awards.RecommendationApprovalRun"]
    B --> C{"Approval step complete?"}
    C -- "No" --> D["Approval node<br/>Award Approval Gate"]
    D --> E["AdvanceApprovalProcess"]
    E --> J{"Approval run closed?"}
    J -- "No: next/approved" --> C
    J -- "Yes: rejected" --> K["TransitionRecommendation<br/>No Action / Closed"]
    C -- "Yes" --> F["CreateBestowal action<br/>Awards.CreateBestowal"]
    F --> G["BestowalHandoffService<br/>eligibility checks"]
    G --> H["BestowalCreationService<br/>create bestowal + links"]
    H --> I["Bestowal owned lifecycle"]
```

## 2) Approval ownership and visibility model

```mermaid
flowchart TD
    A["Recommendation with approval run"] --> B{"Run status"}
    B -- "in_progress / changes_requested" --> C["Active cycle"]
    B -- "approved / consumed / closed / cancelled" --> D["Inactive cycle"]

    C --> E{"User in current pending approver set?"}
    E -- "Yes" --> F["View + Edit + Request feedback"]
    E -- "No" --> G["No visibility via approval path"]

    D --> H{"retain_read_visibility on prior step?"}
    H -- "Yes + user responded previously" --> I["Read-only visibility"]
    H -- "No" --> J["Fallback to branch/level policy only"]
```

## 3) Bestowal lifecycle and recommendation projection

```mermaid
flowchart LR
    A["Open without gathering"] --> B["Open with gathering"]
    A --> C["Given"]
    B --> C
    A --> X["Cancelled"]
    B --> X

    A -. "sync rec: Scheduling / Need to Schedule" .-> R1["Recommendation"]
    B -. "sync rec: To Give / Scheduled" .-> R1
    C -. "sync rec: Closed / Given" .-> R1
    X -. "clear link + rehydrate approval" .-> R1
```

## 4) Linking, unlinking, grouping, and cancellation interactions

```mermaid
flowchart TD
    L1["Link recommendation to bestowal"] --> L2["assertLinkable:<br/>not grouped child<br/>member match<br/>not on another active bestowal"]
    L2 --> L3["Create/reuse join row"]
    L3 --> L4["Cancel/supersede active approval runs<br/>terminal_reason = superseded_by_bestowal_link"]
    L4 --> L5["Sync shortcut + state<br/>refresh primary rec + notes"]

    U1["Unlink recommendation"] --> U2["Must leave >= 1 linked recommendation"]
    U2 --> U3["Clear bestowal projection"]
    U3 --> U4["Clear recommendation.bestowal_id"]
    U4 --> U5["Delete join row"]
    U5 --> U6["Refresh primary rec + notes"]
    U6 --> U7["Rehydrate approval if prior run was consumed/superseded"]

    G1["Group / Ungroup / Remove child"] --> G2["assertGroupingPermitted"]
    G2 --> G3{"Active approval run exists?"}
    G3 -- "Yes" --> G4["Allowed only if actor is current pending approver for every active run"]
    G3 -- "No" --> G5["Allowed if member compatibility passes"]

    C1["Cancel bestowal"] --> C2["Reject if Given/already Cancelled"]
    C2 --> C3["Set lifecycle_status = cancelled"]
    C3 --> C4["Clear recommendation.bestowal_id + gathering_id"]
    C4 --> C5["Delete active join rows"]
    C5 --> C6["Mark consumed/superseded approval runs cancelled<br/>rehydrate approval when needed"]
```

## 5) Bestowal preparation To-Dos

```mermaid
flowchart LR
    A["Event Scheduled<br/>assigns bestowal gathering"] --> B["Added to Agenda"]
    B --> C["Given"]
    A -. "gating" .-> D["Mark Given enabled only after all gating To-Dos complete"]
    B -. "gating" .-> D
    C -. "gating" .-> D
```

The default bestowal checklist requires **Event Scheduled**, **Added to Agenda**, and **Given** before a bestowal can be marked given. **Added to Agenda** is blocked until **Event Scheduled** is complete because the court agenda imports bestowals from the assigned gathering.

Required-field To-Dos can opt into system auto-close with `auto_complete_when_satisfied`. The built-in **Event Scheduled** and **Added to Agenda** items use this flag: once the bestowal has an assigned gathering, or a valid court/roaming assignment respectively, the ActionItem service records a system completion note and closes the satisfied To-Do. If a required field is later cleared, the same synchronization reopens the completed To-Do with a system audit note.

Ad-hoc bestowals may be linked to an existing member account or recorded with only the recipient SCA name, matching recommendation submission for recipients who are not registered in KMP.

## 6) Data interaction map

```mermaid
flowchart LR
    R["awards_recommendations"] <--> AR["awards_recommendation_approval_runs"]
    R <--> BR["awards_bestowal_recommendations"]
    B["awards_bestowals"] <--> BR
    W["workflow_instances / workflow_approvals / responses"] --> AR

    H["BestowalHandoffService"] --> AR
    H --> B
    H --> BR
    H --> R
    H --> L["RecommendationApprovalWorkflowLifecycleService"]

    S["BestowalRecommendationSyncService"] --> B
    S --> R

    C["BestowalCancellationService"] --> B
    C --> R
    C --> BR
    C --> L

    G["RecommendationGroupingService"] --> R
    G --> L
    G --> W

    L --> AR
    L --> W
```

## 7) Workflow definitions currently in play

| Definition file | Trigger event | Key actions in flow |
| --- | --- | --- |
| `awards-recommendation-submitted.json` | `Awards.RecommendationCreateRequested` | `CreateRecommendation` -> `StartApprovalProcess` -> approval loop -> `CreateBestowal` |
| `awards-existing-recommendation-approval.json` | `Awards.ExistingRecommendationApprovalRequested` | `StartApprovalProcess` -> approval loop -> `CreateBestowal` |
| `awards-bestowal-transition.json` | `Awards.BestowalTransitionRequested` | `TransitionBestowal` -> `SyncRecommendationsFromBestowal` |
| `awards-bestowal-update.json` | `Awards.BestowalUpdateRequested` | `UpdateBestowal` (link/unlink + transition + sync) |
| `awards-bestowal-bulk-transition.json` | `Awards.BestowalBulkTransitionRequested` | `BulkTransitionBestowals` |
| `awards-bestowal-cancel.json` | `Awards.BestowalCancelRequested` | `CancelBestowal` (clear projection + unlink cleanup + approval rehydration) |
| `awards-bestowal-cancelled.json` | `Awards.BestowalCancelled` | notification flow |
| `awards-recommendations-group.json` | `Awards.RecommendationsGroupRequested` | `GroupRecommendations` |
| `awards-recommendations-ungroup.json` | `Awards.RecommendationsUngroupRequested` | `UngroupRecommendations` |
| `awards-recommendation-remove-from-group.json` | `Awards.RecommendationRemoveFromGroupRequested` | `RemoveRecommendationFromGroup` |

## 8) Team test checklist by flow

| Flow | Primary actor | Expected owner of next action | Must verify |
| --- | --- | --- | --- |
| Submit recommendation | Requester | Current pending approver set | Approval run created, only current approvers see active item |
| Active approval edit/feedback | Current approver | Current approver | Can edit + request feedback; non-current cannot |
| Multi-step approval advance | Current approver | Next configured approver set | Pending set rotates, previous step visibility retained only when configured |
| Approval complete -> bestowal create | Final approver/workflow action | Bestowal workflow owner(s) | Only the final approval step selects the bestowal gathering; handoff blocks active runs, bestowal created with source approval provenance, approved run marked consumed |
| Link recommendation to existing bestowal | Noble/admin path | Bestowal workflow owner(s) | Active approval run cancelled/superseded, member match enforced, grouped child blocked |
| Unlink recommendation | Noble/admin path | Recommendation workflow owner(s) | Bestowal projection and shortcut cleared, join row removed, primary recomputed, approval rehydrated when prior run was consumed/superseded |
| Group/ungroup during approval | Current approver or admin override | Same active approver set | Grouping denied for non-current approver; origin snapshot restore works |
| Bestowal transition to court states | Bestowal owner(s) | Bestowal owner(s) | Recommendation projection state sync follows mapping |
| Bestowal cancellation | Bestowal owner(s) | Recommendation workflow owner(s) | Cancel denied for Given; Bestowal projection, links, and shortcuts cleared; active join rows deleted; consumed/superseded approval runs cancelled and rehydrated when needed |
| Turnover/reassignment events | System + admins | New eligible approvers | Pending approver set reflects new eligibility without leaking old active queue access |

## 9) High-risk regression points

1. Approval lifecycle must be driven by `Awards.RecommendationApprovalRuns` plus workflow runtime rows, not recommendation state/status.
2. Active approval visibility scoping must stay limited to current pending approvers for active cycles.
3. Link integrity between `recommendation.bestowal_id` and `awards_bestowal_recommendations`.
4. Group-child guardrails preventing direct child linking/handoff, including active runs on group heads.
5. Cancellation/unlink cleanup consistency: recommendation projection, shortcut clear, join-row delete, approval-run terminal reason, and rehydration must stay together.
