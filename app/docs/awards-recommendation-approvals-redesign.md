# Awards Recommendation lifecycle in the Workflow + Approvals paradigm

> Status: design / redesign note for the `feature/workflow-engine` branch.
>
> Audience: maintainers reasoning about how an award **Recommendation** moves through its
> lifecycle now that we have a workflow-engine **Approval** system and a dedicated **Bestowal**
> object/workflow.

## Why this document exists

Earlier on this branch we briefly moved the Recommendation **state machine** (its statuses,
states, field rules, and transitions) out of YAML app-settings and into database tables
(`awards_recommendation_statuses`, `awards_recommendation_states`,
`awards_recommendation_state_field_rules`, `awards_recommendation_state_transitions`).

Since then we built two things that change the picture:

1. A **workflow-engine Approval system** — `ApprovalProcess` / `ApprovalProcessStep` definitions
   plus `RecommendationApprovalRun` instances. This now owns the **approval decision** that used to
   be expressed as a handful of recommendation states.
2. A dedicated **Bestowal** object and workflow (`Bestowal`, `BestowalState`, its own DB state
   machine, sync + policy services). This now owns the **scheduling → giving** half of the old
   lifecycle.

Because nothing has shipped, we are taking the clean path: **delete the DB state-machine tables**
and go back to **YAML-defined recommendation states** (as on `main`), while keeping the new
Approval engine and Bestowal subsystem. This doc re-visualizes how a Recommendation *should*
optimally flow in that combined model.

> Out of scope here: migrating existing recommendation **data** into the new model. That is a
> separate future plan. Legacy `awards_recommendations` columns are intentionally retained.

## The three subsystems and who owns what

```mermaid
flowchart LR
    subgraph A["A. Recommendation state machine (YAML)"]
        A1["Awards.RecommendationStatuses<br/>Awards.RecommendationStateRules<br/>Awards.RecommendationStatesRequireCanViewHidden"]
        A2["Recommendation entity<br/>getStatuses() / getStates()<br/>getStateRules() / getHiddenStates()"]
        A1 --> A2
    end

    subgraph B["B. Approval engine (workflow)"]
        B1["ApprovalProcess / ApprovalProcessStep"]
        B2["RecommendationApprovalRun<br/>in_progress / changes_requested /<br/>approved / closed / cancelled"]
        B1 --> B2
    end

    subgraph C["C. Bestowal (scheduling → giving)"]
        C1["Bestowal / BestowalState<br/>(own DB state machine)"]
        C2["BestowalRecommendationSyncService<br/>RecommendationBestowalStatePolicyService"]
        C1 --> C2
    end

    B2 -- "decision sets<br/>rec state" --> A2
    C2 -- "syncs Bestowal progress<br/>into rec state" --> A2
```

- **A — Recommendation state machine (YAML):** the source of truth for *which* lifecycle state a
  recommendation is displayed/filtered in. Strings, not DB rows. Field-level edit rules and hidden
  states are also YAML.
- **B — Approval engine:** owns the *approval decision*. Progress through approval lives in the
  `RecommendationApprovalRun` status, **not** in recommendation states. When an approval run reaches
  a terminal decision it sets the recommendation's YAML state (e.g. `King Approved`).
- **C — Bestowal:** owns *scheduling and giving*. It has its **own** DB state machine
  (`awards_bestowal_states`) and projects its progress back onto the recommendation's YAML state via
  the sync service.

> The audit log (`awards_recommendations_states_logs` / `RecommendationStateLogService`) is the
> **state-change history**, not the state-machine definition. It pre-exists on `main` and stays.

## Recommendation states (YAML, restored)

Status → states map seeded by `AwardsPlugin::bootstrap()`:

| Status (`Awards.RecommendationStatuses`) | States |
| --- | --- |
| **In Progress** | Submitted, In Consideration, Awaiting Feedback, Deferred till Later, King Approved, Queen Approved, **Linked** |
| **Scheduling** | Need to Schedule |
| **To Give** | Scheduled, Announced Not Given |
| **Closed** | Given, No Action, **Linked - Closed** |

`Linked` / `Linked - Closed` are the grouping states consumed by `RecommendationGroupingService`
(`LINKED_STATES`). They are seeded `is_hidden = true` (require `canViewHidden`) and their field rules
disable all fields.

Approval progress states that briefly existed on-branch (`In Approval`, `Changes Requested`) are
**not** part of YAML — that progress now lives in `RecommendationApprovalRun.status`.

## Optimal lifecycle (end to end)

```mermaid
stateDiagram-v2
    [*] --> Submitted

    state "In Progress (consideration)" as Consider {
        Submitted --> In_Consideration
        In_Consideration --> Awaiting_Feedback
        Awaiting_Feedback --> In_Consideration
        In_Consideration --> Deferred_till_Later
        Deferred_till_Later --> In_Consideration
    }

    In_Consideration --> ApprovalRun : start approval (workflow)

    state "Approval run (workflow engine)" as Approval {
        [*] --> in_progress
        in_progress --> changes_requested
        changes_requested --> in_progress
        in_progress --> approved
        in_progress --> cancelled
    }

    ApprovalRun --> King_Approved : run approved
    ApprovalRun --> No_Action : run cancelled / declined

    King_Approved --> Need_to_Schedule : hand off to Bestowal

    state "Bestowal workflow (own DB state machine)" as Bestowal {
        [*] --> Created
        Created --> Gathering_Assigned
        Gathering_Assigned --> Scroll_Notified
        Scroll_Notified --> Scroll_Ready
        Scroll_Ready --> Court_Pending
        Court_Pending --> Court_Scheduled
        Court_Scheduled --> Ready_for_Court
        Ready_for_Court --> Given_b : given in court
        Ready_for_Court --> Announced_Not_Given_b : announced only
        Created --> Cancelled_b : unwind
    }

    Need_to_Schedule --> Scheduled : Bestowal -> Court Scheduled / Ready for Court
    Scheduled --> Given : Bestowal -> Given
    Scheduled --> Announced_Not_Given : Bestowal -> Announced Not Given
    Need_to_Schedule --> King_Approved : Bestowal cancelled (unwind)

    Given --> [*]
    Announced_Not_Given --> [*]
    No_Action --> [*]
```

