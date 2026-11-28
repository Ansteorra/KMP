import { Controller } from "@hotwired/stimulus";

/** Shared recommendation grouping confirmation for the index and detail grids. */
class AwardsRecommendationGroupController extends Controller {
    static targets = ["selectedIds", "validationMessage", "submit"];
    static values = { frameId: String, current: Object, similar: Boolean };

    connect() {
        this.boundHandleBulkAction = this.handleBulkAction.bind(this);
        this.boundHandleSelectionChanged = this.handleSelectionChanged.bind(this);
        document.addEventListener("outlet-btn:notice", this.boundHandleBulkAction);
        document.addEventListener("grid-view:selection-changed", this.boundHandleSelectionChanged);
    }

    disconnect() {
        document.removeEventListener("outlet-btn:notice", this.boundHandleBulkAction);
        document.removeEventListener("grid-view:selection-changed", this.boundHandleSelectionChanged);
    }

    selection({ ids = [], checkboxes = [] } = {}) {
        const current = this.currentValue;
        const selectedIds = current.id ? [...new Set([current.id, ...ids])] : ids;
        const selectedRows = current.id ? [current, ...checkboxes] : checkboxes;
        const memberIds = new Set(checkboxes.map(row => row.memberId).filter(Boolean));
        const activeBestowals = selectedRows.filter(row => [true, "true", "1"].includes(row.activeBestowal));

        let error = "";
        if (selectedIds.length < 2) {
            error = current.id ? "Select a recommendation to group." : "Select at least two recommendations to group.";
        } else if (memberIds.size > 1) {
            error = "Select recommendations for the same member.";
        } else if (this.similarValue && checkboxes.length !== ids.length) {
            error = "Select recommendations on this page.";
        } else if (activeBestowals.length > 1) {
            error = "Select only one recommendation leading an in-flight bestowal.";
        }

        return { ids: selectedIds, error, hasActiveBestowal: activeBestowals.length > 0 };
    }

    handleSelectionChanged(event) {
        const grid = document.getElementById(this.frameIdValue);
        if (!grid || !event.target.contains(grid)) return;

        const button = grid.querySelector('[data-bulk-action-key="group-recs"]');
        if (!button) return;

        const { error } = this.selection(event.detail);
        button.disabled = Boolean(error);
        button.title = error || "Group selected recommendations";
    }

    handleBulkAction(event) {
        const button = event.target;
        const grid = document.getElementById(this.frameIdValue);
        if (!grid?.contains(button) || !button.matches('[data-bulk-action-key="group-recs"]')) return;

        const { ids, error, hasActiveBestowal } = this.selection(event.detail);
        this.selectedIdsTarget.replaceChildren();
        if (!error) {
            for (const id of ids) {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "recommendation_ids[]";
                input.value = id;
                this.selectedIdsTarget.append(input);
            }
        }

        this.submitTarget.disabled = Boolean(error);
        this.validationMessageTarget.classList.toggle("alert-danger", Boolean(error));
        this.validationMessageTarget.classList.toggle("alert-info", !error);
        const destination = hasActiveBestowal
            ? " The recommendation with the in-flight bestowal will lead the group."
            : " An existing group will keep its lead. New groups use the earliest recommendation.";
        this.validationMessageTarget.textContent = error || `${ids.length} recommendations will be grouped.${destination}`;
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-group"] = AwardsRecommendationGroupController;