Reading the diagram:

1. **Consideration (YAML, manual).** A recommendation is `Submitted`, worked through
   `In Consideration` / `Awaiting Feedback` / `Deferred till Later`. These are plain YAML states with
   field rules; no workflow run is required yet.
2. **Approval (workflow engine).** When it is ready for a decision, a `RecommendationApprovalRun` is
   started against an `ApprovalProcess`. The run progresses `in_progress ↔ changes_requested` and
   ends `approved` / `cancelled`. **This progress is not a recommendation state.** On `approved` the
   recommendation's YAML state becomes `King Approved` (or `Queen Approved`); a declined/cancelled run
   resolves to `No Action`.
3. **Hand-off to Bestowal.** An approved recommendation that needs to be conferred moves to the
   handoff state `Need to Schedule` (`RecommendationBestowalStatePolicyService::HANDOFF_STATE`) and a
   `Bestowal` takes ownership of scheduling/giving.
4. **Bestowal owns scheduling → giving.** Bestowal runs its **own** DB state machine. Its progress is
   projected back onto the recommendation's YAML state by `BestowalRecommendationSyncService`.
5. **Closed.** Terminal YAML states are `Given`, `Announced Not Given`, or `No Action` (plus
   `Linked - Closed` for grouped recommendations).

## Bestowal state → Recommendation state sync map

`BestowalRecommendationSyncService` keeps the recommendation's YAML state in step with Bestowal
progress. After the DB-state-machine removal these mappings are stored on `awards_bestowal_states`
as **YAML state-name strings** (`sync_recommendation_state`, `unwind_recommendation_state`) rather
than integer FKs into a deleted table.

```mermaid
flowchart TD
    subgraph BestowalStates["Bestowal state (awards_bestowal_states)"]
        bs1["Created / Gathering Assigned /<br/>Scroll Notified / Scroll Ready / Court Pending"]
        bs2["Court Scheduled / Ready for Court"]
        bs3["Given"]
        bs4["Announced Not Given"]
        bs5["Cancelled (unwind)"]
    end

    subgraph RecStates["Recommendation state (YAML)"]
        rs1["Need to Schedule"]
        rs2["Scheduled"]
        rs3["Given"]
        rs4["Announced Not Given"]
        rs5["King Approved"]
    end

    bs1 -- sync --> rs1
    bs2 -- sync --> rs2
    bs3 -- sync --> rs3
    bs4 -- sync --> rs4
    bs5 -- unwind --> rs5
```

| Bestowal state | Recommendation state (sync) |
| --- | --- |
| Created, Gathering Assigned, Scroll Notified, Scroll Ready, Court Pending | Need to Schedule *(handoff)* |
| Court Scheduled, Ready for Court | Scheduled |
| Given | Given |
| Announced Not Given | Announced Not Given |
| Cancelled *(unwind)* | King Approved |

These are the contract validated by
`RecommendationBestowalStatePolicyService::assertBestowalSyncMappingsConfigured()`
(`EXPECTED_SYNC_MAPPINGS` / `EXPECTED_UNWIND_MAPPINGS`). The strings on both sides must remain valid
members of `Recommendation::getStates()`.

## Design principles going forward

- **One owner per concern.** Approval *decisions* live in approval runs; *scheduling/giving* lives
  in Bestowal; the recommendation's YAML state is the **displayed projection** of those, plus the
  manual consideration phase.
- **Recommendation states stay in YAML.** They are presentation/filter states with field rules — a
  small, stable, admin-editable vocabulary. We do not re-introduce a DB state-machine for them.
- **Bestowal keeps its own DB state machine.** It is genuinely richer (per-state field rules,
  transitions, gathering support) and is the right home for the conferral process.
- **Sync is one-directional and explicit.** Bestowal → Recommendation only, via the sync service,
  using state-name strings. The recommendation never drives Bestowal.
- **Grouping is orthogonal.** `Linked` / `Linked - Closed` describe how recommendations are grouped,
  independent of approval/bestowal progress.

## What was removed vs kept (summary)

| Removed (DB state machine) | Kept |
| --- | --- |
| `awards_recommendation_statuses` / `_states` / `_state_field_rules` / `_state_transitions` tables + entities/tables | YAML app-settings + `Recommendation` entity state API |
| `RecommendationStates` / `RecommendationStatuses` controllers, policies, grids, templates, nav links | `RecommendationStateLogService` + `awards_recommendations_states_logs` audit log |
| State-machine seed migrations (`CreateRecommendationStatesTables`, `AddLinkedClosedState`, `AddRecommendationApprovalStates`, `RemoveApprovalRecommendationStates`) | Approval engine (`ApprovalProcess`, `RecommendationApprovalRun`, workflow actions/conditions) |
| Integer FK `sync/unwind_recommendation_state_id` on `awards_bestowal_states` | Bestowal subsystem with string `sync/unwind_recommendation_state` mappings |
